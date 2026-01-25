<?php

namespace app\model\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

class AiChatSession extends BaseModel
{
    use ModelTrait;

    protected $name = 'ai_chat_sessions';

    protected $pk = 'id';

    public function searchUserIdAttr($query, $value)
    {
        if ($value) $query->where('user_id', $value);
    }

    public function searchAgentIdAttr($query, $value)
    {
        if ($value) $query->where('agent_id', $value);
    }
}
