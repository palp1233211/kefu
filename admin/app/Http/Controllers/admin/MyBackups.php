<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use Illuminate\Http\Request;
use App\Model\Mydump;

class MyBackups extends Base
{
    public function index()
    {
        return view('admin.myBackups.index  ');
    }

    /**
     * 获取群数据
     * @param Request $request
     * @return mixed
     */
    public function show(Request $request)
    {

        //获取页数
        $page = $request->input('page',0);

        //每页的条数
        $limit = $request->input('limit',15);
        $offset = ($page - 1) * $limit;
        //接受搜素的值 $searchParams 是一个obj
        $searchParams = json_decode($request->input('searchParams',''));
        //三元运算获取对应的数据

        $setTime =  $searchParams ? $searchParams->setTime:'';
        $where = [0,0];
        $list=[];
        $count=0;
        try{
            if  (!empty($setTime)){
                $between = explode(' - ',$setTime);
                $list = Mydump::whereBetween('create_time', $between )->offset($offset)
                    ->limit($limit)->orderBy('id','desc')->get();
                $count = Mydump::whereBetween('create_time', $between )->count();
            }else{
                $list = Mydump::offset($offset)->limit($limit)->orderBy('id','desc')->get();
                $count = Mydump::count();
            }
        }catch (\Exception $e){
            return $this->error('2002','mysql 执行失败');
        }
        if(!empty($list)){
            return $this->success($list,$count);
        }

    }

    /**
     * 数据备份
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function create(Request $request)
    {
        if ($request->isMethod('post')){
            $data = $request->all();
            //备份保存的地址
            $filename = '/var/www/html/workerman/admin/public/dump/'.date('Ymdhis',time()).'.sql';
            //开始数据备份
            $exec="/usr/bin/mysqldump -uroot -p123456  laraver > $filename";
            $status =  exec($exec);

            //执行成功返回0
            if(empty($status)){

                $id = Mydump::insertGetId([
                    'path' => $filename,
                    'explain' => $data['explain'],
                    'create_time'=>date('Y-m-d h:i:s',time()),
                ]);


                //记录操作日志
                $userName = $_COOKIE['userName'];
                $userId = $_COOKIE['userId'];
                $data=[
                    'causer_id'=>$userId,
                    'causer_name'=>$userName,
                    'type'=>'备份',
                    'subject_id'=>$id,
                    'description'=>'数据库备份成功',
                ];
                $this->activityLog($data);


                return $this->success('备份成功');
            }else{
                return $this->error(2002,'备份失败');
            }



        }
        return view('admin.myBackups.addMysql');
    }

    /**
     * 备份还原
     * @param Request $request
     * @return mixed
     */
    public function reductionDump(Request $request){
        $data = $request->all();
        $exec="/usr/bin/mysql -uroot -p123456  laraver < ".$data['path'];
        $status =  exec($exec);
        //记录操作日志
        $userName = $_COOKIE['userName'];
        $userId = $_COOKIE['userId'];
        $data=[
            'causer_id'=>$userId,
            'causer_name'=>$userName,
            'type'=>'还原',
            'subject_id'=>$data['id'],
            'description'=>'数据库备份还原:',
        ];
        $this->activityLog($data);
        //执行成功返回0
        if(empty($status)){
            return $this->success('备份还原成功');
        }else{
            return $this->error(2002,'备份还原失败');
        }
    }

    /**
     * 删除备份
     * @param $id
     * @return mixed
     */
    public function delete(Request $request,$id){
        $path = $request->input('path');

        if  (empty($id)){
            return $this->error('2002','删除失败');
        }
        if (strpos($id,',')){
            //批删
            $id = explode(',',$id);
        }
        try{
            //删除文件，必须先删除文件在删除数据库

            if (is_array($path)){
                //批量删除，
                foreach ($path as $item){
                    if (is_file($item)){
                        unlink($item);
                    }
                }
            }else{
                //单删
                if (is_file($path)){
                    unlink($path);
                }
            }
            Mydump::destroy($id);
            //记录操作日志
            $userName = $_COOKIE['userName'];
            $userId = $_COOKIE['userId'];
            $data=[
                'causer_id'=>$userId,
                'causer_name'=>$userName,
                'type'=>'删除',
                'subject_id'=>\GuzzleHttp\json_encode($id),
                'description'=>'数据库备份删除',
            ];
            $this->activityLog($data);
        }catch (\Exception $e){
            return $this->error('2002',$e->getMessage());
        }
        return $this->success('删除成功');
    }

 }
