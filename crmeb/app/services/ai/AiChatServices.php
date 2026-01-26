<?php

namespace app\services\ai;

use app\dao\ai\AiAgentDao;
use app\dao\ai\AiCategoryDao;
use app\dao\ai\AiChatMessageDao;
use app\dao\ai\AiChatSessionDao;
use app\dao\system\config\SystemConfigDao;
use crmeb\services\CacheService;
use crmeb\services\HttpService;
use think\facade\Log;

class AiChatServices
{
    protected SystemConfigDao $systemConfigDao;
    protected AiAgentDao $aiAgentDao;
    protected AiCategoryDao $aiCategoryDao;
    protected AiChatSessionDao $aiChatSessionDao;
    protected AiChatMessageDao $aiChatMessageDao;

    public function __construct(
        SystemConfigDao $systemConfigDao,
        AiAgentDao $aiAgentDao,
        AiCategoryDao $aiCategoryDao,
        AiChatSessionDao $aiChatSessionDao,
        AiChatMessageDao $aiChatMessageDao
    ) {
        $this->systemConfigDao = $systemConfigDao;
        $this->aiAgentDao = $aiAgentDao;
        $this->aiCategoryDao = $aiCategoryDao;
        $this->aiChatSessionDao = $aiChatSessionDao;
        $this->aiChatMessageDao = $aiChatMessageDao;
    }


    public function getEnabledMatrix(): array
    {
        $categories = $this->aiCategoryDao->selectList(['status' => 1], 'id,cate_name,sort', 0, 0, 'sort DESC, id DESC')->toArray();
        $agents = $this->aiAgentDao->selectList(['status' => 1], 'id,agent_name,avatar,description,category_id,tags,sort', 0, 0, 'sort DESC, id DESC')->toArray();

        $agentMap = [];
        foreach ($agents as $agent) {
            $catId = (int)$agent['category_id'];
            if (!isset($agentMap[$catId])) {
                $agentMap[$catId] = [];
            }
            $agentMap[$catId][] = $agent;
        }

        $result = [];
        foreach ($categories as $cat) {
            $catId = (int)$cat['id'];
            if (!empty($agentMap[$catId])) {
                $cat['agents'] = $agentMap[$catId];
                $result[] = $cat;
            }
        }

        return $result;
    }

    public function chatStream(int $userId, int $agentId, string $message, ?int $sessionId = null): \Generator
    {
        // 1. 校验智能体
        $agent = $this->aiAgentDao->get($agentId);
        if (!$agent || (int)$agent['status'] !== 1) {
            yield "data: " . json_encode(['error' => '智能体不存在或未启用'], JSON_UNESCAPED_UNICODE) . "\n\n";
            yield "data: [DONE]\n\n";
            return;
        }

        // 2. 会话管理
        if ($sessionId) {
            $session = $this->aiChatSessionDao->get($sessionId);
            if (!$session || (int)$session['user_id'] !== $userId) {
                yield "data: " . json_encode(['error' => '会话不存在或无权访问'], JSON_UNESCAPED_UNICODE) . "\n\n";
                yield "data: [DONE]\n\n";
                return;
            }
        } else {
            $session = $this->aiChatSessionDao->save([
                'user_id' => $userId,
                'agent_id' => $agentId,
                'title' => mb_substr($message, 0, 20),
                'status' => 1,
            ]);
            $sessionId = (int)$session->id;
        }

        // 3. 存储用户消息
        $this->aiChatMessageDao->save([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $message,
        ]);

        // 4. 构建上下文
        $history = $this->aiChatMessageDao->search(['session_id' => $sessionId])
            ->order('id DESC')
            ->limit(10)
            ->select()
            ->toArray();
        $history = array_reverse($history);

        $messages = [];
        // 添加系统人设（如果有）
        // if (!empty($agent['description'])) {
        //     $messages[] = ['role' => 'system', 'content' => $agent['description']];
        // }

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // 5. Determine API endpoint and payload based on bot_id format
        $botId = $agent['bot_id'];
        $apiKey = $agent['api_key'];
        $isApp = ctype_digit((string)$botId); // Numeric ID implies Application/Agent API

        if ($isApp) {
            // User requested v2 endpoint from documentation
            $url = 'https://open.bigmodel.cn/api/llm-application/open/v2/application/' . $botId . '/conversation';
            
            // v2 API typically uses 'prompt' and 'conversation_id'
            // It might not support the full 'messages' history structure in the same way as v3/v4.
            // We'll send the latest message as 'prompt'.
            // If history is needed, v2 might handle it via conversation_id context on server side, 
            // or requires a specific 'history' field.
            
            $payload = [
                'prompt' => $message,
                'request_id' => md5(uniqid('', true)),
                'stream' => true,
            ];
            
            // Note: v2 requires conversation_id for context. 
            // Since we don't have a dedicated column for remote_conversation_id in eb_ai_chat_sessions,
            // and frontend sends local session_id. 
            // We might lose context on Zhipu side if we don't send conversation_id.
            // For now, we focus on fixing the stream response.
            // If the user wants context, they might need to add a column or use v3 with full history.
        } else {
            // Standard Chat Completion API
            $url = 'https://open.bigmodel.cn/api/paas/v4/chat/completions';
            $payload = [
                'model' => $botId,
                'messages' => $messages,
                'stream' => true,
            ];
        }

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $apiKey
                ],
                'content' => json_encode($payload),
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $fp = fopen($url, 'r', false, $context);

        if (!$fp) {
            yield "data: " . json_encode(['error' => '连接AI服务失败'], JSON_UNESCAPED_UNICODE) . "\n\n";
            yield "data: [DONE]\n\n";
            return;
        }

        $assistantContent = '';
        $firstChunk = true;

        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                // 检查是否为 API 错误返回 (JSON 格式且不包含 data: 前缀)
                if ($firstChunk && strpos(trim($line), '{') === 0 && strpos($line, 'data:') === false) {
                    $errData = json_decode($line, true);
                    if (isset($errData['error'])) {
                        $errMsg = is_array($errData['error']) ? ($errData['error']['message'] ?? json_encode($errData['error'])) : $errData['error'];
                        // Handle specific Application API error format if needed
                        if (isset($errData['msg'])) $errMsg = $errData['msg'];
                        
                        yield "data: " . json_encode(['error' => 'AI接口报错: ' . $errMsg], JSON_UNESCAPED_UNICODE) . "\n\n";
                        yield "data: [DONE]\n\n";
                        fclose($fp);
                        return;
                    }
                }
                $firstChunk = false;

                $line = trim($line);
                if (empty($line)) continue;
                if (strpos($line, 'data: ') === 0) {
                    $dataStr = substr($line, 6);
                    if ($dataStr === '[DONE]') break;
                    $data = json_decode($dataStr, true);
                    
                    // Handle different response formats
                    $content = '';
                    if ($isApp) {
                        // Application API v2 stream format
                        // Typical SSE event:
                        // event: add
                        // data: content
                        // event: finish
                        // data: ...
                        //
                        // However, standard SSE parser (fgets) reads line by line.
                        // We check for 'data: ' prefix.
                        // If it is v2, data might be just text, not JSON.
                        // Or it might be JSON.
                        
                        // Try to decode as JSON first
                        if (is_array($data)) {
                             if (isset($data['choices'][0]['delta']['content'])) {
                                 $content = $data['choices'][0]['delta']['content'];
                             } elseif (isset($data['content'])) {
                                  $content = $data['content'];
                             }
                        } else {
                             // If data is not array (json_decode returned string or null?)
                             // Actually json_decode("text") returns null usually if not quoted, or syntax error.
                             // But json_decode('"text"') returns "text".
                             // If raw data was just text without quotes, json_decode fails.
                             // So we use the raw dataStr.
                             $content = $dataStr;
                        }
                    } else {
                        // Standard Chat Completion
                        if (isset($data['choices'][0]['delta']['content'])) {
                            $content = $data['choices'][0]['delta']['content'];
                        }
                    }

                    if ($content !== '') {
                        $assistantContent .= $content;
                        // 输出 SSE 格式
                        yield "data: " . json_encode(['content' => $content, 'session_id' => $sessionId], JSON_UNESCAPED_UNICODE) . "\n\n";
                    }
                }
            }
        }
        fclose($fp);

        // 6. 存储 AI 回复
        if (!empty($assistantContent)) {
            $this->aiChatMessageDao->save([
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $assistantContent,
            ]);
            // 更新会话最后时间
            $this->aiChatSessionDao->update($sessionId, ['updated_at' => date('Y-m-d H:i:s')]);
        }

        yield "data: [DONE]\n\n";
    }

    public function getChatHistory(int $userId, int $sessionId, int $page, int $limit): array
    {
        $session = $this->aiChatSessionDao->get($sessionId);
        if (!$session || (int)$session['user_id'] !== $userId) {
            return [];
        }

        $list = $this->aiChatMessageDao->search(['session_id' => $sessionId])
            ->order('id DESC')
            ->page($page, $limit)
            ->select()
            ->toArray();
        
        return array_reverse($list);
    }

    public function getRecentSession(int $userId, int $agentId)
    {
        return $this->aiChatSessionDao->search(['user_id' => $userId, 'agent_id' => $agentId, 'status' => 1])
            ->order('updated_at DESC, id DESC')
            ->find();
    }

    public function getHomeAgentConfig(): array
    {
        $this->ensureConfigItems();
        $apiKey = (string)sys_config('ai_bigmodel_api_key', '');
        return [
            'name' => (string)sys_config('ai_home_agent_name', '首页引流助手'),
            'status' => (int)sys_config('ai_home_agent_status', 1),
            'model' => (string)sys_config('ai_home_agent_model', ''),
            'temperature' => (float)sys_config('ai_home_agent_temperature', 0.7),
            'systemRules' => (string)sys_config('ai_home_agent_system_rules', ''),
            'persona' => (string)sys_config('ai_home_agent_persona', ''),
            'outputFormat' => (string)sys_config('ai_home_agent_output_format', ''),
            'growthPolicy' => (string)sys_config('ai_home_agent_growth_policy', ''),
            'fallbackText' => (string)sys_config('ai_home_agent_fallback_text', ''),
            'enabled' => (int)sys_config('ai_enabled', 0),
            'chatUrl' => (string)sys_config('ai_bigmodel_chat_url', 'https://open.bigmodel.cn/api/paas/v4/chat/completions'),
            'hasApiKey' => $apiKey !== '' ? 1 : 0,
            'apiKey' => '',
        ];
    }

    public function saveHomeAgentConfig(array $data): void
    {
        $this->ensureConfigItems();

        $this->setConfig('ai_home_agent_name', (string)($data['name'] ?? '首页引流助手'));
        $this->setConfig('ai_home_agent_status', (int)($data['status'] ?? 1));
        $this->setConfig('ai_home_agent_model', (string)($data['model'] ?? ''));
        $this->setConfig('ai_home_agent_temperature', (float)($data['temperature'] ?? 0.7));
        $this->setConfig('ai_home_agent_system_rules', (string)($data['systemRules'] ?? ''));
        $this->setConfig('ai_home_agent_persona', (string)($data['persona'] ?? ''));
        $this->setConfig('ai_home_agent_output_format', (string)($data['outputFormat'] ?? ''));
        $this->setConfig('ai_home_agent_growth_policy', (string)($data['growthPolicy'] ?? ''));
        $this->setConfig('ai_home_agent_fallback_text', (string)($data['fallbackText'] ?? ''));

        if (array_key_exists('enabled', $data)) {
            $this->setConfig('ai_enabled', (int)$data['enabled']);
        }
        if (array_key_exists('baseUrl', $data)) {
            $this->setConfig('ai_bigmodel_base_url', (string)$data['baseUrl']);
        }
        if (array_key_exists('appId', $data)) {
            $this->setConfig('ai_bigmodel_app_id', (string)$data['appId']);
        }
        if (array_key_exists('chatUrl', $data)) {
            $this->setConfig('ai_bigmodel_chat_url', (string)$data['chatUrl']);
        }
        if (array_key_exists('apiKey', $data)) {
            $apiKey = trim((string)$data['apiKey']);
            if ($apiKey !== '') {
                $this->setConfig('ai_bigmodel_api_key', $apiKey);
            }
        }

        CacheService::clear();
    }

    public function homeChat(string $message): array
    {
        $this->ensureConfigItems();
        $reqId = md5(uniqid('homechat_', true));

        $enabled = (int)sys_config('ai_enabled', 0);
        if (!$enabled) {
            Log::info('ai.home_chat.disabled', ['reqId' => $reqId]);
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI暂未启用，请稍后再试。'),
                'error' => 'AI未启用',
            ];
        }

        $chatUrl = trim((string)sys_config('ai_bigmodel_chat_url', ''));
        $apiKey = trim((string)sys_config('ai_bigmodel_api_key', ''));
        $model = trim((string)sys_config('ai_home_agent_model', ''));
        $temperature = (float)sys_config('ai_home_agent_temperature', 0.7);

        if ($chatUrl === '') {
            Log::warning('ai.home_chat.missing_chat_url', ['reqId' => $reqId]);
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI配置未完成，请先在后台填写接口地址。'),
                'error' => 'AI接口地址缺失',
            ];
        }
        if ($apiKey === '') {
            Log::warning('ai.home_chat.missing_api_key', ['reqId' => $reqId]);
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI配置未完成，请先在后台填写 API Key。'),
                'error' => 'AI密钥缺失',
            ];
        }
        if ($model === '') {
            Log::warning('ai.home_chat.missing_model', ['reqId' => $reqId]);
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI配置未完成，请先在后台填写模型标识。'),
                'error' => 'AI模型缺失',
            ];
        }

        $systemParts = [
            (string)sys_config('ai_home_agent_system_rules', ''),
            (string)sys_config('ai_home_agent_persona', ''),
            (string)sys_config('ai_home_agent_output_format', ''),
            (string)sys_config('ai_home_agent_growth_policy', ''),
        ];
        $systemParts = array_values(array_filter(array_map('trim', $systemParts), static function ($v) {
            return $v !== '';
        }));
        $systemPrompt = implode("\n\n", $systemParts);

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'temperature' => $temperature,
        ];

        Log::info('ai.home_chat.request', [
            'reqId' => $reqId,
            'chatUrl' => $chatUrl,
            'model' => $model,
            'temperature' => $temperature,
            'messageLen' => mb_strlen($message),
        ]);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ];

        $raw = HttpService::postRequest($chatUrl, $body, $headers, 25);
        if ($raw === false) {
            $status = HttpService::getStatus();
            $code = is_array($status) ? ($status['http_code'] ?? 0) : 0;
            Log::error('ai.home_chat.http_failed', ['reqId' => $reqId, 'httpCode' => $code]);
            return ['ok' => false, 'reply' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。'), 'error' => 'HTTP请求失败:' . (string)$code];
        }

        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            Log::error('ai.home_chat.bad_json', ['reqId' => $reqId, 'rawPrefix' => mb_substr((string)$raw, 0, 200)]);
            return ['ok' => false, 'reply' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。'), 'error' => '响应解析失败'];
        }

        $reply = '';
        if (isset($resp['choices'][0]['message']['content']) && is_string($resp['choices'][0]['message']['content'])) {
            $reply = (string)$resp['choices'][0]['message']['content'];
        } elseif (isset($resp['choices'][0]['messages']['content']['msg']) && is_string($resp['choices'][0]['messages']['content']['msg'])) {
            $reply = (string)$resp['choices'][0]['messages']['content']['msg'];
        } elseif (isset($resp['choices'][0]['text']) && is_string($resp['choices'][0]['text'])) {
            $reply = (string)$resp['choices'][0]['text'];
        }

        $reply = trim($reply);
        if ($reply === '') {
            Log::warning('ai.home_chat.empty_reply', ['reqId' => $reqId, 'respKeys' => array_keys($resp)]);
            return ['ok' => false, 'reply' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。'), 'error' => '模型未返回内容'];
        }

        Log::info('ai.home_chat.ok', ['reqId' => $reqId, 'replyLen' => mb_strlen($reply)]);
        return ['ok' => true, 'reply' => $reply];
    }

    public function homeChatStream(string $message): \Generator
    {
        $this->ensureConfigItems();
        $reqId = md5(uniqid('homechat_stream_', true));

        $enabled = (int)sys_config('ai_enabled', 0);
        if (!$enabled) {
            Log::info('ai.home_chat_stream.disabled', ['reqId' => $reqId]);
            yield "data: " . json_encode(['error' => (string)sys_config('ai_home_agent_fallback_text', 'AI暂未启用，请稍后再试。')], JSON_UNESCAPED_UNICODE) . "\n\n";
            yield "data: [DONE]\n\n";
            return;
        }

        $chatUrl = trim((string)sys_config('ai_bigmodel_chat_url', ''));
        $apiKey = trim((string)sys_config('ai_bigmodel_api_key', ''));
        $model = trim((string)sys_config('ai_home_agent_model', ''));
        $temperature = (float)sys_config('ai_home_agent_temperature', 0.7);
        $thinkingEnabled = (int)sys_config('ai_home_agent_thinking_enabled', 0) === 1;

        if ($chatUrl === '' || $apiKey === '' || $model === '') {
            Log::warning('ai.home_chat_stream.missing_config', [
                'reqId' => $reqId,
                'hasChatUrl' => $chatUrl !== '' ? 1 : 0,
                'hasApiKey' => $apiKey !== '' ? 1 : 0,
                'hasModel' => $model !== '' ? 1 : 0,
            ]);
            yield "data: " . json_encode(['error' => (string)sys_config('ai_home_agent_fallback_text', 'AI配置未完成，请先在后台填写接口地址、模型与 API Key。')], JSON_UNESCAPED_UNICODE) . "\n\n";
            yield "data: [DONE]\n\n";
            return;
        }

        $systemParts = [
            (string)sys_config('ai_home_agent_system_rules', ''),
            (string)sys_config('ai_home_agent_persona', ''),
            (string)sys_config('ai_home_agent_output_format', ''),
            (string)sys_config('ai_home_agent_growth_policy', ''),
        ];
        $systemParts = array_values(array_filter(array_map('trim', $systemParts), static function ($v) {
            return $v !== '';
        }));
        $systemPrompt = implode("\n\n", $systemParts);

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'temperature' => $temperature,
        ];
        if ($thinkingEnabled) {
            $payload['thinking'] = ['type' => 'enabled'];
        }

        Log::info('ai.home_chat_stream.request', [
            'reqId' => $reqId,
            'chatUrl' => $chatUrl,
            'model' => $model,
            'temperature' => $temperature,
            'thinking' => $thinkingEnabled ? 1 : 0,
            'messageLen' => mb_strlen($message),
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($opts);
        $fp = fopen($chatUrl, 'r', false, $context);
        if (!$fp) {
            Log::error('ai.home_chat_stream.connect_failed', ['reqId' => $reqId]);
            yield "data: " . json_encode(['error' => '连接AI服务失败'], JSON_UNESCAPED_UNICODE) . "\n\n";
            yield "data: [DONE]\n\n";
            return;
        }

        $assistantContent = '';
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line === false) {
                usleep(50_000);
                continue;
            }
            $line = trim($line);
            if ($line === '' || strpos($line, 'data:') !== 0) {
                continue;
            }
            $dataStr = trim(substr($line, 5));
            if ($dataStr === '[DONE]') {
                break;
            }

            $data = json_decode($dataStr, true);
            if (!is_array($data)) {
                Log::warning('ai.home_chat_stream.bad_json_line', ['reqId' => $reqId, 'linePrefix' => mb_substr($dataStr, 0, 200)]);
                continue;
            }
            if (!empty($data['error'])) {
                Log::error('ai.home_chat_stream.remote_error', ['reqId' => $reqId, 'error' => (string)$data['error']]);
                yield "data: " . json_encode(['error' => (string)$data['error']], JSON_UNESCAPED_UNICODE) . "\n\n";
                break;
            }

            $content = '';
            if (isset($data['choices'][0]['delta']['content']) && is_string($data['choices'][0]['delta']['content'])) {
                $content = (string)$data['choices'][0]['delta']['content'];
            } elseif (isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
                $content = (string)$data['choices'][0]['message']['content'];
            }

            if ($content !== '') {
                $assistantContent .= $content;
                yield "data: " . json_encode(['content' => $content], JSON_UNESCAPED_UNICODE) . "\n\n";
            }
        }
        fclose($fp);

        if ($assistantContent === '') {
            Log::warning('ai.home_chat_stream.empty_reply', ['reqId' => $reqId]);
            yield "data: " . json_encode(['error' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。')], JSON_UNESCAPED_UNICODE) . "\n\n";
        } else {
            Log::info('ai.home_chat_stream.ok', ['reqId' => $reqId, 'replyLen' => mb_strlen($assistantContent)]);
        }
        yield "data: [DONE]\n\n";
    }

    public function chat(string $message, string $conversationId = '', string $agentId = ''): array
    {
        $this->ensureConfigItems();

        $enabled = (int)sys_config('ai_enabled', 0);
        if (!$enabled) {
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI暂未启用，请稍后再试。'),
                'conversation_id' => $conversationId,
                'error' => 'AI未启用',
            ];
        }

        $baseUrl = (string)sys_config('ai_bigmodel_base_url', 'https://open.bigmodel.cn/api/llm-application/open');
        $appConfig = $this->resolveApplicationConfig($agentId);
        $appId = (string)($appConfig['appId'] ?? '');
        $apiKey = (string)($appConfig['apiKey'] ?? '');

        if ($appId === '' || $apiKey === '') {
            if (($appConfig['mode'] ?? '') === 'matrix') {
                return [
                    'ok' => false,
                    'reply' => (string)sys_config('ai_home_agent_fallback_text', '该智能体暂不可用，请稍后再试。'),
                    'conversation_id' => $conversationId,
                    'error' => (string)($appConfig['error'] ?? '智能体不可用'),
                ];
            }
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', 'AI配置未完成，请先在后台填写 appId 和 apiKey。'),
                'conversation_id' => $conversationId,
                'error' => 'AI配置缺失',
            ];
        }

        try {
            $result = $this->invokeBigModelApplication($baseUrl, $apiKey, [
                'app_id' => $appId,
                'conversation_id' => $conversationId ?: null,
                'message' => $message,
            ]);

            if (!$result['ok']) {
                return [
                    'ok' => false,
                    'reply' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。'),
                    'conversation_id' => $conversationId,
                    'error' => $result['error'] ?? '调用失败',
                ];
            }

            return [
                'ok' => true,
                'reply' => (string)$result['reply'],
                'conversation_id' => (string)($result['conversation_id'] ?? $conversationId),
            ];
        } catch (\Throwable $e) {
            Log::error('AI调用异常:' . $e->getMessage());
            return [
                'ok' => false,
                'reply' => (string)sys_config('ai_home_agent_fallback_text', '服务繁忙，请稍后再试。'),
                'conversation_id' => $conversationId,
                'error' => '异常',
            ];
        }
    }

    protected function invokeBigModelApplication(string $baseUrl, string $apiKey, array $params): array
    {
        $url = rtrim($baseUrl, '/') . '/v3/application/invoke';
        $payload = [
            'app_id' => $params['app_id'],
            'stream' => false,
            'send_log_event' => false,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input',
                            'value' => (string)$params['message'],
                        ],
                    ],
                ],
            ],
        ];
        if (!empty($params['conversation_id'])) {
            $payload['conversation_id'] = (string)$params['conversation_id'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ];

        $raw = HttpService::postRequest($url, $body, $headers, 25);
        if ($raw === false) {
            $status = HttpService::getStatus();
            $code = is_array($status) ? ($status['http_code'] ?? 0) : 0;
            return ['ok' => false, 'error' => 'HTTP请求失败:' . (string)$code];
        }

        $resp = json_decode($raw, true);
        if (!is_array($resp)) {
            return ['ok' => false, 'error' => '响应解析失败'];
        }

        $reply = $resp['choices'][0]['messages']['content']['msg'] ?? '';
        $conversationId = $resp['conversation_id'] ?? '';
        if ($reply === '') {
            return ['ok' => false, 'error' => '模型未返回内容'];
        }

        return [
            'ok' => true,
            'reply' => $reply,
            'conversation_id' => $conversationId,
        ];
    }

    protected function ensureConfigItems(): void
    {
        $items = [
            'ai_enabled' => ['type' => 'switch', 'default' => 0, 'info' => 'AI开关'],
            'ai_bigmodel_base_url' => ['type' => 'text', 'default' => 'https://open.bigmodel.cn/api/llm-application/open', 'info' => 'AI服务地址'],
            'ai_bigmodel_chat_url' => ['type' => 'text', 'default' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions', 'info' => 'AI聊天接口地址'],
            'ai_bigmodel_app_id' => ['type' => 'text', 'default' => '', 'info' => 'AI应用ID'],
            'ai_bigmodel_api_key' => ['type' => 'text', 'default' => '', 'info' => 'AI密钥'],
            'ai_home_agent_name' => ['type' => 'text', 'default' => '首页引流助手', 'info' => '首页助手名称'],
            'ai_home_agent_status' => ['type' => 'switch', 'default' => 1, 'info' => '首页助手状态'],
            'ai_home_agent_model' => ['type' => 'text', 'default' => '', 'info' => '模型标识'],
            'ai_home_agent_temperature' => ['type' => 'text', 'input_type' => 'number', 'default' => 0.7, 'info' => '温度'],
            'ai_home_agent_thinking_enabled' => ['type' => 'switch', 'default' => 0, 'info' => '思考开关'],
            'ai_home_agent_system_rules' => ['type' => 'textarea', 'default' => '', 'info' => '系统规则'],
            'ai_home_agent_persona' => ['type' => 'textarea', 'default' => '', 'info' => '人设与语气'],
            'ai_home_agent_output_format' => ['type' => 'textarea', 'default' => '', 'info' => '输出结构'],
            'ai_home_agent_growth_policy' => ['type' => 'textarea', 'default' => '', 'info' => '引流策略'],
            'ai_home_agent_fallback_text' => ['type' => 'textarea', 'default' => '', 'info' => '降级话术'],
        ];

        foreach ($items as $key => $meta) {
            if ($this->systemConfigDao->be(['menu_name' => $key])) {
                continue;
            }
            $this->systemConfigDao->save([
                'menu_name' => $key,
                'type' => $meta['type'] ?? 'text',
                'input_type' => $meta['input_type'] ?? 'input',
                'config_tab_id' => 0,
                'parameter' => '',
                'upload_type' => 1,
                'required' => '',
                'width' => 0,
                'high' => ($meta['type'] ?? '') === 'textarea' ? 5 : 0,
                'value' => json_encode($meta['default'], JSON_UNESCAPED_UNICODE),
                'info' => $meta['info'] ?? '',
                'desc' => '',
                'sort' => 0,
                'status' => 0,
                'level' => 0,
                'link_id' => 0,
                'link_value' => 0,
            ]);
        }
    }

    protected function setConfig(string $key, $value): void
    {
        $this->systemConfigDao->update($key, ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)], 'menu_name');
    }

    protected function resolveApplicationConfig(string $agentId): array
    {
        $agentId = trim($agentId);
        if ($agentId !== '' && ctype_digit($agentId) && (int)$agentId > 0) {
            $agent = $this->aiAgentDao->get((int)$agentId);
            if ($agent && (int)($agent['status'] ?? 0) === 1) {
                $categoryId = (int)($agent['category_id'] ?? 0);
                $category = $this->aiCategoryDao->get($categoryId);
                if ($category && (int)($category['status'] ?? 0) === 1) {
                    return [
                        'mode' => 'matrix',
                        'appId' => trim((string)($agent['bot_id'] ?? '')),
                        'apiKey' => trim((string)($agent['api_key'] ?? '')),
                        'error' => '',
                    ];
                }
            }
            return [
                'mode' => 'matrix',
                'appId' => '',
                'apiKey' => '',
                'error' => '智能体未启用或不存在',
            ];
        }

        return [
            'mode' => 'default',
            'appId' => (string)sys_config('ai_bigmodel_app_id', ''),
            'apiKey' => (string)sys_config('ai_bigmodel_api_key', ''),
            'error' => '',
        ];
    }
}

