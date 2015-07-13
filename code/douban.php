<?php
include_once 'config.php';

class Douban extends YDOAuth{
	public function get_User_Info($args){
		return $this->format_user_info(
		        $this->http->get('https://api.douban.com/v2/user/~me')
        );
	}
	private function format_user_info($user){
	    if( ! @$user['uid']){
	        $this->error = $user;
	        return array();
	    }
		return array(
		        "nick"    =>$user['uid'],
		        "name"    =>$user['name'],
		        "avatar"  =>$user['avatar'],
		        "uid"     =>$user['id'],
		        "site"    =>"douban.com",
		        "orignal" =>$user);
	}
	
	public function getOauthAccessTokenURL(){
	    return "https://www.douban.com/service/auth2/token";
	}
	public function getOauthAuthorizeURL(){
	    return "https://www.douban.com/service/auth2/auth";
	}
	public function getOauthScope(){
	    return "douban_basic_common";
	}
}

$douban = new Douban(YDLOGIN_DOUBAN_APPKEN, YDLOGIN_DOUBAN_SECRET);
$douban->doLogin("http://".rtrim($_SERVER['SERVER_NAME'],"/").$_SERVER['PHP_SELF']);
?>