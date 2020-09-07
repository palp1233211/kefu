<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>layui</title>
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="/static/layuimini/lib/layui-v2.5.5/css/layui.css" media="all">
    <link rel="stylesheet" href="/static/layuimini/css/public.css" media="all">
</head>
<body>
<div class="layuimini-container">
    <div class="layuimini-main">

        <table class="layui-hide" id="currentTableId" lay-filter="currentTableFilter"></table>

    </div>
</div>
<script src="/static/layuimini/lib/layui-v2.5.5/layui.js" charset="utf-8"></script>
<script>
    layui.use(['form', 'table','laydate'], function () {
        var $ = layui.jquery,
            form = layui.form,
            table = layui.table,
            laydate = layui.laydate;
        //日期时间范围
        laydate.render({
            elem: '#test10'
            ,type: 'datetime'
            ,range: true
        });
        table.render({
            elem: '#currentTableId',
            url: '/log/show',
            toolbar: '#toolbarDemo',
            response: {
                statusCode: 2000 //规定成功的状态码，默认：
            },
            defaultToolbar: [],
            cols: [[
                {field: 'id', width: 100, title: 'ID', sort: true,align:'center'},
                {field: 'causer_id', width: 150, title: '操作者ID', sort: true ,align:'center'},
                {field: 'causer_name', width: 200, title: '操作者姓名',align:'center'},
                {field: 'type', width: 200, title: '操作类型',align:'center'},
                {field: 'description', width: 300, title: '操作描述',align:'center'},
                {field: 'created_at', minWidth: 250, title: '操作时间',align:'center'},
            ]],
            limits: [10, 15, 20, 25, 50, 100],
            limit: 15,
            page: true,
            skin: 'line'
        });



    });
</script>

</body>
</html>
