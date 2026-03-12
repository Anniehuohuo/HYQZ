<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\services\ai\AiPowerServices;
use crmeb\services\FormBuilder as Form;
use think\facade\App;
use think\facade\Db;
use think\facade\Route as Url;

class AiPowerUser extends AuthController
{
    protected AiPowerServices $powerServices;

    public function __construct(App $app, AiPowerServices $powerServices)
    {
        parent::__construct($app);
        $this->powerServices = $powerServices;
    }

    public function giveForm(int $uid)
    {
        if ($uid <= 0) return app('json')->fail(100026);
        $quota = $this->powerServices->quota($uid);
        $f = [];
        $f[] = Form::input('uid', '用户编号', (string)$uid)->disabled(true);
        $f[] = Form::input('current_balance', '当前算力', (string)($quota['balance'] ?? 0))->disabled(true);
        $f[] = Form::number('amount', '赠送算力', 0)->min(1)->precision(0)->max(999999);
        $f[] = Form::textarea('remark', '备注', '')->maxlength(100);
        return app('json')->success(create_form('赠送算力', $f, Url::buildUrl('/ai/power_user/give/' . $uid), 'PUT'));
    }

    public function give(int $uid)
    {
        $data = $this->request->postMore([
            ['amount', 0],
            ['remark', ''],
        ]);
        if ($uid <= 0) return app('json')->fail(100100);
        $amount = (int)($data['amount'] ?? 0);
        if ($amount <= 0) {
            return app('json')->fail('赠送算力必须大于0');
        }
        $remark = trim((string)($data['remark'] ?? ''));
        $ok = $this->powerServices->increaseBalance($uid, $amount);
        if (!$ok) {
            return app('json')->fail('赠送失败');
        }
        $sid = 'admin:' . (int)$this->adminId;
        if ($remark !== '') {
            $sid .= ':' . mb_substr($remark, 0, 40);
        }
        $sid = trim($sid);
        if (mb_strlen($sid) > 64) {
            $sid = mb_substr($sid, 0, 64);
        }
        try {
            Db::name('ai_power_user')->where('uid', $uid)->update(['update_time' => time()]);
        } catch (\Throwable $e) {
        }
        try {
            Db::name('ai_power_bill')->insert([
                'uid' => (int)$uid,
                'agent_id' => 0,
                'session_id' => $sid,
                'cost' => $amount,
                'type' => 'admin_give',
                'add_time' => time(),
            ]);
        } catch (\Throwable $e) {
        }
        return app('json')->success(100001);
    }
}

