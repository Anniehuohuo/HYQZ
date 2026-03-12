<?php

namespace app\services\ai;

use crmeb\services\SystemConfigService;
use crmeb\services\CacheService;
use think\facade\Db;

class AiPowerServices
{
    public function quota(int $uid, int $freeLimit = 3, int $costPerChat = 1): array
    {
        $this->ensureTables();
        $uid = (int)$uid;
        if ($uid <= 0) {
            return [
                'enabled' => 0,
                'free_limit' => $freeLimit,
                'free_used' => 0,
                'free_remaining' => $freeLimit,
                'cost_per_chat' => $costPerChat,
                'balance' => 0,
            ];
        }
        $enabled = $this->cfgInt('ai_power_enabled', 1) === 1 ? 1 : 0;
        $freeLimit = $this->cfgInt('ai_power_free_daily_limit', $freeLimit);
        $costPerChat = $this->cfgInt('ai_power_cost_per_chat', $costPerChat);
        $freeUsed = $this->getFreeUsedToday($uid);
        $balance = $this->getBalance($uid);
        return [
            'enabled' => $enabled,
            'free_limit' => $freeLimit,
            'free_used' => $freeUsed,
            'free_remaining' => max(0, $freeLimit - $freeUsed),
            'cost_per_chat' => max(1, $costPerChat),
            'balance' => $balance,
        ];
    }

    public function prepareChat(int $uid, int $agentId, string $sessionId = ''): array
    {
        $this->ensureTables();
        $enabled = $this->cfgInt('ai_power_enabled', 1) === 1;
        if (!$enabled) {
            return [
                'allowed' => true,
                'mode' => 'free',
                'token' => '',
                'quota' => $this->quota($uid),
            ];
        }
        $freeLimit = $this->cfgInt('ai_power_free_daily_limit', 3);
        $cost = max(1, $this->cfgInt('ai_power_cost_per_chat', 1));
        $freeUsed = $this->getFreeUsedToday($uid);
        $freeRemaining = max(0, $freeLimit - $freeUsed);
        $balance = $this->getBalance($uid);
        if ($freeRemaining > 0) {
            $token = md5(uniqid('aipwr_free_', true));
            CacheService::set($this->pendingKey($token), [
                'uid' => $uid,
                'mode' => 'free',
                'cost' => 0,
                'agent_id' => (int)$agentId,
                'session_id' => (string)$sessionId,
                'day' => $this->todayKey(),
                'ts' => time(),
            ], 900);
            return [
                'allowed' => true,
                'mode' => 'free',
                'token' => $token,
                'quota' => [
                    'enabled' => 1,
                    'free_limit' => $freeLimit,
                    'free_used' => $freeUsed,
                    'free_remaining' => $freeRemaining,
                    'cost_per_chat' => $cost,
                    'balance' => $balance,
                ],
            ];
        }
        if ($balance >= $cost) {
            $token = md5(uniqid('aipwr_paid_', true));
            CacheService::set($this->pendingKey($token), [
                'uid' => $uid,
                'mode' => 'paid',
                'cost' => $cost,
                'agent_id' => (int)$agentId,
                'session_id' => (string)$sessionId,
                'day' => $this->todayKey(),
                'ts' => time(),
            ], 900);
            return [
                'allowed' => true,
                'mode' => 'paid',
                'token' => $token,
                'quota' => [
                    'enabled' => 1,
                    'free_limit' => $freeLimit,
                    'free_used' => $freeUsed,
                    'free_remaining' => 0,
                    'cost_per_chat' => $cost,
                    'balance' => $balance,
                ],
            ];
        }
        return [
            'allowed' => false,
            'mode' => '',
            'token' => '',
            'quota' => [
                'enabled' => 1,
                'free_limit' => $freeLimit,
                'free_used' => $freeUsed,
                'free_remaining' => 0,
                'cost_per_chat' => $cost,
                'balance' => $balance,
            ],
        ];
    }

    public function commitChat(string $token): bool
    {
        $token = trim($token);
        if ($token === '') return false;
        $consumedKey = $this->consumedKey($token);
        if (CacheService::get($consumedKey)) return true;
        $pending = CacheService::get($this->pendingKey($token));
        if (!is_array($pending)) return false;

        $uid = (int)($pending['uid'] ?? 0);
        if ($uid <= 0) return false;
        $mode = (string)($pending['mode'] ?? '');
        $cost = (int)($pending['cost'] ?? 0);
        $agentId = (int)($pending['agent_id'] ?? 0);
        $sessionId = (string)($pending['session_id'] ?? '');

        if ($mode === 'free') {
            $this->incrementFreeUsedToday($uid);
            CacheService::set($consumedKey, 1, 3600);
            CacheService::delete($this->pendingKey($token));
            $this->writeBill($uid, $agentId, $sessionId, 0, 'free');
            return true;
        }
        if ($mode === 'paid') {
            if ($cost <= 0) return false;
            $ok = $this->decreaseBalance($uid, $cost);
            if (!$ok) return false;
            CacheService::set($consumedKey, 1, 3600);
            CacheService::delete($this->pendingKey($token));
            $this->writeBill($uid, $agentId, $sessionId, $cost, 'paid');
            return true;
        }
        return false;
    }

    protected function cfgInt(string $key, int $default = 0): int
    {
        try {
            $v = SystemConfigService::get($key, $default, false);
            return (int)$v;
        } catch (\Throwable $e) {
            return (int)$default;
        }
    }

    public function increaseBalance(int $uid, int $amount): bool
    {
        $this->ensureTables();
        $uid = (int)$uid;
        $amount = (int)$amount;
        if ($uid <= 0 || $amount <= 0) return false;
        $this->ensureUserRow($uid);
        Db::name('ai_power_user')->where('uid', $uid)->inc('balance', $amount)->update();
        return true;
    }

    protected function getBalance(int $uid): int
    {
        $this->ensureUserRow($uid);
        try {
            $v = Db::name('ai_power_user')->where('uid', $uid)->value('balance');
            return max(0, (int)$v);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function decreaseBalance(int $uid, int $cost): bool
    {
        $this->ensureUserRow($uid);
        $uid = (int)$uid;
        $cost = (int)$cost;
        if ($uid <= 0 || $cost <= 0) return false;
        return (bool)Db::transaction(function () use ($uid, $cost) {
            $affected = Db::name('ai_power_user')->where('uid', $uid)->where('balance', '>=', $cost)->dec('balance', $cost)->update();
            return $affected > 0;
        });
    }

    protected function writeBill(int $uid, int $agentId, string $sessionId, int $cost, string $type): void
    {
        try {
            Db::name('ai_power_bill')->insert([
                'uid' => (int)$uid,
                'agent_id' => (int)$agentId,
                'session_id' => (string)$sessionId,
                'cost' => (int)$cost,
                'type' => (string)$type,
                'add_time' => time(),
            ]);
        } catch (\Throwable $e) {
        }
    }

    protected function ensureUserRow(int $uid): void
    {
        $uid = (int)$uid;
        if ($uid <= 0) return;
        try {
            $exists = Db::name('ai_power_user')->where('uid', $uid)->value('uid');
            if (!$exists) {
                Db::name('ai_power_user')->insert([
                    'uid' => $uid,
                    'balance' => 0,
                    'add_time' => time(),
                    'update_time' => time(),
                ]);
            }
        } catch (\Throwable $e) {
        }
    }

    protected function getFreeUsedToday(int $uid): int
    {
        $key = $this->freeUsedKey($uid);
        $v = CacheService::get($key);
        return max(0, (int)$v);
    }

    protected function incrementFreeUsedToday(int $uid): int
    {
        $key = $this->freeUsedKey($uid);
        $used = max(0, (int)CacheService::get($key));
        $used++;
        CacheService::set($key, $used, $this->secondsToEndOfDay() + 7200);
        return $used;
    }

    protected function todayKey(): string
    {
        return date('Ymd');
    }

    protected function freeUsedKey(int $uid): string
    {
        return 'ai_power_free_used:' . (int)$uid . ':' . $this->todayKey();
    }

    protected function pendingKey(string $token): string
    {
        return 'ai_power_pending:' . $token;
    }

    protected function consumedKey(string $token): string
    {
        return 'ai_power_consumed:' . $token;
    }

    protected function secondsToEndOfDay(): int
    {
        $now = time();
        $end = strtotime(date('Y-m-d 23:59:59', $now));
        return max(0, $end - $now);
    }

    protected function ensureTables(): void
    {
        try {
            Db::query("SELECT 1");
        } catch (\Throwable $e) {
            return;
        }
        $defaultConn = (string)config('database.default', 'mysql');
        $prefix = (string)config('database.connections.' . $defaultConn . '.prefix', '');
        if ($prefix === '') {
            $prefix = (string)config('database.connections.mysql.prefix', '');
        }
        $tUser = $prefix . 'ai_power_user';
        $tBill = $prefix . 'ai_power_bill';
        $tRecharge = $prefix . 'ai_power_recharge';
        try {
            Db::name('ai_power_user')->limit(1)->select();
        } catch (\Throwable $e) {
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tUser}` (`uid` int(10) unsigned NOT NULL, `balance` int(10) unsigned NOT NULL DEFAULT '0', `add_time` int(10) unsigned NOT NULL DEFAULT '0', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`uid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
            }
        }
        try {
            Db::name('ai_power_bill')->limit(1)->select();
        } catch (\Throwable $e) {
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tBill}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `uid` int(10) unsigned NOT NULL DEFAULT '0', `agent_id` int(10) unsigned NOT NULL DEFAULT '0', `session_id` varchar(64) NOT NULL DEFAULT '', `cost` int(10) unsigned NOT NULL DEFAULT '0', `type` varchar(16) NOT NULL DEFAULT '', `add_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `uid_time` (`uid`,`add_time`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
            }
        }
        try {
            Db::name('ai_power_recharge')->limit(1)->select();
        } catch (\Throwable $e) {
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tRecharge}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `order_id` varchar(32) NOT NULL DEFAULT '', `uid` int(10) unsigned NOT NULL DEFAULT '0', `pay_price` decimal(10,2) NOT NULL DEFAULT '0.00', `power_amount` int(10) unsigned NOT NULL DEFAULT '0', `pay_type` varchar(16) NOT NULL DEFAULT '', `paid` tinyint(1) unsigned NOT NULL DEFAULT '0', `add_time` int(10) unsigned NOT NULL DEFAULT '0', `pay_time` int(10) unsigned NOT NULL DEFAULT '0', `trade_no` varchar(64) NOT NULL DEFAULT '', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `order_id` (`order_id`), KEY `uid_time` (`uid`,`add_time`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
            }
        }
    }
}
