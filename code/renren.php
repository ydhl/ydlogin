<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class Renren_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_RENREN);
		return new Renren($app_cfg->get_app_key(), $app_cfg->get_app_secret(), $site);
	}
}

class Renren implements IOAuth{
	private $appkey;
	private $secretkey;
	private $page=1;
	private $count = 30;
	private $site_account;


	public function __construct($apikey, $secret, $site){
		$this->appkey 		= $apikey;
		$this->secretkey 	= $secret;
		$this->site_account	= $site;
	}
	public function format_user_info($user){
		return array("name"=>$user['name'],"nick"=>$user['name'],"avatar"=>@$user['tinyurl'],"uid"=>$user['uid'],"site"=>Open_Api_Model::SITE_NAME_RENREN);
	}


	/**
	 * 用户的资料,{
   "mac_algorithm":"hmac-sha-1",
   "mac_key":"6c590a5e2c6748d297770a14e2f239d3",
   "scope":"read_user_feed read_user_album",
   "token_type":"mac",
   "user":{
      "id":2238192232,
      "name":"QianLong",
      "avatar":[
            {"type":"avatar",
              "url":"http://hdn.xnimg.cn/photos/hdn121/20130705/2055/h_head_KFTQ_d536000000d0111a.jpg"},
            {"type":"tiny",
            "url":"http://hdn.xnimg.cn/photos/hdn221/20130705/2055/tiny_jYQe_ec4300051e7a113e.jpg"
            },
            {"type":"main",
           "url":"http://hdn.xnimg.cn/photos/hdn121/20130705/2055/h_main_ksPJ_d536000000d0111a.jpg"
            },
            {"type":"large",
           "url":"http://hdn.xnimg.cn/photos/hdn121/20130705/2055/h_large_yxZz_d536000000d0111a.jpg"
            }
      ]
   },
   "access_token":"127089|2.nVvgz3cjNLOvCsVDTp0khw6rI4AejbHs.229819774.1326448901423"
}
	 */
	public function get_User_Info($args)
	{
		$avatars = $args['user']['avatar'];
		
		foreach ($avatars as $avatar){
			if(strcasecmp($avatar['type'],"tiny")==0){
				$tiny = $avatar['url'];break;
			}
		}
		return array(
			"name"	=>$args['user']['name'],
			"uid"	=>$args['user']['id'],
			"tinyurl"	=>@$tiny,
		);
	}
	
	public function saveUser($userinfo, $token){
		return save_user(Open_Api_Model::SITE_NAME_RENREN, Open_Api_Model::SITE_URL_RENREN,
		@$userinfo['name'], @$userinfo['tinyurl'], "", @$userinfo['uid'],
		"http://www.renren.com/".@$userinfo['uid'], $userinfo,
		$token['access_token'], "-1", "", "",$token['mac_key']);
	}
	
	public function get_paging_params($paging_flag = "before"){

		//renren 不支持向后翻页
		$params['pageNumber'] 	= $this->page;
		$params['pageSize'] 	= $this->count;
		return $params;
	}
	
	public function build_paging_params(YZE_Request $request){
		$this->count 	= $request->get_from_get("count");
		$this->page 	= $request->get_from_get("page");
		
	}
	
	public function repost(Site_Account_Model $account, $message, $rid,  $owner_id=null){

		$args['ugcId']			= $rid;
		$args['ugcType'] 		= "TYPE_SHARE";
		$args['ugcOwnerId'] 	= $owner_id;
		$args['comment']		= $message;

		$rst =  $this->post('/v2/share/ugc/put',$args);

		return true;
	}
	public function get_comments(Site_Account_Model $site, $rid){
		@ini_set("precision", 64);
		$uid = $site->get("site_uid");
		
		$comments = array();
		$source 	= array();
		
		$r =  $this->get('/v2/comment/list', array(
				'entryId'			=> $rid,
				'entryOwnerId'		=> $uid,
				"commentType"	=> "BLOG",
				'pageSize'=>30
		), $site);

		$source['user-name'] 		= $r['name'];
		$source['user-url'] 			=  "http://http://www.renren.com/".$r['uid'];
		$source['feed-url'] 			= "http://blog.renren.com/blog/".$r['uid']."/".$r['id'];
		$source['user-head'] 		= $r['headurl'];
		$source['text'] 				= $r['content'];
		$source['user-id'] 			= $r['uid'];
		$source['rid']				= $r['id'];
		$source['created-at']		= strtotime($r['time']);
		$source['comments-count']	= 0;
		$source['repost-count']		= 0;
		$source['source']			= Open_Api_Model::SITE_NAME_RENREN;
		
		if(@$r['comments']){
			$comments = $this->format_feed_comments($r['comments']);
		}
		
		return array('source'=>$source, "comments"=>$comments);
	}
	public function get_feeds($paging_flag = "before"){
		@ini_set("precision", 64);
		$params = $this->get_paging_params($paging_flag);
		$params['feedType']="ALL";
		$feeds =  $this->get('/v2/feed/list', $params, $this->site_account);
		$this->page++;
		return $this->format_feeds(@$feeds['response']);
	}
	public function get_remaining_hits(Site_Account_Model $site){
		return false;
	}

	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		$app_cfg = new App_Module();
		if(@$rid){//reply
			//如果有图片给出连接
			if($pic){
				$message 	.= get_file_url($pic);
			}
			$args['content']			= nl2br($message);
			$args['entryOwnerId'] 	= $site->get("site_uid");
			$args['entryId'] 			= $rid;
			$args['commentType'] 	= "BLOG";

			$rst =  $this->post('/v2/comment/put', (array)$args);
			
			$rst = $rst['response'];
		}else{
			$args['title']	= mb_substr($message, 0, 80, "utf-8")."...";
			$args['content']= nl2br($message);

			if(@$pic){
				$args['content'] .= "<img alt='交流瓶' src='".get_file_url($pic)."' />";
			}
			$rst =  $this->post('/v2/blog/put', (array)$args);//curl函数发送请求
			$rst = $rst['response'];
		}

		return array(
			"created_at"	=> time(), 
			"rid"			=> $rst['id'],
			"message_url"	=> "http://blog.renren.com/blog/".$site->get("site_uid")."/".$rst['id'],
			"user"=>array(
				"uid"	=>$site->get("site_uid"),
				"name"	=>$site->get("user_name"),
				"url"	=>$site->get("user_home"),
				"avatar"=>$site->get("user_avatar_url"),
				"description"=>""
				));
	}

	public function get_message($rid, $owner_id=null){
		$r =  $this->get('/v2/blog/get', array(
				'blogId'		=> $rid,
				'ownerId'		=> $owner_id
		));
		
		return array(
				"created_at"=> strtotime($r['time']),
				"rid"		=> $r['id'],
				"message"	=> $r['content'],
				"images"	=> array(array("normal" => "", "big" => '')),
				"message_url"=> "#",
				"user"=>array(
						"uid"	=> $r['uid'],
						"name"	=> $r['name'],
						"url"	=> "http://http://www.renren.com/".$r['uid'],
						"avatar"=> $r['headurl'],
						"description"=> ''
				));
	}


	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}
		

		@ini_set("precision", 64);

		$rst =  $this->get('/v2/comment/list', array(
				'desc'		=> true,
				'pageNumber'		=> 0,
				"commentType"	=> "BLOG",
				'pageSize'		=> $this->get_count_per_page(),
				'entryId'		=> $rid,
				'entryOwnerId'		=> $site->get_site()->get("site_uid")
		));
		

		$replys = array();
		foreach ($rst as $r){
			$replys[] = array(
				"created_at"=>strtotime($r['time']), 
				"rid"		=>$r['id'], 
				"message"	=>$r['content'],
				"message_url"	=> "#",
				"user"=>array(
					"uid"	=>$r['uid'],
					"name"	=>$r['name'],
					"url"	=>"http://www.renren.com/".$r['uid'],
					"avatar"=>$r['headurl'],
					"description"=>''
					));
		}

		return $replys;
	}
	public function get_count_per_page()
	{
		return 20;
	}


	private function post($api, $params){
		$ts = intval(time()/1000);
		$nonce = md5(uniqid());
		$tokens ="{$ts}\n{$nonce}\nPOST\n{$api}\napi.renren.com\n80\n\n";
		$mac = base64_encode ( hash_hmac ( 'sha1', $tokens, $this->site_account->get("access_token_secret"), true ) );//access_token_secret is mac key
		$authHeader = 'MAC id="'.$this->site_account->get("access_token").'",ts="'.$ts.'",nonce="'.$nonce.'",mac="'.$mac.'"';
		$http = new OAuthHttpClient($authHeader);
		return $this->toArray($http->post("http://api.renren.com".$api, $params));
	}
	
	private function get($api, $params, Site_Account_Model $account){
		$url = $params ? $api."?".http_build_query($params) : $api;
		$ts = intval(time()/1000);
		$nonce = md5(uniqid());
		$tokens ="{$ts}\n{$nonce}\nGET\n{$url}\napi.renren.com\n80\n\n";

		$mac = base64_encode ( hash_hmac ( 'sha1', $tokens, $this->site_account->get("access_token_secret"), true ) );//access_token_secret is mac key
		$authHeader = 'MAC id="'.$this->site_account->get("access_token").'",ts="'.$ts.'",nonce="'.$nonce.'",mac="'.$mac.'"';
		$http = new OAuthHttpClient($authHeader);
		return $this->toArray($http->get("http://api.renren.com".$api, $params));
	}
	
	private function toArray($str){
		$ret = json_decode($str, true);//it's json
		if(json_last_error()!==JSON_ERROR_NONE){
			throw new Sns_Publish_Message_Exception($str);
		}
		$this->_check_ret($ret);
		return $ret;
	}
	private function format_feed_comments($feeds){
		$format_feeds = array();
		$index = 0;
		//doc at  http://wiki.dev.renren.com/wiki/Blog.get
	
		foreach((array)$feeds as $index=>$feed){
			$format_feeds[$index]['user-head'] 		= $feed['headurl'];
			$format_feeds[$index]['feed-url'] 		= "#";
			$format_feeds[$index]['user-url'] 		= "http://http://www.renren.com/".$feed['uid'];
			$format_feeds[$index]['user-id'] 		= $feed['uid'];
			$format_feeds[$index]['user-name'] 	= $feed['name'];
			
			$format_feeds[$index]['created-at']		= strtotime($feed['time']);
			$format_feeds[$index]['comments-count']	= 0;
			$format_feeds[$index]['repost-count']	= 0;
			$format_feeds[$index]['source']		= Open_Api_Model::SITE_NAME_RENREN;
			$format_feeds[$index]['text']			= $feed['content'];
			$format_feeds[$index]['rid']				= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_RENREN;
	
		}
		return $format_feeds;
	}
	private function format_feeds($feeds){
		$format_feeds = array();
		$index = 0;
		
		//doc at  http://wiki.dev.renren.com/wiki/Feed.get
		
		foreach((array)$feeds as $index=>$feed){
			$format_feeds[$index]['user-head'] 		= $feed['sourceUser']['avatar'][0]['url'];
			$format_feeds[$index]['feed-url'] 		= $feed['resource']['url'];
			$format_feeds[$index]['user-url'] 		= "http://www.renren.com/".$feed['sourceUser']['id'];
			$format_feeds[$index]['user-id'] 		= $feed['sourceUser']['id'];
			$format_feeds[$index]['user-name'] 	= $feed['sourceUser']['name'];
			if(@$feed['attachment']){
				foreach ($feed['attachment'] as $attachment){
					if( in_array($attachment['media_type'], array("photo","image"))){//TODO 先只处理图片
						$format_feeds[$index]['image'][] 		= array("normal"=>$attachment['src'], "big"=>@$attachment['raw_src'] ? $attachment['raw_src'] : $attachment['src']);
					}
				}
			}
			$format_feeds[$index]['created-at']		= strtotime($feed['time']);
			$format_feeds[$index]['comments-count']	= intval(@$feed['comments']['count']);
			$format_feeds[$index]['repost-count']	= 0;
			$format_feeds[$index]['source']		= $feed['source'] ? $feed['source'] : Open_Api_Model::SITE_NAME_RENREN;
			$format_feeds[$index]['text']			= $feed['message'].":".$feed['resource']['title'];
			$format_feeds[$index]['rid']				= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_RENREN;
		
			if(@$feed['comments']){
				$comments = $feed['comments'][0];
				$format_feeds[$index]['repost']['user-name'] 		= $comments['author']['name'];
				$format_feeds[$index]['repost']['user-url'] 			=  "http://www.renren.com/".$comments['author']['id'];
				$format_feeds[$index]['repost']['feed-url'] 			= $feed['resource']['url'];
				$format_feeds[$index]['repost']['user-head'] 		= $comments['author']['avatar'][0]['url'];
				$format_feeds[$index]['repost']['text'] 				= $comments['content'];
				$format_feeds[$index]['repost']['user-id'] 			= $comments['author']['id'];
				/*if($feed->small_pic){
					$format_feeds[$index]['repost']['image'][] 		= array("normal"=>$feed->small_pic, "big"=>$feed->middle_pic);
				}
				*/
				$format_feeds[$index]['repost']['rid']				= $comments['id'];
				$format_feeds[$index]['repost']['created-at']		= strtotime($comments['time']);
				$format_feeds[$index]['repost']['comments-count']	= 0;
				$format_feeds[$index]['repost']['repost-count']		= 1;
				$format_feeds[$index]['repost']['source']			= '';
			}
		}
		return $format_feeds;
	}
	
	private function _check_ret($rst)
	{
		if(!$rst || @!$rst['code']) return;
		
		if(in_array(@$rst['code'], array("EXPIRED-TOKEN"))){
			throw new Token_Expire_Exception(@$rst['code'].",".@$rst['message']);
		}elseif(@$rst['code']=="ENTRY_NOT_EXIST"){
			throw new Has_Deleted_Exception(@$rst['code'].",".@$rst['message']);
		}elseif(@$rst['code']=="APP_OVER_INVOCATION_LIMIT"){
			throw new Request_Reached_Limit_Exception(@$rst['code'].",".@$rst['message']);
		}else{
			throw new Sns_Publish_Message_Exception(@$rst['code'].",".@$rst['message']);
		}
	}
	public function get_My_Friends(Site_Account_Model $account){
		$json =  $this->get('/v2/user/friend/list', array(
				//'access_token'		=> $account->get("access_token"),
				'userId'				=> $account->get("site_uid")
		), $account);

		$users = array();
		// array(array("uid","name","url","avatar","desc","status","status_time"=>时间戳,"location","sex"=>男｜女)) 
		foreach(@$json['response'] as $user){
			foreach ($user['avatar'] as $avatar){
				if(strcasecmp($avatar['size'],"tiny")==0){
					$tiny = $avatar['url'];break;
				}
			}
			$users[] = array(
				"uid"	=> $user['id'],
				"name"	=> $user['name'],
				"url"	=> "http://www.renren.com/".@$user['id'],
				"avatar"=> @$tiny,
				"desc"=>"",
				"status"=>"",
				"status_time"=>"",
				"location"=>"",
				"sex"=>"");
		}
		return $users;
		
	}
}
?>