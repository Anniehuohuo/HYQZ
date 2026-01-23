<?php

namespace app\services\ai;

use app\dao\system\config\SystemConfigDao;
use crmeb\services\CacheService;
use crmeb\services\HttpService;
use think\facade\Log;

class AiChatServices
{
    protected SystemConfigDao $systemConfigDao;

    public function __construct(SystemConfigDao $systemConfigDao)
    {
        $this->systemConfigDao = $systemConfigDao;
    }

    public function getHomeAgentConfig(): array
    {
        $this->ensureConfigItems();
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
            'baseUrl' => (string)sys_config('ai_bigmodel_base_url', 'https://open.bigmodel.cn/api/llm-application/open'),
            'appId' => (string)sys_config('ai_bigmodel_app_id', ''),
            'apiKey' => (string)sys_config('ai_bigmodel_api_key', ''),
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
        if (array_key_exists('apiKey', $data)) {
            $this->setConfig('ai_bigmodel_api_key', (string)$data['apiKey']);
        }

        CacheService::clear();
    }

    public function chat(string $message, string $conversationId = ''): array
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

        $appId = (string)sys_config('ai_bigmodel_app_id', '');
        $apiKey = (string)sys_config('ai_bigmodel_api_key', '');
        $baseUrl = (string)sys_config('ai_bigmodel_base_url', 'https://open.bigmodel.cn/api/llm-application/open');

        if ($appId === '' || $apiKey === '') {
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
            'ai_bigmodel_app_id' => ['type' => 'text', 'default' => '', 'info' => 'AI应用ID'],
            'ai_bigmodel_api_key' => ['type' => 'text', 'default' => '', 'info' => 'AI密钥'],
            'ai_home_agent_name' => ['type' => 'text', 'default' => '首页引流助手', 'info' => '首页助手名称'],
            'ai_home_agent_status' => ['type' => 'switch', 'default' => 1, 'info' => '首页助手状态'],
            'ai_home_agent_model' => ['type' => 'text', 'default' => '', 'info' => '模型标识'],
            'ai_home_agent_temperature' => ['type' => 'text', 'input_type' => 'number', 'default' => 0.7, 'info' => '温度'],
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
}

