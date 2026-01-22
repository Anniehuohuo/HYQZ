<?php

namespace app\adminapi\controller\v1\forum;

use app\adminapi\controller\AuthController;
use app\services\forum\ForumLikeServices;
use think\facade\App;

class ForumLike extends AuthController
{
    public function __construct(App $app, ForumLikeServices $services)
    {
        parent::__construct($app);
        $this->services = $services;
    }

    public function index()
    {
        $where = $this->request->getMore([
            ['post_id', ''],
            ['uid', ''],
        ]);
        return app('json')->success($this->services->getAdminLikeList($where));
    }

    public function delete($id)
    {
        $this->services->adminDeleteLike((int)$id);
        return app('json')->success(100002);
    }
}
