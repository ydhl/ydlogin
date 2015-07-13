<?php
abstract class YDOAuth{
    protected $appkey;
    protected $appsecret;
    protected $http;
    protected $error;
    
    public function __construct($appkey, $appsecret){
        $this->appkey 		= $appkey;
        $this->appsecret 	= $appsecret;
        $this->http			= new OAuthHttpClient();
    }
    
	/**
	 * 取得用户信息，出错返回异常
	 * 
	 * @param  $args
	 * 
	 * @return stdClass
	 */
	public abstract function get_User_Info($args);
	public abstract function getOauthAccessTokenURL();
	public abstract function getOauthAuthorizeURL();
	public abstract function getOauthScope();
	
	
	public function doLogin($backurl){
	    $auth = new YDHL_OAuthClient($this->appkey, $this->appsecret, $backurl);
	    $auth->accessTokenURL  = $this->getOauthAccessTokenURL();
	    $auth->authorizeURL    = $this->getOauthAuthorizeURL();
	    
	    $auth_code 	= @$_GET["code"];
	    $error     	= @$_GET["error"];
	    if($error){
	        YDHook::do_hook(YDHook::HOOK_LOGIN_FAIL, array());
	        die;
	    }
	    
	    //第一步申请code
	    if( ! $auth_code){
	        ob_clean();
	        header("Location: ".$auth->get_Authorize_URL("code", $this->getOauthScope()));
	        die;
	    }
	    
	    //第二步申请access token
	    $str = $auth->get_Access_Token_From_Code($auth_code);
	    
	    if(!($resp = json_decode($str, true))){
	        $resp = array();
	        parse_str($str, $resp);//白痴 QQ，这里返回string，其它地方又返回json string
	    }
	    
	    if(@$resp['access_token']){
	        $user = $this->get_user_info($resp);
	        if($user){
                YDHook::do_hook(YDHook::HOOK_LOGIN_SUCCESS, $this->error);
	        }else{
	            YDHook::do_hook(YDHook::HOOK_LOGIN_FAIL, $resp);
	        }
	    }else{
	       YDHook::do_hook(YDHook::HOOK_LOGIN_FAIL, $resp);
	    }
	}
}
?>