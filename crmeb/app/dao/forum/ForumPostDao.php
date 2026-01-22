<?php

namespace app\dao\forum;

use app\dao\BaseDao;
use app\model\forum\ForumPost;

class ForumPostDao extends BaseDao
{
    protected function setModel(): string
    {
        return ForumPost::class;
    }

    public function getTitleMapByIds(array $postIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (!$ids) return [];
        return $this->getModel()->whereIn('id', $ids)->column('title', 'id') ?: [];
    }

    public function getList(array $where, int $page, int $limit, string $field = '*')
    {
        return $this->search($where)->field($field)->page($page, $limit)->order('add_time desc,id desc')->select()->toArray();
    }

    public function getDetail(int $id, string $field = '*')
    {
        return $this->getModel()->where(['id' => $id, 'is_del' => 0])->field($field)->find();
    }
}

