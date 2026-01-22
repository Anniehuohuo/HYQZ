<?php

namespace app\model\forum;

use crmeb\basic\BaseModel;

class ForumPostLike extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'forum_post_like';
    protected $autoWriteTimestamp = false;
    protected $createTime = false;
    protected $updateTime = false;

    public function searchPostIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('post_id', (int)$value);
        }
    }

    public function searchUidAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('uid', (int)$value);
        }
    }
}
