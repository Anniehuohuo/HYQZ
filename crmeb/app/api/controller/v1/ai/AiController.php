<?php

namespace app\api\controller\v1\ai;

use app\Request;
use app\services\ai\AiAgentGoodsServices;
use app\services\ai\AiChatServices;
use app\services\ai\AiPowerServices;
use crmeb\services\CacheService;
use think\facade\Log;

class AiController
{
    protected AiChatServices $aiChatServices;
    protected AiPowerServices $aiPowerServices;
    protected AiAgentGoodsServices $aiAgentGoodsServices;

    public function __construct(AiChatServices $aiChatServices, AiPowerServices $aiPowerServices, AiAgentGoodsServices $aiAgentGoodsServices)
    {
        $this->aiChatServices = $aiChatServices;
        $this->aiPowerServices = $aiPowerServices;
        $this->aiAgentGoodsServices = $aiAgentGoodsServices;
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
            ['agent_id', ''], // Allow non-numeric to fallback home agent
            ['session_id', 0],
            ['stream', true],
            ['format', 'text'],
        ]);

        $message = trim((string)$data['message']);
        if ($message === '') {
            return app('json')->fail('请输入内容');
        }

        $agentIdRaw = trim((string)($data['agent_id'] ?? ''));
        $sessionId = (int)($data['session_id'] ?? 0);
        $stream = (int)($data['stream'] ?? 1) === 1;
        $format = trim((string)($data['format'] ?? 'text'));
        if ($format === '') $format = 'text';

        $isNumericAgent = $agentIdRaw !== '' && ctype_digit($agentIdRaw) && (int)$agentIdRaw > 0;
        $agentId = $isNumericAgent ? (int)$agentIdRaw : 0;

        $powerToken = '';
        $powerCheck = null;
        if ($isNumericAgent) {
            $access = $this->aiAgentGoodsServices->ensureUnlocked($uid, $agentId);
            if (!($access['unlocked'] ?? false)) {
                return app('json')->fail('未解锁，请先购买', [
                    'need_purchase' => 1,
                    'product_id' => (int)($access['product_id'] ?? 0),
                    'agent_id' => $agentId,
                ]);
            }
            $powerCheck = $this->aiPowerServices->prepareChat($uid, $agentId, (string)$sessionId);
            if (!($powerCheck['allowed'] ?? false)) {
                return app('json')->fail('今日免费次数已用完，请充值算力', [
                    'need_recharge' => 1,
                    'quota' => $powerCheck['quota'] ?? [],
                ]);
            }
            $powerToken = (string)($powerCheck['token'] ?? '');
        }

        Log::info('api.ai.chat.request', [
            'uid' => $uid,
            'ip' => (string)($request->ip() ?? ''),
            'route' => (string)$request->baseUrl(),
            'stream' => $stream ? 1 : 0,
            'agentIdRaw' => $agentIdRaw,
            'agentId' => $agentId,
            'sessionId' => $sessionId,
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

            if ($isNumericAgent) {
                $generator = $this->aiChatServices->chatStream($uid, $agentId, $message, $sessionId ?: null, $format);
            } else {
                $generator = $this->aiChatServices->homeChatStream($message);
            }

            $committed = false;
            foreach ($generator as $chunk) {
                if ($isNumericAgent && !$committed && $powerToken !== '') {
                    $lines = preg_split("/\r?\n/", (string)$chunk) ?: [];
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || strpos($line, 'data:') !== 0) continue;
                        $dataStr = trim(substr($line, 5));
                        if ($dataStr === '' || $dataStr === '[DONE]') continue;
                        $payload = json_decode($dataStr, true);
                        if (!is_array($payload)) continue;
                        if (!empty($payload['content'])) {
                            $ok = $this->aiPowerServices->commitChat($powerToken);
                            if (!$ok) {
                                echo "data: " . json_encode(['error' => '算力不足，请充值后继续'], JSON_UNESCAPED_UNICODE) . "\n\n";
                                echo "data: [DONE]\n\n";
                                if (ob_get_level() > 0) {
                                    @ob_flush();
                                }
                                flush();
                                exit;
                            }
                            $committed = true;
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

        if ($isNumericAgent) {
            $reply = '';
            $newSessionId = $sessionId;
            $generator = $this->aiChatServices->chatStream($uid, $agentId, $message, $sessionId ?: null);
            $committed = false;
            foreach ($generator as $chunk) {
                $lines = preg_split("/\r?\n/", (string)$chunk) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, 'data:') !== 0) {
                        continue;
                    }
                    $dataStr = trim(substr($line, 5));
                    if ($dataStr === '[DONE]') {
                        continue;
                    }
                    $payload = json_decode($dataStr, true);
                    if (!is_array($payload)) {
                        continue;
                    }
                    if (!empty($payload['error'])) {
                        return app('json')->fail((string)$payload['error']);
                    }
                    if (!empty($payload['session_id'])) {
                        $newSessionId = (int)$payload['session_id'];
                    }
                    if (!empty($payload['content'])) {
                        if (!$committed && $powerToken !== '') {
                            $ok = $this->aiPowerServices->commitChat($powerToken);
                            if (!$ok) {
                                return app('json')->fail('算力不足，请充值后继续', [
                                    'need_recharge' => 1,
                                    'quota' => $powerCheck['quota'] ?? [],
                                ]);
                            }
                            $committed = true;
                        }
                        $reply .= (string)$payload['content'];
                    }
                }
            }
            $reply = trim($reply);
            if ($reply === '') {
                return app('json')->fail('服务繁忙，请稍后再试');
            }
            return app('json')->success(['reply' => $reply, 'session_id' => $newSessionId]);
        }

        $result = $this->aiChatServices->homeChat($message);
        if (!($result['ok'] ?? false)) {
            return app('json')->fail((string)($result['reply'] ?? '服务繁忙，请稍后再试'));
        }
        return app('json')->success(['reply' => (string)$result['reply']]);

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

    public function clearHistory(Request $request)
    {
        $uid = (int)$request->uid();
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        [$sessionId, $agentId] = $request->postMore([
            ['session_id', 0],
            ['agent_id', 0],
        ], true);
        $sessionId = (int)$sessionId;
        $agentId = (int)$agentId;
        if ($sessionId <= 0 && $agentId <= 0) return app('json')->fail('参数错误');
        $ok = $this->aiChatServices->clearChatHistory($uid, $sessionId, $agentId);
        if (!$ok) return app('json')->fail('清空失败');
        return app('json')->success(['ok' => 1]);
    }
}
