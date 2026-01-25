<?php

namespace app\services\ai;

use app\dao\ai\AiAgentDao;
use app\dao\ai\AiCategoryDao;

class AiAgentMatrixServices
{
    protected AiCategoryDao $categoryDao;
    protected AiAgentDao $agentDao;

    public function __construct(AiCategoryDao $categoryDao, AiAgentDao $agentDao)
    {
        $this->categoryDao = $categoryDao;
        $this->agentDao = $agentDao;
    }

    public function getEnabledMatrix(): array
    {
        $categories = $this->categoryDao->selectList(['status' => 1], 'id,cate_key,cate_name,sort', 0, 0, 'sort DESC, id DESC')->toArray();
        $agents = $this->agentDao->selectList(['status' => 1], 'id,agent_name,avatar,description,category_id,tags,sort', 0, 0, 'sort DESC, id DESC')->toArray();

        $agentMap = [];
        foreach ($agents as $agent) {
            $catId = (int)$agent['category_id'];
            if (!isset($agentMap[$catId])) {
                $agentMap[$catId] = [];
            }
            $agent['tags'] = $this->parseTags((string)($agent['tags'] ?? ''));
            $agent['abbr'] = $this->abbr((string)$agent['agent_name']);
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
