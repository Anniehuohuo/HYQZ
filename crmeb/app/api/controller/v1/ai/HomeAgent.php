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
            'build_tag' => 'ai-agent-sell-20260205',
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function chat(Request $request)
    {
        $data = $request->postMore([
            ['message', ''],
            ['session_id', ''],
            ['stream', 0],
            ['round', 1],
            ['recent_recommended_id', 0],
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

        $cfg = $this->aiChatServices->getHomeAgentConfig();
        $dailyLimit = max(0, (int)($cfg['freeDailyLimit'] ?? 0));
        $uid = (int)$request->uid();
        $dailyKey = $this->dailyKey($uid, $ip);
        if ($dailyLimit > 0 && $dailyKey !== '') {
            $used = (int)CacheService::get($dailyKey, 0);
            if ($used >= $dailyLimit) {
                return app('json')->fail('今日免费对话次数已用完，请明天再来');
            }
        }

        $stream = (int)($data['stream'] ?? 0) === 1;
        $round = max(1, (int)($data['round'] ?? 1));
        $recentRecommendedId = max(0, (int)($data['recent_recommended_id'] ?? 0));
        $sessionId = trim((string)($data['session_id'] ?? ''));
        Log::info('api.ai.home_chat', [
            'ip' => (string)($request->ip() ?? ''),
            'stream' => $stream ? 1 : 0,
            'route' => (string)$request->baseUrl(),
            'round' => $round,
            'recentRecommendedId' => $recentRecommendedId,
            'sessionId' => $sessionId !== '' ? 1 : 0,
            'messageLen' => mb_strlen($message),
        ]);
        if ($stream) {
            @set_time_limit(0);
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            echo "data: " . json_encode(['content' => ''], JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();

            $generator = $this->aiChatServices->homeChatStream($message, $round, $recentRecommendedId, $sessionId);
            $counted = false;
            foreach ($generator as $chunk) {
                if (!$counted && $dailyLimit > 0 && $dailyKey !== '') {
                    $lines = preg_split("/\r?\n/", (string)$chunk) ?: [];
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || strpos($line, 'data:') !== 0) continue;
                        $dataStr = trim(substr($line, 5));
                        if ($dataStr === '' || $dataStr === '[DONE]') continue;
                        $payload = json_decode($dataStr, true);
                        if (!is_array($payload)) continue;
                        $content = (string)($payload['content'] ?? '');
                        if ($content !== '') {
                            $this->incDaily($dailyKey);
                            $counted = true;
                            break;
                        }
                    }
                }
                echo $chunk;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            exit;
        }

        $result = $this->aiChatServices->homeChat($message, $round, $recentRecommendedId, $sessionId);
        if (!($result['ok'] ?? false)) {
            return app('json')->fail((string)($result['reply'] ?? '服务繁忙，请稍后再试'));
        }
        if ($dailyLimit > 0 && $dailyKey !== '' && trim((string)($result['reply'] ?? '')) !== '') {
            $this->incDaily($dailyKey);
        }

        return app('json')->success([
            'reply' => (string)$result['reply'],
        ]);
    }

    protected function dailyKey(int $uid, string $ip): string
    {
        $day = date('Ymd');
        if ($uid > 0) {
            return 'ai_home_agent_daily:' . $day . ':uid:' . $uid;
        }
        $ip = trim($ip);
        if ($ip !== '') {
            return 'ai_home_agent_daily:' . $day . ':ip:' . md5($ip);
        }
        return '';
    }

    protected function incDaily(string $key): void
    {
        $key = trim($key);
        if ($key === '') return;
        try {
            $used = (int)CacheService::get($key, 0);
            CacheService::set($key, $used + 1, 172800);
        } catch (\Throwable $e) {
        }
    }
}
