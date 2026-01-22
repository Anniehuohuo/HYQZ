<?php

namespace app\dao\forum;

use app\dao\BaseDao;
use app\model\forum\ForumComment;

class ForumCommentDao extends BaseDao
{
    protected function setModel(): string
    {
        return ForumComment::class;
    }

    public function getList(array $where, int $page, int $limit, string $field = '*')
    {
        return $this->search($where)->field($field)->page($page, $limit)->order('add_time desc,id desc')->select()->toArray();
    }
}

