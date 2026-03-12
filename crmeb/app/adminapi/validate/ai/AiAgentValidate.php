<?php

namespace app\adminapi\validate\ai;

use think\Validate;

class AiAgentValidate extends Validate
{
    protected $rule = [
        'agent_name' => 'require|max:64',
        'avatar' => 'max:255',
        'description' => 'require|max:255',
        'welcome' => 'max:600',
        'suggestions' => 'max:1000',
        'context_mode' => 'in:platform,unified',
        'system_prompt' => 'max:4000',
        'temperature' => 'number',
        'category_id' => 'require|number',
        'provider' => 'in:local,qingyan,managed,coze',
        'bot_id' => 'max:128',
        'api_key' => 'max:255',
        'provider_assistant_id' => 'max:128',
        'managed_model' => 'max:64',
        'managed_knowledge' => 'max:20000',
        'tags' => 'max:255',
        'sort' => 'number',
        'status' => 'in:0,1',
        'unlock_price' => 'number',
        'gift_power' => 'number',
    ];

    protected $message = [
        'agent_name.require' => 'agent_name必填',
        'agent_name.max' => 'agent_name长度不能超过64',
        'avatar.max' => 'avatar长度不能超过255',
        'description.require' => 'description必填',
        'description.max' => 'description长度不能超过255',
        'welcome.max' => 'welcome长度不能超过600',
        'suggestions.max' => 'suggestions长度不能超过1000',
        'context_mode.in' => 'context_mode只能为platform或unified',
        'system_prompt.max' => 'system_prompt长度不能超过4000',
        'temperature.number' => 'temperature必须为数字',
        'category_id.require' => 'category_id必填',
        'category_id.number' => 'category_id必须为数字',
        'bot_id.max' => 'bot_id长度不能超过128',
        'api_key.max' => 'api_key长度不能超过255',
        'provider.in' => 'provider只能为local或qingyan或managed或coze',
        'provider_assistant_id.max' => 'assistant_id长度不能超过128',
        'managed_model.max' => 'managed_model长度不能超过64',
        'managed_knowledge.max' => 'managed_knowledge长度不能超过20000',
        'tags.max' => 'tags长度不能超过255',
        'sort.number' => 'sort必须为数字',
        'status.in' => 'status只能为0或1',
        'unlock_price.number' => 'unlock_price必须为数字',
        'gift_power.number' => 'gift_power必须为数字',
    ];

    protected $scene = [
        'create' => ['agent_name', 'avatar', 'description', 'welcome', 'suggestions', 'context_mode', 'category_id', 'provider', 'bot_id', 'api_key', 'provider_assistant_id', 'managed_model', 'managed_knowledge', 'tags', 'sort', 'status', 'unlock_price', 'gift_power'],
        'update' => ['agent_name', 'avatar', 'description', 'welcome', 'suggestions', 'context_mode', 'category_id', 'provider', 'bot_id', 'api_key', 'provider_assistant_id', 'managed_model', 'managed_knowledge', 'tags', 'sort', 'status', 'unlock_price', 'gift_power'],
    ];
}
