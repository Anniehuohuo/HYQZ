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
        $uid = (int)$request->uid();
        if (!$uid) {
             return app('json')->fail('请先登录');
        }

        $data = $request->postMore([
            ['message', ''],
            ['conversation_id', ''], // Keep for backward compatibility if needed
            ['agent_id', 0], // Changed default to 0
            ['session_id', 0],
            ['stream', false],
        ], true);

        $message = trim((string)$data['message']);
        if ($message === '') {
            return app('json')->fail('请输入内容');
        }

        $agentId = (int)$data['agent_id'];
        
        // Use new ChatStream if agentId is provided (Matrix Chat)
        // If agentId is 0, we also return stream error to prevent frontend spinning
        $sessionId = (int)$data['session_id'];
        return response()->stream(function() use ($uid, $agentId, $message, $sessionId) {
            if ($agentId <= 0) {
                 yield "data: " . json_encode(['error' => '请先选择一个智能体'], JSON_UNESCAPED_UNICODE) . "\n\n";
                 yield "data: [DONE]\n\n";
                 return;
            }
            $generator = $this->aiChatServices->chatStream($uid, $agentId, $message, $sessionId ?: null);
            foreach ($generator as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no' // Nginx buffering off
        ]);

        /* Old logic fallback removed/commented out to enforce stream consistency
        $ip = (string)($request->ip() ?? '');
        if ($ip !== '') {
            $key = 'ai_chat_rate:' . md5($ip);
            $count = (int)CacheService::get($key, 0);
            if ($count >= 30) {
                return app('json')->fail('请求过于频繁，请稍后再试');
            }
            CacheService::set($key, $count + 1, 60);
        }

        $result = $this->aiChatServices->chat($message, (string)$data['conversation_id'], (string)$data['agent_id']);
        if (!$result['ok']) {
            return app('json')->fail($result['reply'] ?: '服务繁忙，请稍后再试');
        }

        return app('json')->success([
            'reply' => $result['reply'],
            'conversation_id' => $result['conversation_id'],
        ]);
        */
    }

    public function history(Request $request)
    {
        $uid = (int)$request->uid();
        if (!$uid) {
             return app('json')->fail('请先登录');
        }
        
        $data = $request->getMore([
            ['session_id', 0],
            ['page', 1],
            ['limit', 20],
        ]);

        $sessionId = (int)$data['session_id'];
        if (!$sessionId) {
            return app('json')->fail('参数错误');
        }

        $list = $this->aiChatServices->getChatHistory($uid, $sessionId, (int)$data['page'], (int)$data['limit']);
        return app('json')->success($list);
    }

    public function recentSession(Request $request)
    {
        $uid = (int)$request->uid();
        if (!$uid) {
             return app('json')->fail('请先登录');
        }
        $agentId = (int)$request->get('agent_id');
        if (!$agentId) {
             return app('json')->fail('参数错误');
        }
        
        $session = $this->aiChatServices->getRecentSession($uid, $agentId);
        return app('json')->success($session ?: []);
    }
}

