<?php

namespace app\api\controller\v1\forum;

use app\Request;
use app\services\forum\ForumLikeServices;
use app\services\forum\ForumPostServices;

class ForumPostController
{
    protected $services;
    protected $likeServices;

    public function __construct(ForumPostServices $services, ForumLikeServices $likeServices)
    {
        $this->services = $services;
        $this->likeServices = $likeServices;
    }

    public function lst(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = 0;
        try {
            $uid = (int)$request->uid();
        } catch (\Throwable $e) {
            $uid = 0;
        }
        [$tab, $keyword] = $request->getMore([
            ['tab', ''],
            ['keyword', ''],
        ], true);

        $where = [];
        if ($tab !== '' && $tab !== null) $where['tab'] = $tab;
        if ($keyword !== '' && $keyword !== null) $where['keyword'] = $keyword;

        $data = $this->services->getPostList($where);
        if ($uid > 0 && isset($data['list']) && is_array($data['list']) && $data['list']) {
            $postIds = array_column($data['list'], 'id');
            $likedMap = $this->likeServices->getLikedPostIdMap($uid, $postIds);
            $data['list'] = array_map(function ($item) use ($likedMap) {
                $id = (int)($item['id'] ?? 0);
                $item['liked'] = isset($likedMap[$id]) ? true : false;
                return $item;
            }, $data['list']);
        }
        return app('json')->success($data);
    }

    public function detail(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = 0;
        try {
            $uid = (int)$request->uid();
        } catch (\Throwable $e) {
            $uid = 0;
        }
        $postId = (int)$id;
        if ($postId <= 0) {
            return app('json')->fail('缺少帖子ID');
        }
        $post = $this->services->getPostDetail($postId, $uid);
        $post['liked'] = $uid ? $this->likeServices->hasLiked($uid, $postId) : false;
        return app('json')->success($post);
    }

    public function create(Request $request)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $data = $request->postMore([
            ['tab', ''],
            ['title', ''],
            ['content', ''],
        ]);
        $id = $this->services->createPost($uid, $data);
        return app('json')->success(['id' => $id]);
    }

    public function update(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $postId = (int)$id;
        $data = $request->postMore([
            ['tab', ''],
            ['title', ''],
            ['content', ''],
        ]);
        $this->services->updatePost($uid, $postId, $data);
        return app('json')->success();
    }

    public function delete(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $postId = (int)$id;
        $this->services->deletePost($uid, $postId);
        return app('json')->success();
    }

    public function toggleLike(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        $postId = (int)$id;
        $this->services->ensurePostExists($postId);
        $liked = $this->likeServices->toggleLike($uid, $postId);
        $this->services->bumpLikes($postId, $liked ? 1 : -1);
        return app('json')->success(array_merge(['liked' => $liked], $this->services->getPostCounters($postId)));
    }

    public function edit(Request $request, $id)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        return app('json')->success($this->services->getEditPost($uid, (int)$id));
    }

    public function my(Request $request)
    {
        if (!(int)sys_config('forum_enabled', 0)) return app('json')->fail('该功能暂未开放');
        $uid = (int)$request->uid();
        return app('json')->success($this->services->getMyPosts($uid));
    }
}
