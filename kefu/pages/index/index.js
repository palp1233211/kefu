//index.js
//获取应用实例
const app = getApp()

Page({
  data: {
    motto: 'Hello World',
    userInfo: {},
    hasUserInfo: false,
    canIUse: wx.canIUse('button.open-type.getUserInfo')
  },
  //事件处理函数
  bindViewTap: function() {
    let that = this
    wx.login({
      success (res) {
        if (res.code) {
          //发起网络请求
          wx.request({
            url: 'http://lc.gloryfs.com:8888/web/login',
            method:'post',
            data: {
              'code':42,
              'wxCode': res.code,
              'name':that.data.userInfo.nickName,
              'avatarUrl':that.data.userInfo.avatarUrl,
            },
            header: {
              'content-type': 'application/x-www-form-urlencoded' // 默认值
            },
            success:function(e){
              console.log(e)
              if(e.data.code == 1){
                wx.setStorage({
                  key:"name",
                  data:e.data.data.name
                })
                wx.setStorage({
                  key:"id",
                  data:e.data.data.Userid
                })
                wx.setStorage({
                  key:"identity",
                  data:e.data.data.identity
                })
                wx.setStorage({
                  key:"username",
                  data:e.data.data.username
                })
                wx.setStorage({
                  key:"token",
                  data:e.data.data.token
                })
                
                wx.redirectTo({
                  url: '/pages/userList/userList'
                })
              }

              
            }
          })
        } else {
          console.log('登录失败！' + res.errMsg)
        }
      }
    })
    
  },
  onLoad: function () {
    if (app.globalData.userInfo) {
      this.setData({
        userInfo: app.globalData.userInfo,
        hasUserInfo: true
      })
    } else if (this.data.canIUse){
      // 由于 getUserInfo 是网络请求，可能会在 Page.onLoad 之后才返回
      // 所以此处加入 callback 以防止这种情况
      app.userInfoReadyCallback = res => {
        this.setData({
          userInfo: res.userInfo,
          hasUserInfo: true
        })
      }
    } else {
      // 在没有 open-type=getUserInfo 版本的兼容处理
      wx.getUserInfo({
        success: res => {
          app.globalData.userInfo = res.userInfo
          this.setData({
            userInfo: res.userInfo,
            hasUserInfo: true
          })
        }
      })
    }
    //用户登录
    this.bindViewTap()



  },
  getUserInfo: function(e) {
    console.log(e)
    app.globalData.userInfo = e.detail.userInfo
    this.setData({
      userInfo: e.detail.userInfo,
      hasUserInfo: true
    })
  }
})
