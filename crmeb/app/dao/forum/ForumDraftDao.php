<?php

namespace app\dao\forum;

use app\dao\BaseDao;
use app\model\forum\ForumDraft;

class ForumDraftDao extends BaseDao
{
    protected function setModel(): string
    {
        return ForumDraft::class;
    }

    public function getList(array $where, int $page, int $limit, string $field = '*')
    {
        return $this->search($where)->field($field)->page($page, $limit)->order('update_time desc,id desc')->select()->toArray();
    }
}

