<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class Sina_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_SINA);
		return new Sina($app_cfg->get_app_key(), $app_cfg->get_app_secret(),  $site->get("access_token"));
	}
}

class Sina implements IOAuth{
	private $client_id 		= ""; //api key
	private $client_secret 	= ""; //app secret
	private $redirect_uri 	= "";//回调地址，所在域名必须与开发者注册应用时所提供的网站根域名列表或应用的站点地址（如果根域名列表没填写）的域名相匹配
	private $access_token;
	private $http;


	private $since_id = 0;//若指定此参数，则返回ID比since_id大的微博（即比since_id时间晚的微博），默认为0。
	private $max_id = 0;//若指定此参数，则返回ID小于或等于max_id的微博，默认为0
	private $count = 10;//单页返回的记录条数，默认为50。
	private $pageflag = "before";
	
	public function __construct($client_id, $client_secret, $access_token=null){
		$this->http 				= new OAuthHttpClient();
		$this->client_id 			= $client_id;
		$this->client_secret 	= $client_secret;
		$this->access_token 	= $access_token;
		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度
	}
	public function format_user_info($user){
		return array("name"=>$user['name'],"nick"=>$user['screen_name'],"avatar"=>@$user['profile_image_url'],"uid"=>$user['id'],"site"=>Open_Api_Model::SITE_NAME_SINA);
	}
	public function saveUser($userinfo, $access_token){
		return save_user(Open_Api_Model::SITE_NAME_SINA, Open_Api_Model::SITE_URL_SINA,
		@$userinfo['screen_name'], @$userinfo['profile_image_url'], "", @$userinfo['id'],
		"http://weibo.com/".@$userinfo['profile_url'], $userinfo,
		$access_token['access_token'], $access_token['expires_in'], "", "");
	}
	
	public function repost(Site_Account_Model $account, $msg, $rid, $owner_id=null){
		$params = array(
				'access_token' 	=> $account->get("access_token"),
				'status' 		=> $msg,
				'id' 			=> $rid,
		);
		
		$rst = $this->toArray($this->http->post("https://api.weibo.com/2/statuses/repost.json", $params));
		
		return $rst && @$rst['id'];
	}
	public function get_comments(Site_Account_Model $site, $rid){
		$params['access_token'] = $this->access_token;
		$params['id'] = $rid;
		
		
		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度

		$_ = $ret = $this->toArray($this->http->get("https://api.weibo.com/2/comments/show.json", $params));

		if(@$_['comments']){
			$last = array_pop($_['comments']);
			$this->max_id = $last['id'];
			$first = array_shift($_['comments']);
			if($first){
				$this->since_id = $first['id'];
			}else{
				$this->since_id = $last['id'];
			}
		}
		$feeds = $this->format_feeds($ret['comments']);
		return array('source'=>$feeds[0]['repost'], "comments"=>$feeds);
	}
	public function get_feeds($paging_flag = "before"){
		$params = $this->get_paging_params($paging_flag);
		$params['access_token'] = $this->access_token;
		
		
		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度

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
		$_ = $ret = $this->toArray($this->http->get("https://api.weibo.com/2/statuses/home_timeline.json", $params));

		if(@$_['statuses']){
			$last = array_pop($_['statuses']);
			$this->max_id = $last['id'];
			$first = array_shift($_['statuses']);
			if($first){
				$this->since_id = $first['id'];
			}else{
				$this->since_id = $last['id'];
			}
		}
		return $this->format_feeds($ret['statuses']);
	}
	/**
	 *     [ip_limit] => 10000
	 [limit_time_unit] => HOURS
	 [remaining_ip_hits] => 10000
	 [remaining_user_hits] => 1000
	 [reset_time] => 2012-05-06 16:00:00
	 [reset_time_in_seconds] => 2165
	 [user_limit] => 1000
	 */
	public function get_remaining_hits(Site_Account_Model $site){
		$params['access_token'] = $site->get("access_token");

		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度

		$rst = $this->toArray($this->http->get("https://api.weibo.com/2/account/rate_limit_status.json", $params));

		if(!$rst || !@$rst['reset_time_in_seconds']){
			return false;
		}

		return array('reset_time_in_seconds'=>$rst['reset_time_in_seconds'],
			'remaining_hits'=>$rst['remaining_user_hits'],
			'reset_time'	=>$rst['reset_time']);
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
		$message = cutMsg($message, $bottle_id);
		
		$params = array(
		    	'access_token' 	=> $site->get("access_token"),
		    	'status' 		=> $message,
		);

		$app_cfg = new App_Module();

		if(@$rid){
			//如果有图片给出连接
			if($pic){
				$message 	.= get_file_url($pic);
			}
			$params['id'] 		= "$rid";
			$params['comment'] 	= $message;
			$params['comment_ori'] 	= "1";
			unset($params['status']);

			$rst = $this->toArray($this->http->post("https://api.weibo.com/2/comments/create.json", $params));
		}elseif(@$pic){
			$params['pic'] = '@'.$pic;
			$rst = $this->toArray($this->http->post("https://api.weibo.com/2/statuses/upload.json", $params, true));
		}else{
			$rst = $this->toArray($this->http->post("https://api.weibo.com/2/statuses/update.json", $params));
		}

		$mid = null;
		try{
			$mid = $this->toArray($this->http->get("https://api.weibo.com/2/statuses/querymid.json",
			array("access_token"=>$site->get("access_token"),"id"=>$rst['id'],"type"=>1,"is_batch"=>0)
			));
		}catch(\Exception $e){
			//ignore
		}

		/*ob_start();
		 echo "sina mid:";
		 print_r($mid);
		 YZE_Object::log(ob_get_clean());*/

		return array(
			"created_at"=>strtotime($rst['created_at']), 
			"rid"		=>$rst['id'],
			"message_url"=> $mid && @$mid['mid'] ? "http://weibo.com/".$rst['user']['id']."/".$mid['mid'] : "",  
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
		$params['id'] 			= $rid;
		$params['access_token'] = $this->access_token;


		$r = $this->toArray($this->http->get("https://api.weibo.com/2/statuses/show.json", $params));
		
		return array(
				"created_at"=> strtotime($r['created_at']),
				"rid"		=> $r['id'],
				"message"	=> $r['text'],
				"images"	=> array(array("normal" => @$r['thumbnail_pic'], "big" => @$r['bmiddle_pic'])),
				"message_url"=> "#",
				"user"=>array(
						"uid"	=> $r['user']['id'],
						"name"	=> $r['user']['screen_name'],
						"url"	=>$r['user']['url'],
						"avatar"=> $r['user']['profile_image_url'],
						"description"=> $r['user']['description']
				));
	}
	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}

		$params['since_id'] 	= 0;
		$params['id'] 		= $rid;
		$params['access_token'] = $site->get_site()->get("access_token");

		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度

		$rst = $this->toArray($this->http->get("https://api.weibo.com/2/comments/show.json", $params));

		$replys = array();
		foreach ($rst['comments'] as $r){
			$mid = null;
			try{
				$mid = $this->toArray($this->http->get("https://api.weibo.com/2/statuses/querymid.json",
				array("access_token"=>$site->get_site()->get("access_token")),
				array("id"=>$r['id']),
				array("type"=>2),
				array("is_batch"=>0)
				));
			}catch(\Exception $e){
				//ignore
			}

			$replys[] = array(
				"created_at"=>strtotime($r['created_at']), 
				"rid"		=>$r['id'], 
				"message"	=>$r['text'],
				"message_url"=> $mid && @$mid['mid'] ? "http://weibo.com/".$r['user']['id']."/".$mid['mid'] : "",
				"user"=>array(
					"uid"	=>$r['user']['id'],
					"name"	=>$r['user']['screen_name'],
					"url"	=>$r['user']['url'],
					"avatar"=>$r['user']['profile_image_url'],
					"description"=>$r['user']['description']
			));
		}

		return $replys;
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
	

	public function get_User_Info($args){
		$params = array(
		    	'access_token' => $args['access_token'],
		    	'uid' => $args['uid'],
		);
		return $this->toArray($this->http->get("https://api.weibo.com/2/users/show.json",$params));
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
		if(!@$rst['error_code']) return;
		
		if(@substr($rst['error_code'], 0, 3) == 213){
			throw new Token_Expire_Exception($rst['error_code'].",".@$rst['error']);
		}elseif(@$rst['error_code'] == 20101){
			throw new Has_Deleted_Exception(@$rst['error_code'].",".@$rst['error']);
		}elseif(@in_array($rst['error_code'], array(10022,10023,10024))){
			throw new Request_Reached_Limit_Exception(@$rst['error_code'].",".@$rst['error']);
		}else{
			throw new Sns_Publish_Message_Exception($rst['error_code'].",".@$rst['error']);
		}
	}
	
	private function format_feeds($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		foreach((array)$feeds as $feed){
			$format_feeds[$index]['user-head'] 		= $feed['user']['profile_image_url'];
			$format_feeds[$index]['user-url'] 		= "http://weibo.com/u/".$feed['user']['id'];
			$format_feeds[$index]['user-id'] 		= $feed['user']['id'];
			$format_feeds[$index]['feed-url'] 		= "#";
			$format_feeds[$index]['user-name'] 		= $feed['user']['screen_name'];
			if(@$feed['thumbnail_pic']){
				$format_feeds[$index]['image'][] 		= array("normal" => @$feed['thumbnail_pic'], "big" => @$feed['bmiddle_pic']);
			}
			$format_feeds[$index]['created-at']			= strtotime($feed['created_at']);
			$format_feeds[$index]['comments-count']	= @$feed['comments_count'];
			$format_feeds[$index]['repost-count']		= @$feed['reposts_count'];
			$format_feeds[$index]['source']			= $feed['source'];
			$format_feeds[$index]['text']			= $feed['text'];
			$format_feeds[$index]['rid']				= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_SINA;
		
			if(@$feed['status']){//取得某条微博的评论时显示该字段
				$retweeted_status = $feed['status'];
			}else{//取微博列表时显示该字段
				$retweeted_status = @$feed['retweeted_status'];
			}
		
			if(@$retweeted_status){
				$format_feeds[$index]['repost']['user-name'] 		= $retweeted_status['user']['screen_name'];
				$format_feeds[$index]['repost']['user-url'] 		= "http://weibo.com/u/".$retweeted_status['user']['id'];
				$format_feeds[$index]['repost']['feed-url'] 		= "#";
				$format_feeds[$index]['repost']['user-id'] 			= $retweeted_status['user']['id'];
				$format_feeds[$index]['repost']['user-head'] 		= $retweeted_status['user']['profile_image_url'];
				$format_feeds[$index]['repost']['text'] 			= $retweeted_status['text'];
		
				if(@$retweeted_status['thumbnail_pic']){
					$format_feeds[$index]['repost']['image'][] 		= array("normal" => @$retweeted_status['thumbnail_pic'], "big" => @$retweeted_status['bmiddle_pic']);
				}
		
				$format_feeds[$index]['repost']['rid']				= $retweeted_status['id'];
		
				$format_feeds[$index]['repost']['created-at']		= strtotime($retweeted_status['created_at']);
				$format_feeds[$index]['repost']['comments-count']	= $retweeted_status['comments_count'];
				$format_feeds[$index]['repost']['repost-count']		= $retweeted_status['reposts_count'];
				$format_feeds[$index]['repost']['source']			= $retweeted_status['source'];
			}
			$index++;
		}
		return $format_feeds;
	}
	public function get_My_Friends(Site_Account_Model $site){
		$params['access_token'] = $site->get("access_token");
		$params['trim_status'] = 0;//返回完整的status
		$params['uid'] = $site->get("site_uid");//返回完整的status

		@ini_set("precision", 64);//sina 返回的id超过了默认的14位长度

		$rst = $this->toArray($this->http->get("https://api.weibo.com/2/friendships/friends.json", $params));

		if( ! $rst)return array();
		$users = array();
		foreach ($rst['users'] as $r){
			$users[] = array(
					"uid"		=> $r['id'],
					"name"		=> $r['screen_name'],
					"url"		=> $r['url'],
					"avatar"	=> $r['profile_image_url'],
					"desc"		=> $r['description'],
					"status"		=> $r['status']['text'],
					"status_time"		=> strtotime($r['status']['created_at']),
					"location"	=> $r['location'],
					"sex"		=> $r['gender']=="m" ? "男" : "女"
			);
		}
		return $users;
	}
}