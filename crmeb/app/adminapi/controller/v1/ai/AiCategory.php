<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\adminapi\validate\ai\AiCategoryValidate;
use app\services\ai\AiCategoryServices;
use think\facade\App;

class AiCategory extends AuthController
{
    protected $service;

    public function __construct(App $app, AiCategoryServices $services)
    {
        parent::__construct($app);
        $this->service = $services;
    }

    public function index()
    {
        [$keyword, $status, $page, $limit] = $this->request->getMore([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 20],
        ], true);

        $where = [
            'keyword' => $keyword,
            'status' => $status,
        ];

        return app('json')->success($this->service->getAdminList($where, (int)$page, (int)$limit));
    }

    public function save()
    {
        $data = $this->request->postMore([
            [['cate_key', 's'], ''],
            [['cate_name', 's'], ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, AiCategoryValidate::class, 'create');
        $this->service->create($data);
        return app('json')->success(100000);
    }

    public function update(int $id)
    {
        $data = $this->request->postMore([
            [['cate_key', 's'], ''],
            [['cate_name', 's'], ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, AiCategoryValidate::class, 'update');
        $this->service->update($id, $data);
        return app('json')->success(100001);
    }

    public function delete(int $id)
    {
        $this->service->delete($id);
        return app('json')->success(100002);
    }

    public function setStatus(int $id, int $status)
    {
        $this->service->setStatus($id, $status);
        return app('json')->success(100001);
    }
}
