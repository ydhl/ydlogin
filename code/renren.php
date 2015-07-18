<?php
include_once 'config.php';

class Renren extends YDOAuth{
    public function formatAccessToken($access_token){
        $info = json_decode($access_token, true);
        if( @ $info['access_token']){
            return $info;
        }
        $this->error = $info['error'];
        return array();
    }
	public function get_oAuthUser_Info($oAuthInfo){
		return $this->format_user_info(
		        json_decode($this->http->get('https://api.renren.com/v2/user/get', 
		                array(
		                        "access_token"=>$oAuthInfo['access_token'])), true)
        );
	}
	private function format_user_info($user){
	    if( ! @$user['id']){
	        $this->error = $user['error'];
	        return array();
	    }
	    $info = new YDLoginUser();
	    $info->avatar      = $user['avatar'];
	    $info->displayName = $user['name'];
	    $info->fromSite    = "renren";
	    $info->openid      = $user['id'];
	    $info->origData    = $user;
	    return $info;
	}
	
	public function getOauthAccessTokenURL(){
	    return "https://graph.renren.com/oauth/token";
	}
	public function getOauthAuthorizeURL(){
	    return "https://graph.renren.com/oauth/authorize";
	}
	public function getOauthScope(){
	    return "";
	}
}

$client = new Renren(YDLOGIN_RENREN_APPKEY, YDLOGIN_RENREN_SECRET);
$client->doLogin("http://".rtrim($_SERVER['SERVER_NAME'],"/").$_SERVER['PHP_SELF']);
?>