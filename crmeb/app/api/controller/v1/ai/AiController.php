<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiChatServices;
use crmeb\services\CacheService;

class AiController
{
    protected AiChatServices $aiChatServices;

    public function __construct(AiChatServices $aiChatServices)
    {
        $this->aiChatServices = $aiChatServices;
    }

    public function chat(Request $request)
    {
        $data = $request->postMore([
            ['message', ''],
            ['conversation_id', ''],
            ['agent_id', ''],
        ], true);

        $message = trim((string)$data['message']);
        if ($message === '') {
            return app('json')->fail('请输入内容');
        }

        $ip = (string)($request->ip() ?? '');
        if ($ip !== '') {
            $key = 'ai_chat_rate:' . md5($ip);
            $count = (int)CacheService::get($key, 0);
            if ($count >= 30) {
                return app('json')->fail('请求过于频繁，请稍后再试');
            }
            CacheService::set($key, $count + 1, 60);
        }

        $result = $this->aiChatServices->chat($message, (string)$data['conversation_id']);
        if (!$result['ok']) {
            return app('json')->fail($result['reply'] ?: '服务繁忙，请稍后再试');
        }

        return app('json')->success([
            'reply' => $result['reply'],
            'conversation_id' => $result['conversation_id'],
        ]);
    }
}

