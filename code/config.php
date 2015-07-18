<?php
/**
 * @author leeboo@ydhl
 */

$cwd = dirname ( __FILE__ );
include_once "$cwd/user.class.php";
include_once "$cwd/YDOAuth.php";
include_once "$cwd/OAuthHttpClient.class.php";
include_once "$cwd/OAuthUtil.class.php";
include_once "$cwd/ydhooks.php";

session_start();

/**
 * hook实现文件包含路径
 */
define("YDLOGIN_HOOK_DIR",          "");
/**
 * 网站域名
 * @var unknown
 */
define("YDLOGIN_SITE_URL",          "");

//豆瓣appkey http://developers.douban.com/
//回调地址填写你域名下的douban.php
//API权限选择豆瓣公共
define("YDLOGIN_DOUBAN_APPKEY",     "065363db4e9fac9912fa2b91ebdf05cb");
define("YDLOGIN_DOUBAN_SECRET",     "6c82e2707ba32be8");

//微信appkey http://open.weixin.qq.com/
//回调域名填写你的域名
define("YDLOGIN_WEIXIN_APPKEY",     "wx0e342b4f06f8eaab");
define("YDLOGIN_WEIXIN_SECRET",     "61bf850cd5d9f03a11466453cf0a5474");

//新浪微博appkey http://open.weibo.com/
//回调域名填写你的域名
define("YDLOGIN_SINA_APPKEY",     "3258380496");
define("YDLOGIN_SINA_SECRET",     "ccaf6e9f554602f475661b96763e0305");

//搜狐微博appkey http://open.sohu.com/
//回调域名填写你的域名下的sohu.php
define("YDLOGIN_SOHU_APPKEY",     "ed54915b6935466f9169504426fa1ae4");
define("YDLOGIN_SOHU_SECRET",     "b7f854c3d2c66432405ceaffeadca9f8");

//qqappkey http://connect.qq.com/
//回调域名填写你域名下的qq.php
define("YDLOGIN_QQ_APPKEY",     "101231167");//qq app id
define("YDLOGIN_QQ_SECRET",     "58dc73d71c54f06e239a1b9c1f07d5e6");//qq app key

//人人appkey http://open.renren.com/
define("YDLOGIN_RENREN_APPKEY",     "6f98112caeb5424090085953f112c9cc");
define("YDLOGIN_RENREN_SECRET",     "d9f1a402ea97493a85eb90487379aded");

//kaixin appkey http://open.kaixin001.com
define("YDLOGIN_KAIXIN_APPKEY",     "8722383312670374bb11499d75b04857");
define("YDLOGIN_KAIXIN_SECRET",     "394db8e2371cbc690785fab9d7c9c613");

if(YDLOGIN_HOOK_DIR){
    YDHook::include_files(YDLOGIN_HOOK_DIR);
}