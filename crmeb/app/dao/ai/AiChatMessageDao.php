<?php

namespace app\dao\ai;

use app\dao\BaseDao;
use app\model\ai\AiChatMessage;

class AiChatMessageDao extends BaseDao
{
    protected function setModel(): string
    {
        return AiChatMessage::class;
    }
}
