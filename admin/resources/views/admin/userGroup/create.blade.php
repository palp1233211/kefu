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
            <legend>群添加</legend>
        </fieldset>

        <form class="layui-form" action="/userGroup/create" data-type="ajax"  method="post" lay-filter="example">
            @CSRF

            <div class="layui-form-item">
                <label class="layui-form-label">群名称</label>
                <div class="layui-input-block">
                    <input type="text" name="name" lay-verify="title" autocomplete="off" placeholder="请输入群名称" class="layui-input">
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">群图片</label>
                <input type="hidden" name="img">
                <div class="layui-input-block">
                    <button type="button" class="layui-btn" id="test1">上传图片</button>
                    <div class="layui-upload-list">
                        <img class="layui-upload-img" id="demo1">
                        <p id="demoText"></p>
                    </div>
                </div>
            </div>


            <div class="layui-form-item">
                <label class="layui-form-label">是否启用</label>
                <div class="layui-input-block">
                    <input type="radio" name="status" value="1" title="是" checked="">
                    <input type="radio" name="status" value="0" title="否">
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
                if (value.length == 0) {
                    return '群名称不能为空';
                }
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
                $("input[name=\"img\"]").attr('value',res.data)
                //图片展示
                $("#demo1").attr('str',res.data)
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
