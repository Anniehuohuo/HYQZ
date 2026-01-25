<?php

use think\facade\Route;

Route::group('ai', function () {
    Route::get('home_agent', 'v1.ai.HomeAgent/info')->option(['real_name' => '获取首页引流助手配置']);
    Route::post('home_agent', 'v1.ai.HomeAgent/save')->option(['real_name' => '保存首页引流助手配置']);
    Route::get('agent_categories', 'v1.ai.AiCategory/index')->option(['real_name' => '获取智能体分类列表']);
    Route::post('agent_categories', 'v1.ai.AiCategory/save')->option(['real_name' => '新增智能体分类']);
    Route::put('agent_categories/:id', 'v1.ai.AiCategory/update')->option(['real_name' => '编辑智能体分类']);
    Route::delete('agent_categories/:id', 'v1.ai.AiCategory/delete')->option(['real_name' => '删除智能体分类']);
    Route::put('agent_categories/set_status/:id/:status', 'v1.ai.AiCategory/setStatus')->option(['real_name' => '修改智能体分类状态']);

    Route::get('agents', 'v1.ai.AiAgent/index')->option(['real_name' => '获取智能体列表']);
    Route::post('agents', 'v1.ai.AiAgent/save')->option(['real_name' => '新增智能体']);
    Route::put('agents/:id', 'v1.ai.AiAgent/update')->option(['real_name' => '编辑智能体']);
    Route::delete('agents/:id', 'v1.ai.AiAgent/delete')->option(['real_name' => '删除智能体']);
    Route::put('agents/set_status/:id/:status', 'v1.ai.AiAgent/setStatus')->option(['real_name' => '修改智能体状态']);
})->middleware([
    \app\http\middleware\AllowOriginMiddleware::class,
    \app\adminapi\middleware\AdminAuthTokenMiddleware::class,
    \app\adminapi\middleware\AdminCheckRoleMiddleware::class,
    \app\adminapi\middleware\AdminLogMiddleware::class,
])->option(['mark' => 'ai', 'mark_name' => '智能体']);

