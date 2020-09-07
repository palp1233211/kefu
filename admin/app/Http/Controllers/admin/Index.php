<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class Index extends Base
{
    public function index()
    {
        return view('admin.index.index');
    }
    public function show(){
        return view('admin.index.show');
    }
    // 获取初始化数据
    public function getSystemInit(){
        $homeInfo = [
            'title' => '首页',
            'href'  => '/index/show',
        ];
        $logoInfo = [
            'title' => 'LAYUI MINI',
            'image' => '/static/layuimini/images/logo.png',
        ];
//        $menuInfo = $this->getMenuList();
        $systemInit = [
            'homeInfo' => $homeInfo,
            'logoInfo' => $logoInfo,
            'menuInfo' => [
                [
                    "title"=> "常规管理",
                    "icon"=> "fa fa-address-book",
                    "href"=> "",
                    "target"=> "_self",
                    "child"=> [
                        [
                            "title"=> "用户管理",
                            "href"=> "",
                            "icon"=> "fa fa-home",
                            "target"=> "_self",
                            "child"=> [
                                [
                                    "title"=> "群聊管理",
                                    "href"=> "/userGroup",
                                    "icon"=> "fa fa-tachometer",
                                    "target"=> "_self"
                                ],
                                [
                                    "title"=> "会员成员",
                                    "href"=> "/keeper",
                                    "icon"=>"fa fa-tachometer",
                                    "target"=> "_self"
                                ],
                            ]
                        ],
                        [
                            "title"=> "数据库备份",
                            "href"=> "myBackups/index",
                            "icon"=> "fa fa-floppy-o",
                            "target"=> "_self"
                        ],
                        [
                            "title"=> "操作日志",
                            "href"=> "log/index",
                            "icon"=> "fa fa-gears",
                            "target"=> "_self"
                        ],
                        [
                            "title"=> "系统设置",
                            "href"=> "page/setting.html",
                            "icon"=> "fa fa-gears",
                            "target"=> "_self"
                        ],
                    ]
                ]
            ]
        ];
        return response()->json($systemInit);
    }




}
