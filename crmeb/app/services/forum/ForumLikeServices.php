<?php

namespace app\services\forum;

use app\dao\forum\ForumPostLikeDao;
use app\services\BaseServices;
use crmeb\exceptions\ApiException;

class ForumLikeServices extends BaseServices
{
    public function __construct(ForumPostLikeDao $dao)
    {
        $this->dao = $dao;
    }

    public function toggleLike(int $uid, int $postId): bool
    {
        if ($uid <= 0) throw new ApiException('未登录');
        $exists = $this->dao->exists($postId, $uid);
        if ($exists) {
            $this->dao->deleteByPostAndUid($postId, $uid);
            return false;
        }
        try {
            $res = $this->dao->save([
                'post_id' => $postId,
                'uid' => $uid,
            ]);
            if (!$res) throw new ApiException('点赞失败');
            return true;
        } catch (\Throwable $e) {
            if ($this->dao->exists($postId, $uid)) {
                return true;
            }
            throw new ApiException('点赞失败');
        }
    }

    public function hasLiked(int $uid, int $postId): bool
    {
        if ($uid <= 0) return false;
        return $this->dao->exists($postId, $uid);
    }

    public function getLikedPostIdMap(int $uid, array $postIds): array
    {
        if ($uid <= 0) return [];
        $likedIds = $this->dao->getLikedPostIds($uid, $postIds);
        $map = [];
        foreach ($likedIds as $id) {
            $map[(int)$id] = true;
        }
        return $map;
    }

    public function getAdminLikeList(array $where): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $list = $this->dao->getList($where, $page, $limit, 'id,post_id,uid,add_time');
        $count = $this->dao->count($where);
        $list = array_map(function ($item) {
            return [
                'id' => (int)$item['id'],
                'post_id' => (int)($item['post_id'] ?? 0),
                'uid' => (int)($item['uid'] ?? 0),
                'add_time' => $this->parseUnixTime($item['add_time'] ?? 0),
            ];
        }, $list);
        return compact('list', 'count');
    }

    public function adminDeleteLike(int $likeId): void
    {
        $like = $this->dao->get($likeId);
        if (!$like) {
            throw new ApiException('点赞记录不存在');
        }
        $like->delete();
    }

    private function parseUnixTime($value): int
    {
        if ($value === null || $value === '' || $value === false) return 0;
        if (is_int($value)) return $value > 0 ? $value : 0;
        if (is_numeric($value)) return (int)$value;
        $ts = strtotime((string)$value);
        return $ts ? (int)$ts : 0;
    }
}
