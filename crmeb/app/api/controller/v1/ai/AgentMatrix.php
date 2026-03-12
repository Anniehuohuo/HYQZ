<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiAgentMatrixServices;

class AgentMatrix
{
    protected AiAgentMatrixServices $services;

    public function __construct(AiAgentMatrixServices $services)
    {
        $this->services = $services;
    }

    public function index(Request $request)
    {
        $uid = (int)$request->uid();
        return app('json')->success($this->services->getEnabledMatrix($uid));
    }
}

