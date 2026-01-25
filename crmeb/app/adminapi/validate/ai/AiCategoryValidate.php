<?php

namespace app\adminapi\validate\ai;

use think\Validate;

class AiCategoryValidate extends Validate
{
    protected $rule = [
        'cate_key' => 'require|max:32',
        'cate_name' => 'require|max:64',
        'sort' => 'number',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'cate_key.require' => 'cate_key必填',
        'cate_key.max' => 'cate_key长度不能超过32',
        'cate_name.require' => 'cate_name必填',
        'cate_name.max' => 'cate_name长度不能超过64',
        'sort.number' => 'sort必须为数字',
        'status.in' => 'status只能为0或1',
    ];

    protected $scene = [
        'create' => ['cate_key', 'cate_name', 'sort', 'status'],
        'update' => ['cate_key', 'cate_name', 'sort', 'status'],
    ];
}

