<?php

namespace app\services\ai;

use crmeb\services\CacheService;
use crmeb\services\HttpService;
use think\facade\Log;

class QingyanServices
{
    protected string $defaultBaseUrl = 'https://chatglm.cn/chatglm/assistant-api/v1';

    protected function baseUrl(): string
    {
        $cfg = trim((string)sys_config('qingyan_base_url', ''));
        if ($cfg !== '' && preg_match('/^https?:\/\//i', $cfg)) {
            return rtrim($cfg, '/');
        }
        return rtrim($this->defaultBaseUrl, '/');
    }

    protected function extractText($content): string
    {
        if (is_string($content)) return $content;
        if (!is_array($content)) return '';
        if (array_key_exists('type', $content)) {
            $type = (string)($content['type'] ?? '');
            if ($type !== 'text' && $type !== 'markdown') return '';
            $t = $content['text'] ?? ($content['content'] ?? '');
            return is_string($t) ? $t : '';
        }

        $out = '';
        foreach ($content as $item) {
            if (!is_array($item)) continue;
            $type = (string)($item['type'] ?? '');
            if ($type !== 'text' && $type !== 'markdown') continue;
            $t = $item['text'] ?? ($item['content'] ?? '');
            if (is_string($t) && $t !== '') $out .= $t;
        }
        return $out;
    }

    protected function extractType($content): string
    {
        if (is_array($content) && isset($content['type']) && is_string($content['type'])) {
            return (string)$content['type'];
        }
        if (is_array($content) && count($content) === 1) {
            $item = $content[0] ?? null;
            if (is_array($item) && isset($item['type']) && is_string($item['type'])) {
                return (string)$item['type'];
            }
        }
        return '';
    }

    public function getToken(): array
    {
        $cacheKey = 'qingyan_access_token_v1';
        $cached = CacheService::get($cacheKey);
        if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp']) && (int)$cached['exp'] > time() + 600) {
            return ['ok' => true, 'token' => (string)$cached['token'], 'error' => ''];
        }

        $apiKey = trim((string)sys_config('qingyan_api_key', ''));
        $apiSecret = trim((string)sys_config('qingyan_api_secret', ''));
        if ($apiKey === '' || $apiSecret === '') {
            return ['ok' => false, 'token' => '', 'error' => '请先在后台配置清言 api_key/api_secret'];
        }

        $url = $this->baseUrl() . '/get_token';
        $payload = json_encode(['api_key' => $apiKey, 'api_secret' => $apiSecret], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $raw = HttpService::postRequest($url, $payload, $headers, 20);
        if ($raw === false) {
            $status = HttpService::getStatus();
            $code = is_array($status) ? (int)($status['http_code'] ?? 0) : 0;
            try {
                Log::channel('qingyan_debug')->error('qingyan.token.http_failed', ['httpCode' => $code]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'token' => '', 'error' => '获取token失败' . ($code ? ('(HTTP ' . $code . ')') : '')];
        }
        $resp = json_decode((string)$raw, true);
        if (!is_array($resp)) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.token.bad_json', ['rawPrefix' => substr((string)$raw, 0, 200)]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'token' => '', 'error' => '获取token响应解析失败'];
        }
        if ((int)($resp['status'] ?? 1) !== 0) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.token.bad_status', ['status' => (int)($resp['status'] ?? 1), 'message' => (string)($resp['message'] ?? '')]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'token' => '', 'error' => (string)($resp['message'] ?? '获取token失败')];
        }
        $token = (string)($resp['result']['access_token'] ?? '');
        $expiresIn = (int)($resp['result']['expires_in'] ?? 0);
        if ($token === '' || $expiresIn <= 0) {
            return ['ok' => false, 'token' => '', 'error' => '获取token返回数据不完整'];
        }
        $exp = time() + $expiresIn;
        CacheService::set($cacheKey, ['token' => $token, 'exp' => $exp], max(60, $expiresIn - 300));
        return ['ok' => true, 'token' => $token, 'error' => ''];
    }

    public function verifyAssistant(string $assistantId, string $prompt = 'ping'): array
    {
        $assistantId = trim($assistantId);
        if ($assistantId === '') {
            return ['ok' => false, 'conversation_id' => '', 'error' => 'assistant_id不能为空'];
        }
        $tk = $this->getToken();
        if (!($tk['ok'] ?? false)) {
            return ['ok' => false, 'conversation_id' => '', 'error' => (string)($tk['error'] ?? 'token不可用')];
        }

        $url = $this->baseUrl() . '/stream_sync';
        $payload = json_encode([
            'assistant_id' => $assistantId,
            'prompt' => $prompt,
        ], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string)$tk['token'],
            'Accept-Encoding: identity',
        ];
        try {
            Log::channel('qingyan_debug')->info('qingyan.verify.begin', [
                'assistantId' => $assistantId,
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
        }
        $raw = HttpService::postRequest($url, $payload, $headers, 30);
        if ($raw === false) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.verify.http_failed', [
                    'assistantId' => $assistantId,
                    'httpCode' => (int)(HttpService::getStatus()['http_code'] ?? 0),
                ]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'error' => '验证请求失败'];
        }
        $resp = json_decode((string)$raw, true);
        if (!is_array($resp)) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.verify.json_invalid', [
                    'assistantId' => $assistantId,
                    'raw' => (string)$raw,
                ]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'error' => '验证响应解析失败'];
        }
        if ((int)($resp['status'] ?? 1) !== 0) {
            try {
                Log::channel('qingyan_debug')->info('qingyan.verify.remote_reject', [
                    'assistantId' => $assistantId,
                    'status' => (int)($resp['status'] ?? 1),
                    'message' => (string)($resp['message'] ?? ''),
                ]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'error' => (string)($resp['message'] ?? '验证失败')];
        }
        $conversationId = (string)($resp['result']['conversation_id'] ?? '');
        try {
            Log::channel('qingyan_debug')->info('qingyan.verify.ok', [
                'assistantId' => $assistantId,
                'conversationId' => $conversationId,
            ]);
        } catch (\Throwable $e) {
        }
        return ['ok' => true, 'conversation_id' => $conversationId, 'error' => ''];
    }

    public function syncChat(string $assistantId, string $prompt, string $conversationId, string $token): array
    {
        $assistantId = trim($assistantId);
        if ($assistantId === '') {
            return ['ok' => false, 'conversation_id' => '', 'text' => '', 'error' => 'assistant_id不能为空'];
        }
        $url = $this->baseUrl() . '/stream_sync';
        $payloadArr = [
            'assistant_id' => $assistantId,
            'prompt' => $prompt,
        ];
        if (trim($conversationId) !== '') {
            $payloadArr['conversation_id'] = $conversationId;
        }
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Accept-Encoding: identity',
        ];
        $raw = HttpService::postRequest($url, $payload, $headers, 60);
        if ($raw === false) {
            $status = HttpService::getStatus();
            $code = is_array($status) ? (int)($status['http_code'] ?? 0) : 0;
            try {
                Log::channel('qingyan_debug')->error('qingyan.sync.http_failed', ['httpCode' => $code]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'text' => '', 'error' => '请求失败'];
        }
        $resp = json_decode((string)$raw, true);
        if (!is_array($resp)) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.sync.bad_json', ['rawPrefix' => substr((string)$raw, 0, 200)]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'text' => '', 'error' => '响应解析失败'];
        }
        if ((int)($resp['status'] ?? 1) !== 0) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.sync.bad_status', ['status' => (int)($resp['status'] ?? 1), 'message' => (string)($resp['message'] ?? '')]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => '', 'text' => '', 'error' => (string)($resp['message'] ?? '请求失败')];
        }
        $result = $resp['result'] ?? null;
        if (!is_array($result)) {
            return ['ok' => false, 'conversation_id' => '', 'text' => '', 'error' => '返回数据不完整'];
        }
        $cid = (string)($result['conversation_id'] ?? '');
        $msg = $result['message'] ?? $result;
        $text = '';
        if (is_array($msg)) {
            $text = $this->extractText($msg['content'] ?? null);
        }
        if ($text === '') {
            try {
                Log::channel('qingyan_debug')->warning('qingyan.sync.empty_text', [
                    'assistantId' => $assistantId,
                    'conversationId' => $cid,
                    'contentType' => is_array($msg) && isset($msg['content']) ? gettype($msg['content']) : 'none',
                ]);
            } catch (\Throwable $e) {
            }
            return ['ok' => false, 'conversation_id' => $cid, 'text' => '', 'error' => '清言未返回文本内容'];
        }
        return ['ok' => true, 'conversation_id' => $cid, 'text' => $text, 'error' => ''];
    }

    public function stream(string $assistantId, string $prompt, string $conversationId, string $token): \Generator
    {
        $url = $this->baseUrl() . '/stream';
        $payloadArr = [
            'assistant_id' => $assistantId,
            'prompt' => $prompt,
        ];
        if (trim($conversationId) !== '') {
            $payloadArr['conversation_id'] = $conversationId;
        }
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                    'Accept-Encoding: identity',
                    'Cache-Control: no-cache',
                    'Connection: keep-alive',
                    'Authorization: Bearer ' . $token,
                ]) . "\r\n",
                'content' => $payload,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $fp = fopen($url, 'r', false, $context);
        if (!$fp) {
            try {
                Log::channel('qingyan_debug')->error('qingyan.stream.connect_failed', []);
            } catch (\Throwable $e) {
            }
            yield ['type' => 'error', 'error' => '连接清言失败'];
            return;
        }

        $mbLen = function (string $s): int {
            return function_exists('mb_strlen') ? (int)mb_strlen($s, 'UTF-8') : strlen($s);
        };
        $mbSub = function (string $s, int $start): string {
            return function_exists('mb_substr') ? (string)mb_substr($s, $start, null, 'UTF-8') : substr($s, $start);
        };
        $mbStartsWith = function (string $text, string $prefix) use ($mbLen): bool {
            if ($prefix === '') return true;
            $plen = $mbLen($prefix);
            if ($plen <= 0) return true;
            $head = function_exists('mb_substr') ? (string)mb_substr($text, 0, $plen, 'UTF-8') : substr($text, 0, $plen);
            return $head === $prefix;
        };

        $currentHistoryId = '';
        $lastFullText = '';
        $eventDataLines = [];
        $plainJsonBuf = '';
        $plainJsonMax = 200000;

        $handleJson = function (string $jsonStr) use (&$currentHistoryId, &$lastFullText, $mbLen, $mbSub, $mbStartsWith): array {
            $data = json_decode($jsonStr, true);
            if (!is_array($data)) return [];
            if (isset($data['status']) && (int)$data['status'] !== 0) {
                $msg = '';
                if (isset($data['message']) && is_string($data['message'])) $msg = (string)$data['message'];
                if ($msg === '' && isset($data['msg']) && is_string($data['msg'])) $msg = (string)$data['msg'];
                return [['type' => 'error', 'error' => $msg !== '' ? $msg : '清言接口报错']];
            }

            $result = $data['message'] ?? $data['result'] ?? null;
            if (!is_array($result)) {
                $result = $data;
            }

            $out = [];
            $conv = (string)($result['conversation_id'] ?? '');
            if ($conv !== '') {
                $out[] = ['type' => 'conversation_id', 'conversation_id' => $conv];
            }

            $historyId = (string)($result['history_id'] ?? '');
            if ($historyId !== '' && $historyId !== $currentHistoryId) {
                $currentHistoryId = $historyId;
                $lastFullText = '';
            }

            $msgObj = $result['message'] ?? $result;
            if (!is_array($msgObj)) return $out;
            $contentObj = $msgObj['content'] ?? null;
            $ctype = $this->extractType($contentObj);
            if ($ctype !== '' && $ctype !== 'text' && $ctype !== 'markdown') {
                $out[] = ['type' => 'content', 'content_type' => $ctype, 'content' => $contentObj];
                return $out;
            }
            $text = $this->extractText($contentObj);
            if ($text === '') return $out;

            $delta = $text;
            if ($lastFullText !== '' && $mbStartsWith($text, $lastFullText)) {
                $delta = $mbSub($text, $mbLen($lastFullText));
                $lastFullText = $text;
            } else {
                $lastFullText .= $text;
                $cap = 20000;
                $l = $mbLen($lastFullText);
                if ($l > $cap) {
                    $lastFullText = $mbSub($lastFullText, $l - $cap);
                }
            }

            if ($delta !== '') {
                $out[] = ['type' => 'delta', 'delta' => $delta];
            }
            return $out;
        };

        while (!feof($fp)) {
            $raw = fgets($fp);
            if ($raw === false) continue;
            $line = rtrim((string)$raw, "\r\n");

            if ($line === '') {
                if ($eventDataLines) {
                    $jsonStr = trim(implode("\n", $eventDataLines));
                    $eventDataLines = [];
                    if ($jsonStr !== '' && $jsonStr !== '[DONE]') {
                        foreach ($handleJson($jsonStr) as $evt) {
                            yield $evt;
                            if (($evt['type'] ?? '') === 'error') {
                                fclose($fp);
                                return;
                            }
                        }
                    }
                }
                continue;
            }

            if (strpos($line, 'data:') === 0) {
                $dataStr = ltrim(substr($line, 5));
                if ($dataStr === '') continue;
                if ($dataStr === '[DONE]') continue;
                $eventDataLines[] = $dataStr;
                continue;
            }

            if ($eventDataLines) {
                continue;
            }

            $trimmed = ltrim($line);
            if ($plainJsonBuf !== '' || strpos($trimmed, '{') === 0) {
                $plainJsonBuf .= $line;
                if (strlen($plainJsonBuf) > $plainJsonMax) {
                    $plainJsonBuf = '';
                    continue;
                }
                $data = json_decode($plainJsonBuf, true);
                if (is_array($data)) {
                    foreach ($handleJson($plainJsonBuf) as $evt) {
                        yield $evt;
                        if (($evt['type'] ?? '') === 'error') {
                            fclose($fp);
                            return;
                        }
                    }
                    $plainJsonBuf = '';
                }
            }
        }
        fclose($fp);
    }
}
