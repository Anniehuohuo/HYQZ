<?php

namespace app\dao\forum;

use app\dao\BaseDao;
use app\model\forum\ForumPostLike;

class ForumPostLikeDao extends BaseDao
{
    protected function setModel(): string
    {
        return ForumPostLike::class;
    }

    public function exists(int $postId, int $uid): bool
    {
        return $this->be(['post_id' => $postId, 'uid' => $uid]);
    }

    public function deleteByPostAndUid(int $postId, int $uid): bool
    {
        return false !== $this->getModel()->where(['post_id' => $postId, 'uid' => $uid])->delete();
    }

    public function getLikedPostIds(int $uid, array $postIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (!$uid || !$ids) return [];
        return $this->getModel()->where('uid', $uid)->whereIn('post_id', $ids)->column('post_id') ?: [];
    }

    public function getList(array $where, int $page, int $limit, string $field = '*')
    {
        return $this->search($where)->field($field)->page($page, $limit)->order('add_time desc,id desc')->select()->toArray();
    }
}
