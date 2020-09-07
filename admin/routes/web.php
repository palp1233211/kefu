<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/login', 'admin\Login@index');
Route::post('/verifyLogin', 'admin\Login@verifyLogin');
//公共路由
//图片上传
Route::post('/upload','admin\Base@upload');
Route::group(["namespace"=>"admin",'middleware'=>['CheckAge']],function () {
    Route::get('/', 'Index@index');


    Route::get('/getSystemInit','Index@GetSystemInit');
    Route::get('/index/show','Index@Show');

    //会员管理
    Route::get('/keeper','Keeper@index');
    Route::get('/keeper/show','Keeper@show');
    Route::match(['get', 'post'],'/keeper/create/','Keeper@create');//添加会员页面
    Route::match(['get'],'/keeper/update/{id}','Keeper@update'); //展示编辑会员页面
    Route::put('/keeper/edit/{id}','Keeper@edit'); //保存编辑会员信息
    Route::put('/keeper/editIdentity/{id}','Keeper@editIdentity'); //编辑会员身份


    //群聊的
    Route::get('/userGroup','UserGroup@index');
    Route::match(['get', 'post'],'/userGroup/create/','UserGroup@create');//添加群
    Route::delete('/userGroup/delete/{id}','UserGroup@delete'); //删除群组
    Route::match(['get', 'put'],'/userGroup/update/{id}','UserGroup@update'); //编辑群组

    Route::get('/userGroup/show','UserGroup@show');
    Route::get('/userGroup/addUser/{id}','UserGroup@addUser');
    Route::get('/userGroup/addShow','UserGroup@addShow');
    Route::post('/userGroup/addGroupUser/{id}','UserGroup@addGroupUser'); //群组添加用户
    Route::delete('/userGroup/delGroupUser/{id}','UserGroup@delGroupUser'); //群组删除用户

    //数据库备份
    Route::get('/myBackups/index','MyBackups@index');
    Route::get('/myBackups/show','MyBackups@show');
    Route::match(['get', 'post'],'/myBackups/create','MyBackups@create');
    Route::get('/myBackups/reductionDump','MyBackups@reductionDump');
    Route::delete('/myBackups/delete/{id}','MyBackups@delete'); //群组删除用户

    //日志
    Route::get('/log/index','Log@index');
    Route::get('/log/show','Log@show');

});
