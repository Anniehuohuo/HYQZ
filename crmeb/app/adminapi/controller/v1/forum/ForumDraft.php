<?php

namespace app\adminapi\controller\v1\forum;

use app\adminapi\controller\AuthController;
use app\services\forum\ForumDraftServices;
use think\facade\App;

class ForumDraft extends AuthController
{
    public function __construct(App $app, ForumDraftServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    public function index()
    {
        $where = $this->request->getMore([
            ['uid', ''],
            ['tab', ''],
            ['keyword', ''],
            ['is_del', ''],
        ]);
        return app('json')->success($this->services->getAdminDraftList($where));
    }

    public function delete($id)
    {
        $this->services->adminDeleteDraft((int)$id);
        return app('json')->success(100002);
    }
}
