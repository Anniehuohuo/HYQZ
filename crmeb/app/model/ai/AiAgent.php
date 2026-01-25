<?php

namespace app\model\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

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
        });
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
