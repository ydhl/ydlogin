<?php
/**
 * @author leeboo from ydhl
 */
$cwd = dirname ( __FILE__ );
include_once "$cwd/YDOAuth.php";
include_once "$cwd/OAuthHttpClient.class.php";
include_once "$cwd/OAuthUtil.class.php";
include_once "$cwd/ydhl_oauthclient.php";
include_once "$cwd/ydhooks.php";

/**
 * hook实现文件包含路径
 */
define("YDLOGIN_HOOK_DIR",          "");
/**
 * 网站域名
 * @var unknown
 */
define("YDLOGIN_SITE_URL",          "");
define("YDLOGIN_DOUBAN_APPKEN",     "065363db4e9fac9912fa2b91ebdf05cb");
define("YDLOGIN_DOUBAN_SECRET",     "6c82e2707ba32be8");
define("YDLOGIN_DOUBAN_SITE_URL",          "6c82e2707ba32be8");

if(YDLOGIN_HOOK_DIR){
    YDHook::include_files(YDLOGIN_HOOK_DIR);
}