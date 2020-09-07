//公共的提交方法 form 需要添加属性data-type='ajax'

$(document).on('submit','form[data-type=ajax]',function(){
    //获取数据
    var url = $(this).attr('action');
    var data = $(this).serializeArray();//序列化表单元素
    //弹出询问框
    layer.confirm('您确定提交处理吗?',{icon:3, title:'提示'},function(index){
        //异步提交
        $.ajax({
            type: "POST",
            dataType:"json",
            url:url,
            data:data,
            success:function(obj){
                var icon_num = (obj.code==2000) ? 1 : 2;
                if(obj.code>=2000 && obj.code<3000){
                    layer.open({
                      content: obj.data,
                      btn: ['确定'],
                      shade: 0.1,
                      icon: icon_num,
                      yes: function(index, layero){
                          if(obj.url){
                              location.href = obj.url; //跳转指定地址
                          }else if(icon_num == 1){
                              layer.close(index);
                              if (obj.reload){
                                  //刷新父页面，这里是用来对操作成功后上限table数据的
                                  window.parent.location.reload();
                              }
                              let parentIndex = parent.layer.getFrameIndex(window.name);
                              parent.layer.close(parentIndex)
                          }else{
                              layer.close(index);
                          }

                      },
                      cancel: function(){
                          if(obj.url){
                              location.href = obj.url; //跳转指定地址
                          }else{
                              layer.close();
                          }
                      },
                    });
                }
            },
            error:function(data){
                layer.alert('网络故障!');
            }
        });
    });
    return false;
});
//搜索方法
layui.use(['table'], function () {
    var form = layui.form,
        table = layui.table
    form.on('submit(data-search-btn)', function (data) {
        var url = data.form.action
        var tableID = data.form.dataset.table
        var result = JSON.stringify(data.field);
        //执行搜索重载
        table.reload(tableID, {
            url: url,
            page: {
                curr: 1
            }
            , where: {
                searchParams: result
            }
        }, 'data');

        return false;
    });
});




