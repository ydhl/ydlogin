<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class JLPPlugin_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config($site->get("site_name"));
		if(!$app_cfg) $app_cfg = new Open_Api_Model();
		return new JLPPlugin($app_cfg, $site->get("access_token"));
	}
}

class JLPPlugin implements IOAuth{
	private $client_id 		= ""; //api key
	private $client_secret 	= ""; //app secret
	private $redirect_uri 	= "";//回调地址，所在域名必须与开发者注册应用时所提供的网站根域名列表或应用的站点地址（如果根域名列表没填写）的域名相匹配
	private $access_token;
	private $http;
	/**
	 * 
	 * @var Open_Api_Model
	 */
	private $open_site;


	private $since_id = 0;//若指定此参数，则返回ID比since_id大的微博（即比since_id时间晚的微博），默认为0。
	private $max_id = 0;//若指定此参数，则返回ID小于或等于max_id的微博，默认为0
	private $count = 10;//单页返回的记录条数，默认为50。
	private $pageflag = "before";
	
	public function __construct(Open_Api_Model $open_site, $access_token){
		$this->http 				= new OAuthHttpClient();
		$this->open_site		= $open_site;
		$this->client_id 			= $open_site->get_app_key();
		$this->client_secret 	= $open_site->get_app_secret();
		$this->access_token 	= $access_token;
	}

	public function get_User_Info($args){
		//print_r($this->open_site->get("userinfo_url"));
		//echo $this->get($this->open_site->get("userinfo_url"), $params);die;
		return $this->toArray($this->get($this->open_site->get("userinfo_url"), array()));
	}
	public function format_user_info($user){
		return array("nick"=>$user['nick'],"name"=>$user['name'],"avatar"=>$user['avatar'],"uid"=>$user['uid'],"site"=>$this->open_site->get("open_name"));
	}
	public function saveUser($userinfo, $access_token){

		return save_user($this->open_site->get("open_name"), $this->open_site->get("site_url"),
		@$userinfo['nick'], @$userinfo['avatar'], "", @$userinfo['uid'],
		@$userinfo['home'], $userinfo,
		$access_token['access_token'], $access_token['expires_in'], $access_token['refresh_token'],"");
	}

	public function repost(Site_Account_Model $account, $msg, $rid, $owner_id=null){
		return  false;
	}

	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}
	
		$params['rid'] 		= $rid;
	
		return $this->toArray($this->get($this->open_site->get("replylist_url"), $params));
	}
	
	public function get_feeds($paging_flag = "before"){
		$params = $this->get_paging_params($paging_flag);
		

		if($this->pageflag=="before"){
			unset($params['since_id']);
		}else if($this->pageflag=="after"){
			unset($params['max_id']);
		}
		
		if(@ !$params['since_id']){
			unset($params['since_id']);
		}
		if(@ !$params['max_id']){
			unset($params['max_id']);
		}
		unset($params['pageflag']);
		
		$_  = $rst = $this->toArray($this->get($this->open_site->get("update_url"), $params));
		
		if(@$_){
			$last = array_pop($_);
			$this->max_id = $last['rid'];
			$first = array_shift($_);
			if($first){
				$this->since_id = $first['rid'];
			}else{
				$this->since_id = $last['rid'];
			}
		}
		return $this->format_feeds($rst);
	}
	
	public function get_remaining_hits(Site_Account_Model $site){
		return false;
	}


	/**
	 *
	 * @param access_token
	 * @param status
	 * @param pic optional
	 *
	 * @link http://open.weibo.com/wiki/2/statuses/upload
	 * @link http://open.weibo.com/wiki/2/statuses/update
	 *
	 */
	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		$params = array(
				'msg' 		=> $message
		);
		if($rid){
			$params['rid'] = $rid;
			$api_url = $this->open_site->get("reply_url");
		}else{
			$api_url = $this->open_site->get("post_url");
		}
		
		$message = cutMsg($message, $bottle_id);
		
		
		
		if($pic){
			$params['pic'] = '@'.$pic;
		}

		$app_cfg = new App_Module();
		
		return $this->toArray($this->post($api_url, $params , $pic ? true : false));
	}
	public function get_comments(Site_Account_Model $site, $rid){
		if(!$rid){
			return array();
		}
		$params['rid'] 			= $rid;
		$source = $this->format_feeds(array($this->get_message($rid)));
		return array("comments"=>$this->format_feeds($this->toArray($this->get($this->open_site->get("replylist_url"), $params))), 
				"source"=>$source[0]
				);
	}
	
	public function get_message($rid, $owner_id=null){

		if(!$rid){
			return array();
		}
		$params['rid'] 			= $rid;
		return $this->toArray($this->get($this->open_site->get("msg_url") , $params));
	}
	//------------------ 分页处理
	
	public function get_paging_params($paging_flag = "before"){
		$this->pageflag		= $paging_flag;
		$params['since_id'] = $this->since_id;
		$params['max_id'] 	= $this->max_id;
		$params['count'] 	= $this->count;
		$params['pageflag'] 	= $this->pageflag;
	
		return $params;
	}
	
	public function build_paging_params(YZE_Request $request){
		$this->since_id = $request->get_from_get("since_id");
		$this->max_id 	= $request->get_from_get("max_id");
		$this->count 	= $request->get_from_get("count");
	
		$this->pageflag = $request->get_from_get("pageflag");
	}
	public function get_count_per_page()
	{
		return 100;
	}
	

	private function get($url, $params){
		$params['access_token'] = $this->access_token;
		//print_r($params);die;
		return $this->http->get($url, $params);
	}
	
	private function post($url, $params, $multi=false){
		$params['access_token'] = $this->access_token;
		return $this->http->post($url, $params, $multi);
	}
	private function format_feeds($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		foreach((array)$feeds as $feed){
			$format_feeds[$index]['user-head'] 		= $feed['user']['avatar'];
			$format_feeds[$index]['user-url'] 		= $feed['user']['url'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['uid'];
			$format_feeds[$index]['feed-url'] 		= $feed['message_url'];
			$format_feeds[$index]['user-name'] 	= $feed['user']['name'];
			$format_feeds[$index]['can_not_repost']= true;
			/* if(@$feed['image']){
				$format_feeds[$index]['image'][] 		= array("normal" => @$feed['normal'], "big" => @$feed['big']);
			} */
			$format_feeds[$index]['created-at']			= strtotime(@$feed['created_at']);
			$format_feeds[$index]['comments-count']	= @$feed['comments-count'];
			$format_feeds[$index]['repost-count']		=0;
			$format_feeds[$index]['source']			= $this->open_site->get("open_name");
			$format_feeds[$index]['text']			= $feed['message'];
			$format_feeds[$index]['rid']				= $feed['rid'];
			$format_feeds[$index]['site']			= $this->open_site->get("open_name");
	
			$index++;
		}
		return $format_feeds;
	}

	private function toArray($str){
		$ret = json_decode($str, true);//it's json
		if(json_last_error()!==JSON_ERROR_NONE){
			throw new Sns_Publish_Message_Exception($str);
		}
		$this->_check_ret($ret);
		return $ret['data'];
	}
	
	private function _check_ret($rst)
	{
		if(!@$rst['error_code']) return;
		
		if(@$rst['error_code']==100){
			throw new Token_Expire_Exception($rst['error_code'].",".@$rst['error']);
		}elseif(@$rst['error_code'] == 200){
			throw new Has_Deleted_Exception(@$rst['error_code'].",".@$rst['error']);
		}else{
			throw new Sns_Publish_Message_Exception($rst['error_code'].",".@$rst['error']);
		}
	}
	public function get_My_Friends(Site_Account_Model $account){
	
	}
}