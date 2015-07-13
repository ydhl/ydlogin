<?php
namespace app;
use yangzie\YZE_Request;

use app\bottles\Drift_To_Site_Model;

use app\bottles\Site_Account_Model;

use app\sites\Open_Api_Model;
class QZone_Sns_Factory extends Sns_Factory{

	public function get_sns(Site_Account_Model $site){
		$app_cfg = Open_Api_Model::get_config(Open_Api_Model::SITE_NAME_QQ);
		return new QQ($app_cfg->get_app_key(), $app_cfg->get_app_secret(), 
		$site->get("access_token"),  $site->get("openid"),$site->get("refresh_token"));
	}
}
//QQ互联
class QZone implements IOAuth{
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
		return array();
	}
	
	
	public function get_paging_params($paging_flag = "before"){
		
		return array();
	}
	
	public function get_comments(Site_Account_Model $site, $rid){
		//不能取得回复
		return array();
	}
	public function get_feeds($paging_flag = "before"){
		//不能得到最新动态
		return array();
	}
	public function repost(Site_Account_Model $account, $message, $rid, $owner_id=null){
		//不能转播或评论
		return null;
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
			'site'=>'交流瓶',
			'title' => $app_cfg->server_ip,//'REMOTE_ADDR'
		);
		$multi = false;
		if($rid){
			return array();//不能回复
		}
		
		$url = 'https://graph.qq.com/share/add_share';
		if($pic){
			$params['images'] = $pic;
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
	public function format_user_info($user){
		if(!$user) array();
		
		return array("name"=>$user['nickname'],"nick"=>$user['nickname'],
				"avatar"=>@$user['figureurl_1'],"uid"=>$this->openid,
				"site"=>Open_Api_Model::SITE_NAME_QZone);
	}
	public function saveUser($user, $access_token){
		if(!$user)return;
		
		
		return save_user(Open_Api_Model::SITE_NAME_QZone, Open_Api_Model::SITE_URL_QZone,
		@$user['nickname'], @$user['figureurl_1'], $this->openid, $this->openid,
		"http://qzone.qq.com", $user,
		$access_token['access_token'], $access_token["expires_in"], $access_token["refresh_token"], "", "");
	}
	
	public function get_message($rid, $owner_id=null){
		//无法获得指定的消息
		return array();
	}
	
	public function get_reply(Drift_To_Site_Model $site){
		//无法获得空间回复
		return array();
	}

	public function get_count_per_page()
	{
		return 100;
	}
	
	
	public function get_User_Info($token){
		$cp = $this->http->get("https://graph.qq.com/oauth2.0/me", array("access_token"=>$token['access_token']));
		preg_match("/\((.+)\)/", $cp, $m);
		
		$cp = json_decode($m[1], true);
		$this->openid = $cp['openid'];
		
		$args 	= $this->add_basic_args(array());
		
		return $this->toArray($this->http->get("https://graph.qq.com/user/get_user_info", $args));
	}
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
	
		if(@$rst['ret'] == 100014){
			throw new Token_Expire_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
		}else{
			throw new Sns_Publish_Message_Exception(@$rst['ret'].",".@$rst['errcode'].":".@$rst['msg']);
		}
	}
	public function get_My_Friends(Site_Account_Model $account){
		//无法获得空间好友
		return array();
	}
}
?>