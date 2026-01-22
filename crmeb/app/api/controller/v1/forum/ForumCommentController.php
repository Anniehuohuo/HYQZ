<?php

namespace app\api\controller\v1\forum;

use app\Request;
use app\services\forum\ForumCommentServices;
use app\services\forum\ForumPostServices;

class ForumCommentController
{
    protected $services;
    protected $postServices;

    public function __construct(ForumCommentServices $services, ForumPostServices $postServices)
    {
        $this->services = $services;
        $this->postServices = $postServices;
    }

    public function lst(Request $request, $id)
    {
        $postId = (int)$id;
        $this->postServices->ensurePostExists($postId);
        return app('json')->success($this->services->getCommentList($postId));
    }

    public function create(Request $request, $id)
    {
        $uid = (int)$request->uid();
        $postId = (int)$id;
        [$content] = $request->postMore([
            ['content', ''],
        ], true);
        $this->postServices->ensurePostExists($postId);
        $commentId = $this->services->createComment($uid, $postId, (string)$content);
        $this->postServices->bumpComments($postId, 1);
        return app('json')->success(['id' => $commentId]);
    }

    public function delete(Request $request, $id)
    {
        $uid = (int)$request->uid();
        $commentId = (int)$id;
        $postId = $this->services->deleteComment($uid, $commentId);
        $this->postServices->bumpComments($postId, -1);
        return app('json')->success();
    }

    public function my(Request $request)
    {
        $uid = (int)$request->uid();
        $data = $this->services->getMyComments($uid);
        $postIds = [];
        foreach ($data['list'] as $c) {
            $postIds[] = (int)($c['postId'] ?? 0);
        }
        $titleMap = $this->postServices->getPostTitleMap($postIds);
        $data['list'] = array_map(function ($c) use ($titleMap) {
            $postId = (int)($c['postId'] ?? 0);
            $c['postTitle'] = (string)($titleMap[$postId] ?? '');
            return $c;
        }, $data['list']);
        return app('json')->success($data);
    }
}

