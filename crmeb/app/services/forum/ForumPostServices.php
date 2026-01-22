<?php

namespace app\services\forum;

use app\dao\forum\ForumPostDao;
use app\dao\forum\ForumCommentDao;
use app\dao\forum\ForumDraftDao;
use app\dao\forum\ForumPostLikeDao;
use app\services\BaseServices;
use app\services\user\UserServices;
use crmeb\exceptions\ApiException;

class ForumPostServices extends BaseServices
{
    public function __construct(ForumPostDao $dao)
    {
        $this->dao = $dao;
    }

    public function getEditPost(int $uid, int $postId): array
    {
        $post = $this->dao->get($postId);
        if (!$post || (int)$post['is_del'] === 1) {
            throw new ApiException('帖子不存在或已删除');
        }
        if ((int)$post['uid'] !== $uid) {
            throw new ApiException('无权限');
        }
        return [
            'id' => (int)$post['id'],
            'tab' => (string)($post['tab'] ?? ''),
            'title' => (string)($post['title'] ?? ''),
            'content' => (string)($post['content'] ?? ''),
        ];
    }

    public function getPostTitleMap(array $postIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (!$ids) return [];
        return $this->dao->getTitleMapByIds($ids);
    }

    public function getPostList(array $where): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $where = array_merge(['is_del' => 0], $where);
        $list = $this->dao->getList($where, $page, $limit, 'id,uid,tab,title,content,views,likes,comments,add_time,update_time');
        $count = $this->dao->count($where);
        $list = $this->formatPostList($list);
        return compact('list', 'count');
    }

    public function getAdminPostList(array $where): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $list = $this->dao->getList($where, $page, $limit, 'id,uid,tab,title,views,likes,comments,add_time,is_del');
        $count = $this->dao->count($where);
        $list = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'uid' => (int)($p['uid'] ?? 0),
                'tab' => (string)($p['tab'] ?? ''),
                'title' => (string)($p['title'] ?? ''),
                'views' => (int)($p['views'] ?? 0),
                'likes' => (int)($p['likes'] ?? 0),
                'comments' => (int)($p['comments'] ?? 0),
                'add_time' => $this->parseUnixTime($p['add_time'] ?? 0),
                'is_del' => (int)($p['is_del'] ?? 0),
            ];
        }, $list);
        return compact('list', 'count');
    }

    public function getPostDetail(int $postId, int $viewerUid = 0): array
    {
        $post = $this->dao->getDetail($postId, 'id,uid,tab,title,content,views,likes,comments,add_time,update_time');
        if (!$post) {
            throw new ApiException('帖子不存在或已删除');
        }
        try {
            $this->dao->search(['id' => $postId, 'is_del' => 0])->inc('views', 1)->update();
        } catch (\Throwable $e) {
        }
        $post = $post->toArray();
        $post['views'] = (int)$post['views'] + 1;
        $post = $this->formatPost($post);
        $post['isMine'] = $viewerUid ? ((int)$post['authorUid'] === $viewerUid) : false;
        return $post;
    }

    public function ensurePostExists(int $postId): void
    {
        $exists = $this->dao->be(['id' => $postId, 'is_del' => 0]);
        if (!$exists) throw new ApiException('帖子不存在或已删除');
    }

    public function bumpLikes(int $postId, int $delta): void
    {
        if ($delta === 0) return;
        $model = $this->dao->search(['id' => $postId, 'is_del' => 0]);
        if ($delta > 0) {
            $model->inc('likes', $delta)->update();
        } else {
            $dec = abs($delta);
            $model->where('likes', '>=', $dec)->dec('likes', $dec)->update();
        }
    }

    public function bumpComments(int $postId, int $delta): void
    {
        if ($delta === 0) return;
        $model = $this->dao->search(['id' => $postId, 'is_del' => 0]);
        if ($delta > 0) {
            $model->inc('comments', $delta)->update();
        } else {
            $dec = abs($delta);
            $model->where('comments', '>=', $dec)->dec('comments', $dec)->update();
        }
    }

    public function createPost(int $uid, array $data): int
    {
        $tab = trim((string)($data['tab'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));

        if ($tab === '' || $title === '' || $content === '') {
            throw new ApiException('参数不完整');
        }
        if (mb_strlen($title) < 4) {
            throw new ApiException('标题至少4字');
        }
        if (mb_strlen($content) < 10) {
            throw new ApiException('内容至少10字');
        }

        $res = $this->dao->save([
            'uid' => $uid,
            'tab' => $tab,
            'title' => $title,
            'content' => $content,
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'is_del' => 0,
        ]);
        if (!$res) {
            throw new ApiException('发布失败');
        }
        return (int)$res->id;
    }

    public function updatePost(int $uid, int $postId, array $data): bool
    {
        $post = $this->dao->get($postId);
        if (!$post || (int)$post['is_del'] === 1) {
            throw new ApiException('帖子不存在或已删除');
        }
        if ((int)$post['uid'] !== $uid) {
            throw new ApiException('无权限');
        }

        $tab = trim((string)($data['tab'] ?? $post['tab']));
        $title = trim((string)($data['title'] ?? $post['title']));
        $content = trim((string)($data['content'] ?? $post['content']));

        if ($tab === '' || $title === '' || $content === '') {
            throw new ApiException('参数不完整');
        }
        if (mb_strlen($title) < 4) {
            throw new ApiException('标题至少4字');
        }
        if (mb_strlen($content) < 10) {
            throw new ApiException('内容至少10字');
        }

        return false !== $post->save([
            'tab' => $tab,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function deletePost(int $uid, int $postId): bool
    {
        $post = $this->dao->get($postId);
        if (!$post || (int)$post['is_del'] === 1) {
            throw new ApiException('帖子不存在或已删除');
        }
        if ((int)$post['uid'] !== $uid) {
            throw new ApiException('无权限');
        }
        return false !== $post->save(['is_del' => 1]);
    }

    public function adminDeletePost(int $postId): void
    {
        $post = $this->dao->get($postId);
        if (!$post) {
            throw new ApiException('帖子不存在');
        }
        if ((int)$post['is_del'] === 0) {
            $post->save(['is_del' => 1]);
            return;
        }

        $this->transaction(function () use ($postId) {
            $this->dao->delete($postId);
            app()->make(ForumCommentDao::class)->delete(['post_id' => $postId]);
            app()->make(ForumPostLikeDao::class)->delete(['post_id' => $postId]);
            app()->make(ForumDraftDao::class)->delete(['post_id' => $postId]);
        });
    }

    public function getMyPosts(int $uid): array
    {
        [$page, $limit, $defaultLimit] = $this->getPageValue();
        $page = $page ?: 1;
        $limit = $limit ?: $defaultLimit;

        $where = ['uid' => $uid, 'is_del' => 0];
        $list = $this->dao->getList($where, $page, $limit, 'id,uid,tab,title,content,views,likes,comments,add_time,update_time');
        $count = $this->dao->count($where);
        $list = $this->formatPostList($list);
        return compact('list', 'count');
    }

    public function getPostCounters(int $postId): array
    {
        $post = $this->dao->getDetail($postId, 'id,views,likes,comments');
        if (!$post) {
            throw new ApiException('帖子不存在或已删除');
        }
        $arr = $post->toArray();
        return [
            'views' => (int)($arr['views'] ?? 0),
            'likes' => (int)($arr['likes'] ?? 0),
            'comments' => (int)($arr['comments'] ?? 0),
        ];
    }

    private function formatPostList(array $list): array
    {
        $uids = [];
        foreach ($list as $item) {
            $uids[] = (int)($item['uid'] ?? 0);
        }
        $uids = array_values(array_unique(array_filter($uids)));
        $userMap = $this->getUserMap($uids);

        return array_map(function ($p) use ($userMap) {
            $p = $this->formatPost($p, $userMap);
            unset($p['content']);
            return $p;
        }, $list);
    }

    private function formatPost(array $p, array $userMap = []): array
    {
        $uid = (int)($p['uid'] ?? 0);
        $user = $userMap[$uid] ?? null;
        $author = $user ? ($user['nickname'] ?: ($user['real_name'] ?: ('用户' . $uid))) : ('用户' . $uid);

        $content = (string)($p['content'] ?? '');
        $desc = preg_replace('/\s+/u', ' ', trim($content));
        if (mb_strlen($desc) > 60) {
            $desc = mb_substr($desc, 0, 60) . '…';
        }

        $addTime = $this->parseUnixTime($p['add_time'] ?? 0);
        $updateTime = $this->parseUnixTime($p['update_time'] ?? 0);

        return [
            'id' => (int)$p['id'],
            'tab' => (string)($p['tab'] ?? ''),
            'title' => (string)($p['title'] ?? ''),
            'desc' => $desc,
            'content' => $content,
            'views' => (int)($p['views'] ?? 0),
            'likes' => (int)($p['likes'] ?? 0),
            'comments' => (int)($p['comments'] ?? 0),
            'authorUid' => $uid,
            'author' => $author,
            'authorInitial' => mb_substr(trim($author), 0, 1) ?: 'A',
            'avatar' => $user ? (string)($user['avatar'] ?? '') : '',
            'add_time' => $this->normalizeDateTimeValue($p['add_time'] ?? null),
            'update_time' => $this->normalizeDateTimeValue($p['update_time'] ?? null),
            'createdAt' => $addTime ? date('Y-m-d H:i:s', $addTime) : '',
            'updatedAt' => $updateTime ? date('Y-m-d H:i:s', $updateTime) : '',
            'createdAtTs' => $addTime,
            'updatedAtTs' => $updateTime,
            'time' => $addTime ? date('Y-m-d H:i', $addTime) : '',
        ];
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
}
