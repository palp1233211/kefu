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
                <form class="layui-form layui-form-pane" action="/brandsShow" data-type="" data-table="currentTableId"  >
                    <div class="layui-form-item">
                        <div class="layui-inline">
                            <label class="layui-form-label">用户名</label>
                            <div class="layui-input-inline">
                                <input type="text" name="name" value="" autocomplete="off" class="layui-input">
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
                <button class="layui-btn layui-btn-normal layui-btn-sm data-add-btn" lay-event="add"> 添加 </button>
                <button class="layui-btn layui-btn-sm layui-btn-danger data-delete-btn" lay-event="delete"> 删除 </button>
            </div>
        </script>

        <table class="layui-hide" id="currentTableId" lay-filter="currentTableFilter"></table>

        <script type="text/html" id="currentTableBar">
            <a class="layui-btn  layui-btn-xs data-count-add" lay-event="addUser">添加</a>
            <a class="layui-btn layui-btn-xs layui-btn-danger data-count-delete" lay-event="delete">删除</a>
        </script>

    </div>
</div>
<script src="/static/layuimini/lib/layui-v2.5.5/layui.js" charset="utf-8"></script>
<script>
    let groupId = {{$row->id}},groupName="{{$row->name}}",groupImg="{{$row->img}}";//群组id
    let group = {id:groupId,name:groupName,img:groupImg};
    layui.use(['form', 'table'], function () {
        var $ = layui.jquery,
            form = layui.form,
            table = layui.table;

        table.render({
            elem: '#currentTableId',
            url: '/userGroup/addShow?groupId='+groupId,
            toolbar: '#toolbarDemo',
            response: {
                statusCode: 2000 //规定成功的状态码，默认：
            },
            defaultToolbar: [],
            cols: [[
                {type: "checkbox", width: 100},
                {field: 'id', width: 150, title: 'ID', sort: true},
                {field: 'name', width: 250, title: '用户名'},

                {
                    field: 'avatarUrl', width: 200, align: 'center', templet: function (d) {
                            if (d.avatarUrl){
                                return '<img class="layui-upload-img" src="'+d.avatarUrl+'"  style="width: 50px">';
                            }else{
                                return '';
                            }

                    }, title: '图片'
                },
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
                url: '/userGroup/addShow',
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
            var checkStatus = table.checkStatus('currentTableId')
                , data = checkStatus.data, idArr = [];
            //获取选中的复选框ID属性保存到 idArr数组中
            for (var i = 0; i < data.length; i++) {
                idArr.push({'id':data[i]['id'],'name':data[i]['name']})
            }
            if (idArr.length == 0) {
                return layer.alert('您未选择数据')
            }
            // ids = idArr.join(',')

            if (obj.event === 'add') {  // 监听添加操作
                $.post('/userGroup/addGroupUser/'+ groupId, {users: idArr,group,'_token': '{{ csrf_token() }}' }, function (e) {
                    if (e.code == 2000) {
                        layer.msg(e.data)
                    } else {
                        layer.msg(e.data)
                    }
                })
                return false;
            }else if (obj.event === 'delete') {  // 监听删除操作
                layer.confirm('真的删除行么？', function (index) {

                    $.ajax({
                        url: '/userGroup/delGroupUser/' + groupId,
                        type: 'post',
                        data: {users: idArr, group,'_method': 'DELETE', '_token': '{{ csrf_token() }}'},
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
            //之所以写成功二维数组，是因为在添加过个的时候是一个二维数组

            var data = [{id:obj.data.id,name:obj.data.name}];

             if (obj.event === 'addUser'){
                 // return
                 $.post('/userGroup/addGroupUser/'+ groupId, {users: data,group,'_token': '{{ csrf_token() }}' }, function (e) {
                     if (e.code == 2000) {
                         layer.msg(e.data);
                         $(obj.tr[0]).find('input[type="checkbox"]').prop('checked',true)
                         form.render();
                         // window.location.reload();
                     } else {
                         layer.msg(e.data)
                     }
                 })
                return false;
            }else if (obj.event === 'delete') {
                layer.confirm('真的删除行么？', function (index) {
                    $.post('/userGroup/delGroupUser/'+groupId, {users: data,group,'_method':'DELETE','_token': '{{ csrf_token() }}' }, function (e) {
                        if (e.code == 2000) {
                            layer.msg(e.data)
                            $(obj.tr[0]).find('input[type="checkbox"]').prop('checked',false)
                            form.render();
                            // window.location.reload();
                        } else {
                            layer.msg(e.data)
                        }
                    })
                    // layer.close(index);
                })
            }
        });

    });
</script>

</body>
</html>
