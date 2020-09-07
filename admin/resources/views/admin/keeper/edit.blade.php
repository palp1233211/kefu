<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>menu</title>
    <link rel="stylesheet" href="/static/layuimini/lib/layui-v2.5.5/css/layui.css" media="all">
    <link rel="stylesheet" href="/static/layuimini/css/public.css" media="all">
    <style>
        .layui-btn:not(.layui-btn-lg ):not(.layui-btn-sm):not(.layui-btn-xs) {
            height: 34px;
            line-height: 34px;
            padding: 0 8px;
        }
    </style>
</head>
<body>
<div class="layuimini-container">
    <div class="layuimini-main">

        <fieldset class="layui-elem-field layui-field-title" style="margin-top: 50px;">
            <legend>群修改</legend>
        </fieldset>

        <form class="layui-form" action="/keeper/edit/{{$data->id}}" data-type="ajax"  method="post" lay-filter="example">
            @CSRF
            @method('PUT')
            <input type="hidden" name="id" value="{{$data->id}}">
            <div class="layui-form-item">
                <label class="layui-form-label">用户账号</label>
                <div class="layui-input-block">
                    <input type="text" name="user"  value="{{$data->user}}" lay-verify="title" autocomplete="off" placeholder="请输入用户账号" class="layui-input">
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">用户密码</label>
                <div class="layui-input-block">
                    <input type="password" name="password"  value="" lay-verify="title" autocomplete="off" placeholder="请输入用户密码" class="layui-input">
                </div>
            </div>




            <div class="layui-form-item">
                <label class="layui-form-label">用户名称</label>
                <div class="layui-input-block">
                    <input type="text" name="name"  value="{{$data->name}}" lay-verify="title" autocomplete="off" placeholder="请输入用户名称" class="layui-input">
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">盐值</label>
                <div class="layui-input-block">
                    <input type="text" name="salt"  value="{{$data->salt}}" lay-verify="title" autocomplete="off" placeholder="请输入用于密码加密的盐" class="layui-input">
                </div>
            </div>


            <div class="layui-form-item">
                <label class="layui-form-label">用户头像</label>
                <input type="hidden" name="avatarUrl" value="{{$data->avatarUrl}}">
                <div class="layui-input-block">
                    <button type="button" class="layui-btn" id="test1">上传图片</button>
                    <div class="layui-upload-list">
                        <img class="layui-upload-img" src="{{$data->avatarUrl ?? ''}}" id="demo1" style="width: 100px">
                        <p id="demoText"></p>
                    </div>
                </div>
            </div>



            <div class="layui-form-item">
                <label class="layui-form-label">管理员</label>
                <div class="layui-input-block">
                    <input type="radio" name="identity" value="1" title="是" @if ($data->identity == 1)  checked @endif >
                    <input type="radio" name="identity" value="0" title="否" @if ($data->identity == 0)  checked @endif >
                </div>
            </div>

            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit="" lay-filter="demo1">立即提交</button>
                </div>
            </div>
        </form>

    </div>
</div>
<!-- 注意：如果你直接复制所有代码到本地，上述js路径需要改成你本地的 -->
@include('admin.public.js')
<script>
    layui.use(['form', 'layedit', 'laydate' ,'upload'], function () {
        var form = layui.form
            , layer = layui.layer
            , layedit = layui.layedit
            ,upload = layui.upload
            ,layuimini = layui.layuimini;

        //自定义验证规则
        form.verify({
            title: function (value) {
                // if (value.length == 0) {
                //     return '用户名称不能为空';
                // }
            }
            , content: function (value) {
                layedit.sync(editIndex);
            }
        });


        //表单初始赋值
        form.val('example', {
            "username": "" // "name": "value"

        })

        //普通图片上传
        var uploadInst = upload.render({
            elem: '#test1'
            ,url: '/upload' //改成您自己的上传接口
            ,data: {
                "_token": "{{csrf_token()}}"
            }
            ,before: function(obj){
                //预读本地文件示例，不支持ie8
                obj.preview(function(index, file, result){
                    $('#demo1').attr('src', result).css('width','100px'); //图片链接（base64）
                });
            }
            ,done: function(res){
                //如果上传失败
                if(res.code != 2000){
                    return layer.msg(res.data);
                }
                //上传成功
                $("input[name=\"avatarUrl\"]").attr('value', res.data)
            }
            ,error: function(){
                //演示失败状态，并实现重传
                var demoText = $('#demoText');
                demoText.html('<span style="color: #FF5722;">上传失败</span> <a class="layui-btn layui-btn-xs demo-reload">重试</a>');
                demoText.find('.demo-reload').on('click', function(){
                    uploadInst.upload();
                });
            }
        });

    });
</script>
</body>
</html>
