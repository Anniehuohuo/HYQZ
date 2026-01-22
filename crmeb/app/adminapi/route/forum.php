<?php
use think\facade\Route;

Route::group('forum', function () {
    Route::group(function () {
        Route::get('post', 'v1.forum.ForumPost/index')->option(['real_name' => '论坛帖子列表']);
        Route::delete('post/:id', 'v1.forum.ForumPost/delete')->option(['real_name' => '删除论坛帖子']);
    })->option(['parent' => 'forum', 'cate_name' => '帖子管理']);

    Route::group(function () {
        Route::get('comment', 'v1.forum.ForumComment/index')->option(['real_name' => '论坛评论列表']);
        Route::delete('comment/:id', 'v1.forum.ForumComment/delete')->option(['real_name' => '删除论坛评论']);
    })->option(['parent' => 'forum', 'cate_name' => '评论管理']);

    Route::group(function () {
        Route::get('like', 'v1.forum.ForumLike/index')->option(['real_name' => '论坛点赞列表']);
        Route::delete('like/:id', 'v1.forum.ForumLike/delete')->option(['real_name' => '删除论坛点赞']);
    })->option(['parent' => 'forum', 'cate_name' => '点赞管理']);

    Route::group(function () {
        Route::get('draft', 'v1.forum.ForumDraft/index')->option(['real_name' => '论坛草稿列表']);
        Route::delete('draft/:id', 'v1.forum.ForumDraft/delete')->option(['real_name' => '删除论坛草稿']);
    })->option(['parent' => 'forum', 'cate_name' => '草稿管理']);
})->middleware([
    \app\http\middleware\AllowOriginMiddleware::class,
    \app\adminapi\middleware\AdminAuthTokenMiddleware::class,
    \app\adminapi\middleware\AdminCheckRoleMiddleware::class,
    \app\adminapi\middleware\AdminLogMiddleware::class
])->option(['mark' => 'forum', 'mark_name' => '论坛管理']);
