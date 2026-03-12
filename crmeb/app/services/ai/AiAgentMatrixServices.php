<?php

namespace app\services\ai;

use app\dao\ai\AiAgentDao;
use app\dao\ai\AiCategoryDao;
use think\facade\Db;

class AiAgentMatrixServices
{
    protected AiCategoryDao $categoryDao;
    protected AiAgentDao $agentDao;

    public function __construct(AiCategoryDao $categoryDao, AiAgentDao $agentDao)
    {
        $this->categoryDao = $categoryDao;
        $this->agentDao = $agentDao;
    }

    public function getEnabledMatrix(int $uid = 0): array
    {
        $categories = $this->categoryDao->selectList(['status' => 1], 'id,cate_key,cate_name,sort', 0, 0, 'sort DESC, id DESC')->toArray();
        $agents = $this->agentDao->selectList(['status' => 1], 'id,agent_name,avatar,description,category_id,tags,sort,provider_meta', 0, 0, 'sort DESC, id DESC')->toArray();

        $unlockedMap = [];
        $uid = (int)$uid;
        if ($uid > 0) {
            try {
                $unlockedIds = Db::name('ai_agent_unlock')->where('uid', $uid)->where('status', 1)->column('agent_id');
                if (is_array($unlockedIds) && $unlockedIds) {
                    foreach ($unlockedIds as $aid) {
                        $unlockedMap[(int)$aid] = 1;
                    }
                }
            } catch (\Throwable $e) {
                $unlockedMap = [];
            }
        }

        $agentMap = [];
        foreach ($agents as $agent) {
            $catId = (int)$agent['category_id'];
            if (!isset($agentMap[$catId])) {
                $agentMap[$catId] = [];
            }
            $agent['tags'] = $this->parseTags((string)($agent['tags'] ?? ''));
            $agent['abbr'] = $this->abbr((string)$agent['agent_name']);
            $agent['unlocked'] = isset($unlockedMap[(int)$agent['id']]) ? 1 : 0;
            $meta = $this->decodeProviderMeta($agent['provider_meta'] ?? '');
            $agent['welcome'] = $this->resolveWelcome($agent, $meta);
            $agent['suggestions'] = $this->resolveSuggestions($meta);
            $agentMap[$catId][] = $agent;
        }

        $result = [];
        foreach ($categories as $cat) {
            $catId = (int)$cat['id'];
            $cat['agents'] = $agentMap[$catId] ?? [];
            if (!empty($cat['agents'])) {
                $result[] = $cat;
            }
        }

        return $result;
    }

    protected function decodeProviderMeta($raw): array
    {
        if (is_array($raw)) return $raw;
        $s = trim((string)$raw);
        if ($s === '') return [];
        $v = json_decode($s, true);
        return is_array($v) ? $v : [];
    }

    protected function resolveWelcome(array $agent, array $meta): string
    {
        $w = trim((string)($meta['welcome'] ?? ''));
        if ($w !== '') return $w;
        $name = trim((string)($agent['agent_name'] ?? ''));
        $desc = trim((string)($agent['description'] ?? ''));
        if ($name !== '' && $desc !== '') {
            return '你好，我是' . $name . '，' . $desc;
        }
        if ($name !== '') return '你好，我是' . $name . '。';
        return '';
    }

    protected function resolveSuggestions(array $meta): array
    {
        $raw = $meta['suggestions'] ?? null;
        $list = [];
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw)) {
            $list = preg_split("/\\r?\\n/", $raw) ?: [];
        }
        $out = [];
        foreach ((array)$list as $x) {
            $s = trim((string)$x);
            if ($s === '') continue;
            $out[] = $s;
            if (count($out) >= 3) break;
        }
        if (!$out) {
            $out = ['给我一个具体场景', '我想练习一句表扬话术', '请给我的话术打分并改写'];
        }
        return $out;
    }

    protected function parseTags(string $tags): array
    {
        $tags = trim($tags);
        if ($tags === '') {
            return [];
        }
        $arr = preg_split('/[\\s,，、]+/u', $tags) ?: [];
        $arr = array_values(array_filter(array_map('trim', $arr), function ($v) {
            return $v !== '';
        }));
        return array_values(array_unique($arr));
    }

    protected function abbr(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return (string)mb_substr($name, 0, 1, 'UTF-8');
        }
        return substr($name, 0, 1);
    }
}
