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

        <fieldset class="table-search-fieldset">
            <legend>搜索信息</legend>
            <div style="margin: 10px 10px 10px 10px">
                <form class="layui-form layui-form-pane" action="/myBackups" data-type="" data-table="currentTableId"  >
                    <div class="layui-form-item">

                        <div class="layui-inline">
                            <label class="layui-form-label">时间范围</label>
                            <div class="layui-input-inline">
                                <input type="text" name="setTime" class="layui-input" id="test10" placeholder=" - ">
                            </div>
                        </div>

                        <div class="layui-inline">
                            <button type="submit" class="layui-btn layui-btn-primary"  lay-submit lay-filter="data-search-btn"><i class="layui-icon"></i> 搜 索</button>
                        </div>
                    </div>
                </form>
            </div>
        </fieldset>

        <script type="text/html" id="toolbarDemo">
            <div class="layui-btn-container">
                <button class="layui-btn layui-btn-normal layui-btn-sm data-add-btn" lay-event="add"> 备份 </button>
                <button class="layui-btn layui-btn-sm layui-btn-danger data-delete-btn" lay-event="delete"> 删除 </button>
            </div>
        </script>

        <table class="layui-hide" id="currentTableId" lay-filter="currentTableFilter"></table>

        <script type="text/html" id="currentTableBar">
            <a class="layui-btn layui-btn-normal layui-btn-xs data-count-edit" lay-event="reduction">还原</a>
            <a class="layui-btn layui-btn-xs layui-btn-danger data-count-delete" lay-event="delete">删除</a>
        </script>

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
            url: '/myBackups/show',
            toolbar: '#toolbarDemo',
            response: {
                statusCode: 2000 //规定成功的状态码，默认：
            },
            defaultToolbar: [],
            cols: [[
                {type: "checkbox", width: 100},
                {field: 'id', width: 150, title: 'ID', sort: true},
                {field: 'explain', width: 300, title: '备份原因'},
                {field: 'create_time', width: 250, title: '备份时间'},
                {title: '操作', minWidth: 150, toolbar: '#currentTableBar', align: "center"}
            ]],
            limits: [10, 15, 20, 25, 50, 100],
            limit: 15,
            page: true,
            skin: 'line'
        });

        // 监听搜索操作
        form.on('submit(data-search-btn)', function (data) {
            var result = JSON.stringify(data.field);

            //执行搜索重载
            table.reload('currentTableId', {
                url: '/myBackups/show',
                page: {
                    curr: 1
                }
                , where: {
                    searchParams: result
                }
            }, 'data');

            return false;
        });

        /**
         * toolbar监听事件
         */
        table.on('toolbar(currentTableFilter)', function (obj) {
            if (obj.event === 'add') {  // 监听添加操作
                var index = layer.open({
                    title: '数据库备份',
                    type: 2,
                    shade: 0.2,
                    maxmin:true,
                    shadeClose: true,
                    area: ['80%', '80%'],
                    content: '/myBackups/create/',
                });
                $(window).on("resize", function () {
                    layer.full(index);
                });
            }else if (obj.event === 'delete') {  // 监听删除操作
                layer.confirm('真的删除行么？', function (index) {
                    var checkStatus = table.checkStatus('currentTableId')
                        , data = checkStatus.data, idArr = [],path=[];
                    //获取选中的复选框ID属性保存到 idArr数组中
                    for (let i = 0; i < data.length; i++) {
                        idArr.push(data[i]['id']);
                        path.push(data[i]['path'])
                    }
                    if (idArr.length == 0) {
                        return layer.alert('您未选择数据')
                    }
                    console.log(idArr)
                    $.ajax({
                        url: '/myBackups/delete/' + idArr,
                        type: 'post',
                        data: {id: idArr,path:path, '_method': 'DELETE', '_token': '{{ csrf_token() }}'},
                        success: function (e) {
                            if (e.code == 2000) {
                                //重新加载数据
                                layer.alert(e.data, function () {
                                    window.location.reload();
                                })

                            } else {
                                layer.alert(e.data)
                            }
                        }
                    })
                    // layer.alert(JSON.stringify(idArr));
                    layer.close(index);
                    return false;
                })
            }
        });

        //监听表格复选框选择
        table.on('checkbox(currentTableFilter)', function (obj) {
            console.log(obj)
        });

        table.on('tool(currentTableFilter)', function (obj) {
            var data = obj.data;
            if (obj.event === 'reduction') {
                $.get('/myBackups/reductionDump/', {id: data.id,path:data.path,'_token': '{{ csrf_token() }}' }, function (e) {
                    if (e.code == 2000) {
                        layer.msg(e.data)
                    } else {
                        layer.msg(e.data)
                    }
                })
                return false;
            } else if (obj.event === 'delete') {
                layer.confirm('真的删除行么？', function (index) {
                    $.post('/myBackups/delete/'+ data.id, {id: data.id,path:data.path,'_method':'DELETE','_token': '{{ csrf_token() }}' }, function (e) {
                        if (e.code == 2000) {
                            layer.msg(e.data)
                            obj.del();
                        } else {
                            layer.msg(e.data)
                        }
                    })
                    layer.close(index);
                })
            }
        });

    });
</script>

</body>
</html>
