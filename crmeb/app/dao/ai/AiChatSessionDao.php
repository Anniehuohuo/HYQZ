<?php

namespace app\dao\ai;

use app\dao\BaseDao;
use app\model\ai\AiChatSession;

class AiChatSessionDao extends BaseDao
{
    protected function setModel(): string
    {
        return AiChatSession::class;
    }
}
