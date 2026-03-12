<?php

namespace app\api\controller\v1\forum;

use app\Request;
use app\services\forum\ForumDraftServices;

class ForumDraftController
{
    protected $services;

    public function __construct(ForumDraftServices $services)
    {
        $this->services = $services;
    }

    public function lst(Request $request)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        return app('json')->success($this->services->getDraftList($uid));
    }

    public function detail(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        return app('json')->success($this->services->getDraftDetail($uid, (int)$id));
    }

    public function save(Request $request)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $data = $request->postMore([
            ['id', 0],
            ['postId', 0],
            ['tab', ''],
            ['title', ''],
            ['content', ''],
        ]);
        $id = $this->services->saveDraft($uid, $data);
        return app('json')->success(['id' => $id]);
    }

    public function delete(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $this->services->deleteDraft($uid, (int)$id);
        return app('json')->success();
    }
}

