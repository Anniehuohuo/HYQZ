<?php

use think\facade\Route;

Route::group('ai', function () {
    Route::get('home_agent', 'v1.ai.HomeAgent/info')->option(['real_name' => '获取首页引流助手配置']);
    Route::post('home_agent', 'v1.ai.HomeAgent/save')->option(['real_name' => '保存首页引流助手配置']);
})->option(['mark' => 'ai', 'mark_name' => '智能体']);

