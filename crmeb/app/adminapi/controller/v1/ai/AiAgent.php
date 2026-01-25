<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\adminapi\validate\ai\AiAgentValidate;
use app\services\ai\AiAgentServices;
use think\facade\App;

class AiAgent extends AuthController
{
    protected $service;

    public function __construct(App $app, AiAgentServices $services)
    {
        parent::__construct($app);
        $this->service = $services;
    }

    public function index()
    {
        [$keyword, $categoryId, $status, $page, $limit] = $this->request->getMore([
            ['keyword', ''],
            ['category_id', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 20],
        ], true);

        $where = [
            'keyword' => $keyword,
            'category_id' => $categoryId,
            'status' => $status,
        ];

        return app('json')->success($this->service->getAdminList($where, (int)$page, (int)$limit));
    }

    public function save()
    {
        $data = $this->request->postMore([
            [['agent_name', 's'], ''],
            [['avatar', 's'], ''],
            [['description', 's'], ''],
            ['category_id', 0],
            [['bot_id', 's'], ''],
            [['api_key', 's'], ''],
            [['tags', 's'], ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, AiAgentValidate::class, 'create');
        $this->service->create($data);
        return app('json')->success(100000);
    }

    public function update(int $id)
    {
        $data = $this->request->postMore([
            [['agent_name', 's'], ''],
            [['avatar', 's'], ''],
            [['description', 's'], ''],
            ['category_id', 0],
            [['bot_id', 's'], ''],
            [['api_key', 's'], ''],
            [['tags', 's'], ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, AiAgentValidate::class, 'update');
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
