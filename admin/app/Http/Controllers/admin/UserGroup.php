<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Model\User;
use App\Model\GroupUsers;
use App\Model\Group;
use Illuminate\Support\Facades\Redis;

class UserGroup extends Base
{
    public function index()
    {
        return view('admin.userGroup.index');
    }

    /**
     *添加群
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function create(Request $request)
    {
        if ($request->isMethod('post')){
            $data = $request->all();
            $rules =[
                'name' => 'required|max:24',
            ];
            $messages = [
                'name.required' => '群名必填',
                'name.max' => '群长度必须小于24',
            ];
            $validator = $this->AjaxValidator($data, $rules ,$messages);
            if (!empty($validator)){
                return $validator;
            }
            try{
                $row = Group::insertGetId(
                    [
                        'name' => $data['name'],
                        'img' => $data['img'],
                        'status'=>$data['status'],
                        'create_date' => date('Y-m-d h:i:s',time()),
                    ]
                );
            }catch (\Exception $e){
                $this->error('2002','数据入库失败 ');
            }
            if(!empty($row)){
                //记录操作日志
                $userName = $_COOKIE['userName'];
                $userId = $_COOKIE['userId'];
                $data=[
                    'causer_id'=>$userId,
                    'causer_name'=>$userName,
                    'type'=>'新增',
                    'subject_id'=>$row,
                    'description'=>'新增群组:'.$data['name'],
                ];
                $this->activityLog($data);
                return $this->success('添加成功');
            }


        }
        return view('admin.userGroup.create');
    }

    /**
     * 删除群组
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function delete(Request $request,$id){
        if (empty($id)){
            return  $this->error(2002,'未选择要删除的用户');
        }

        if (strpos($id,',')){
            //批删
            $id = explode(',',$id);
            foreach ($id as $item){
                if(GroupUsers::getGroupUser($item)) {
                    return  $this->error(2002,'id:'.$item.'的群组下有用户不可删除');
                }
            }
        }else{
            //单删
            if(GroupUsers::getGroupUser($id)) {
                return  $this->error(2002,'该群组下有用户不可删除');
            }
        }
        try{
            //记录操作日志
            $userName = $_COOKIE['userName'];
            $userId = $_COOKIE['userId'];
            $data=[
                'causer_id'=>$userId,
                'causer_name'=>$userName,
                'type'=>'删除',
                'subject_id'=>json_encode($id),
                'description'=>'删除群组:',
            ];
            $this->activityLog($data);
            //删除数据库中的数据
            Group::destroy($id);
        }catch (\Exception $e){

            return  $this->error(2002,'删除失败');
        }
        //清空redis中所有用户的群信息
        $this->delUserRedis();
        return  $this->success('删除成功');

    }

    /**
     * 修改群组
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function update(Request $request,$id){
        $row = Group::find($id);
        if ($request->isMethod('put')){
            $data=$request->all();
            try{
                Group::where('id',$id)->update(['name'=>$data['name'],'img'=>$data['img'],'status'=>$data['status']]);


                //清空redis中所有用户的群信息
                $this->delUserRedis();
            }catch (\Exception $e){
                return $this->error('2002','修改失败');
            }
            //记录操作日志

            $userName = $_COOKIE['userName'];
            $userId = $_COOKIE['userId'];
            $data=[
                'causer_id'=>$userId,
                'causer_name'=>$userName,
                'type'=>'修改',
                'subject_id'=>$id,
                'description'=>'修改群组:'.$row->name.' 信息',
            ];
            $this->activityLog($data);
            return $this->success('修改成功');
        }
        return view('admin.userGroup.edit')->with('data',$row);
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
        $name = $searchParams ? $searchParams->name: '';
        $status =  $searchParams ? $searchParams->status:'';
        $list=[];
        $count=0;
        try{
            $list = Group::where([['name', 'like', '%'.$name.'%'],['status', 'like', '%'.$status.'%' ]])->offset($offset)->limit($limit)->get();

            $count = Group::where([['status', 'like', '%'.$status.'%' ]])->count();
        }catch (\Exception $e){
            $this->error('2002','查询失败！ ');
        }

        if(!empty($list)){
            return $this->success($list,$count);
        }

    }

    /**
     * 展示群组添加用户页面
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function addUser(Request $request,$id){
        $row = Group::find($id);

        return view('admin.userGroup.addUser')->with(['row'=>$row]);
    }

    /**
     * 获取用户列表
     * @param Request $request
     * @return mixed
     */
    public function addShow(Request $request){
        //获取群组id
        $id = $request->input('groupId',0);
        //获取页数
        $page = $request->input('page',0);
        //每页的条数
        $limit = $request->input('limit',15);
        $offset = ($page - 1) * $limit;
        //接受搜素的值 $searchParams 是一个obj
        $searchParams = json_decode($request->input('searchParams',''));
        //三元运算获取对应的数据
        $name = $searchParams ? $searchParams->name: '';
        $list = [];
        $count = 0;
        try{
            $list = User::selectUser($name,$offset,$limit);

            //获取该群组下的所有用户,一对多获取。废弃，改为从redis获取
//            $users = Group::find($id)->users;
            //对象转数组
//            $users = $this->toArray($users);
            //获取该群组下的所有用户,
            $users = GroupUsers::getGroupUser($id);
            $uids = array_column($users,'id');

            foreach ($list as $item){
                if (in_array($item->id,$uids)){
                    $item->LAY_CHECKED = true;
                }
            }


            $count = count($list);
        }catch (\Exception $e){
            $this->error('2002','查询失败！ ');
        }
        return $this->success($list,$count);
    }

    /**
     * 向群组中添加新用户
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function addGroupUser(Request $request,$id){
        $data = $request->all();
        $group = $data['group']??'';

        $users = $data['users'];


        //
        try{

            $addUser= $this->arrayColumnKey($users,'id');
            //新建的群没有用户是空数组，所以不用进行处理。
            $redidUser = [];
            //获取redis中的该群已存在的用户
            $groupUsers = GroupUsers::getGroupUser($id);
            if (!empty($groupUsers)){
                $redidUser = $this->arrayColumnKey($groupUsers,'id');
            }

        }catch (\Exception $e){
            return $this->error(2002,$e->getMessage());
        }

        $users = array_diff_key($addUser,$redidUser);
        if (empty($users)){
            //需要添加的 数据为空，直接返回成功
            return $this->success('添加成功');
        }

        try {
            //数据入库
            GroupUsers::addUser($id, $users);
        }catch (\Exception $e){
            return $this->error(2002,'添加入库失败');
        }

        //明天的作业，报接收到的群组信息和用户信息，循环向用户的redis群组列表中添加该群组的信息
        $this->reUserGroupRedis($group,$users);
        //清空redis中所有用户的群信息
//        $this->delUserRedis();

        //记录操作日志

        $userName = $_COOKIE['userName'];
        $userId = $_COOKIE['userId'];
        $data=[
            'causer_id'=>$userId,
            'causer_name'=>$userName,
            'type'=>'新增',
            'subject_id'=>$id,
            'description'=>'新增群组的用户:'.\GuzzleHttp\json_encode($users,JSON_UNESCAPED_UNICODE),
        ];
        $this->activityLog($data);

        return $this->success('成功');
    }

    /**
     * 删除群组中的用户
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function delGroupUser(Request $request,$id){
        $data = $request->all();
        $users = $data['users'];
        $group = $data['group'];
        //获取redis中的该群已存在的用户
        $groupUsers = GroupUsers::getGroupUser($id);
        //
        try{
            $addUser= $this->arrayColumnKey($users,'id');
            //新建的群没有用户是空数组，所以不用进行处理。
            $redidUser = [];
            if (!empty($groupUsers)){
                $redidUser = $this->arrayColumnKey($groupUsers,'id');
            }
        }catch (\Exception $e){
            return $this->error(2002,$e->getMessage());
        }
        //获取真实需要删除的用户
        $users = array_intersect_key($addUser,$redidUser);

        if (empty($users)){

            //为空说明用户删除的数据并不在群组中
            return $this->success('删除成功');
        }

        try {
            //删除数据库的数据
            GroupUsers::delUser($id, $users);
        }catch (\Exception $e){
            return $this->error(2002,'删除失败');
        }
        //记录操作日志

        $userName = $_COOKIE['userName'];
        $userId = $_COOKIE['userId'];
        $data=[
            'causer_id'=>$userId,
            'causer_name'=>$userName,
            'type'=>'删除',
            'subject_id'=>$id,
            'description'=>'删除群组的用户:'.\GuzzleHttp\json_encode($users,JSON_UNESCAPED_UNICODE),
        ];
        $this->activityLog($data);


        $this->delUserGroupRedis($group,$users);

        //清空redis中所有用户的群信息
//        $this->delUserRedis();

        return $this->success('删除成功');
    }

}
