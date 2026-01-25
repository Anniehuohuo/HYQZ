<?php

namespace app\services\ai;

use app\dao\ai\AiAgentDao;
use app\dao\ai\AiCategoryDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;

class AiAgentServices extends BaseServices
{
    protected AiCategoryDao $categoryDao;

    public function __construct(AiAgentDao $dao, AiCategoryDao $categoryDao)
    {
        $this->dao = $dao;
        $this->categoryDao = $categoryDao;
    }

    public function getAdminList(array $where, int $page, int $limit): array
    {
        $count = $this->dao->count($where);
        $list = $this->dao->getList($where, $page, $limit);
        if ($list) {
            $cateIds = array_values(array_unique(array_map(function ($item) {
                return (int)($item['category_id'] ?? 0);
            }, $list)));
            $cateRows = $this->categoryDao->selectList([['id', 'in', $cateIds]], 'id,cate_key,cate_name');
            $cateMap = [];
            foreach ($cateRows as $row) {
                $cateMap[(int)$row['id']] = $row;
            }
            foreach ($list as &$item) {
                $c = $cateMap[(int)$item['category_id']] ?? null;
                $item['cate_key'] = $c['cate_key'] ?? '';
                $item['cate_name'] = $c['cate_name'] ?? '';
            }
            unset($item);
        }
        return compact('list', 'count');
    }

    public function create(array $data): void
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        if (!$this->categoryDao->be($categoryId)) {
            throw new AdminException('分类不存在');
        }

        $botId = trim((string)($data['bot_id'] ?? ''));
        if ($botId === '') {
            throw new AdminException('bot_id不能为空');
        }
        if ($this->dao->be(['bot_id' => $botId])) {
            throw new AdminException('bot_id已存在');
        }

        $this->dao->save([
            'agent_name' => (string)($data['agent_name'] ?? ''),
            'avatar' => (string)($data['avatar'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'category_id' => $categoryId,
            'bot_id' => $botId,
            'api_key' => (string)($data['api_key'] ?? ''),
            'tags' => (string)($data['tags'] ?? ''),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }

        $categoryId = (int)($data['category_id'] ?? $info['category_id']);
        if (!$this->categoryDao->be($categoryId)) {
            throw new AdminException('分类不存在');
        }

        $botId = trim((string)($data['bot_id'] ?? $info['bot_id']));
        if ($botId === '') {
            throw new AdminException('bot_id不能为空');
        }
        if ($this->dao->be([['bot_id', '=', $botId], ['id', '<>', $id]])) {
            throw new AdminException('bot_id已存在');
        }

        $this->dao->update($id, [
            'agent_name' => (string)($data['agent_name'] ?? $info['agent_name']),
            'avatar' => (string)($data['avatar'] ?? $info['avatar']),
            'description' => (string)($data['description'] ?? $info['description']),
            'category_id' => $categoryId,
            'bot_id' => $botId,
            'api_key' => (string)($data['api_key'] ?? $info['api_key']),
            'tags' => (string)($data['tags'] ?? $info['tags']),
            'sort' => (int)($data['sort'] ?? $info['sort']),
            'status' => (int)($data['status'] ?? $info['status']),
        ]);
    }

    public function delete(int $id): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }
        $this->dao->delete($id);
    }

    public function setStatus(int $id, int $status): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('智能体不存在');
        }
        $this->dao->update($id, ['status' => $status ? 1 : 0]);
    }
}

