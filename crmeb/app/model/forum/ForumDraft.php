<?php

namespace app\model\forum;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

class ForumDraft extends BaseModel
{
    use ModelTrait;

    protected $pk = 'id';
    protected $name = 'forum_draft';
    protected $autoWriteTimestamp = false;
    protected $createTime = false;
    protected $updateTime = false;

    public function searchUidAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('uid', (int)$value);
        }
    }

    public function searchTabAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('tab', $value);
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
            $query->whereLike('title|content', '%' . $value . '%');
        }
    }
}
