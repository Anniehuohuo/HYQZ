<?php

namespace app\adminapi\controller\v1\forum;

use app\adminapi\controller\AuthController;
use app\services\forum\ForumCommentServices;
use think\facade\App;

class ForumComment extends AuthController
{
    public function __construct(App $app, ForumCommentServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    public function index()
    {
        $where = $this->request->getMore([
            ['post_id', ''],
            ['uid', ''],
            ['keyword', ''],
            ['is_del', ''],
        ]);
        return app('json')->success($this->services->getAdminCommentList($where));
    }

    public function delete($id)
    {
        $this->services->adminDeleteComment((int)$id);
        return app('json')->success(100002);
    }
}
