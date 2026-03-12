<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiAgentGoodsServices;

class AgentGoods
{
    protected AiAgentGoodsServices $services;

    public function __construct(AiAgentGoodsServices $services)
    {
        $this->services = $services;
    }

    public function saleInfo(Request $request)
    {
        $uid = (int)$request->uid();
        $agentId = (int)$request->get('agent_id', 0);
        if ($agentId <= 0) {
            return app('json')->fail('缺少agent_id');
        }
        $data = $this->services->saleInfo($uid, $agentId);
        if (!$data) {
            return app('json')->fail('智能体不存在或未启用');
        }
        return app('json')->success($data);
    }

    public function access(Request $request)
    {
        $uid = (int)$request->uid();
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        $agentId = (int)$request->get('agent_id', 0);
        if ($agentId <= 0) {
            return app('json')->fail('缺少agent_id');
        }
        $data = $this->services->access($uid, $agentId);
        return app('json')->success($data);
    }
}

