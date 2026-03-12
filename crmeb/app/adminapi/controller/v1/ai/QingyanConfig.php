<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\services\ai\AiChatServices;
use app\services\ai\QingyanServices;
use app\services\system\config\SystemConfigServices;
use crmeb\services\CacheService;
use think\facade\App;

class QingyanConfig extends AuthController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function get(AiChatServices $aiChatServices)
    {
        $aiChatServices->getHomeAgentConfig();
        $apiKey = (string)sys_config('qingyan_api_key', '');
        $apiSecret = (string)sys_config('qingyan_api_secret', '');
        $hasSecret = trim($apiSecret) !== '';
        $maskedSecret = $hasSecret ? (mb_strlen($apiSecret) > 6 ? (mb_substr($apiSecret, 0, 2) . '****' . mb_substr($apiSecret, -2)) : '****') : '';
        return app('json')->success([
            'qingyan_api_key' => $apiKey,
            'qingyan_api_secret' => $maskedSecret,
            'has_secret' => $hasSecret ? 1 : 0,
        ]);
    }

    public function save(SystemConfigServices $configServices, AiChatServices $aiChatServices)
    {
        $aiChatServices->getHomeAgentConfig();
        [$apiKey, $apiSecret] = $this->request->postMore([
            ['qingyan_api_key', ''],
            ['qingyan_api_secret', ''],
        ], true);
        $apiKey = trim((string)$apiKey);
        $apiSecret = trim((string)$apiSecret);

        if ($apiKey !== '') {
            $configServices->update('qingyan_api_key', ['value' => json_encode($apiKey, JSON_UNESCAPED_UNICODE)], 'menu_name');
        }
        if ($apiSecret !== '') {
            $configServices->update('qingyan_api_secret', ['value' => json_encode($apiSecret, JSON_UNESCAPED_UNICODE)], 'menu_name');
            CacheService::delete('qingyan_access_token_v1');
        }
        CacheService::clear();
        return app('json')->success('保存成功');
    }

    public function verify(QingyanServices $qingyanServices)
    {
        [$assistantId] = $this->request->postMore([
            ['assistant_id', ''],
        ], true);
        $res = $qingyanServices->verifyAssistant((string)$assistantId, 'ping');
        if (!($res['ok'] ?? false)) {
            return app('json')->fail((string)($res['error'] ?? '验证失败'));
        }
        return app('json')->success('验证成功', [
            'conversation_id' => (string)($res['conversation_id'] ?? ''),
        ]);
    }
}
