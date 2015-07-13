<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class QQ_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_QQ);
		return new QQ($app_cfg->get_app_key(), $app_cfg->get_app_secret(), 
		$site->get("access_token"),  $site->get("openid"),$site->get("refresh_token"));
	}
}
class QQ implements IOAuth{
	private $pageflag 		= "0";		//分页标识（0：第一页，before 1：向下翻页， after 2：向上翻页
	private $pagetime_before= 0;		//本页起始时间（第一页：填0，向上翻页：填上一次请求返回的第一条记录时间，向下翻页：填上一次请求返回的最后一条记录时间）
	private $pagetime_after	= 0;		//本页起始时间（第一页：填0，向上翻页：填上一次请求返回的第一条记录时间，向下翻页：填上一次请求返回的最后一条记录时间）
	private $type 			= 3;		//如需拉取多个类型请使用|，如(0x1|0x2)得到3，则type=3即可，填零表示拉取所有类型
	private $contenttype	= 0;		//内容过滤。0-表示所有类型，1-带文本，2-带链接，4-带图片，8-带视频，0x10-带音频 建议不使用contenttype为1的类型，如果要拉取只有文本的微博，建议使用0x80
	private $reqnum 		= 10;		//每次请求记录的条数（1-70条）
	public $format 			= "json";
	private $twitterid		= 0;
	private $access_token;
	private $access_token_secret;
	private $refresh_token;
	private $openid;
	private $app_key;
	private $app_secret;
	/**
	 * 
	 * @var OAuthHttpClient
	 */
	private $http;

	public function __construct($client_id, $app_secret, $access_token, $openid, $refresh_token=null){
		$this->http 			= new OAuthHttpClient();
		$this->app_key 		= $client_id;
		$this->app_secret 	= $app_secret;
		$this->access_token= $access_token;
		$this->openid 		= $openid;
		$this->refresh_token = $refresh_token;
	}
	
	public function build_paging_params(YZE_Request $request){
		$this->pageflag 	= $request->get_from_get("pageflag");
		$this->type 			= $request->get_from_get("type");
		$this->contenttype 	= $request->get_from_get("contenttype");
		$this->reqnum 		= $request->get_from_get("reqnum");
		$this->format 		= $request->get_from_get("format");
		$this->pagetime_before 	= $request->get_from_get("pagetime_before");
		$this->pagetime_after= $request->get_from_get("pagetime_after");
		
		if($this->pageflag==="before"){
			$this->pagetime 	= $request->get_from_get("pagetime_before");
		}elseif($this->pageflag==="after"){
			$this->pagetime 	= $request->get_from_get("pagetime_after");
		}
	}
	
	
	public function get_paging_params($paging_flag = "before"){
		$params['format'] 		= "json";
		$params['type'] 		= $this->type;
		$params['contenttype'] 	= $this->contenttype;
		$params['pageflag'] 	= $this->pageflag = $paging_flag;
		$params['pagetime_before'] 	= $this->pagetime_before;
		$params['pagetime_after'] 	= $this->pagetime_after;
		
		if($this->pageflag=="before"){
			$params['pagetime'] 	= $this->pagetime_before;
		}elseif($this->pageflag=="after"){
			$params['pagetime'] 	= $this->pagetime_after;
		}else{
			$params['pagetime'] 	= 0;
		}
		
		$params['reqnum'] 		= $this->reqnum;
		return $params;
	}
	
	public function get_comments(Site_Account_Model $site, $rid){
		if(!$rid){
			return array();
		}
		$params['pageflag'] = 1;
		$params['flag'] 		= 2;
		$params['rootid'] 	= $rid;
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->get("https://open.t.qq.com/api/t/re_list", $params));
		if(!@$rst['data'])return $rst;
		
		if($rst['data']['info']){//init
			$this->pagetime_before = $rst['data']['info'][count($rst['data']['info'])-1]['timestamp'];
			$this->pagetime_after = $rst['data']['info'][0]['timestamp'];
		}
		$format_feeds = $this->format_feeds($rst);
		return array("source"=>$format_feeds[0]['repost'], "comments"=>$format_feeds);
	}
	public function get_feeds($paging_flag = "before"){
		$params = $this->get_paging_params($paging_flag);
		unset($params['pagetime_after']);
		unset($params['pagetime_before']);
	
		if($params['pageflag']=="before"){
			$params['pageflag'] = 1;
		}else if($params['pageflag']=="after"){
			$params['pageflag'] = 2;
		}else{
			$params['pageflag'] = 0;
		}
		
		$params 	= $this->add_basic_args($params);
		$rst = $this->toArray($this->http->get("https://open.t.qq.com/api/statuses/home_timeline", $params));

		if(!@$rst['data'])return $rst;
	
		if($rst['data']['info']){//init
			$this->pagetime_before = $rst['data']['info'][count($rst['data']['info'])-1]['timestamp'];
			$this->pagetime_after = $rst['data']['info'][0]['timestamp'];
		}
		return $this->format_feeds($rst);
	}
	public function repost(Site_Account_Model $account, $message, $rid, $owner_id=null){
		$app_cfg = new App_Module();
		$params = array(
				'content' => $message,
				'format' => "json",
				'clientip' => $app_cfg->server_ip,//'REMOTE_ADDR'
		);
		
		$url = 'https://open.t.qq.com/api/t/re_add';
		$params['reid'] = $rid;
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->post($url, $params, false));
		
		return @$rst['data'] && @$rst['data']['id'];
	}

	public function get_remaining_hits(Site_Account_Model $site){
		return false;
	}
	public function send_msg(Site_Account_Model $site, $message, $pic=null, $rid=null, $bottle_id=null){
		$message = cutMsg($message, $bottle_id);
		
		$app_cfg = new App_Module();
		$params = array(
			'content' => $message,
			'format' => "json",
			'clientip' => $app_cfg->server_ip,//'REMOTE_ADDR'
		);
		$multi = false;
		if($rid){
			$url = 'https://open.t.qq.com/api/t/comment';
			$params['reid'] = $rid;
			//如果有图片给出连接
			if($pic){
				$params['content'] .= get_file_url($pic);
			}
		}elseif($pic){
			$url = 'https://open.t.qq.com/api/t/add_pic';//add_pic 接口总是报file size error错误
			$params['pic'] = '@'.$pic;
			$multi = true;
		}else{
			$url = 'https://open.t.qq.com/api/t/add';
		}
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->post($url, $params, $multi));

		$timestamp = @$rst['data']['time'];
		return array(
			"created_at"=> $timestamp ? $timestamp : time(), 
			"message_url"=>"http://t.qq.com/p/t/".$rst['data']['id'],
			"rid"=>$rst['data']['id'], 
			"user"=>array("uid"	=>$site->get("site_uid"),
				"name"	=>$site->get("user_name"),
				"url"	=>$site->get("user_home"),
				"avatar"=>$site->get("user_avatar_url"),
				"description"=>"")) ;
	}
	public function format_user_info($userinfo){
		if(!$userinfo['data']['info']) array();
		$user = $userinfo['data']['info'][0];
		
		return array("name"=>$user['name'],"nick"=>$user['nick'],"avatar"=>@$user['head']."/50","uid"=>$user['openid'],"site"=>Open_Api_Model::SITE_NAME_QQ);
	}
	public function saveUser($userinfo, $access_token){
		if(!$userinfo['data']['info'])return;
		
		$user = $userinfo['data']['info'][0];
		
		return save_user(Open_Api_Model::SITE_NAME_QQ, Open_Api_Model::SITE_URL_QQ,
		@$user['nick'], @$user['head']."/50", $user['openid'], @$user['openid'],
		"http://t.qq.com/".@$user['name'], $user,
		$access_token['access_token'], $access_token["expires_in"], $access_token["refresh_token"], "", "");
	}
	
	public function get_message($rid, $owner_id=null){
		$params['format']	= "json";
		$params['id']		= $rid;
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->get("https://open.t.qq.com/api/t/show", $params));

		if(!@$rst['data'])return array();

		$ret = array(
				"created_at"=>$rst['data']['timestamp'],
				"rid"		=>$rid,
				"message"	=>$rst['data']['origtext'],
				"images"	=> array(),
				"message_url"=>"http://t.qq.com/p/t/".$rid,
				"user"=>array(
						"uid"	=>$rst['data']['openid'],
						"name"	=>$rst['data']['nick'],
						"url"	=>"http://t.qq.com/".@$rst['data']['name'],
						"avatar"=>$rst['data']['head']."/50",
						"description"=>""
				));
		
		if(@$rst['data']['image']){
			foreach((array)$rst['data']['image'] as $img ){
				$ret["images"][]	= array("normal"=>$img."/150", "big"=>$img."/460");
			}
		}
		return $ret;
	}
	
	public function get_reply(Drift_To_Site_Model $site){
		$rid = $site->get("rid");
		if(!$rid){
			return array();
		}
		/**
		 format 	返回数据的格式（json或xml）
		 flag 		标识。0－转播列表 1－点评列表 2－点评与转播列表
		 rootid 	转发或回复的微博根结点id（源微博id）
		 pageflag 	分页标识（0：第一页，1：向下翻页，2：向上翻页）
		 pagetime 	本页起始时间（第一页：填0，向上翻页：填上一次请求返回的第一条记录时间，向下翻页：填上一次请求返回的最后一条记录时间）
		 reqnum 	每次请求记录的条数（1-100条）
		 twitterid 	翻页用，第1-100条填0，继续向下翻页，填上一次请求返回的最后一条记录id
		 http://wiki.open.t.qq.com/index.php/%E5%BE%AE%E5%8D%9A%E7%9B%B8%E5%85%B3/%E8%8E%B7%E5%8F%96%E5%8D%95%E6%9D%A1%E5%BE%AE%E5%8D%9A%E7%9A%84%E8%BD%AC%E5%8F%91%E6%88%96%E7%82%B9%E8%AF%84%E5%88%97%E8%A1%A8
		 */
		$params['format'] 		= "json";
		$params['flag'] 		= 2;
		$params['rootid'] 		= $rid;
		
		$params['pageflag'] 	= 0;
		$params['pagetime'] 	= 0;
		$params['reqnum'] 		= $this->get_count_per_page();
		$params['twitterid'] 	= 0;
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->get("https://open.t.qq.com/api/t/re_list", $params));

		$replys = array();
		foreach ((array)@$rst['data']['info'] as $r){
			$replys[] = array(
				"created_at"=>$r['timestamp'], 
				"rid"		=>$r['id'], 
				"message"	=>$r['text'],
				"message_url"=>"http://t.qq.com/p/t/".$rid,
				"user"=>array(
					"uid"	=>$r['openid'],
					"name"	=>$r['nick'],
					"url"	=>"http://t.qq.com/".@$r['name'],
					"avatar"=>$r['head']."/50",
					"description"=>""
					));
		}
		return $replys;
	}

	public function get_count_per_page()
	{
		return 100;
	}
	
	
	public function get_User_Info($token){
		$args =array("format"=>"json");
		$args['fopenids'] = $token['openid'];
		$args 	= $this->add_basic_args($args);
		return $this->toArray($this->http->get("https://open.t.qq.com/api/user/infos", $args));
	}
	/*  
	public function getMyFriends(Site_Account_Model $account){
		$args =array("format"=>"json");
		$args['fopenids'] = $token['openid'];
		$args 	= $this->add_basic_args($args);
		return $this->toArray($this->http->get("https://open.t.qq.com/api/user/infos", $args));
	} */
	
	/**
	 * 
	 * 增加QQ OAuth请求必需的参数
	 * 
	 * @author leeboo
	 * 
	 * @param unknown $args
	 * @return array
	 */
	private function add_basic_args($args){
		$args  = $args ? $args : array();
		$args['oauth_consumer_key'] = $this->app_key;
		$args['access_token'] = $this->access_token;
		$args['openid'] = $this->openid;
		$args['clientip'] = $_SERVER['REMOTE_ADDR'];
		$args['oauth_version'] = "2.a";
		$args['scope'] = "all";
		return $args;
	}
	
	private function format_feeds($feeds){
		//print_r($feeds);
		$format_feeds = array();
		$index = 0;
		
		foreach((array)$feeds['data']['info'] as $feed){
			if(!$feed['origtext'])continue;//没有内容的跳过，比如转播
		
			$format_feeds[$index]['user-url'] 		= "http://t.qq.com/".$feed['name'];
			$format_feeds[$index]['user-head'] 		= $feed['head']."/50";
			$format_feeds[$index]['user-id'] 		= "";
			$format_feeds[$index]['feed-url'] 		= "http://t.qq.com/p/t/".$feed['id'];
			$format_feeds[$index]['user-name'] 		= $feed['nick'];
			foreach ((array)$feed['image'] as $image){
				$format_feeds[$index]['image'][] 	= array("normal"=>$image."/150", "big"=>$image."/460");
			}
			$format_feeds[$index]['created-at']		= $feed['timestamp'];
			$format_feeds[$index]['comments-count']	= $feed['count'];
			$format_feeds[$index]['repost-count']	= $feed['mcount'];
			$format_feeds[$index]['source']			= '<a href="'.$feed['fromurl'].'" target="_blank">'.$feed['from'].'</a>';;
			$format_feeds[$index]['text']			= $feed['origtext'];
			$format_feeds[$index]['rid']			= $feed['id'];
			$format_feeds[$index]['site']			= Open_Api_Model::SITE_NAME_QQ;
		
			if(@$feed['source']){
				$format_feeds[$index]['repost']['user-name'] 		= $feed['source']['nick'];
				$format_feeds[$index]['repost']['user-url']			= "http://t.qq.com/".$feed['source']['name'];
				$format_feeds[$index]['repost']['user-head']		= $feed['source']['head']."/50";
				$format_feeds[$index]['repost']['feed-url'] 		= "http://t.qq.com/p/t/".$feed['source']['id'];
				$format_feeds[$index]['repost']['text'] 			= $feed['source']['origtext'];
				$format_feeds[$index]['repost']['rid']				= $feed['source']['id'];
				$format_feeds[$index]['repost']['user-id'] 			= "";
		
				foreach ((array)$feed['source']['image'] as $image){
					$format_feeds[$index]['repost']['image'][] 	= array("normal"=>$image."/150", "big"=>$image."/460");
				}
				$format_feeds[$index]['repost']['created-at']		= $feed['source']['timestamp'];
		
				$format_feeds[$index]['repost']['comments-count']	= $feed['source']['count'];
				$format_feeds[$index]['repost']['repost-count']		= $feed['source']['mcount'];
				$format_feeds[$index]['repost']['source']			= '<a href="'.$feed['source']['fromurl'].'" target="_blank">'.$feed['source']['from'].'</a>';
			}
			$index++;
		}
		return $format_feeds;
	}
	
	private function toArray($str){
		//echo $str;
		$ret = json_decode($str, true);//it's json
		if(json_last_error()!==JSON_ERROR_NONE){
			throw new Sns_Publish_Message_Exception($str);
		}
		$this->_check_ret($ret);
		return $ret;
	}
	
	private function _check_ret($rst)
	{
		if(@$rst['ret'] == 0 || !$rst['ret'])return;
	
		if(@$rst['ret'] == 3){
			throw new Token_Expire_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
		}elseif(@$rst['ret'] == 2){
			throw new Request_Reached_Limit_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
		}elseif(@$rst['ret'] == 4){
			if(in_array(@$rst['errcode'], array(6,11))){
				throw new Has_Deleted_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
			}else{
				throw new Sns_Publish_Message_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
			}
		}else{
			throw new Sns_Publish_Message_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
		}
	}
	public function get_My_Friends(Site_Account_Model $account){
		$params['format']	= "json";
		$params['startindex']		= 0;
		$params['reqnum']		= 30;
		$params = $this->add_basic_args($params);
		$rst = $this->toArray($this->http->get("https://open.t.qq.com/api/friends/idollist", $params));
		//print_r($rst);
		$users = array();
		foreach ($rst['data']['info'] as $r){
			$users[] = array(
					"uid"		=>$r['openid'],
					"name"		=>$r['nick'],
					"url"		=>"http://t.qq.com/".@$r['name'],
					"avatar"	=>$r['head']."/50",
					"desc"		=>"",
					"status"		=>$r['tweet'][0]['text'],
					"status_time"=>$r['tweet'][0]['timestamp'],
					"location"	=>$r['location'],
					"sex"		=>$r['sex']==1 ? "男" : ($r['sex']==2 ? "女" :"")
			);
		}
		return $users;
	}
}
?>