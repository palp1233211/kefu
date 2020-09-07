// pages/userList/userList.js
Page({

  /**
   * 页面的初始数据
   */
  data: {
    CODE:{
      'SUCCESS':1, //成功
      'ERROR':0, //失败
      'ISLOGIN':3,//验证登录
      'SERVERMSG':4,//接收服务端文本消息
      'CLIENTMSG':5,//客户端发送文本消息
      'CLIENTGETMSG':6,//客户端发送获取用户间的历史聊天记录的请求
      'SERVERSETMSG':7,//服务端发送用户间的历史聊天消息
      'ISKICKED':8,//验证用户是否已经被踢
      
      'TEXTMSG':10,//文本消息
      'IMGMSG':11,//图片消息
      'CLIENTCLOSE':13,//用户离线通知
      'CLIENTCONTENT':14,//用户上线通知
      'UNREADMSG':15,//未读消息处理
      'ADDUNREADMSG':16,//未读消息数量+1
      'DELUNREADMSG':17,//未读消息数量清空
      'SERVERUNREADNUM' :18, //服务端向客户端发送未读消息数量
      'PINGINTERVAL':20,//心跳检测
    },
    test:2,
    name:'',
    uid:0,
    identity:0,
    token:'',
    username:'',
    socketOpen : false,
    isgroup:0,//当前聊天对象是否是群聊,1为群组，0为会员
    textContent:'',//用户输入的消息
    guserInfo:{},//目标用户信息
    userListAll:[],//展示的用户列表
    groupListAll:[],//用户加入的群组列表
    userList:[],//所有的用户列表信息
    groupList:[],//展示群组列表
    chatBoxKuang:'none',//聊天是否展示
    msgList:[],//保存用户间聊天内容
    toView:'item0',//聊天界面拉到那个位置
    userListStatus:true,//用户列表是否展示
    userSearchInput:true,//搜素框的展示
    userSearchName:'',
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    let that = this;
    wx.getStorage({
      key: 'name',
      success (res) {
        that.setData({
          name:res.data
        })
      }
    })
    wx.getStorage({
      key: 'id',
      success (res) {
        that.setData({
          uid:res.data
        })
      }
    })
    wx.getStorage({
      key: 'identity',
      success (res) {
        that.setData({
          identity:res.data
        })
      }
    })
    wx.getStorage({
      key: 'token',
      success (res) {
        that.setData({
          token:res.data
        })
      }
    })
    wx.getStorage({
      key: 'username',
      success (res) {
        that.setData({
          username:res.data
        })
      }
    })


    this.websocket()
      // 心跳检测
    setInterval(()=>{
      let data = {code:this.data.CODE.PINGINTERVAL};
      //开始发送
      this.sendSocket(data)
      // wx.sendSocketMessage({
      //   data: JSON.stringify(data),
      // })
    }, 50000);
  },

  /**
   * 生命周期函数--监听页面初次渲染完成
   */
  onReady: function () {

  },

  /**
   * 生命周期函数--监听页面显示
   */
  onShow: function () {

  },

  /**
   * 生命周期函数--监听页面隐藏
   */
  onHide: function () {

  },

  /**
   * 生命周期函数--监听页面卸载
   */
  onUnload: function () {

  },

  /**
   * 页面相关事件处理函数--监听用户下拉动作
   */
  onPullDownRefresh: function () {

  },

  /**
   * 页面上拉触底事件的处理函数
   */
  onReachBottom: function () {

  },

  /**
   * 用户点击右上角分享
   */
  onShareAppMessage: function () {

  },
  /**
   * 建立websocket链接
   */
  websocket:function(){
    wx.connectSocket({
      url: 'ws://212.64.71.48:2345',
      header:{
        'content-type': 'application/json'
      },
    })
    //链接建立成功的回调
    wx.onSocketOpen((result) => {
      let name = this.data.name,
          uid = this.data.uid,
          identity=this.data.identity,
          code = this.data.CODE.ISLOGIN;
      let data = {'key':name,uid,code,identity};
      //链接建立成功修改链接状态
      this.setData({
        socketOpen:true
      })
      wx.sendSocketMessage({
        data:JSON.stringify(data)
      })
      console.log('建立连接成功');
    })
    // 接受服务端发送的数据  
    wx.onSocketMessage((e)=>{
      let data = e.data;
      data = JSON.parse(data);
      console.log(data)
      this.message(data)
    });
    wx.onSocketClose((e)=>{
       //链接建立成功修改链接状态
       this.setData({
        socketOpen:false
      })
    })

  },
  //处理websocket接受到的消息
  message:function(e){
   let that = this;
   console.log(e)
    switch(e.code){
      case this.data.CODE.ERROR:
        wx.showModal({
          title: '提示',
          content: e.msg+',您需要重新登陆',
          showCancel:false,
          success (res) {
            if (res.confirm) {
              wx.redirectTo({
                url: '/pages/index/index'
              })
              console.log('用户点击确定')
            }
          }
        })
      break;
      case this.data.CODE.SUCCESS:
        //登录成功，渲染用户，群组列表
        that.setData({
          userList:e.data.userList,
          userListAll:e.data.userList,
          groupList: e.data.groupList,
          groupListAll: e.data.groupList,
        })


      break;
      case this.data.CODE.CLIENTCONTENT:
        this.reuser_status(e.uid,1)
      break;
      case this.data.CODE.CLIENTCLOSE:
        this.reuser_status(e.uid,0)
      break;
      case this.data.CODE.SERVERSETMSG:
        let msgList = [];
        console.log(e.data)
        //与目标用户的历史聊天记录
        e.data.forEach((item)=>{
         msgList.push(JSON.parse(item));
        })
        this.setData({
          msgList:msgList
        })
         //设置滚动条的位置
         let set = `item${msgList.length-1}`;
         this.setData({
          toView:set
        })
        console.log(set)
        console.log(this.data.toView,123)
      break;
      case this.data.CODE.SERVERMSG:
        //接受目标用户发送的数据
        let msg = this.data.msgList;
        
        msg.push(e)
        this.setData({
          msgList:msg
        })
        //设置滚动条的位置
        this.setData({
          toView:`item${msg.length-1}`
        })
        console.log(this.data.toView,123)
        break;
      case this.data.CODE.PINGINTERVAL:
        //心跳数据无需处理
        break;
      case this.data.CODE.SERVERUNREADNUM:
        //服务器发送过来的未读消息数量

       this.unread_num(e.isgroup,e.gid,e.unread);
        break
    }
  },
  //修改在线用户状态
  reuser_status:function( id,user_status=0){
      let userListAll = this.data.userListAll;
      let that = this;
      if(!id){
        console.log('没有接受到用户id');
        return;
      }
      if(userListAll){
        //修改用户列表中的用户状态
        userListAll.forEach((item)=>{
          if( item.id == id){
              item.status = user_status;
            return;
          }
        })
        that.setData({
          userListAll:userListAll,
          userList:userListAll
        })
      }
      
  },
  //用户聊天消息双向绑定
  bindName(e){
    this.setData({
      textContent: e.detail.value
    })
  },
  //当用户点击某个用户开始聊天的初始化操作
  usertap:function(e){
    let item = e.currentTarget.dataset.item,
        isgroup =  e.currentTarget.dataset.isgroup,
        that = this;
    //隐藏用户搜索框
    that.setData({
      userSearchInput:false,
      isgroup:isgroup,
    })
    that.setData({
      guserInfo: item
    })
    //展示聊天页面
    that.setData({
      chatBoxKuang:'block',
    })
    //获取与目标用户的聊天记录
    that.setMsg();
    //清空与好友的聊天未读消息数量
    let data = {
      code:that.data.CODE.UNREADMSG,
      uid:that.data.uid,
      gid:that.data.guserInfo.id,
      type:that.data.CODE.DELUNREADMSG,
      isgroup:isgroup,
    };
    that.sendSocket(data)
  },
      /**
     * 未读消息数量+1
     */
     unreadMsg:function(uid,gid,type,isgroup){
      //如果uid 或gid不存在则不处理
      if (!uid && !gid) {
          console.log('有数据不存在')
          return  ;
      }
      if (this.data.socketOpen) {
          let data = {code:this.data.CODE.UNREADMSG,uid,gid,type,isgroup};
          console.log(data)
          console.log(456)
          //调用发送消息方法
          this.sendSocket(data)
      }
  },
  //关闭聊天页面
  chatClose:function(){


    //显示用户搜索框
    this.setData({
      userSearchInput:true,
      userList:this.data.userListAll,
      groupList:this.data.groupListAll
    })

    //清空历史聊天记录
    this.setData({
      msgList:[],
    })
    //清空输入框数据
    this.setData({
      textContent:''
    })
    //清空与好友的聊天未读消息数量
    let data = {
      code:this.data.CODE.UNREADMSG,
      uid:this.data.uid,
      gid:this.data.guserInfo.id,
      type:this.data.CODE.DELUNREADMSG,
      isgroup:this.data.isgroup
    };
    this.sendSocket(data)
    // 数据初始化
    this.setData({
      chatBoxKuang:'none',
      isgroup:0,
    })
  },
  //获取与目标用户的聊天记录
  setMsg:function(){
    let uid = this.data.uid,
        gid = this.data.guserInfo.id,
        isgroup = this.data.isgroup,
        data = {code:this.data.CODE.CLIENTGETMSG,uid,gid,isgroup};
    console.log(data)
    //开始发送
    this.sendSocket(data)
  },
  //发送文本消息
  send:function(){
    let textContent = this.data.textContent;
    let time = this.Format('yyyy-MM-dd hh:mm:ss');
    let data = {
      code:this.data.CODE.CLIENTMSG,
      uid:this.data.uid,
      gid:this.data.guserInfo.id,
      uname:this.data.username,
      textContent:textContent,
      type:this.data.CODE.TEXTMSG,
      create_date:time,
      isgroup:this.data.isgroup,
    };
    //消息过滤
    if  (!this.msgFilter(data)){
      console.log('数据不合法')
      return;
    }

    //开始发送
    this.sendSocket(data)

    let msgList = this.data.msgList
    msgList.push(data);
     
    //聊天历史追加消息
    this.setData({
      msgList:msgList
    })
    //清空输入框数据
    this.setData({
      textContent:''
    })
    //设置滚动条的位置
    this.setData({
      toView:`item${msgList.length-1}`
    })
    console.log(this.data.toView,456)
    //未读消息++1
    this.unreadMsg(data.uid,data.gid,this.data.CODE.ADDUNREADMSG,this.data.isgroup);
    // wx.sendSocketMessage({
    //   data:JSON.stringify(data)
    // });

  },




//发送图片
sendImg:function(){
  let that = this;
  console.log(545145145)
  wx.chooseImage({
    count:1,
    success (res) {
      const tempFilePaths = res.tempFilePaths
      wx.uploadFile({
        url: 'http://lc.gloryfs.com/upload', //仅为示例，非真实的接口地址
        filePath: tempFilePaths[0],
        name: 'file',
        success (res){
          let data = res.data,
              imgInfo = JSON.parse(data);
          if(imgInfo.code == 2000){
            let textContent = imgInfo.data,
                time = that.Format('yyyy-MM-dd hh:mm:ss'),
                data = {
                  code:that.data.CODE.CLIENTMSG,
                  uid:that.data.uid,
                  gid:that.data.guserInfo.id,
                  uname:that.data.username,
                  textContent:textContent,
                  type:that.data.CODE.IMGMSG,
                  create_date:time,
                  isgroup:that.data.isgroup,
                };
                console.log(textContent)
              //消息过滤
              if  (!that.msgFilter(data)){
                console.log('数据不合法')
                return;
              }
              //开始发送
              that.sendSocket(data)

              let msgList = that.data.msgList
              msgList.push(data);
              
              //聊天历史追加消息
              that.setData({
                msgList:msgList
              })
              //清空输入框数据
              that.setData({
                textContent:''
              })
              //设置滚动条的位置
              that.setData({
                toView:`item${msgList.length-1}`
              })
              console.log(that.data.toView,456)
              //未读消息++1
              that.unreadMsg(data.uid,data.gid,that.data.CODE.ADDUNREADMSG,that.data.isgroup);
          }else{
            wx.showToast({
              title: '发送失败',
              icon: 'none',
              duration: 1000
            })
          }
        }
      })
    },
    fail(){
      console.log('调取失败')
    }
  })
},












  //发送socket消息
  sendSocket:function(data){
    console.log(JSON.stringify(data))
    if(this.data.socketOpen ){
      wx.sendSocketMessage({
        data:JSON.stringify(data)
      });
    }else{
      console.log('socket链接断开');
    }
  },

  //获取当前时间
  // (new Date()).Format("yyyy-MM-dd hh:mm:ss.S") ==> 2006-07-02 08:09:04.423
  // (new Date()).Format("yyyy-M-d h:m:s.S") ==> 2006-7-2 8:9:4.18
  Format:function(fmt) { // author: meizz
      let that = (new Date());
      var o = {
        "M+": that.getMonth() + 1, // 月份
        "d+": that.getDate(), // 日
        "h+": that.getHours(), // 小时
        "m+": that.getMinutes(), // 分
        "s+": that.getSeconds(), // 秒
        "q+": Math.floor((that.getMonth() + 3) / 3), // 季度
        "S": that.getMilliseconds() // 毫秒
      };
      if (/(y+)/.test(fmt))
        fmt = fmt.replace(RegExp.$1, (that.getFullYear() + "").substr(4 - RegExp.$1.length));
      for (var k in o)
        if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
          return fmt;
  },


  //消息校验
  msgFilter(data){
    //数据过滤
		  let	text = data.textContent.toString().replace(/<.*?>|<\?.*?php|^\s*/imsg, "");
			if(!text){
        wx.showToast({
          title: '不能为空，特殊数据会被过滤！',
          icon: 'none',
          duration: 2000
        })
				return false;
      }
      return true;
  },
  //搜素列表展示
  userSearch:function(e){
    //保存搜索的值
    let text = e.detail.value,
        userSearchList = [],
        groupSearchList = [];
    this.setData({
      userSearchName: text
    })

    //搜索的数据为空时，
    if  (text == ''){
      this.setData({
        userList:this.data.userListAll,
        groupList:this.data.groupListAll
      })
      return;
    }

    text = text.replace(/(\()/g,'\\(')
    text = text.replace(/(\))/g,'\\)')
    console.log(text)
    let page =new RegExp(text)
    this.data.userList.forEach(function(item){
      if (page.test(item.name)) {
        userSearchList.push(item)
      }
    })
    this.data.groupList.forEach(function(item){
      if (page.test(item.name)) {
        groupSearchList.push(item)
      }
    })


    this.setData({
      userList:userSearchList,
      groupList:groupSearchList,
    })



  },
  // //服务器发送过来的未读消息数量
   unread_num:function (isgroup,Gid,unread=0){
     let userList = isgroup==1 ? this.data.groupListAll : this.data.userListAll;
     let that=this;
     userList.forEach(function(item){
       if (Gid == item.id){
          if (Gid != that.data.guserInfo.gid) {
            item.unread=unread;
          }else{
            item.unread=0;
          }
       }
       //同时修改，所有的用户列表，和当前展示的用户列表
       if(isgroup != 1){
          that.setData({
            userListAll:userList,
            userList:userList
          })
       }else{
          that.setData({
            groupListAll:userList,
            groupList:userList
          })
       }
       

     })
    }
  

  
})