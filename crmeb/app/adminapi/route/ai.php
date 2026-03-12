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
    Route::post('agents/auto_bind_goods', 'v1.ai.AiAgent/autoBindGoods')->option(['real_name' => '自动补齐智能体售卖绑定']);
    Route::get('agents/:id/kb_docs', 'v1.ai.AiAgent/kbDocs')->option(['real_name' => '获取中台知识文档']);
    Route::post('agents/:id/kb_docs/import', 'v1.ai.AiAgent/importKbDoc')->option(['real_name' => '导入中台知识文档']);
    Route::delete('agents/:id/kb_docs/:doc_id', 'v1.ai.AiAgent/deleteKbDoc')->option(['real_name' => '删除中台知识文档']);

    Route::get('power_user/give_form/:uid', 'v1.ai.AiPowerUser/giveForm')->option(['real_name' => '赠送算力表单']);
    Route::put('power_user/give/:uid', 'v1.ai.AiPowerUser/give')->option(['real_name' => '赠送算力']);

    Route::get('power_config', 'v1.ai.AiPowerConfig/get')->option(['real_name' => '算力配置']);
    Route::post('power_config', 'v1.ai.AiPowerConfig/save')->option(['real_name' => '保存算力配置']);

    Route::get('qingyan_config', 'v1.ai.QingyanConfig/get')->option(['real_name' => '获取清言配置']);
    Route::post('qingyan_config', 'v1.ai.QingyanConfig/save')->option(['real_name' => '保存清言配置']);
    Route::post('qingyan_config/verify', 'v1.ai.QingyanConfig/verify')->option(['real_name' => '验证清言智能体']);
})->middleware([
    \app\http\middleware\AllowOriginMiddleware::class,
    \app\adminapi\middleware\AdminAuthTokenMiddleware::class,
    \app\adminapi\middleware\AdminCheckRoleMiddleware::class,
    \app\adminapi\middleware\AdminLogMiddleware::class,
])->option(['mark' => 'ai', 'mark_name' => '智能体']);

