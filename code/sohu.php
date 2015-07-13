<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class Sohu_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_SOHU);
		return new Sohu($app_cfg->get_app_key(), $app_cfg->get_app_secret(),
		$site->get("access_token"));
	}
}
class Sohu implements IOAuth{
	private $count = 30;
	private $since_id = 0;
	private $max_id = 0;
	private $pageflag = "before";
	private $page;
	
	private $appkey;
	private $appsecret;
	private $access_token;
	private $http;

	public function __construct($appkey, $appsecret, $access_token){
		$this->appkey 		= $appkey;
		$this->http			= new OAuthHttpClient($access_token, true);
		$this->appsecret 	= $appsecret;
		$this->access_token= $access_token;
	}
	public function format_user_info($user){
		return array("nick"=>$user['screen_name'],"name"=>$user['name'],"avatar"=>@$user['profile_image_url'],"uid"=>$user['id'],"site"=>Open_Api_Model::SITE_NAME_SOHU);
	}
	
	public function saveUser($userinfo, $access_token){
		return save_user(Open_Api_Model::SITE_NAME_SOHU, Open_Api_Model::SITE_URL_SOHU,
		@$userinfo['screen_name'], @$userinfo['profile_image_url'], "", @$userinfo['id'],
		"http://t.sohu.com/people?uid=".@$userinfo['id'], $userinfo,
		$access_token['access_token'], $access_token['expires_in'], $access_token["refresh_token"], "", "");
	}
	
	
	public function repost(Site_Account_Model $account, $message, $rid, $owner_id=null){
		$params = array(
				'status' 		=> urlencode($message),
		);
		$app_cfg = new App_Module();
		
		$url = "https://api.t.sohu.com/statuses/transmit/{$rid}.json";
		$rst = $this->post($url, $params, false);
		
		return $rst && @$rst['id'];
	}
	public function get_comments(Site_Account_Model $site, $rid){
		$params['count'] 	= $this->count;
		$params['page'] 	= 0;
		
		if( !$rid){
			return array();//不支持向上分页
		}
		
		$ret = $this->get("https://api.t.sohu.com/statuses/comments/".$rid.".json", $params);
		
		$this->page = $this->page+1;
		
		
		$format_rets  = array();
		foreach ($ret as $r){
			$format_rets[$r['id']] = $r;
		}
		//获取评论数与转发数
		try{
			$counts = $this->get("https://api.t.sohu.com/statuses/counts.json", array("ids"=>array_keys($format_rets)));
			foreach ((array)$counts as $c){
				$format_rets[strval($c['id'])]['comments_count'] = $c['comments_count'];
				$format_rets[strval($c['id'])]['transmit_count'] = $c['transmit_count'];
			}
		}catch(\Exception $e){}
		return $this->format_feed_comments($format_rets, $rid);
	}
	public function get_feeds($paging_flag = "before"){
		//max_id
		//最大微博id，传递这个id之后，会返回比指定微博更早的微博。在传递了max_id之后，cursor和page都将不再起作用，它具有最高优先级。
		//since_id
		//获取指定的文章id之后的更新，这是一个长整型变量。
		
		$params = $this->get_paging_params($paging_flag);
		
		if($this->pageflag=="before"){
			unset($params['since_id']);
		}else if($this->pageflag=="after"){
			unset($params['max_id']);
		}
		unset($params['pageflag']);

		if(@ !$params['since_id']){
			unset($params['since_id']);
		}
		if(@ !$params['max_id']){
			unset($params['max_id']);
		}
		
		$ret = $_ = array();

		try{
			$_ = $ret = $this->get("https://api.t.sohu.com/statuses/friends_timeline.json", $params);
		}catch (\Exception $e){
			return array();//忽略异常
		}
		
		if(@$_){
			$first 	= array_shift($_);
			$this->since_id = $first['id'];
			$last = array_pop($_);
			if($last){
				$this->max_id = $last['id'];
			}else{
				$this->max_id = $first['id'];
			}
		}
		
		
		$format_rets  = array();
		foreach ($ret as $r){
			$format_rets[$r['id']] = $r;
		}
		//获取评论数与转发数
		try{
			if($format_rets){
				$counts = $this->get("https://api.t.sohu.com/statuses/counts.json", array("ids"=>join(",",array_keys($format_rets))));
				foreach ((array)$counts as $c){
					$format_rets[strval($c['id'])]['comments_count'] = $c['comments_count'];
					$format_rets[strval($c['id'])]['transmit_count'] = $c['transmit_count'];
				}
			}
		}catch(\Exception $e){
			//忽略这步异常
		}
		return $this->format_feeds($format_rets);
	}
	
	public function get_paging_params($paging_flag = "before"){
		$params['count'] 	= $this->count;
		$params['since_id'] = $this->since_id;
		$params['max_id'] 	= $this->max_id;
		$params['pageflag'] = $paging_flag;
		return $params;
	}
	
	public function build_paging_params(YZE_Request $request){
		$this->count 	= $request->get_from_get("count");
		$this->since_id = $request->get_from_get("since_id");
		$this->max_id 	= $request->get_from_get("max_id");
		$this->pageflag	= $request->get_from_get("pageflag");
	}
	public function get_remaining_hits(Site_Account_Model $site){
		$rst = $this->get("https://api.t.sohu.com/account/rate_limit_status.json", array());

		return array('reset_time_in_seconds'=>$rst['reset_time_in_seconds'], 'remaining_hits'=>$rst['remaining_hits']);
	}
	
	/**
	 * @link http://open.t.sohu.com/en/%E5%8F%91%E5%B8%83%E4%B8%80%E6%9D%A1%E5%BE%AE%E5%8D%9A%28%E5%B8%A6%E5%9B%BE%29
	 * @link http://open.t.sohu.com/en/%E5%8F%91%E5%B8%83%E4%B8%80%E6%9D%A1%E5%BE%AE%E5%8D%9A%28%E4%B8%8D%E5%B8%A6%E5%9B%BE%29
	 *
	 */
	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		$message = cutMsg($message, $bottle_id);
		
		$params = array(
    	'status' 		=> urlencode($message),
		);
		$app_cfg = new App_Module();
		$multi = false;
		if(@$rid){
			$params['id'] 		= $rid;
			//如果有图片给出连接
			if($pic){
				$params['comment'] 	= $message.get_file_url($pic);
			}else{
				$params['comment'] 	= $message;
			}

			unset($params['status']);
			$url = "https://api.t.sohu.com/statuses/comment.json";
		}elseif(@$pic){
			$params['pic'] = "@".$pic;
			$url = "https://api.t.sohu.com/statuses/upload.json";
			$multi = true;
		}else{
			$url = "https://api.t.sohu.com/statuses/update.json";
		}
		$rst = $this->post($url, $params, $multi);

		return array(
			"created_at"=>strtotime($rst['created_at']), 
			"rid"		=>$rst['id'],
			"message_url"=>"http://t.sohu.com/m/".$rst['id'],  
			"user"=>array(
				"uid"	=>$rst['user']['id'],
				"name"	=>$rst['user']['screen_name'],
				"url"	=>$rst['user']['url'],
				"avatar"=>$rst['user']['profile_image_url'],
				"description"=>$rst['user']['description']
		));
	}


	function get($url, $params = array())
	{
		$params['format'] = "json";
		if($this->access_token){
			$params['access_token'] = $this->access_token;
		}
		return $this->toArray($this->http->get($url, $params));
	}
	function post($url, $params = array(),$multi=false)
	{
		$params['format'] = "json";
		if($this->access_token){
			$params['access_token'] = $this->access_token;
		}
		return $this->toArray($this->http->post($url, $params,$multi));
	}
	
	
	private function toArray($str){
		$ret = json_decode($str, true);//it's json
		if(json_last_error()!==JSON_ERROR_NONE){
			throw new Sns_Publish_Message_Exception($str);
		}
		$this->_check_ret($ret);
		return $ret;
	}
	
	private function _check_ret($rst)
	{
		if(!$rst || @!$rst['code']) return;

		if(@substr(@$rst['code'], 0, 3) == 401){
			throw new Token_Expire_Exception(@$rst['code'].",".@$rst['error']);
		}elseif(@substr(@$rst['code'], 0, 3) == 403){
			throw new Request_Reached_Limit_Exception(@$rst['code'].",".@$rst['error']);
		}else{
			throw new Sns_Publish_Message_Exception(@$rst['code'].",".@$rst['error']);
		}
	}

	public function get_message($rid, $owner_id=null){
		if(!$rid){
			return array();
		}
		$r = $this->get("https://api.t.sohu.com/statuses/show/".$rid.".json", array());

		return array(
			"created_at"=> strtotime($r['created_at']),
			"rid"		=> $r['id'],
			"message"	=> $r['text'],
			"comments-count"=> 0,
			"repost-count"	=> 0,
			"source"		=> $r['source'],
			"images"	=> array(
					array("normal"=>$r['small_pic'], "big"=>$r['middle_pic'])
				),
			"message_url"=> "http://t.sohu.com/m/".$rid,
			"user"=>array(
					"uid"	=> $r['user']['id'],
					"name"	=> $r['user']['screen_name'],
					"url"	=> $r['user']['url'],
					"avatar"=> $r['user']['profile_image_url'],
					"description"=> $r['user']['description']
			));
	}
	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}

		//总是取最新的get_count_per_page条
		$params['page'] 	=  0;
		$params['count'] 	= $this->get_count_per_page();

		$rst = $this->get("https://api.t.sohu.com/statuses/comments/".$rid.".json", $params);
		

		$replies = array();
		foreach ($rst as $r){
			$replies[] = array(
				"created_at"=>strtotime($r['created_at']), 
				"rid"		=>$r['id'],
				"message"	=>$r['text'],  
				"message_url"=>"http://t.sohu.com/m/".$rid,  
				"user"=>array(
					"uid"	=>$r['user']['id'],
					"name"	=>$r['user']['screen_name'],
					"url"	=>$r['user']['url'],
					"avatar"=>$r['user']['profile_image_url'],
					"description"=>$r['user']['description']
			));
		}

		return $replies;
	}
	
	public function get_count_per_page()
	{
		return 20;
	}
	

	public function get_User_Info($args){
		return $this->get("https://api.t.sohu.com/users/show.json");
	}
	
	private function format_feed_comments($feeds, $rid){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		
		foreach((array)$feeds as $feed){
			$format_feeds[$index]['user-head'] 		= $feed['user']['profile_image_url'];
			$format_feeds[$index]['feed-url'] 		= "http://t.sohu.com/m/".$feed['id'];
			$format_feeds[$index]['user-url'] 		= "http://t.sohu.com/u/".$feed['user']['id'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['id'];
			$format_feeds[$index]['user-name'] 	= $feed['user']['screen_name'];
			if(@$feed['small_pic']){
				$format_feeds[$index]['image'][] 		= array("normal"=>$feed['small_pic'], "big"=>$feed['middle_pic']);
			}
			$format_feeds[$index]['created-at']		= strtotime($feed['created_at']);
			$format_feeds[$index]['comments-count']	= 0;
			$format_feeds[$index]['repost-count']	= 0;
			$format_feeds[$index]['source']			= $feed['source'];
			$format_feeds[$index]['text']			= $feed['text'];
			$format_feeds[$index]['rid']			= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_SOHU;
			$index++;
		}
		
		//取原微博
		try{
			$message 		= $this->get_message($rid);
		}catch(\Exception $e){
			//ignore exception
			$message = null;
		}
		$source 		= array();
		if($message){
			$source['user-name'] 		= $message['user']['name'];
			$source['user-head'] 		= $message['user']['avatar'];
			$source['user-url'] 		= $message['user']['url'];
			$source['feed-url'] 		= $message['message_url'];
			$source['text'] 			= $message['message'];
			$source['user-id'] 			= $message['user']['uid'];
			$source['rid'] 				= $message['rid'];
			$source['created-at'] 		= $message['created_at'];
			$source['image'] 			= $message['images'];
			$source['comments-count'] 	= $message['comments-count'];
			$source['repost-count'] 	= $message['repost-count'];
			$source['source'] 			= $message['source'];
		}
		
		return array('source'=>$source, "comments"=>$format_feeds);
	}
	
	private function format_feeds($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		
		foreach((array)$feeds as $feed){
			$format_feeds[$index]['user-head'] 		= $feed['user']['profile_image_url'];
			$format_feeds[$index]['feed-url'] 		= "http://t.sohu.com/m/".$feed['id'];
			$format_feeds[$index]['user-url'] 		= "http://t.sohu.com/u/".$feed['user']['id'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['id'];
			$format_feeds[$index]['user-name'] 		= $feed['user']['screen_name'];
			if(@$feed['small_pic']){
				$format_feeds[$index]['image'][] 		= array("normal"=>$feed['small_pic'], "big"=>$feed['middle_pic']);
			}
			$format_feeds[$index]['created-at']		= strtotime($feed['created_at']);
			$format_feeds[$index]['comments-count']	= intval(@$feed['comments_count']);
			$format_feeds[$index]['repost-count']	= intval(@$feed['transmit_count']);
			$format_feeds[$index]['source']			= $feed['source'];
			$format_feeds[$index]['text']			= $feed['text'];
			$format_feeds[$index]['rid']				= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_SOHU;
		
			if(@$feed['in_reply_to_status_id']){
				$format_feeds[$index]['repost']['user-name'] 		= $feed['in_reply_to_screen_name'];
				$format_feeds[$index]['repost']['user-url'] 		= "http://t.sohu.com/u/".$feed['in_reply_to_user_id'];
				$format_feeds[$index]['repost']['feed-url'] 		= "http://t.sohu.com/m/".$feed['in_reply_to_status_id'];
				$format_feeds[$index]['repost']['user-head'] 		= "";
				$format_feeds[$index]['repost']['text'] 				= $feed['in_reply_to_status_text'];
				$format_feeds[$index]['repost']['user-id'] 			= $feed['in_reply_to_user_id'];
				/*if($feed->small_pic){
					$format_feeds[$index]['repost']['image'][] 		= array("normal"=>$feed->small_pic, "big"=>$feed->middle_pic);
				}
				*/
				$format_feeds[$index]['repost']['rid']				= $feed['in_reply_to_status_id'];
				$format_feeds[$index]['repost']['created-at']		= "";
				$format_feeds[$index]['repost']['comments-count']	= 0;
				$format_feeds[$index]['repost']['repost-count']		= 1;
				$format_feeds[$index]['repost']['source']			= '';
			}
			$index++;
		}
		return $format_feeds;
	}
	public function get_My_Friends(Site_Account_Model $account){
		$ret = $this->get("https://api.t.sohu.com/statuses/friends/".$account->get("site_uid").".json", array());
		$users  = array();
		foreach ($ret as $r){
			$users[] = array(
				"uid"		=> $r['id'],
				"name"		=> $r['screen_name'],
				"url"		=> $r['url'],
				"avatar"		=> $r['profile_image_url'],
				"desc"		=> $r['description'],
				"status"		=> $r['status']['text'],
				"status_time"		=> strtotime($r['status']['created_at']),
				"location"		=> $r['location'],
				"sex"		=>$r['gender'] ? "男" : "女"
			);
		}

		return $users;
	}
}


?>