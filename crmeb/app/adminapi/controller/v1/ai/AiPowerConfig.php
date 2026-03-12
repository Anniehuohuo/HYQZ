<?php

namespace app\adminapi\controller\v1\ai;

use app\adminapi\controller\AuthController;
use app\dao\system\config\SystemConfigDao;
use crmeb\services\CacheService;
use think\facade\App;

class AiPowerConfig extends AuthController
{
    protected SystemConfigDao $systemConfigDao;

    public function __construct(App $app, SystemConfigDao $systemConfigDao)
    {
        parent::__construct($app);
        $this->systemConfigDao = $systemConfigDao;
    }

    public function get()
    {
        $this->ensureConfigItems();
        $cfg = [
            'enabled' => (int)sys_config('ai_power_enabled', 1),
            'cost_per_chat' => (int)sys_config('ai_power_cost_per_chat', 1),
            'free_daily_limit' => (int)sys_config('ai_power_free_daily_limit', 3),
            'recharge_title' => (string)sys_config('ai_power_recharge_title', '算力充值'),
            'recharge_attention' => (string)sys_config('ai_power_recharge_attention', ''),
            'packages_raw' => (string)sys_config('ai_power_recharge_packages', ''),
        ];
        $packages = $this->parsePackages($cfg['packages_raw']);
        return app('json')->success([
            'enabled' => $cfg['enabled'],
            'cost_per_chat' => $cfg['cost_per_chat'],
            'free_daily_limit' => $cfg['free_daily_limit'],
            'recharge_title' => $cfg['recharge_title'],
            'recharge_attention' => $cfg['recharge_attention'],
            'packages' => $packages,
        ]);
    }

    public function save()
    {
        $this->ensureConfigItems();
        $data = $this->request->postMore([
            ['enabled', 1],
            ['cost_per_chat', 1],
            ['free_daily_limit', 3],
            [['recharge_title', 's'], '算力充值'],
            [['recharge_attention', 's'], ''],
            ['packages', []],
        ]);

        $enabled = (int)($data['enabled'] ?? 1) ? 1 : 0;
        $costPerChat = max(0, (int)($data['cost_per_chat'] ?? 1));
        $freeDailyLimit = max(0, (int)($data['free_daily_limit'] ?? 3));
        $title = trim((string)($data['recharge_title'] ?? '算力充值'));
        if ($title === '') $title = '算力充值';
        $attention = (string)($data['recharge_attention'] ?? '');

        $packages = $this->sanitizePackages($data['packages'] ?? []);
        if (!$packages) {
            return app('json')->fail('请至少配置一个充值套餐');
        }
        $packagesRaw = json_encode($packages, JSON_UNESCAPED_UNICODE);

        $this->setConfig('ai_power_enabled', $enabled);
        $this->setConfig('ai_power_cost_per_chat', $costPerChat);
        $this->setConfig('ai_power_free_daily_limit', $freeDailyLimit);
        $this->setConfig('ai_power_recharge_title', $title);
        $this->setConfig('ai_power_recharge_attention', $attention);
        $this->setConfig('ai_power_recharge_packages', $packagesRaw);

        return app('json')->success(100001);
    }

    protected function ensureConfigItems(): void
    {
        $defaults = [
            'ai_power_enabled' => ['type' => 'switch', 'default' => 1, 'info' => '算力开关'],
            'ai_power_cost_per_chat' => ['type' => 'text', 'input_type' => 'number', 'default' => 1, 'info' => '每次对话扣费算力'],
            'ai_power_free_daily_limit' => ['type' => 'text', 'input_type' => 'number', 'default' => 3, 'info' => '每日免费次数'],
            'ai_power_recharge_title' => ['type' => 'text', 'default' => '算力充值', 'info' => '充值标题'],
            'ai_power_recharge_attention' => ['type' => 'textarea', 'default' => '', 'info' => '充值说明'],
            'ai_power_recharge_packages' => ['type' => 'textarea', 'default' => '', 'info' => '充值套餐JSON'],
        ];
        foreach ($defaults as $key => $meta) {
            if ($this->systemConfigDao->be(['menu_name' => $key])) continue;
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
        CacheService::delete('system_config_' . $key);
    }

    protected function parsePackages(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [
                ['id' => 1, 'price' => '9.90', 'power' => 30],
                ['id' => 2, 'price' => '19.90', 'power' => 80],
                ['id' => 3, 'price' => '49.90', 'power' => 240],
            ];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        return $this->sanitizePackages($decoded);
    }

    protected function sanitizePackages($packages): array
    {
        if (!is_array($packages)) return [];
        $out = [];
        $ids = [];
        foreach ($packages as $it) {
            if (!is_array($it)) continue;
            $id = (int)($it['id'] ?? 0);
            $price = (string)($it['price'] ?? '0');
            $power = (int)($it['power'] ?? 0);
            $priceNum = (float)$price;
            if ($id <= 0 || $power <= 0 || $priceNum <= 0) continue;
            if (isset($ids[$id])) continue;
            $ids[$id] = 1;
            $out[] = [
                'id' => $id,
                'price' => number_format($priceNum, 2, '.', ''),
                'power' => $power,
            ];
        }
        usort($out, static function ($a, $b) {
            return (int)$a['id'] <=> (int)$b['id'];
        });
        return $out;
    }
}

