<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use App\Model\AdminUser;
use Illuminate\Http\Request;
use App\Model\AdminActivityLog;


class Log extends Base
{
    public function index()
    {
        return view('admin.log.index');
    }

    public function show(Request $request){
        //获取页数
        $page = $request->input('page',0);
        //每页的条数
        $limit = $request->input('limit',15);
        $offset = ($page - 1) * $limit;
        try{
            $list = AdminActivityLog::offset($offset)
                ->limit($limit)->orderBy('id','desc')->get();
            $count=AdminActivityLog::count();
        }catch (\Exception $e){
            return $this->error('2002','mysql 执行失败');
        }
        if(!empty($list)){
            return $this->success($list,$count);
        }

    }
}
