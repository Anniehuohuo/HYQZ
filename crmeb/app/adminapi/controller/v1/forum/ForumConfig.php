<?php

namespace app\adminapi\controller\v1\forum;

use app\adminapi\controller\AuthController;
use crmeb\services\CacheService;
use think\facade\App;
use think\facade\Db;

class ForumConfig extends AuthController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function read()
    {
        $enabled = (int)sys_config('forum_enabled', 0) ? 1 : 0;
        return app('json')->success(['enabled' => $enabled]);
    }

    public function save()
    {
        [$enabled] = $this->request->postMore([
            ['enabled', 0],
        ], true);
        $enabled = (int)$enabled ? 1 : 0;

        $row = Db::name('system_config')->where('menu_name', 'forum_enabled')->find();
        $value = json_encode($enabled, JSON_UNESCAPED_UNICODE);
        if ($row) {
            Db::name('system_config')->where('id', (int)$row['id'])->update(['value' => $value]);
        } else {
            Db::name('system_config')->insert([
                'menu_name' => 'forum_enabled',
                'type' => 'radio',
                'input_type' => 'input',
                'config_tab_id' => 132,
                'parameter' => "1=>开启\n0=>关闭",
                'upload_type' => 1,
                'required' => '',
                'width' => 0,
                'high' => 0,
                'value' => $value,
                'info' => '论坛开关',
                'desc' => '小程序论坛功能开关，审核期间请关闭',
                'sort' => 0,
                'status' => 1,
                'level' => 0,
                'link_id' => 0,
                'link_value' => 0,
            ]);
        }
        CacheService::clear();
        return app('json')->success();
    }
}

