<?php

namespace app\services\ai;

use app\dao\ai\AiCategoryDao;
use app\dao\ai\AiAgentDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;

class AiCategoryServices extends BaseServices
{
    protected AiAgentDao $agentDao;

    public function __construct(AiCategoryDao $dao, AiAgentDao $agentDao)
    {
        $this->dao = $dao;
        $this->agentDao = $agentDao;
    }

    public function getAdminList(array $where, int $page, int $limit): array
    {
        $count = $this->dao->count($where);
        $list = $this->dao->getList($where, $page, $limit);
        return compact('list', 'count');
    }

    public function create(array $data): void
    {
        $cateKey = trim((string)($data['cate_key'] ?? ''));
        if ($cateKey === '') {
            throw new AdminException('cate_key不能为空');
        }
        if ($this->dao->be(['cate_key' => $cateKey])) {
            throw new AdminException('cate_key已存在');
        }
        $this->dao->save([
            'cate_key' => $cateKey,
            'cate_name' => (string)($data['cate_name'] ?? ''),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('分类不存在');
        }
        $cateKey = trim((string)($data['cate_key'] ?? $info['cate_key']));
        if ($cateKey === '') {
            throw new AdminException('cate_key不能为空');
        }
        if ($this->dao->be([['cate_key', '=', $cateKey], ['id', '<>', $id]])) {
            throw new AdminException('cate_key已存在');
        }
        $this->dao->update($id, [
            'cate_key' => $cateKey,
            'cate_name' => (string)($data['cate_name'] ?? $info['cate_name']),
            'sort' => (int)($data['sort'] ?? $info['sort']),
            'status' => (int)($data['status'] ?? $info['status']),
        ]);
    }

    public function delete(int $id): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('分类不存在');
        }
        if ($this->agentDao->count(['category_id' => $id]) > 0) {
            throw new AdminException('该分类下仍有智能体，无法删除');
        }
        $this->dao->delete($id);
    }

    public function setStatus(int $id, int $status): void
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('分类不存在');
        }
        $this->dao->update($id, ['status' => $status ? 1 : 0]);
    }
}
