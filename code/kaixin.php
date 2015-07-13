<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class Kaixin_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_KAIXIN);
		return new Kaixin($app_cfg->get_app_key(), $app_cfg->get_app_secret(), $site->get("access_token"));
	}
}
class Kaixin implements  IOAuth{

	private $client_id = ""; //api key
	private $client_secret = ""; //app secret
	private $redirect_uri = "";//回调地址，所在域名必须与开发者注册应用时所提供的网站根域名列表或应用的站点地址（如果根域名列表没填写）的域名相匹配
	private $access_token;
	private $host = "https://api.kaixin001.com/";
	
	private $num = 30;
	private $start;
	

	function __construct($client_id, $client_secret, $access_token)
	{
		$this->http 			= new OAuthHttpClient();
		$this->client_id 		= $client_id;
		$this->client_secret = $client_secret;
		$this->access_token= $access_token;
	}
	public function format_user_info($user){
		return array("name"=>$user['name'],"nick"=>$user['name'],"avatar"=>$user['logo50'],"uid"=>$user['uid'],"site"=>Open_Api_Model::SITE_NAME_KAIXIN);
	}
	
	public function saveUser($userinfo, $token){
		return save_user(Open_Api_Model::SITE_NAME_KAIXIN, Open_Api_Model::SITE_URL_KAIXIN,
		@$userinfo['name'], @$userinfo['logo50'], "", @$userinfo['uid'],
		"http://www.kaixin001.com/home/".@$userinfo['uid'].".html", $userinfo,
		@$token['access_token'], @$token['expires_in'], @$token['refresh_token'] ,'');
	}
	
	
	public function repost(Site_Account_Model $site, $message, $rid, $owner_id=null){
		$app_cfg = new App_Module();
		$url = 'forward/create';
		$param = array(
				'objtype' 	=> "records",
				'objid' 	=> "$rid",
				'ouid' 		=> $owner_id ,
				'content' 	=> $message
		
		);
		
		$response = $this->post($url, $param);
		return @$response['fid'];
	}
	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}
	
		$this->access_token = $site->get_site()->get("access_token");
	
		$params['start'] 		= 0;
		$params['num'] 			= $this->get_count_per_page();
		$params['objid'] 		= $rid;
		$params['objtype'] 		= "records";
		$params['ouid'] 		= $site->get_site()->get("site_uid");
	
		$rst = $this->get("comment/list", $params);
	
		$replys = array();
		foreach ($rst['data'] as $r){
			$replys[] = array(
					"created_at"=>strtotime($r['ctime']),
					"rid"		=>$r['thread_cid'],
					"message"	=>$r['content'],
					"message_url"	=> 'http://www.kaixin001.com/records/'.$site->get_site()->get("site_uid").'/'.$rid.'.html',
					"user"=>array(
							"uid"	=>$r['uid'],
							"name"	=>$r['name'],
							"url"	=>"http://www.kaixin001.com/home/".$r['uid'].".html",
							"avatar"=>$r['logo50'],
							"description"=>""
					));
	
			foreach (@(array)$r['replys'] as $rep){
				$replys[] = array(
						"created_at"=>strtotime($rep['ctime']),
						"rid"		=>$rep['cid'],
						"pid"		=>$r['thread_cid'],
						"message"	=>$rep['content'],
						"message_url"	=> 'http://www.kaixin001.com/records/'.$site->get_site()->get("site_uid").'/'.$rid.'.html',
						"user"=>array(
								"uid"	=>$rep['uid'],
								"name"	=>$rep['name'],
								"url"	=>"http://www.kaixin001.com/home/".$rep['uid'].".html",
								"avatar"=>$rep['logo50'],
								"description"=>""
						));
			}
		}
	
		return $replys;
	}
	public function get_comments(Site_Account_Model $site, $rid){
		if( !$rid){
			return array();//kaixing不支持时间线
		}
		$rst = $this->get("comment/list", array());
		if(@$rst['data']){
			$this->start = count($rst['data']);
		}
		return $this->format_feed_comments($rst);
	}
	public function get_feeds($paging_flag = "before"){
		if($paging_flag=="after"){
			return array();//kaixing不支持时间线
		}
		$params = $this->get_paging_params();
		//friends 返回{"error_code":"304","request":"\/records\/friends.json","error":"304:Error: 服务器内部错误"}
		$rst = $this->get("records/public", $params);
		if(@$rst['data']){
			$this->start = count($rst['data']);
		}
		return $this->format_feeds($rst);
	}
	
	public function build_paging_params(YZE_Request $request){
		$this->num 	= $request->get_from_get("num");
		$this->start = $request->get_from_get("start");
	}
	
	public function get_paging_params($paging_flag = "before"){
		$params['num'] 	= $this->num;
		$params['start'] = $this->start ? $this->start : 0;
		return $params;
	}


	public function get_remaining_hits(Site_Account_Model $site){
		$this->access_token = $site->get("access_token");
		$rst = $this->get("app/rate_limit_status", array());

		return array('reset_time_in_seconds'=>'', 'remaining_hits'=>$rst['remaining_hits']);
	}

	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		$app_cfg = new App_Module();
		if(@$rid){
			//如果有图片给出连接
			if($pic){
				$message .= get_file_url($pic);
			}
			$rst = $this->comment_create($rid, $site->get("site_uid"), $message);
		}else{
			$rst = $this->records_add($message, $pic);
		}
		
		$rid = @$rst['rid'] ? $rst['rid'] : $rst['data']['thread_cid'];
		return array(
			"created_at"	=> time(), 
			"rid"			=> $rid, 
			"message_url"	=> 'http://www.kaixin001.com/records/'.$site->get("site_uid").'/'.$rid.'.html',
			"user"=>array(
				"uid"	=>$site->get("site_uid"),
				"name"	=>$site->get("user_name"),
				"url"	=>$site->get("user_home"),
				"avatar"=>$site->get("user_avatar_url"),
				"description"=>""
				));
	}


	public function get_message($rid, $owner_id=null){
		//无接口
		return array();
	}

	public function get_count_per_page()
	{
		return 20;
	}
	
	/**
	 * 返回授权用户的信息
	 */
	public function get_User_Info($args)
	{
		$url = 'users/me';
		return $this->get($url, $args);
	}


	function get($api, $params = array())
	{
		$url = $this->host.$api.".".$this->http->format;
		if($this->access_token){
			$params['access_token'] = $this->access_token;
		}
		return $this->toArray($this->http->get($url, $params));
	}
	function post($api,$params = array(),$multi=false)
	{
		$url = $this->host.$api.".".$this->http->format;
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
		/*
		 正确返回
		stdClass Object
		(
				[data] => Array()
				[paging] => stdClass Object(
						[prev] => -1
						[next] => -1
						[total] => 0
				))
		错误返回
		stdClass Object
		(
				[error_code] => 401
				[request] => /comment/list.json
				[error] => 40115:Error: Oauth consumer_key不存在
		)
		*/
		$this->_check_ret($ret);
		return $ret;
	}
	
	private function _check_ret($rst)
	{
		if(!$rst || @!$rst['error_code']) return;
		if(@$rst['error_code'] == "401"){
			throw new Token_Expire_Exception(@$rst['error_code'].",".@$rst['error']);
		}elseif(@$rst['error_code']==4000811){
			throw new Has_Deleted_Exception(@$rst['error_code'].",".@$rst['error']);
		}elseif(@$rst['error_code']==40002){
			throw new Request_Reached_Limit_Exception(@$rst['error_code'].",".@$rst['error']);
		}
		throw new Sns_Publish_Message_Exception(@$rst['error_code'].",".@$rst['error']);
	}

	/**
	 * 发布一条记录(可以带一张图片)
	 * content	true	发记录的内容
	 * save_to_album	flase	是否存到记录相册中，0/1-不保存/保存，默认为0不保存
	 * location	flase	记录的地理位置(目前仅在“我的记录”列表中显示)
	 * lat	flase	纬度 -90.0到+90.0，+表示北纬(目前暂不能显示)
	 * lon	flase	经度 -180.0到+180.0，+表示东经(目前暂不能显示)
	 * sync_status	flase	是否同步签名 0/1/2-无任何操作/同步/不同步，默认为0无任何操作
	 * spri	flase	权限设置，0/1/2/3-任何人可见/好友可见/仅自己可见/好友及好友的好友可见,默认为0任何人可见
	 * pic	flase	发记录上传的图片，图片在10M以内，格式支持jpg/jpeg/gif/png/bmp
	 * pic和picurl只能选择其一，两个同时提交时，只取pic
	 * oauth1.0，pic参数不需要参加签名
	 * picurl	flase	外部图片链接，图片在10M以内，格式支持jpg/jpeg/gif/png/bmp
	 * pic和picurl只能选择其一，两个同时提交时，只取pic
	 */
	private function records_add($content,$pic="",$picurl="",$save_to_album="",$location="",$sync_status="",$spri="")
	{
		$url = 'records/add';
		$param = array(
				'content' => $content,
				'save_to_album' => $save_to_album,
				'location' => $location ,
				'sync_status' => $sync_status,
				'spri' => $spri,
				'lat','lon',
		);
		$multi = false;
		if(strlen($pic) > 0)
		{
			$param['pic'] = '@'.$pic;
			$multi = true;
		}
		else if(strlen($picurl) > 0)
		{
			$param['picurl'] = $picurl;
		}
		return $this->post($url, $param, $multi);
	}
	
	private function comment_create($rid, $ouid, $content)
	{
		$url = 'comment/create';
		$param = array(
				'objtype' 	=> "records",
				'objid' 	=> "$rid",
				'ouid' 		=> $ouid ,
				'content' 	=> $content
	
		);
	
		return $this->post($url, $param);
	}
	private function format_feed_comments($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		foreach((array)@$feeds->data->replys as $feed){
			$format_feeds[$index]['user-head'] 		= $feed->logo50;
			$format_feeds[$index]['user-url'] 		= "http://www.kaixin001.com/home/".$feed->uid.".html";
			$format_feeds[$index]['user-id'] 		= $feed->uid;
			$format_feeds[$index]['feed-url'] 		= 'http://www.kaixin001.com/records/'.$feed->uid.'/'.$feed->cid.'.html';
			$format_feeds[$index]['user-name'] 		= $feed->name;
			$format_feeds[$index]['rid']			= $feed->cid;
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_KAIXIN;
			/*
			 if(@$feed->main->pics){
			foreach($feed->main->pics as $img){
			$format_feeds[$index]['image'][] 	= array("normal" => $img->src, "big"=>str_replace("-s**rotate4", "-m**rotate4", $img->src));
			}
			}*/
			$format_feeds[$index]['created-at']		= $feed->ctime;
			$format_feeds[$index]['comments-count']	= 0;
			$format_feeds[$index]['repost-count']	= 0;
			$format_feeds[$index]['source']			= '开心网';
			$format_feeds[$index]['text']			= $feed->content;
		
			$index++;
		}
		
		$source['user-name'] 		= $feed->real_name;
		$source['user-id'] 			= $feed->uid;
		$source['text'] 			= $feed->content;
		/*if(@$feed->source->main->pics){
		 foreach($feed->source->main->pics as $img){
		$format_feeds[$index]['repost']['image'][] 	= array("normal" => $img->src, "big"=>str_replace("-s**rotate4", "-m**rotate4", $img->src));
		}
		}*/
		$source['created-at']		= $feed->ctime;
		$source['comments-count']	= 0;
		$source['repost-count']		= 0;
		$source['source']			= Open_Api_Model::SITE_NAME_KAIXIN;
		
		return array('source'=>$source, "comments"=>$format_feeds);
	}
	private function format_feeds($feeds){
		$format_feeds = array();
		$index = 0;
		foreach((array)@$feeds['data'] as $feed){
			$format_feeds[$index]['user-head'] 		= $feed['user']['logo50'];
			$format_feeds[$index]['user-url'] 		= "http://www.kaixin001.com/home/".$feed['user']['uid'].".html";
			$format_feeds[$index]['user-id'] 		= $feed['user']['uid'];
			$format_feeds[$index]['feed-url'] 		= 'http://www.kaixin001.com/records/'.$feed['user']['uid'].'/'.$feed['rid'].'.html';
			$format_feeds[$index]['user-name'] 	= $feed['user']['name'];
			$format_feeds[$index]['rid']				= $feed['rid'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_RENREN;
		
			if(@$feed['main']['pics']){
				foreach($feed['main']['pics'] as $img){
					$format_feeds[$index]['image'][] 	= array("normal" => $img['src'], "big"=>str_replace("-s**rotate4", "-m**rotate4", $img['src']));
				}
			}
			$format_feeds[$index]['created-at']		= $feed['ctime'];
			$format_feeds[$index]['comments-count']	= $feed['cnum'];
			$format_feeds[$index]['repost-count']	= $feed['rnum'];
			$format_feeds[$index]['source']		= '开心网';
			$format_feeds[$index]['text']			= $feed['main']['content'];
		
			if(@$feed['source']['main']){
				$format_feeds[$index]['repost']['user-name'] 		= $feed['source']['user']['name'];
				$format_feeds[$index]['repost']['user-id'] 			= $feed['source']['user']['uid'];
				$format_feeds[$index]['repost']['text'] 			= $feed['source']['main']['content'];
				if(@$feed['source']['main']['pics']){
					foreach($feed['source']['main']['pics'] as $img){
						$format_feeds[$index]['repost']['image'][] 	= array("normal" => $img['src'], "big"=>str_replace("-s**rotate4", "-m**rotate4", $img['src']));
					}
				}
				$format_feeds[$index]['repost']['created-at']		= $feed['source']['ctime'];
				$format_feeds[$index]['repost']['comments-count']	= $feed['source']['cnum'];
				$format_feeds[$index]['repost']['repost-count']		= $feed['source']['rnum'];
				$format_feeds[$index]['repost']['source']			= '开心网';
			}
			$index++;
		}
		return $format_feeds;
	}
	public function get_My_Friends(Site_Account_Model $account){
		$rst = $this->get("friends/me", array(
				'fields'=>"uid,name,gender,city,logo50,intro"
			));

		$users = array();
		foreach($rst['users'] as $r){
			$users [] = array(
					"uid"	=>$r['uid'],
					"name"	=>$r['name'],
					"url"	=>"http://www.kaixin001.com/home/".@$r['uid'].".html",
					"avatar"	=>$r['logo50'],
					"desc"	=>$r['intro'],
					"status"	=>"",
					"status_time"=>"",
					"location"	=>$r['city'],
					"sex"	=>$r['gender'] ? "女" : "男") ;
		}
		return $users;
	}
}

