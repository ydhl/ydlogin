<?php
include_once 'config.php';

class Kaixin extends YDOAuth{
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
		        json_decode($this->http->get('https://api.kaixin001.com/users/me.json', 
		                array(
		                        "access_token"=>$oAuthInfo['access_token'])), true)
        );
	}
	private function format_user_info($user){
	    if( ! @$user['uid']){
	        $this->error = $user;
	        return array();
	    }
		$info = new YDLoginUser();
	    $info->avatar      = $user['avatar'];
	    $info->displayName = $user['name'];
	    $info->fromSite    = "kaixin";
	    $info->openid      = $user['uid'];
	    $info->origData    = $user;
	    return $info;
	}
	
	public function getOauthAccessTokenURL(){
	    return "https://api.kaixin001.com/oauth2/access_token";
	}
	public function getOauthAuthorizeURL(){
	    return "http://api.kaixin001.com/oauth2/authorize";
	}
	public function getOauthScope(){
	    return "";
	}
}

$client = new Kaixin(YDLOGIN_KAIXIN_APPKEY, YDLOGIN_KAIXIN_SECRET);
$client->doLogin("http://".rtrim($_SERVER['SERVER_NAME'],"/").$_SERVER['PHP_SELF']);
?>