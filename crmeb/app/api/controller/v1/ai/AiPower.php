<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiPowerRechargeServices;
use app\services\ai\AiPowerServices;
use app\services\pay\PayServices;

class AiPower
{
    protected $powerServices;
    protected $rechargeServices;

    public function __construct(AiPowerServices $powerServices, AiPowerRechargeServices $rechargeServices)
    {
        $this->powerServices = $powerServices;
        $this->rechargeServices = $rechargeServices;
    }

    public function quota(Request $request)
    {
        $uid = (int)$request->uid();
        return app('json')->success($this->powerServices->quota($uid));
    }

    public function rechargeConfig()
    {
        return app('json')->success($this->rechargeServices->getRechargeConfig());
    }

    public function recharge(Request $request)
    {
        [$recharId, $from] = $request->postMore([
            ['rechar_id', 0],
            ['from', PayServices::WEIXIN_PAY],
        ], true);
        $uid = (int)$request->uid();
        if ($recharId <= 0) return app('json')->fail('请选择充值档位');
        if (!in_array($from, [PayServices::WEIXIN_PAY, 'weixinh5', 'routine', PayServices::ALIAPY_PAY])) return app('json')->fail('支付方式不支持');
        $re = $this->rechargeServices->recharge($uid, (int)$recharId, (string)$from);
        if ($re) {
            $payType = $re['pay_type'] ?? '';
            unset($re['pay_type']);
            return app('json')->status($payType ?: 'SUCCESS', 'success', $re);
        }
        return app('json')->fail('发起支付失败');
    }
}

