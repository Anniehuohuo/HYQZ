<?php

namespace app\model\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

class AiCategory extends BaseModel
{
    use ModelTrait;

    protected $name = 'ai_categories';

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
            $q->whereLike('cate_key', '%' . $value . '%')
                ->whereOrLike('cate_name', '%' . $value . '%');
        });
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
