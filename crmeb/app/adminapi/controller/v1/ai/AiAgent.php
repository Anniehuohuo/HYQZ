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
            [['welcome', 's'], ''],
            [['suggestions', 's'], ''],
            [['context_mode', 's'], 'platform'],
            [['system_prompt', 's'], ''],
            ['temperature', ''],
            ['category_id', 0],
            [['provider', 's'], 'local'],
            [['bot_id', 's'], ''],
            [['api_key', 's'], ''],
            [['provider_assistant_id', 's'], ''],
            [['managed_model', 's'], ''],
            [['managed_knowledge', 's'], ''],
            [['tags', 's'], ''],
            ['sort', 0],
            ['status', 1],
            ['unlock_price', 0],
            ['gift_power', 0],
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
            [['welcome', 's'], ''],
            [['suggestions', 's'], ''],
            [['context_mode', 's'], 'platform'],
            [['system_prompt', 's'], ''],
            ['temperature', ''],
            ['category_id', 0],
            [['provider', 's'], 'local'],
            [['bot_id', 's'], ''],
            [['api_key', 's'], ''],
            [['provider_assistant_id', 's'], ''],
            [['managed_model', 's'], ''],
            [['managed_knowledge', 's'], ''],
            [['tags', 's'], ''],
            ['sort', 0],
            ['status', 1],
            ['unlock_price', 0],
            ['gift_power', 0],
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

    public function autoBindGoods()
    {
        $data = $this->service->autoBindGoods();
        return app('json')->success($data);
    }

    public function kbDocs(int $id)
    {
        return app('json')->success($this->service->getKbDocs($id));
    }

    public function importKbDoc(int $id)
    {
        [$attachmentId, $attDir] = $this->request->postMore([
            ['attachment_id', 0],
            [['att_dir', 's'], ''],
        ], true);
        $data = $this->service->importKbDoc($id, (int)$attachmentId, (string)$attDir);
        return app('json')->success('导入成功', $data);
    }

    public function deleteKbDoc(int $id, int $doc_id)
    {
        $this->service->deleteKbDoc($id, $doc_id);
        return app('json')->success(100002);
    }
}
