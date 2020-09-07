<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
class KeeperValidate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user' => 'nullable|alpha_dash|between:5,12',
            'name' => 'nullable|required|max:30',
            'password'=>'nullable|between:5,12',
            'avatarUrl' => 'nullable',
            'identity' => 'required',
            'salt' => 'nullable|alpha_num|size:4',
        ];
    }
    public function messages()
    {
        return [
            'user.alpha_dash' => '账号必须为字母|数字|_|-',
            'user.between'  => '账号长度必须在5到12之间',
            'name.required'  => '用户名长度不可为空',
            'name.between'  => '用户名长度必须在5到30之间',
            'password.between'  => '密码长度必须在5到12之间',
            'avatarUrl.regex'  => '必须是一个图片',
            'identity.required'  => '身份不可为空',
            'salt.alpha_num'  => '盐必须为数字',
            'salt.size'  => '盐长度必须为4',
        ];
    }



}
