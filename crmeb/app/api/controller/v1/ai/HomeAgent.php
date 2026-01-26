<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiChatServices;
use crmeb\services\CacheService;
use think\facade\Log;

class HomeAgent
{
    protected AiChatServices $aiChatServices;

    public function __construct(AiChatServices $aiChatServices)
    {
        $this->aiChatServices = $aiChatServices;
    }

    public function config()
    {
        $cfg = $this->aiChatServices->getHomeAgentConfig();
        return app('json')->success([
            'enabled' => (int)($cfg['enabled'] ?? 0),
            'status' => (int)($cfg['status'] ?? 0),
            'name' => (string)($cfg['name'] ?? '首页引流助手'),
        ]);
    }

    public function chat(Request $request)
    {
        $data = $request->postMore([
            ['message', ''],
            ['stream', 0],
        ]);

        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            return app('json')->fail('请输入内容');
        }

        $ip = (string)($request->ip() ?? '');
        if ($ip !== '') {
            $key = 'ai_home_chat_rate:' . md5($ip);
            $count = (int)CacheService::get($key, 0);
            if ($count >= 30) {
                return app('json')->fail('请求过于频繁，请稍后再试');
            }
            CacheService::set($key, $count + 1, 60);
        }

        $stream = (int)($data['stream'] ?? 0) === 1;
        Log::info('api.ai.home_chat', [
            'ip' => (string)($request->ip() ?? ''),
            'stream' => $stream ? 1 : 0,
            'messageLen' => mb_strlen($message),
        ]);
        if ($stream) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $generator = $this->aiChatServices->homeChatStream($message);
            foreach ($generator as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            exit;
        }

        $result = $this->aiChatServices->homeChat($message);
        if (!($result['ok'] ?? false)) {
            return app('json')->fail((string)($result['reply'] ?? '服务繁忙，请稍后再试'));
        }

        return app('json')->success([
            'reply' => (string)$result['reply'],
        ]);
    }
}
