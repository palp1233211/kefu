<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Base;
use App\Model\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Spatie\Activitylog\Models\Activity;


class Login extends Base
{
    public function index()
    {
        return view('admin.login.login');
    }

    public function verifyLogin(Request $request){

        $data = $request->all();
        $rules =[
            'username' => 'required|max:24',
            'password' => 'required|min:6',
            'captcha' => 'required|captcha'
        ];
        $messages = [
            'username.required' => '用户名必填',
            'username.max' => '用户名长度必须小于24',
            'password.required' => '密码必填',
            'password.min' => '密码长度必须大于6',
            'captcha.captcha' => '验证码不正确',
        ];
        $validator = $this->AjaxValidator($data, $rules ,$messages);
        if (!empty($validator)){
            return $validator;
        }

        $row = AdminUser::where(['user'=>$data['username']])->first();

        if ($row){
            if ($row->password == md5($row->salt.md5($data['password']))){
                setcookie('userName',$row->user);
                setcookie('userId',$row->id);

                $data=[
                    'causer_id'=>$row->id,
                    'causer_name'=>$row->user,
                    'type'=>'登陆',
                    'description'=>'登陆后台',
                ];
                $this->activityLog($data);
                //登录互踢
                $key = md5('admin_token_'.$row->id.rand(100000,999999));
                Redis::set('admin_token_'.$row->id,$key);
                setcookie('token',$key);


                return $this->success('登陆成功');
            }else{
                return $this->error(2002,'密码错误');
            }
        }else{
            return $this->error(2002,'账号错误');
        }
    }
}
