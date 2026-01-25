<?php

namespace app\adminapi\validate\ai;

use think\Validate;

class AiAgentValidate extends Validate
{
    protected $rule = [
        'agent_name' => 'require|max:64',
        'avatar' => 'max:255',
        'description' => 'require|max:255',
        'category_id' => 'require|number',
        'bot_id' => 'require|max:128',
        'api_key' => 'require|max:255',
        'tags' => 'max:255',
        'sort' => 'number',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'agent_name.require' => 'agent_name必填',
        'agent_name.max' => 'agent_name长度不能超过64',
        'avatar.max' => 'avatar长度不能超过255',
        'description.require' => 'description必填',
        'description.max' => 'description长度不能超过255',
        'category_id.require' => 'category_id必填',
        'category_id.number' => 'category_id必须为数字',
        'bot_id.require' => 'bot_id必填',
        'bot_id.max' => 'bot_id长度不能超过128',
        'api_key.require' => 'api_key必填',
        'api_key.max' => 'api_key长度不能超过255',
        'tags.max' => 'tags长度不能超过255',
        'sort.number' => 'sort必须为数字',
        'status.in' => 'status只能为0或1',
    ];

    protected $scene = [
        'create' => ['agent_name', 'avatar', 'description', 'category_id', 'bot_id', 'api_key', 'tags', 'sort', 'status'],
        'update' => ['agent_name', 'avatar', 'description', 'category_id', 'bot_id', 'api_key', 'tags', 'sort', 'status'],
    ];
}

