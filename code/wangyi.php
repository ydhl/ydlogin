<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class Wangyi_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_WANGYI);
		return new Wangyi($app_cfg->get_app_key(), $app_cfg->get_app_secret(), $site->get("access_token"));
	}
}
class Wangyi implements IOAuth{
	private $count = 30;
	private $since_id = 0;
	private $max_id = 0;
	private $pageflag = "before";
	private $appkey;
	private $appsecret;
	private $access_token;
	private $http;
	
	public function __construct($appkey, $appsecret, $access_token){
		$this->appkey 		= $appkey;
		$this->appsecret 	= $appsecret;
		$this->access_token = $access_token;
		$this->http			= new OAuthHttpClient();
	}
	
	public function repost(Site_Account_Model $account, $message, $rid, $owner_id=null){
		$params = array(
				'status' 	=> $message,
				'id' 		=> $rid,
		);
		
		$url = "https://api.t.163.com/statuses/retweet/{$rid}.json";
		$rst = $this->post($url, $params, false);
		
		return $rst && @$rst['retweeted_status']['id'];
		
	}
	public function format_user_info($user){
		return array("name"=>$user['name'],"nick"=>$user['screen_name'],"avatar"=>@$user['profile_image_url'],"uid"=>$user['id'],"site"=>Open_Api_Model::SITE_NAME_WANGYI);
	}
	public function saveUser($userinfo, $access_token){
		return save_user(Open_Api_Model::SITE_NAME_WANGYI, Open_Api_Model::SITE_URL_WANGYI,
		@$userinfo['name'], @$userinfo['profile_image_url'], "", @$userinfo['id'],
		"http://t.163.com/".@$userinfo['screen_name'], $userinfo,
		$access_token['access_token'], $access_token['expires_in'], $access_token['refresh_token'], "", "");
	}
	
	public function get_comments(Site_Account_Model $site, $rid){
		if(!$rid){
			return array();
		}
		
		$ret = $this->get("https://api.t.163.com/statuses/comments/{$rid}.json", array());

		return $this->format_feed_comments($ret, $rid);
	}
	public function get_feeds($paging_flag = "before"){
		//http://open.t.163.com/wiki/index.php?title=%E8%8E%B7%E5%8F%96%E5%BD%93%E5%89%8D%E7%99%BB%E5%BD%95%E7%94%A8%E6%88%B7%E5%85%B3%E6%B3%A8%E7%94%A8%E6%88%B7%E7%9A%84%E6%9C%80%E6%96%B0%E5%BE%AE%E5%8D%9A%E5%88%97%E8%A1%A8(statuses/home_timeline)
		//since_id	 可选参数	 该参数需传cursor_id,返回此条索引之前发的微博列表,不包含此条 before
		//max_id	 可选参数	 该参数需传cursor_id,返回此条索引之后发的微博列表,包含此条 after
		
		$params = $this->get_paging_params($paging_flag);
		
		if($this->pageflag=="after"){
			unset($params['since_id']);
		}else if($this->pageflag=="before"){
			unset($params['max_id']);
		}
		unset($params['pageflag']);
		if(@ !$params['since_id']){
			unset($params['since_id']);
		}
		if(@ !$params['max_id']){
			unset($params['max_id']);
		}

		try{
			$_ = $ret = $this->get("https://api.t.163.com/statuses/home_timeline.json", $params);
		}catch(\Exception $e){
			return array();//not care exception
		}
		
		////since_id	 可选参数	 该参数需传cursor_id,返回此条索引之前发的微博列表,不包含此条
		//所包含的这条之前已经取出来了，所以这里又把它去掉
		if($this->pageflag=="after"){
			array_shift($ret);//discard
			array_shift($_);//discard
		}
		
		if(@$_){
			$last = array_pop($_);
			$this->since_id = $last['cursor_id'];
			$first = array_shift($_);
			if($first){
				$this->max_id = $first['cursor_id'];
			}else{
				$this->max_id = $last['cursor_id'];
			}
		}
		return $this->format_feeds($ret);
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
	
	
	//http://open.t.163.com/wiki/index.php?title=%E8%8E%B7%E5%8F%96%E5%BD%93%E5%89%8D%E7%94%A8%E6%88%B7API%E8%AE%BF%E9%97%AE%E9%A2%91%E7%8E%87%E9%99%90%E5%88%B6(account/rate_limit_status)
	public function get_remaining_hits(Site_Account_Model $site){
		$params = array();
		$rst = $this->get("https://api.t.163.com/account/rate_limit_status.json", $params);

		return array('reset_time_in_seconds'=>$rst['reset_in_seconds'],
			'remaining_hits'=>$rst['remaining_hits'],
			'reset_time'	=>$rst['reset_time']);
	}
	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		if($pic){
			$message 	= get_file_url($pic).$message;
		}
		
		$message = cutMsg($message, $bottle_id);
		
		$params = array(
    			'status' 		=> $message,
		);
		
		$app_cfg = new App_Module();
		$multi = false;
		if(@$rid){
			$params['id'] 		= $rid;
			$url = "https://api.t.163.com/statuses/reply.json";
		/*} elseif(@$pic){
			//163图片不能外链，所以先不把图片传过去
			$params['pic'] = '@'.$pic;
			$url = "https://api.t.163.com/statuses/upload.json";
			$multi = true; */
		}else{
			$url = "https://api.t.163.com/statuses/update.json";
		}
		$rst = $this->post($url, $params, $multi);
		
		//163图片不能外链，所以先不把图片传过去
	 	/* if($pic && @$rst['upload_image_url'] && !$rid){//带图新微博
			$params['status'] .= $rst['upload_image_url'];
			return $this->send_msg($site, $params['status'], null, null, $bottle_id);
		}  */
		
		return array(
			"created_at"=>strtotime($rst['created_at']), 
			"rid"		=>$rst['id'],
			"message_url"=>rtrim($site->get("user_home"), "/")."/status/".$rst['id'],  
			"user"=>array(
				"uid"	=>$rst['user']['id'],
				"name"	=>$rst['user']['screen_name'],
				"url"	=>$rst['user']['url'],
				"avatar"=>$rst['user']['profile_image_url'],
				"description"=>$rst['user']['description']
		));
	}


	public function get_message($rid, $owner_id=null){
		if(!$rid){
			return array();
		}
		$r = $this->get("https://api.t.163.com/statuses/show/".$rid.".json", array());

		$ret = array(
			"created_at"=> strtotime($r['created_at']),
			"rid"		=> $r['id'],
			"message"	=> $r['text'],
			"images"	=> array(),
			"comments-count"=> 0,
			"repost-count"	=> $r['retweet_count'],
			"source"		=> $r['source'],
			"message_url"=> "http://t.163.com/".$r['user']['screen_name']."/status/".$rid,
			"user"=>array(
					"uid"	=> $r['user']['id'],
					"name"	=> $r['user']['screen_name'],
					"url"	=> $r['user']['url'] ? $r['user']['url'] : "http://t.163.com/".$r['user']['screen_name'],
					"avatar"=> $r['user']['profile_image_url'],
					"description"=> $r['user']['description']
			));
		
		if(preg_match("/(?P<img>http:\/\/126\.fm\/.+)$/", $ret['message'], $matched)){
			$img = $matched['img'];
			$ret['images'][] 	= array(
					"normal" => "http://timge8.126.net/image?w=140&url=".urlencode($img),
					"big"=>"http://timge8.126.net/image?w=440&url=".urlencode($img));
			$ret['message']			= strtr($ret['message'], array($img=>""));
		}
		return $ret;
	}
	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}
		$params = array();
		
		$rst = $this->get("https://api.t.163.com/statuses/comments/".$rid.".json", $params);

		$replies = array();
		foreach ($rst as $r){
			$replies[] = array(
				"created_at"=> strtotime($r['created_at']), 
				"rid"		=> $r['id'],
				"message"	=> $r['text'],  
				"message_url"=> rtrim($site->get_site()->get("user_home"), "/")."/status/".$rid,
				"user"=>array(
					"uid"	=> $r['user']['id'],
					"name"	=> $r['user']['screen_name'],
					"url"	=> $r['user']['url'] ? $r['user']['url'] : "http://t.163.com/".$r['user']['screen_name'],
					"avatar"=> $r['user']['profile_image_url'],
					"description"=> $r['user']['description']
			));
		}


		return $replies;
	}
	public function get_count_per_page()
	{
		return 100;
	}
	
	public function get_User_Info($args){
		return $this->get('https://api.t.163.com/users/show.json');
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
		if(!$rst || !@$rst['error_code']){
			return;
		}
		if(@substr($rst['error_code'], 0, 3) == 401){
			throw new Token_Expire_Exception($rst['error_code'].",".@$rst['error']);
		}elseif(@substr($rst['error_code'], 0, 3) == 404){
			throw new Has_Deleted_Exception($rst['error_code'].",".@$rst['error']);
		}elseif(@substr($rst['error_code'], 0, 3) == 403){
			throw new Request_Reached_Limit_Exception($rst['error_code'].",".@$rst['error']);
		}else{
			throw new Sns_Publish_Message_Exception($rst['error_code'].",".@$rst['error']);
		}
	}
	private function format_feed_comments($feeds, $rid){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		foreach((array)$feeds as $feed){
		
			//提取出图片
			if(preg_match("/(?P<img>http:\/\/126\.fm\/.+)$/", $feed['text'], $matched)){
				$img = $matched['img'];
				$format_feeds[$index]['image'][] 	= array(
						"normal" => "http://oimagea2.ydstatic.com/image?w=140&url=".urlencode($img),
						"big"=>"http://oimagea2.ydstatic.com/image?w=440&url=".urlencode($img));
				$format_feeds[$index]['text']			= strtr($feed['text'], array($img=>""));
			}else{
				$format_feeds[$index]['text']			= $feed['text'];
			}
		
			$format_feeds[$index]['user-head'] 		= $feed['user']['profile_image_url'];
			$format_feeds[$index]['feed-url'] 		= "http://t.163.com/".$feed['user']['screen_name']."/status/".$feed['id'];
			$format_feeds[$index]['user-url'] 		= "http://t.163.com/".$feed['user']['screen_name'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['id'];
			$format_feeds[$index]['user-name'] 	= $feed['user']['screen_name'];
		
			$format_feeds[$index]['created-at']		= strtotime($feed['created_at']);
			$format_feeds[$index]['comments-count']	= 0;
			$format_feeds[$index]['repost-count']	= 0;
			$format_feeds[$index]['source']			= $feed['source'];
			$format_feeds[$index]['rid']			= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_WANGYI;
		
			$index++;
		}
		
		
		//取原微博
		try{
			$message 		= $this->get_message($rid);
		}catch(\Exception $e){
			$message = array();
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
		return array('source'=>$source, 'comments'=>$format_feeds);
	}
	private function format_feeds($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		foreach((array)$feeds as $feed){
		
			//提取出图片
			if(preg_match("/(?P<img>http:\/\/126\.fm\/.+)$/", $feed['text'], $matched)){
				$img = $matched['img'];
				$format_feeds[$index]['image'][] 	= array(
						"normal" => "http://oimagea2.ydstatic.com/image?w=140&url=".urlencode($img),
						"big"=>"http://oimagea2.ydstatic.com/image?w=440&url=".urlencode($img));
				$format_feeds[$index]['text']			= strtr($feed['text'], array($img=>""));
			}else{
				$format_feeds[$index]['text']			= $feed['text'];
			}
		
			$format_feeds[$index]['user-head'] 		= $feed['user']['profile_image_url'];
			$format_feeds[$index]['feed-url'] 		= "http://t.163.com/".$feed['user']['screen_name']."/status/".$feed['id'];
			$format_feeds[$index]['user-url'] 		= "http://t.163.com/".$feed['user']['screen_name'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['id'];
			$format_feeds[$index]['user-name'] 		= $feed['user']['screen_name'];
		
			$format_feeds[$index]['created-at']		= strtotime($feed['created_at']);
			$format_feeds[$index]['comments-count']	= $feed['comments_count'];
			$format_feeds[$index]['repost-count']	= $feed['retweet_count'];
			$format_feeds[$index]['source']		= $feed['source'];
			$format_feeds[$index]['rid']				= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_WANGYI;
		
		
			if(@$feed['in_reply_to_status_id']){
				$format_feeds[$index]['repost']['user-name'] 		= $feed['in_reply_to_screen_name'];
				$format_feeds[$index]['repost']['user-url'] 		= "http://t.163.com/".$feed['in_reply_to_screen_name'];
				$format_feeds[$index]['repost']['feed-url'] 		= "http://t.163.com/".$feed['in_reply_to_screen_name']."/status/".$feed['in_reply_to_status_id'];
		
				$format_feeds[$index]['repost']['rid']				= $feed['in_reply_to_status_id'];
				$format_feeds[$index]['repost']['user-id'] 			= $feed['in_reply_to_user_id'];
		
				//提取出图片
				if(preg_match("/(?P<img>http:\/\/126\.fm\/.+)$/", $feed['in_reply_to_status_text'], $matched)){
					$img = $matched['img'];
					$format_feeds[$index]['repost']['image'][] 	= array(
							"normal" => "http://oimagea2.ydstatic.com/image?w=140&url=".urlencode($img),
							"big"=>"http://oimagea2.ydstatic.com/image?w=440&url=".urlencode($img));
					$format_feeds[$index]['repost']['text']			= strtr($feed['in_reply_to_status_text'], array($img=>""));
				}else{
					$format_feeds[$index]['repost']['text'] 			= $feed['in_reply_to_status_text'];
				}
		
		
				$format_feeds[$index]['repost']['created-at']		= strtotime($feed['retweet_created_at']);
				$format_feeds[$index]['repost']['comments-count']	= $feed['comments_count'];
				$format_feeds[$index]['repost']['repost-count']		= $feed['retweet_count'];
				$format_feeds[$index]['repost']['source']			= "";
			}
			$index++;
		}
		return $format_feeds;
	}
	public function get_My_Friends(Site_Account_Model $account){
		$ret = $this->get("https://api.t.163.com/statuses/friends.json", array());
		
		$users  = array();
		foreach ($ret['users'] as $r){
			$users[] = array(
				"uid"		=> $r['id'],
				"name"		=> $r['screen_name'],
				"url"		=> $r['url'],
				"avatar"	=> $r['profile_image_url'],
				"desc"		=> $r['description'],
				"status"		=> $r['status']['text'],
				"status_time"	=> strtotime($r['status']['created_at']),
				"location"		=> $r['location'],
				"sex"		=>$r['gender'] ? "男" : "女"
			);
		}

		return $users;
	}
}


?>