<?php

namespace app\model\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\facade\Db;

class AiAgent extends BaseModel
{
    use ModelTrait;

    protected $name = 'ai_agents';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    public function searchKeywordAttr($query, $value)
    {
        if (is_array($value)) {
            $value = implode(',', array_filter($value, function ($item) {
                return $item !== '' && $item !== null;
            }));
        }
        $value = trim((string)$value);
        if ($value === '') {
            return;
        }
        $query->where(function ($q) use ($value) {
            $q->whereLike('agent_name', '%' . $value . '%')
                ->whereOrLike('description', '%' . $value . '%')
                ->whereOrLike('tags', '%' . $value . '%')
                ->whereOrLike('bot_id', '%' . $value . '%');
            if ($this->hasProviderAssistantIdColumn()) {
                $q->whereOrLike('provider_assistant_id', '%' . $value . '%');
            }
        });
    }

    protected function hasProviderAssistantIdColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return (bool)$cached;
        }
        try {
            $prefix = (string)config('database.connections.mysql.prefix', '');
            $table = $prefix . 'ai_agents';
            $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'provider_assistant_id'");
            $cached = is_array($rows) && count($rows) > 0;
        } catch (\Throwable $e) {
            $cached = false;
        }
        return (bool)$cached;
    }

    public function searchCategoryIdAttr($query, $value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        if ($value === '' || $value === null) {
            return;
        }
        $query->where('category_id', (int)$value);
    }

    public function searchStatusAttr($query, $value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        if ($value === '' || $value === null) {
            return;
        }
        $query->where('status', (int)$value);
    }
}
