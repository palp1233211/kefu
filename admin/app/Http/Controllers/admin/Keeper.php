<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use App\Model\User;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\KeeperValidate;
use Illuminate\Http\Request;

class Keeper extends Base
{
    public function index()
    {
        return view('admin.keeper.index');
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
        $identity =  $searchParams ? $searchParams->identity:'';
        $list=[];
        $count=0;
        try{
            $list = User::where([['name', 'like', '%'.$name.'%'],['identity', 'like', '%'.$identity.'%' ]])->offset($offset)->limit($limit)
                ->select('id','name','identity','avatarUrl')->get();

            $count = User::where([['identity', 'like', '%'.$identity.'%' ]])->count();
        }catch (\Exception $e){
            $this->error('2002','查询失败！ ');
        }

        if(!empty($list)){
            return $this->success($list,$count);
        }

    }

    /**
     * 添加用户
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function create(Request $request){
        if($request->isMethod('post')){
//            $data = $keeperValidate->input();
            $data = $request->all();
//            $flight = new User();

            try{
                $id  = User::insertGetId([
                    'user' => $data['user'],
                    'password' => md5($data['salt'].md5($data['password'])),
                    'name' => $data['name'],
                    'salt' => $data['salt'],
                    'identity' => $data['identity'],
                    'avatarUrl' => $data['avatarUrl'],
                ]);
                $this->addUserListRedis($data['identity'],['id'=>$id,'name'=>$data['name']]);

                $userName = $_COOKIE['userName'];
                $userId = $_COOKIE['userId'];
                $description = $data['identity'] ? '管理员':'会员';
                //记录操作日志
                $data=[
                    'causer_id'=>$userId,
                    'causer_name'=>$userName,
                    'type'=>'添加',
                    'subject_id'=>$id,
                    'description'=>'添加新的'.$description,
                ];
                $this->activityLog($data);
                return $this->success('添加成功');
            }catch (\Exception $e){
                return $this->error(2002,$e->getMessage());
            }

        }else{
            return view('admin.keeper.create');

        }
    }




    /**
     * 修改会员信息
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function update(Request $request,$id){
        $data = User::find($id);
        return view('admin.keeper.edit')->with('data',$data);
    }

    /**
     * 修改
     * @param KeeperValidate $request
     * @param $id
     * @return mixed
     */
    public function edit(KeeperValidate $request,$id){
        $data = $request->validated();

        $flight = User::find($id);
        $oldIdentity = $flight->identity;
        $oldStatus = $flight->Status;
        $flight->user = $data['user'];
        $flight->name = $data['name'];
        $flight->avatarUrl = $data['avatarUrl'];
        $flight->identity = $data['identity'];
        $flight->salt = $data['salt'];

        if ( !empty($data['password']) ){
            $flight->password = md5($data['salt'].md5($data['password'])) ;
        }
        $flight->save();

        //确保redis中的数据是实时的。
        //从旧的列表中删除这个条数据
        $this->delUserListRedis($oldIdentity,$id);
        //向新的列表中添加这个条数据
        $this->addUserListRedis($data['identity'],['id'=>$id,'name'=>$data['name'],'img'=>$data['avatarUrl'],'status'=>$oldStatus]);
        //记录操作日志
        $userName = $_COOKIE['userName'];
        $userId = $_COOKIE['userId'];
        $data=[
            'causer_id'=>$userId,
            'causer_name'=>$userName,
            'type'=>'修改',
            'subject_id'=>$id,
            'description'=>'修改'.$data['name'].'的用户信息',
        ];
        $this->activityLog($data);
        return $this->success('修改成功');

    }

    /**
     * 修改用户身份为管理员
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function editIdentity(Request $request,$id){
        $flight = User::find($id);
        if (!empty($flight)){
            $flight->identity = 1;
            $flight->save();
            //确保redis中的数据是实时的。
            //从列表中删除这个条数据
            $this->delUserListRedis(0,$id);
            //向列表中添加这个条数据
            $this->addUserListRedis(1,['id'=>$id,'name'=>$flight->name,'img'=>$flight->avatarUrl,'status'=>$flight->status]);
            //记录操作日志
            $userName = $_COOKIE['userName'];
            $userId = $_COOKIE['userId'];
            $data=[
                'causer_id'=>$userId,
                'causer_name'=>$userName,
                'type'=>'修改',
                'subject_id'=>$id,
                'description'=>'修改'.$flight->name.'的身份为管理员',
            ];
            $this->activityLog($data);
            return $this->success('修改成功');
        }else{
            return $this->error(2002,'修改失败');
        }

    }
}
