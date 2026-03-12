<?php

namespace app\services\ai;

use app\dao\order\StoreOrderStoreOrderCartInfoDao;
use crmeb\services\CacheService;
use think\facade\Db;

class AiAgentGoodsServices
{
    protected AiPowerServices $aiPowerServices;
    protected StoreOrderStoreOrderCartInfoDao $orderJoinDao;

    public function __construct(AiPowerServices $aiPowerServices, StoreOrderStoreOrderCartInfoDao $orderJoinDao)
    {
        $this->aiPowerServices = $aiPowerServices;
        $this->orderJoinDao = $orderJoinDao;
    }

    public function bind(int $agentId, int $productId, int $giftPower = 99, int $status = 1): void
    {
        $this->ensureTables();
        $agentId = (int)$agentId;
        $productId = (int)$productId;
        $giftPower = (int)$giftPower;
        $status = $status ? 1 : 0;
        if ($agentId <= 0 || $productId <= 0) {
            return;
        }
        $now = time();
        $exists = null;
        try {
            $exists = Db::name('ai_agent_goods')->where('agent_id', $agentId)->find();
        } catch (\Throwable $e) {
            $exists = null;
        }
        if (!$exists) {
            Db::name('ai_agent_goods')->insert([
                'agent_id' => $agentId,
                'product_id' => $productId,
                'gift_power' => $giftPower,
                'status' => $status,
                'add_time' => $now,
                'update_time' => $now,
            ]);
        } else {
            Db::name('ai_agent_goods')->where('agent_id', $agentId)->update([
                'product_id' => $productId,
                'gift_power' => $giftPower,
                'status' => $status,
                'update_time' => $now,
            ]);
        }
        CacheService::delete('ai_agent_goods_bind:' . $agentId);
    }

    public function saleInfo(int $uid, int $agentId): array
    {
        $this->ensureTables();
        $agentId = (int)$agentId;
        if ($agentId <= 0) {
            return [];
        }

        $agent = Db::name('ai_agents')->where('id', $agentId)->where('status', 1)->field('id,agent_name,avatar,description,provider_meta')->find();
        if (!$agent) {
            return [];
        }
        $intro = $this->buildAgentIntroFields($agent);

        $bind = $this->getAgentGoodsBind($agentId);
        $productId = (int)($bind['product_id'] ?? 0);
        $giftPower = (int)($bind['gift_power'] ?? 0);

        $product = null;
        if ($productId > 0) {
            try {
                $product = Db::name('store_product')->where('id', $productId)->field('id,store_name,keyword,store_info,image')->find();
            } catch (\Throwable $e) {
                $product = null;
            }
        }

        $unlocked = false;
        if ($uid > 0 && $productId > 0) {
            $access = $this->ensureUnlocked($uid, $agentId);
            $unlocked = (bool)($access['unlocked'] ?? false);
        }

        $title = $product && !empty($product['store_name']) ? (string)$product['store_name'] : (string)($agent['agent_name'] ?? '');
        $slogan = $product && !empty($product['keyword']) ? (string)$product['keyword'] : '';
        $detail = $product && !empty($product['store_info']) ? (string)$product['store_info'] : (string)($agent['description'] ?? '');
        $cover = $product && !empty($product['image']) ? (string)$product['image'] : '';

        return [
            'agent_id' => (int)$agentId,
            'agent_name' => (string)($agent['agent_name'] ?? ''),
            'title' => $title,
            'slogan' => $slogan,
            'detail' => $detail,
            'cover' => $cover,
            'product_id' => $productId,
            'gift_power' => $giftPower,
            'unlocked' => $unlocked ? 1 : 0,
            'welcome' => (string)($intro['welcome'] ?? ''),
            'suggestions' => $intro['suggestions'] ?? [],
        ];
    }

    public function access(int $uid, int $agentId): array
    {
        $this->ensureTables();
        $uid = (int)$uid;
        $agentId = (int)$agentId;
        if ($uid <= 0 || $agentId <= 0) {
            $agent = null;
            if ($agentId > 0) {
                try {
                    $agent = Db::name('ai_agents')->where('id', $agentId)->where('status', 1)->field('id,agent_name,avatar,description,provider_meta')->find();
                } catch (\Throwable $e) {
                    $agent = null;
                }
            }
            $intro = $this->buildAgentIntroFields(is_array($agent) ? $agent : []);
            return [
                'unlocked' => 0,
                'need_login' => $uid <= 0 ? 1 : 0,
                'need_purchase' => 0,
                'product_id' => 0,
                'agent_id' => $agentId,
                'gift_power' => 0,
                'agent_name' => (string)($intro['agent_name'] ?? ''),
                'avatar' => (string)($intro['avatar'] ?? ''),
                'description' => (string)($intro['description'] ?? ''),
                'welcome' => (string)($intro['welcome'] ?? ''),
                'suggestions' => $intro['suggestions'] ?? [],
            ];
        }

        return $this->ensureUnlocked($uid, $agentId);
    }

    public function isUnlockProductId(int $productId): bool
    {
        $this->ensureTables();
        $productId = (int)$productId;
        if ($productId <= 0) return false;
        $cacheKey = 'ai_agent_goods_product:' . $productId;
        $cached = CacheService::get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return (bool)$cached;
        }
        try {
            $exists = Db::name('ai_agent_goods')->where('product_id', $productId)->where('status', 1)->value('id');
            $ok = (int)$exists > 0;
            CacheService::set($cacheKey, $ok ? 1 : 0, 60);
            return $ok;
        } catch (\Throwable $e) {
            CacheService::set($cacheKey, 0, 60);
            return false;
        }
    }

    public function ensureUnlocked(int $uid, int $agentId): array
    {
        $this->ensureTables();
        $uid = (int)$uid;
        $agentId = (int)$agentId;
        $agent = null;
        try {
            $agent = Db::name('ai_agents')->where('id', $agentId)->where('status', 1)->field('id,agent_name,avatar,description,provider_meta')->find();
        } catch (\Throwable $e) {
            $agent = null;
        }
        $intro = $this->buildAgentIntroFields(is_array($agent) ? $agent : []);

        if ($uid <= 0 || $agentId <= 0) {
            return [
                'unlocked' => 0,
                'need_login' => $uid <= 0 ? 1 : 0,
                'need_purchase' => 0,
                'product_id' => 0,
                'agent_id' => $agentId,
                'gift_power' => 0,
                'agent_name' => (string)($intro['agent_name'] ?? ''),
                'avatar' => (string)($intro['avatar'] ?? ''),
                'description' => (string)($intro['description'] ?? ''),
                'welcome' => (string)($intro['welcome'] ?? ''),
                'suggestions' => $intro['suggestions'] ?? [],
            ];
        }

        $bind = $this->getAgentGoodsBind($agentId);
        $productId = (int)($bind['product_id'] ?? 0);
        $giftPower = (int)($bind['gift_power'] ?? 0);

        if ($productId <= 0) {
            return [
                'unlocked' => 0,
                'need_login' => 0,
                'need_purchase' => 0,
                'not_configured' => 1,
                'product_id' => 0,
                'agent_id' => $agentId,
                'gift_power' => $giftPower,
                'agent_name' => (string)($intro['agent_name'] ?? ''),
                'avatar' => (string)($intro['avatar'] ?? ''),
                'description' => (string)($intro['description'] ?? ''),
                'welcome' => (string)($intro['welcome'] ?? ''),
                'suggestions' => $intro['suggestions'] ?? [],
            ];
        }

        $unlock = $this->getUnlockRow($uid, $agentId);
        if ($unlock && (int)($unlock['status'] ?? 0) === 1) {
            return [
                'unlocked' => 1,
                'need_login' => 0,
                'need_purchase' => 0,
                'product_id' => $productId,
                'agent_id' => $agentId,
                'gift_power' => $giftPower,
                'agent_name' => (string)($intro['agent_name'] ?? ''),
                'avatar' => (string)($intro['avatar'] ?? ''),
                'description' => (string)($intro['description'] ?? ''),
                'welcome' => (string)($intro['welcome'] ?? ''),
                'suggestions' => $intro['suggestions'] ?? [],
            ];
        }

        $oid = $this->orderJoinDao->findPaidOrderIdByProduct($uid, $productId);
        if ($oid <= 0) {
            return [
                'unlocked' => 0,
                'need_login' => 0,
                'need_purchase' => 1,
                'product_id' => $productId,
                'agent_id' => $agentId,
                'gift_power' => $giftPower,
                'agent_name' => (string)($intro['agent_name'] ?? ''),
                'avatar' => (string)($intro['avatar'] ?? ''),
                'description' => (string)($intro['description'] ?? ''),
                'welcome' => (string)($intro['welcome'] ?? ''),
                'suggestions' => $intro['suggestions'] ?? [],
            ];
        }

        try {
            $this->settleUnlockOrder((int)$oid, $uid);
        } catch (\Throwable $e) {
        }

        Db::transaction(function () use ($uid, $agentId, $productId, $oid, $giftPower) {
            $unlock = $this->getUnlockRow($uid, $agentId);
            if (!$unlock) {
                Db::name('ai_agent_unlock')->insert([
                    'uid' => $uid,
                    'agent_id' => $agentId,
                    'product_id' => $productId,
                    'oid' => $oid,
                    'gift_power' => $giftPower,
                    'gift_granted' => 0,
                    'status' => 1,
                    'add_time' => time(),
                    'update_time' => time(),
                ]);
                $unlock = $this->getUnlockRow($uid, $agentId);
            } else {
                Db::name('ai_agent_unlock')->where('id', (int)$unlock['id'])->update([
                    'status' => 1,
                    'product_id' => $productId,
                    'oid' => $oid,
                    'gift_power' => $giftPower,
                    'update_time' => time(),
                ]);
            }

            $giftGranted = $unlock ? (int)($unlock['gift_granted'] ?? 0) : 0;
            if ($giftPower > 0 && $giftGranted !== 1) {
                $ok = $this->aiPowerServices->increaseBalance($uid, $giftPower);
                if ($ok) {
                    Db::name('ai_agent_unlock')->where('uid', $uid)->where('agent_id', $agentId)->update([
                        'gift_granted' => 1,
                        'update_time' => time(),
                    ]);
                }
            }
        });

        return [
            'unlocked' => 1,
            'need_login' => 0,
            'need_purchase' => 0,
            'product_id' => $productId,
            'agent_id' => $agentId,
            'gift_power' => $giftPower,
            'agent_name' => (string)($intro['agent_name'] ?? ''),
            'avatar' => (string)($intro['avatar'] ?? ''),
            'description' => (string)($intro['description'] ?? ''),
            'welcome' => (string)($intro['welcome'] ?? ''),
            'suggestions' => $intro['suggestions'] ?? [],
        ];
    }

    protected function decodeProviderMeta($raw): array
    {
        if (is_array($raw)) return $raw;
        $s = trim((string)$raw);
        if ($s === '') return [];
        $v = json_decode($s, true);
        return is_array($v) ? $v : [];
    }

    protected function normalizeSuggestions($value): array
    {
        $list = [];
        if (is_array($value)) {
            $list = $value;
        } elseif (is_string($value)) {
            $list = preg_split("/\\r?\\n/", $value) ?: [];
        }
        $out = [];
        foreach ((array)$list as $x) {
            $s = trim((string)$x);
            if ($s === '') continue;
            $out[] = $s;
            if (count($out) >= 3) break;
        }
        if (!$out) {
            $out = ['给我一个具体场景', '我想练习一句表扬话术', '请给我的话术打分并改写'];
        }
        return $out;
    }

    protected function buildAgentIntroFields(array $agent): array
    {
        $name = trim((string)($agent['agent_name'] ?? ''));
        $avatar = (string)($agent['avatar'] ?? '');
        $desc = trim((string)($agent['description'] ?? ''));
        $meta = $this->decodeProviderMeta($agent['provider_meta'] ?? '');
        $welcome = trim((string)($meta['welcome'] ?? ''));
        if ($welcome === '') {
            if ($name !== '' && $desc !== '') $welcome = '你好，我是' . $name . '，' . $desc;
            elseif ($name !== '') $welcome = '你好，我是' . $name . '。';
        }
        return [
            'agent_name' => $name,
            'avatar' => $avatar,
            'description' => $desc,
            'welcome' => $welcome,
            'suggestions' => $this->normalizeSuggestions($meta['suggestions'] ?? null),
        ];
    }

    protected function settleUnlockOrder(int $oid, int $uid): void
    {
        if ($oid <= 0 || $uid <= 0) return;
        $order = Db::name('store_order')->where('id', $oid)->field('id,order_id,uid,paid,status,delivery_type,virtual_type,pay_price,agent_id,division_id,staff_id,agent_brokerage,division_brokerage,staff_brokerage')->find();
        if (!$order) return;
        if ((int)($order['uid'] ?? 0) !== $uid) return;
        if ((int)($order['paid'] ?? 0) !== 1) return;
        $status = (int)($order['status'] ?? 0);
        if ($status >= 2) return;
        try {
            $user = Db::name('user')->where('uid', $uid)->field('uid,agent_id,division_id,staff_id')->find();
            $user = is_array($user) ? $user : [];
            $updateOrder = [];
            if (empty($order['agent_id']) && !empty($user['agent_id'])) $updateOrder['agent_id'] = (int)$user['agent_id'];
            if (empty($order['division_id']) && !empty($user['division_id'])) $updateOrder['division_id'] = (int)$user['division_id'];
            if (empty($order['staff_id']) && !empty($user['staff_id'])) $updateOrder['staff_id'] = (int)$user['staff_id'];
            if ($updateOrder) {
                Db::name('store_order')->where('id', $oid)->update($updateOrder);
                $order = array_merge($order, $updateOrder);
            }
        } catch (\Throwable $e) {
        }
        try {
            $needAgent = (float)($order['agent_brokerage'] ?? 0) <= 0 && (int)($order['agent_id'] ?? 0) > 0;
            $needDivision = (float)($order['division_brokerage'] ?? 0) <= 0 && (int)($order['division_id'] ?? 0) > 0;
            $needStaff = (float)($order['staff_brokerage'] ?? 0) <= 0 && (int)($order['staff_id'] ?? 0) > 0;
            if ($needAgent || $needDivision || $needStaff) {
                $payPrice = (string)($order['pay_price'] ?? '0');
                $storeBrokerageRatio = (float)sys_config('store_brokerage_ratio', 0);
                $storeBrokerageTwo = (float)sys_config('store_brokerage_two', 0);
                $isSelfBrokerage = (int)sys_config('is_self_brokerage', 0);
                $divisionPercent = app()->make(\app\services\agent\DivisionServices::class)->getDivisionPercent($uid, $storeBrokerageRatio, $storeBrokerageTwo, $isSelfBrokerage);
                $staffPercent = (float)($divisionPercent['staffPercent'] ?? 0);
                $agentPercent = (float)($divisionPercent['agentPercent'] ?? 0);
                $divisionPercentVal = (float)($divisionPercent['divisionPercent'] ?? 0);
                $updateOrder = [];
                if ($needStaff && $staffPercent > 0) {
                    $updateOrder['staff_brokerage'] = bcmul($payPrice, bcdiv((string)$staffPercent, '100', 6), 2);
                }
                if ($needAgent && $agentPercent > 0) {
                    $updateOrder['agent_brokerage'] = bcmul($payPrice, bcdiv((string)$agentPercent, '100', 6), 2);
                }
                if ($needDivision && $divisionPercentVal > 0) {
                    $updateOrder['division_brokerage'] = bcmul($payPrice, bcdiv((string)$divisionPercentVal, '100', 6), 2);
                }
                if ($updateOrder) {
                    Db::name('store_order')->where('id', $oid)->update($updateOrder);
                }
            }
        } catch (\Throwable $e) {
        }
        if ($status < 1) {
            Db::name('store_order')->where('id', $oid)->update([
                'status' => 1,
                'delivery_type' => 'fictitious',
                'virtual_info' => '',
                'remark' => '智能体解锁权益已发放',
            ]);
        }
        app()->make(\app\services\order\StoreOrderTakeServices::class)->takeOrder((string)$order['order_id'], $uid);
    }

    protected function getAgentGoodsBind(int $agentId): array
    {
        $cacheKey = 'ai_agent_goods_bind:' . $agentId;
        $cached = CacheService::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $row = Db::name('ai_agent_goods')->where('agent_id', $agentId)->where('status', 1)->find();
            $row = is_array($row) ? $row : [];
            CacheService::set($cacheKey, $row, 60);
            return $row;
        } catch (\Throwable $e) {
            CacheService::set($cacheKey, [], 60);
            return [];
        }
    }

    protected function getUnlockRow(int $uid, int $agentId): ?array
    {
        try {
            $row = Db::name('ai_agent_unlock')->where('uid', $uid)->where('agent_id', $agentId)->find();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function ensureTables(): void
    {
        $cacheKey = 'ai_agent_goods_tables_ok';
        $cached = CacheService::get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return;
        }
        try {
            Db::query("SELECT 1");
        } catch (\Throwable $e) {
            CacheService::set($cacheKey, 0, 3600);
            return;
        }

        $defaultConn = (string)config('database.default', 'mysql');
        $prefix = (string)config('database.connections.' . $defaultConn . '.prefix', '');
        if ($prefix === '') {
            $prefix = (string)config('database.connections.mysql.prefix', '');
        }
        $tBind = $prefix . 'ai_agent_goods';
        $tUnlock = $prefix . 'ai_agent_unlock';

        try {
            Db::name('ai_agent_goods')->limit(1)->select();
        } catch (\Throwable $e) {
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tBind}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `agent_id` int(10) unsigned NOT NULL DEFAULT '0', `product_id` int(10) unsigned NOT NULL DEFAULT '0', `gift_power` int(10) unsigned NOT NULL DEFAULT '0', `status` tinyint(1) unsigned NOT NULL DEFAULT '1', `add_time` int(10) unsigned NOT NULL DEFAULT '0', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `agent_id` (`agent_id`), KEY `product_id` (`product_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
            }
        }
        try {
            Db::name('ai_agent_unlock')->limit(1)->select();
        } catch (\Throwable $e) {
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tUnlock}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `uid` int(10) unsigned NOT NULL DEFAULT '0', `agent_id` int(10) unsigned NOT NULL DEFAULT '0', `product_id` int(10) unsigned NOT NULL DEFAULT '0', `oid` int(10) unsigned NOT NULL DEFAULT '0', `gift_power` int(10) unsigned NOT NULL DEFAULT '0', `gift_granted` tinyint(1) unsigned NOT NULL DEFAULT '0', `status` tinyint(1) unsigned NOT NULL DEFAULT '1', `add_time` int(10) unsigned NOT NULL DEFAULT '0', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), UNIQUE KEY `uid_agent` (`uid`,`agent_id`), KEY `uid_time` (`uid`,`add_time`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e2) {
            }
        }

        CacheService::set($cacheKey, 1, 3600);
    }
}
