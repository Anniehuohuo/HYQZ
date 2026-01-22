<?php

namespace app\adminapi\controller\v1\forum;

use app\adminapi\controller\AuthController;
use app\services\forum\ForumPostServices;
use think\facade\App;

class ForumPost extends AuthController
{
    public function __construct(App $app, ForumPostServices $services)
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
        return app('json')->success($this->services->getAdminPostList($where));
    }

    public function delete($id)
    {
        $this->services->adminDeletePost((int)$id);
        return app('json')->success(100002);
    }
}
