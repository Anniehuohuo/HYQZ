<?php

namespace app\model\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

class AiChatMessage extends BaseModel
{
    use ModelTrait;

    protected $name = 'ai_chat_messages';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false; // created_at is handled by DB default or manually

    public function searchSessionIdAttr($query, $value)
    {
        if ($value) $query->where('session_id', $value);
    }
}
