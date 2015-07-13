<?php
/**
 * YDHL OAuth2 client 负责与OAuth服务器之间的请求交流流程，具体的请求使用OAuthHttpClient
 *
 * @author leeboo
 *
 */
class YDHL_OAuthClient{
	private $client_id 		= "";
	private $client_secret 	= "";
	private $redirect_uri 	= "";
	/**
	 * 
	 * @var OAuthHttpClient
	 */
	private $http;

	public $authorizeURL = "";
	public $accessTokenURL = "";


	public function __construct($client_id, $client_secret, $redirect_uri){
		$this->http 			= new OAuthHttpClient();
		$this->client_id 		= $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_uri 	= $redirect_uri;
	}

	public function setResponseFormat($format){
		$this->http->format = $format;
	}
	public function get_Authorize_URL($response_type, $scope=null, $state=null, $display=null){
		$params = array(
				'client_id' 	=> $this->client_id,
				'response_type' => $response_type,
				'redirect_uri' 	=> $this->redirect_uri,
		);
		if(!empty($scope))	$params['scope'] = $scope;
		if(!empty($state))	$params['state'] = $state;
		if(!empty($display))	$params['display'] = $display;
		$query = OAuthUtil::build_http_query($params);
		return strrpos($this->authorizeURL, "?")!==FALSE ? $this->authorizeURL."&{$query}"  : $this->authorizeURL."?{$query}";
	}

	public function get_Access_Token_From_Code($code){
		$params = array(
				'grant_type' 	=> "authorization_code",
				'code' 			=> $code,
				'client_id' 		=> $this->client_id,
				'client_secret' 	=> $this->client_secret,
				'redirect_uri' 	=> $this->redirect_uri,
				'token_type'	=>"mac"//支持OAuth mac token的
		);
		return $this->http->post($this->accessTokenURL,$params);
	}

	public function get_Access_Token_From_Refresh_Token($refresh_token){
		$params = array(
				'grant_type' 	=> "refresh_token",
				'client_id' 		=> $this->client_id,
				'client_secret' 	=> $this->client_secret,
				'refresh_token' 	=> $refresh_token,
		);
		return $this->http->post($this->accessTokenURL,$params);
	}
}
?>