<?php

namespace app\api\controller\v1\ai;

use app\services\ai\AiAgentMatrixServices;

class AgentMatrix
{
    protected AiAgentMatrixServices $services;

    public function __construct(AiAgentMatrixServices $services)
    {
        $this->services = $services;
    }

    public function index()
    {
        return app('json')->success($this->services->getEnabledMatrix());
    }
}

