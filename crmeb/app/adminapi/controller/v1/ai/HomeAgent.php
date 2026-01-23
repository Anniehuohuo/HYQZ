<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\Request;
use app\services\ai\AiChatServices;
use think\facade\App;

class HomeAgent extends AuthController
{
    protected AiChatServices $aiChatServices;

    public function __construct(App $app, AiChatServices $aiChatServices)
    {
        parent::__construct($app);
        $this->aiChatServices = $aiChatServices;
    }

    public function info()
    {
        return app('json')->success($this->aiChatServices->getHomeAgentConfig());
    }

    public function save(Request $request)
    {
        $post = $request->post();
        $this->aiChatServices->saveHomeAgentConfig($post);
        return app('json')->success(100001);
    }
}

