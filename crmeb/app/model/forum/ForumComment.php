<?php

namespace app\model\forum;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

class ForumComment extends BaseModel
{
    use ModelTrait;

    protected $pk = 'id';
    protected $name = 'forum_comment';
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

    public function searchIsDelAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_del', (int)$value);
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        $value = trim((string)$value);
        if ($value !== '') {
            $query->whereLike('content', '%' . $value . '%');
        }
    }
}
