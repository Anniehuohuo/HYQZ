<?php

namespace app\dao\ai;

use app\dao\BaseDao;
use app\model\ai\AiAgent;

class AiAgentDao extends BaseDao
{
    protected function setModel(): string
    {
        return AiAgent::class;
    }

    public function getList(array $where, int $page = 0, int $limit = 0, array $field = ['*']): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->field($field)->order('sort DESC,id DESC')->select()->toArray();
    }
}

