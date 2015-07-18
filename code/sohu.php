<?php
include_once 'config.php';

class Sohu extends YDOAuth{
    public function formatAccessToken($access_token){
        $info = json_decode($access_token, true);
        if( @ $info['access_token']){
            return $info;
        }
        $this->error = $info['message'];
        return array();
    }
	public function get_oAuthUser_Info($oAuthInfo){
		return $this->format_user_info(
		        json_decode($this->http->get('https://api.sohu.com/rest/i/prv/1/user/get-basic-info', 
		                array("access_token"=>$access_token)),true)
        );
	}
	private function format_user_info($user){
	    if( @$user['status'] != 30000){
	        $this->error = $user['message'];
	        return array();
	    }
	    $info = new YDLoginUser();
	    $info->avatar      = $user['data']['icon'];
	    $info->displayName = $user['data']['nick'];
	    $info->fromSite    = "sohu";
	    $info->openid      = $user['data']['open_id'];
	    $info->origData    = $user['data'];
	    return $info;
	}
	
	public function getOauthAccessTokenURL(){
	    return "https://api.sohu.com/oauth2/token";
	}
	public function getOauthAuthorizeURL(){
	    return "https://api.sohu.com/oauth2/authorize";
	}
	public function getOauthScope(){
	    return "";
	}
}

$client = new Sohu(YDLOGIN_SOHU_APPKEY, YDLOGIN_SOHU_SECRET);
$client->doLogin("http://".rtrim($_SERVER['SERVER_NAME'],"/").$_SERVER['PHP_SELF']);
?>