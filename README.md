# ydlogin

只需配置一下，便可支持如下网站的登录及消息分享功能；支持

1. 豆瓣
2. 开心网
3. QQ
5. 人人网
6. 新浪网
7. 搜狐网
8. 网易
9. 微信

## 用法

1. 到支持的网站申请appkey,并填写响应的回调地址
    1. 豆瓣：
        1. http://developers.douban.com/
        2. API权限选择豆瓣公共
        3. 回调地址填写你域名下的douban.php
    2. 开心网
    3. QQ
      1. http://connect.qq.com/
    5. 人人网
    6. 新浪网
    7. 搜狐网
    8. 网易
    9. 微信
    	1. https://open.weixin.qq.com/
2. 修改config.php进行配置
3. 实现YDLogin::HOOK_XXXX
    1. YDLogin::HOOK_LOGIN 社会化登录hook，hook参数为登录成功后的用户对象
    2. YDLogin::HOOK_SHARE 分享功能，
    

-----
易点互联 软件开发商 专注实现  http://yidianhulian.com 

贵阳