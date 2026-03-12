<?php

namespace app\services\ai;

use app\services\order\StoreOrderCreateServices;
use app\services\pay\PayServices;
use crmeb\services\CacheService;
use crmeb\services\pay\extend\allinpay\AllinPay;
use think\facade\Db;
use think\facade\Log;

class AiPowerRechargeServices
{
    protected $orderTable = 'ai_power_recharge';

    public function getRechargeConfig(): array
    {
        $packages = $this->getPackages();
        $attention = trim((string)sys_config('ai_power_recharge_attention', ''));
        $attentionArr = $attention !== '' ? explode("\n", $attention) : ['算力仅用于智能体矩阵对话', '每日前三次免费，之后按次扣费'];
        return [
            'enabled' => (int)sys_config('ai_power_enabled', 1),
            'cost_per_chat' => (int)sys_config('ai_power_cost_per_chat', 1),
            'free_daily_limit' => (int)sys_config('ai_power_free_daily_limit', 3),
            'packages' => $packages,
            'attention' => array_values(array_filter(array_map('trim', $attentionArr), static function ($v) {
                return $v !== '';
            })),
        ];
    }

    public function recharge(int $uid, int $recharId, string $from): array
    {
        $uid = (int)$uid;
        $recharId = (int)$recharId;
        $from = trim($from);
        app()->make(AiPowerServices::class)->quota($uid);
        $pkg = $this->getPackageById($recharId);
        if (!$pkg) return [];
        if ($uid <= 0) return [];

        $orderId = app()->make(StoreOrderCreateServices::class)->getNewOrderId('ap');
        $now = time();
        Db::name($this->orderTable)->insert([
            'order_id' => $orderId,
            'uid' => $uid,
            'pay_price' => (string)$pkg['price'],
            'power_amount' => (int)$pkg['power'],
            'pay_type' => '',
            'paid' => 0,
            'add_time' => $now,
            'pay_time' => 0,
            'trade_no' => '',
        ]);

        $pay = app()->make(PayServices::class);
        $openid = '';
        if ($from === PayServices::WEIXIN_PAY) {
            $openid = app()->make(\app\services\wechat\WechatUserServices::class)->uidToOpenid($uid, 'wechat');
        } elseif ($from === 'routine') {
            $openid = app()->make(\app\services\wechat\WechatUserServices::class)->uidToOpenid($uid, 'routine');
        }
        $title = (string)sys_config('ai_power_recharge_title', '算力充值');
        $options = [];
        if ($openid !== '') {
            $options['openid'] = $openid;
        }
        $res = $pay->pay($from, $orderId, (string)$pkg['price'], 'ai_power_recharge', $title, $options);
        if (!$res) {
            return [];
        }

        if (in_array($from, [PayServices::WEIXIN_PAY, 'routine', 'weixinh5', 'pc', 'store'], true)) {
            if (request()->isH5() || $from === 'weixinh5') {
                $payStatus = 'wechat_h5_pay';
            } else {
                $payStatus = 'wechat_pay';
            }
        } else if ($from == PayServices::ALIAPY_PAY) {
            $payStatus = 'alipay_pay';
        } else if ($from == PayServices::ALLIN_PAY) {
            $payStatus = 'allinpay_pay';
        } else {
            $payStatus = 'success';
        }

        $payType = strtoupper($payStatus);
        Db::name($this->orderTable)->where('order_id', $orderId)->update(['pay_type' => $payType, 'update_time' => $now]);
        Log::info('ai.power.recharge.pay_created', ['uid' => $uid, 'orderId' => $orderId, 'from' => $from, 'payType' => $payType]);
        return [
            'pay_url' => AllinPay::UNITODER_H5UNIONPAY,
            'jsConfig' => $res,
            'pay_key' => md5($orderId),
            'orderId' => $orderId,
            'pay_price' => (string)$pkg['price'],
            'power_amount' => (int)$pkg['power'],
            'pay_type' => $payType,
        ];
    }

    public function be(array $where): bool
    {
        try {
            $q = Db::name($this->orderTable);
            foreach ($where as $k => $v) {
                $q = $q->where($k, $v);
            }
            $id = $q->value('id');
            return (int)$id > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function rechargeSuccess(string $orderId, array $data = []): bool
    {
        $orderId = trim($orderId);
        if ($orderId === '') return false;
        $tradeNo = trim((string)($data['trade_no'] ?? ''));
        $payType = trim((string)($data['pay_type'] ?? ''));

        return (bool)Db::transaction(function () use ($orderId, $tradeNo, $payType) {
            $order = Db::name($this->orderTable)->where('order_id', $orderId)->lock(true)->find();
            if (!$order) return true;
            if ((int)($order['paid'] ?? 0) === 1) return true;
            $uid = (int)($order['uid'] ?? 0);
            $power = (int)($order['power_amount'] ?? 0);
            if ($uid <= 0 || $power <= 0) return false;

            Db::name($this->orderTable)->where('order_id', $orderId)->update([
                'paid' => 1,
                'pay_time' => time(),
                'trade_no' => $tradeNo,
                'pay_type' => $payType,
                'update_time' => time(),
            ]);
            app()->make(AiPowerServices::class)->increaseBalance($uid, $power);
            CacheService::delete('ai_power_quota:' . $uid);
            return true;
        });
    }

    protected function getPackages(): array
    {
        $raw = trim((string)sys_config('ai_power_recharge_packages', ''));
        $arr = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $arr = $decoded;
        }
        if (!$arr) {
            $arr = [
                ['id' => 1, 'price' => '9.90', 'power' => 30],
                ['id' => 2, 'price' => '19.90', 'power' => 80],
                ['id' => 3, 'price' => '49.90', 'power' => 240],
            ];
        }
        $out = [];
        foreach ($arr as $it) {
            if (!is_array($it)) continue;
            $id = (int)($it['id'] ?? 0);
            $price = (string)($it['price'] ?? '0');
            $power = (int)($it['power'] ?? 0);
            if ($id <= 0 || $power <= 0) continue;
            $out[] = ['id' => $id, 'price' => $price, 'power' => $power];
        }
        return $out;
    }

    protected function getPackageById(int $id): ?array
    {
        foreach ($this->getPackages() as $it) {
            if ((int)$it['id'] === (int)$id) return $it;
        }
        return null;
    }
}
