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
            <legend>数据库备份</legend>
        </fieldset>

        <form class="layui-form" action="/myBackups/create" data-type="ajax"  method="post" lay-filter="example">
            @CSRF

            <div class="layui-form-item">
                <label class="layui-form-label">备份原因</label>
                <div class="layui-input-block">
                    <input type="text" name="explain" value="日常备份" lay-verify="title" autocomplete="off" placeholder="请输入备份原因" class="layui-input">
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

    });
</script>
</body>
</html>
