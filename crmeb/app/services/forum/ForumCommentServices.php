<?php

namespace app\services\forum;

use app\dao\forum\ForumCommentDao;
use app\services\BaseServices;
use app\services\user\UserServices;
use crmeb\exceptions\ApiException;

class ForumCommentServices extends BaseServices
{
    public function __construct(ForumCommentDao $dao)
    {
        $this->dao = $dao;
    }

    public function getCommentList(int $postId): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $where = ['post_id' => $postId, 'is_del' => 0];
        $list = $this->dao->getList($where, $page, $limit, 'id,post_id,uid,content,add_time');
        $count = $this->dao->count($where);
        $list = $this->formatCommentList($list);
        return compact('list', 'count');
    }

    public function getAdminCommentList(array $where): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $list = $this->dao->getList($where, $page, $limit, 'id,post_id,uid,content,add_time,is_del');
        $count = $this->dao->count($where);
        $list = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'post_id' => (int)($c['post_id'] ?? 0),
                'uid' => (int)($c['uid'] ?? 0),
                'content' => (string)($c['content'] ?? ''),
                'add_time' => $this->parseUnixTime($c['add_time'] ?? 0),
                'is_del' => (int)($c['is_del'] ?? 0),
            ];
        }, $list);
        return compact('list', 'count');
    }

    public function createComment(int $uid, int $postId, string $content): int
    {
        $content = trim($content);
        if ($content === '') {
            throw new ApiException('评论内容不能为空');
        }
        if (mb_strlen($content) > 1000) {
            throw new ApiException('评论内容过长');
        }
        $res = $this->dao->save([
            'post_id' => $postId,
            'uid' => $uid,
            'content' => $content,
            'is_del' => 0,
        ]);
        if (!$res) {
            throw new ApiException('评论失败');
        }
        return (int)$res->id;
    }

    public function deleteComment(int $uid, int $commentId): int
    {
        $comment = $this->dao->get($commentId);
        if (!$comment || (int)$comment['is_del'] === 1) {
            throw new ApiException('评论不存在或已删除');
        }
        if ((int)$comment['uid'] !== $uid) {
            throw new ApiException('无权限');
        }
        $postId = (int)$comment['post_id'];
        $comment->save(['is_del' => 1]);
        return $postId;
    }

    public function adminDeleteComment(int $commentId): void
    {
        $comment = $this->dao->get($commentId);
        if (!$comment) {
            throw new ApiException('评论不存在');
        }
        if ((int)$comment['is_del'] === 0) {
            $postId = (int)($comment['post_id'] ?? 0);
            $comment->save(['is_del' => 1]);
            if ($postId > 0) {
                app()->make(ForumPostServices::class)->bumpComments($postId, -1);
            }
            return;
        }

        $this->dao->delete($commentId);
    }

    public function getMyComments(int $uid): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $where = ['uid' => $uid, 'is_del' => 0];
        $list = $this->dao->getList($where, $page, $limit, 'id,post_id,uid,content,add_time');
        $count = $this->dao->count($where);
        $list = $this->formatCommentList($list);
        return compact('list', 'count');
    }

    private function formatCommentList(array $list): array
    {
        $uids = [];
        foreach ($list as $item) {
            $uids[] = (int)($item['uid'] ?? 0);
        }
        $uids = array_values(array_unique(array_filter($uids)));
        $userMap = $this->getUserMap($uids);

        return array_map(function ($c) use ($userMap) {
            $uid = (int)($c['uid'] ?? 0);
            $user = $userMap[$uid] ?? null;
            $author = $user ? ($user['nickname'] ?: ($user['real_name'] ?: ('用户' . $uid))) : ('用户' . $uid);
            $addTime = $this->parseUnixTime($c['add_time'] ?? 0);
            $time = $addTime ? date('Y-m-d H:i', $addTime) : '';
            return [
                'id' => (int)$c['id'],
                'postId' => (int)($c['post_id'] ?? 0),
                'authorUid' => $uid,
                'author' => $author,
                'authorInitial' => mb_substr(trim($author), 0, 1) ?: 'A',
                'avatar' => $user ? (string)($user['avatar'] ?? '') : '',
                'content' => (string)($c['content'] ?? ''),
                'add_time' => $this->normalizeDateTimeValue($c['add_time'] ?? null),
                'createdAt' => $addTime ? date('Y-m-d H:i:s', $addTime) : '',
                'createdAtTs' => $addTime,
                'time' => $time,
            ];
        }, $list);
    }

    private function getUserMap(array $uids): array
    {
        if (!$uids) return [];
        $users = app()->make(UserServices::class)->getUserInfoArray(['uid' => $uids], 'uid,nickname,real_name,avatar', 'uid');
        $map = [];
        foreach ($users as $u) {
            $map[(int)$u['uid']] = $u;
        }
        return $map;
    }

    private function normalizeDateTimeValue($value): string
    {
        if (is_string($value)) {
            $s = trim($value);
            if ($s !== '' && !is_numeric($s)) return $s;
        }
        $ts = $this->parseUnixTime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : '';
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
