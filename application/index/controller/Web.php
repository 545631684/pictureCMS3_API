<?php
namespace app\index\controller;
use Tool\Controller_controller;
use Tool\Tool;
use think\Db;
use think\facade\Config;
use Org\Util\Imgcompress;

class Web extends Controller_controller {

	public function __construct() 
	{
		parent::__construct();
	}
	
	/**
	* 登录
	*/
	public function login(){
		//登录验证
		if ($this->request->isPost()) {
			//数据创建
			$UsersArrServe = $this->request->param();
			$articleNum = db('article')->where(['state' => 1])->count();
			$userArr = db('users')->where(['userName' => $UsersArrServe['userName']])->find();
			$projectArr = $this->tool->getPublicInfo('project', db('project')->where('1 = 1')->select());
			$typeArr = $this->tool->getPublicInfo('type', db('type')->where('1 = 1')->select());
			$detailsArr = $this->tool->getPublicInfo('details', db('details')->where('1 = 1')->select());
			$groupLabelArr=db('group_label')->where('1 = 1')->select();
			$labelArr=db('label')->where('1 = 1')->select();
			$privacyTypes=db('privacy_type')->where('1 = 1')->select();
			$authgroupArr = db('auth_group')->where(['id' => $userArr['permissions']])->select();
			$userRecovery = db('auth_group')->where('1 = 1')->select();
			$authgroupArr[0]['rules']=json_decode($authgroupArr[0]['rules']);
			// 最后一次登录时间
			$endTime = time();
			//判断用户是否存在
			if (!empty($userArr['uId'])) {
				if ($userArr['state']!=1){
					if (md5($UsersArrServe['password']) == $userArr['password']) {
						
						//修改用户状态
						$rtn=db('users')->where(['uId' => $userArr['uId']])->update(['judgeLogin' => '1', 'endTime' => $endTime]);
						
						//修改用户数据后再查询一遍
						$userArr = db('users')->where(['userName' => $UsersArrServe['userName']])->find();
						
						// 版本转移时的临时添加，因增加字段没有赋值access_token和合法时长
						// if(!$userArr['access_token']){
							// db('users')->where(['uId' => $userArr['uId']])->update(['access_token' => $this->tool->secretkey($userArr['userName']), 'token_expires_in' => 0]);
							// db('users')->where(['uId' => $userArr['uId']])->update(['access_token' => $this->tool->secretkey($userArr['userName']), 'token_expires_in' => (time() + $this->tool->time_day)]);
						// }
						
						$jsonArr = [
							'adminInfo'			=> [
								'uId'				=> $userArr['uId'],
								'headPortraitSrc'	=> $userArr['headPortraitSrc'],
								'userName'			=> $userArr['userName'],
								'nickname'			=> $userArr['nickname'],
								'sex'				=> $userArr['sex'],
								'password'			=> $UsersArrServe['password'],
								'registerTime'		=> $userArr['registerTime'],
								'endTime'			=> $endTime,
								'state'				=> $userArr['state'],
								'permissions'		=> $userArr['permissions'],
								'auth'				=> $authgroupArr ? $authgroupArr[0] : "{}",
								'setPasswordStyle'	=> $UsersArrServe['setPasswordStyle'],
								'isCollapse'		=> false,
								'judgeLogin'		=> $userArr['judgeLogin'],
								'shieldInfo'		=> $userArr['shieldInfo'] != null ? json_decode($userArr['shieldInfo']) : "{}",
							],
							'publicInfo'		=> [
								'projects'			=> $projectArr ? $projectArr : "[]",
								'types'				=> $typeArr ? $typeArr : "[]",
								'details'			=> $detailsArr ? $detailsArr : "[]",
								'groupLabel'		=> $groupLabelArr ? $groupLabelArr : "[]",
								'label'				=> $labelArr ? $labelArr : "[]",
								'privacyTypes'		=> $privacyTypes ? $privacyTypes : "[]",
								'userRecovery'		=> $userRecovery ? $userRecovery : "[]",
								'srcUrl'			=> $this->tool->src_url,
							],
							'token'				=> [
								'access_token'		=> $userArr['access_token'],
								'token_expires_in'	=> $this->tool->setTokenTime8Hour($userArr['token_expires_in'],$userArr['uId']),
							]
						];
						return json(['code'=>$this->tool->success,'data'=>$jsonArr,'msg'=>'登录成功','status'=>db('users')->getLastSql(),]);
					}else{
						return json(['code'=>$this->tool->params_invalid,'data'=>'1','msg'=>'用户名或密码错误','status'=>true,]);
					}
				}else{
					return json(['code'=>$this->tool->params_invalid,'data'=>'2','msg'=>'该用户已被冻结','status'=>true,]);
				}
			}else{
				return json(['code'=>$this->tool->not_found,'data'=>'3','msg'=>'用户名不存在','status'=>true,]);
			}
		}
    }
	
	/**
	* 注册用户
	*/
	public function user_insert(){
		header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求
		if($this->request->isPost())
		{
			$usersArr=$this->request->param();
			//执行添加
			if($usersArr){
				if($usersArr['userName'] =='' && $usersArr['password'] == '' && $usersArr['verification'] == ''){
					return json(['code'=>$this->tool->params_invalid,'data'=>'0','msg'=>'用户名、密码、验证码不能为空','status'=>true,]);
				}
				if($usersArr['verification']!=session('verification')){
					return json(['code'=>$this->tool->params_invalid,'data'=>'2','msg'=>'验证码错误','status'=>true,]);
				}else{
					session("verification",null);
				}
				$sql=db('users')->where(['userName' => $usersArr['userName']])->find();
				if($sql['userName']){
					return json(['code'=>$this->tool->params_invalid,'data'=>'3','msg'=>'该账号已被注册','status'=>true,]);
				}else{
					$usersArr['headPortraitSrc'] = 'image/sq17.png';
					$usersArr['password'] = md5($usersArr['password']);
					$usersArr['nickname'] = $usersArr['userName'];
					$usersArr['state'] = 0;
					$usersArr['permissions'] = 1;
					$usersArr['registerTime'] = time();
					$usersArr['access_token'] = $this->tool->secretkey($usersArr['userName']);
					$usersArr['token_expires_in'] = time() + $this->tool->time_day;
					$rtn=db('users')->insert($usersArr);
					if($rtn)
					{
						return json(['code'=>$this->tool->success,'data'=>'4','msg'=>'注册成功','status'=>true,]);
					}else{
						return json(['code'=>$this->tool->fail,'data'=>'5','msg'=>'注册失败','status'=>true,]);
					}
				}
			}else{
				return json(['code'=>$this->tool->params_invalid,'data'=>'5','msg'=>'格式错误','status'=>true,]);
			}
		}
    }
	
	/**
	* 邮箱发送6位数的验证码
	*/
	public function Send()
	{
		// 生成随机六位数，不足六位两边补零
		$suiji=str_pad(mt_rand(0, 999999), 6, "0", STR_PAD_BOTH);
		if($this->request->isPost())
		{
			if(!empty(input('post.to'))){
				if($this->tool->SendMail($_POST['to'],'用户',$subject = '验证码', $body = $suiji)){
					$jsonArr = [
						'to' 			=> $_POST['to'],
						'verification'	=> strval($suiji),
					];
					session('verification',strval($suiji));
					return json(['code'=>$this->tool->success,'data'=>$jsonArr,'msg'=>'验证码发送成功','status'=>true,]);
				}
				else{
					return json(['code'=>$this->tool->fail,'data'=>'1','msg'=>'发送失败','status'=>true,]);
				}
			}else{
				return json(['code'=>$this->tool->params_invalid,'data'=>'2','msg'=>'参数错误','status'=>true,]);
			}
		}
    }
	
	/**
	* 检查用户名邮箱是否重复
	*/
	public function emailrepeat(){
		// 接收输入的userName
		$userName=input('post.userName');
		if($this->request->isPost())
		{
			// 判断userName不为空
			if($userName!="")
			{
				// 查询数据库
				$Usersaaa=db('users')->where(['userName' => $userName])->find();
				if($Usersaaa){
					// 有此userName 0
					return json(['code'=>$this->tool->params_invalid,'data'=>'0','msg'=>'邮箱已注册','status'=>true,]);
				}else{
					// 没有userName 1
					return json(['code'=>$this->tool->success,'data'=>'1','msg'=>'success','status'=>true,]);
				}
			}
			
		}
	}
	
	/**
	* 密码找回
	*/
	public function retrievePassword(){
		header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求
		if($this->request->isPost())
		{
			$usersArr=$this->request->param();
			//执行添加
			if($usersArr){
				if ($usersArr['userName'] == '' && $usersArr['password'] == '' && $usersArr['verification'] == ''){
					return json(['code'=>$this->tool->params_invalid,'data'=>'0','msg'=>'请填写完整数据在提交','status'=>true,]);
				}
				if ($usersArr['verification']!=session('verification')){
					return json(['code'=>$this->tool->params_invalid,'data'=>'2','msg'=>'验证码错误','status'=>true,]);
				}else{
					session("verification",null);
				}
				$sql=db('users')->where(['userName' => $usersArr['userName']])->find();
				if ($sql['userName']){
					$rtn=db('users')->where(['uId' => $sql['uId']])->update(['password' => md5($usersArr['password'])]);
					if($rtn)
					{
						return json(['code'=>$this->tool->success,'data'=>$usersArr,'msg'=>'密码修改成功','status'=>true,]);
					}else{
						return json(['code'=>$this->tool->fail,'data'=>'7','msg'=>'密码修改失败','status'=>true,]);
					}
				}else{
					return json(['code'=>$this->tool->params_invalid,'data'=>'4','msg'=>'用户名不存在','status'=>true,]);
				}
			}else{
				return json(['code'=>$this->tool->params_invalid,'data'=>'4','msg'=>'参数错误','status'=>true,]);
			}
			exit();
		}
	}
	
	/**
	* 压缩下载
	*/
	public function zipdownload(){
		if($this->request->isPost()){
			//打包压缩文件
			$zip = new \ZipArchive();
			// $downloadFileName = time().'-'.iconv("UTF-8", "GBK", $this->tool->filterFileName(input("post.title")).'.zip');
			$downloadFileName = time().'-document.zip';
			$downloadFileSrc = iconv("UTF-8", "GBK", './down/');
			$packDownloadFileName = $downloadFileSrc.$downloadFileName;
			$downloadFiles = [];
			$files = explode(",",input("post.zipfiles"));
			for($n=0;$n<count($files);$n++){
				$files[$n] = './'.$files[$n];
			}
			$fileNmaes = explode(",",input("post.name"));
			
			
			// 删除下载文件时间超过1小时的文件
			foreach(glob('./down/*.zip') as $filename)
			{
				$temp = iconv('UTF-8','GBK',substr($filename, 0, strrpos($filename, '-')));
				$temp = intval(substr($temp, strrpos($temp, '/') + 1));
				if(abs(time() - $temp) > $this->tool->time_hour) unlink($filename);
			}
			
			// 创建下载目录
			if(!is_dir($downloadFileSrc)) mkdir($downloadFileSrc,0777,true);
			// 打包文件 OVERWRITE   CREATE
			$res = $zip->open($packDownloadFileName, \ZIPARCHIVE::CREATE);
			var_dump($files);
			var_dump($packDownloadFileName);
			var_dump($res);
			if ($res === TRUE) {
			 foreach ($files as $num=>$file) {
				 if(file_exists($file)){
					// 这里直接用原文件的名字进行打包，也可以直接命名，需要注意如果文件名字一样会导致后面文件覆盖前面的文件，所以建议重新命名
					file_exists($file) ? $zip->addFile($file, iconv("UTF-8", "GBK", $fileNmaes[$num])) : exit('压缩到zip失败。失败文件：'.$fileNmaes[$num].'****'.$file);					 
				 }
			 }
			} else {
				exit('无法打开文件，或者文件创建失败');
			}
			$zip->close();
			
			// 文件缓存至浏览器下载
			header('Content-Type: application/zip');
			header( "Content-Transfer-Encoding: Binary" );
			header("Content-disposition: attachment; filename=".$downloadFileName);
			header('Content-Length: ' . filesize($packDownloadFileName));  
			ob_end_clean();
			$readfileInfo = readfile($packDownloadFileName);
			
			if($readfileInfo){
				$mId = input("post.mId");
				$froid = input("post.froid");
				$inid = input("post.inid");
				$userfro = db('users')->where("uId in($froid)")->select();
				$userin = db('users')->where("uId in($inid)")->select();
				$articlefind = db('article')->where("mId =".$mId)->find();
				if($userfro && $userin){
					if($userfro[0]['permissions']=="2"){
						$per="管理员";
					}else{
						$per="用户";
					}
					$sqlArr["froid"]=$froid;
					$sqlArr["inid"]=$inid;
					$sqlArr["information"]="hello.".$userin[0]['nickname']."，".$per."-".$userfro[0]['nickname']."(".$userfro[0]['userName'].")下载了你的文件，标题为"."--'".$articlefind['title']."'"."--id:".$articlefind['mId'];
					$sqlArr["created"]=time();
					$sqlArr["state"]=1;
					if($froid!=$inid){
						$sql = db('information')->insert($sqlArr);
						
					}
				}
			}
			
		} else {
			exit('下载失败');
		}
	}
	
	/**
	* 下载记录
	*/
	public function downloadInfo(){
		if($this->request->isPost()){
			$mId = input("post.mId");
			$froid = input("post.froid");
			$inid = input("post.inid");
			$userfro = db('users')->where("uId in($froid)")->select();
			$userin = db('users')->where("uId in($inid)")->select();
			$articlefind = db('article')->where("mId =".$mId)->find();
			if($userfro && $userin){
				if($userfro[0]['permissions']=="2"){
					$per="管理员";
				}else{
					$per="用户";
				}
				$sqlArr["froid"]=$froid;
				$sqlArr["inid"]=$inid;
				$sqlArr["information"]="hello.".$userin[0]['nickname']."，".$per."-".$userfro[0]['nickname']."(".$userfro[0]['userName'].")下载了你的文件，标题为"."--'".$articlefind['title']."'"."--id:".$articlefind['mId'];
				$sqlArr["created"]=time();
				$sqlArr["state"]=1;
				if($froid!=$inid){
					$sql = db('information')->insert($sqlArr);
					if ($sql) {
						return json(['code'=>$this->tool->success,'data'=>'','msg'=>'添加成功','status'=>true,]);
					}
				}
			}
		}
	}
	
	/**
	* 记录用户操作
	*/
	public function setOperationInfo(){
		if($this->request->isPost()){
			$data = [
				"uId" 						=> input("post.uId"),
				"type" 						=> input("post.type"),
				"time" 						=> time(),
				"contentText"				=> "",
				"content_groupText"			=> "",
				"content_user"				=> input("post.content_user")!=""?input("post.content_user"):"{}",
				"content_article"			=> input("post.content_article")!=""?input("post.content_article"):"{}",
				"content_auth_group"		=> input("post.content_auth_group")!=""?input("post.content_auth_group"):"{}",
				"content_project"			=> input("post.content_project")!=""?input("post.content_project"):"{}",
				"content_type"				=> input("post.content_type")!=""?input("post.content_type"):"{}",
				"content_classification"	=> input("post.content_classification")!=""?input("post.content_classification"):"{}",
				"content_group_label"		=> input("post.content_group_label")!=""?input("post.content_group_label"):"{}",
				"content_label"				=> input("post.content_label")!=""?input("post.content_label"):"{}",
				"content_article_type"		=> input("post.content_article_type")!=""?input("post.content_article_type"):"{}",
				"content_privacy_type"		=> input("post.content_privacy_type")!=""?input("post.content_privacy_type"):"{}"
			];
			$userInfo = db('users')->where(['uId' => (int)$data["uId"]])->find();
			$userAuthGroupInfo = db('auth_group')->where(['id' => $userInfo["permissions"]])->find();
			$userIfo = [];
			switch($data['type']) {
				case '1':
					$articleUserInfo = db('users')->where(['uId' => (int)$data["content_article"]['start']['uId']])->find();
					$articleUserAuthGroupInfo = db('auth_group')->where(['id' => $articleUserInfo["permissions"]])->find();
					if($data["uId"] == $data["content_article"]['start']['uId']){
						$data['contentText'] = "后台查看文章【".$data['content_article']['start']['title']."】";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]后台查看文章【".$data['content_article']['start']['title']."】";
					} else{
						$data['contentText'] = "后台查看[".$articleUserInfo['nickname']."<".$articleUserAuthGroupInfo['title'].">]发布的文章【".$data['content_article']['start']['title']."】";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]后台查看[".$articleUserInfo['nickname']."<".$articleUserAuthGroupInfo['title'].">]发布的文章【".$data['content_article']['start']['title']."】";
					}
					break;
				case '2':
					$articleUserInfo = db('users')->where(['uId' => (int)$data["content_article"]['start']['uId']])->find();
					$articleUserAuthGroupInfo = db('auth_group')->where(['id' => $articleUserInfo["permissions"]])->find();
					if($data["uId"] == $data["content_article"]['start']['uId']){
						$data['contentText'] = "后台删除文章【".$data['content_article']['start']['title']."】";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]后台删除文章【".$data['content_article']['start']['title']."】";
					} else{
						$data['contentText'] = "后台删除[".$articleUserInfo['nickname']."<".$articleUserAuthGroupInfo['title'].">]的文章【".$data['content_article']['start']['title']."】";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]后台删除[".$articleUserInfo['nickname']."<".$articleUserAuthGroupInfo['title'].">]的文章【".$data['content_article']['start']['title']."】";
						
						$data2 = $data;
						$data2['uId'] = $data["content_article"]['start']['uId'];
						$data2['contentText'] = "你的文章【".$data['content_article']['start']['title']."】被[".$userInfo['nickname']."<".$userAuthGroupInfo['title'].">]删除";
						$data2['content_groupText'] = $articleUserInfo['nickname']."[".$articleUserAuthGroupInfo['title']."]的文章【".$data['content_article']['start']['title']."】被[".$userInfo['nickname']."<".$userAuthGroupInfo['title'].">]删除";
					}
					break;
				case '3':
					$data['contentText'] = "还原回收站文章【".$data['content_article']['start']['title']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]还原回收站文章【".$data['content_article']['start']['title']."】";
					break;
				case '4':
					$data['contentText'] = "删除回收站文章【".$data['content_article']['start']['title']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除回收站文章【".$data['content_article']['start']['title']."】";
					break;
				case '5':
					if($data['content_user']['start']['headPortraitSrc'] != $data['content_user']['end']['headPortraitSrc']){
						$userIfo[0] = "头像修改";
					}
					if($data['content_user']['start']['nickname'] != $data['content_user']['end']['nickname']){
						$userIfo[count($userIfo)] = "昵称：(原)".$data['content_user']['start']['nickname']."=>".$data['content_user']['end']['nickname']."(改)";
					}
					if($data['content_user']['start']['sex'] != $data['content_user']['end']['sex']){
						$userIfo[count($userIfo)] = "性别：(原)".$data['content_user']['start']['sex']."=>".$data['content_user']['end']['sex']."(改)";
					}
					if(count($userIfo)!=0){
						$data['contentText'] = "修改个人信息：".implode(", ",$userIfo);
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改个人信息：".implode(", ",$userIfo);
					} else {
						$data['contentText'] = "修改个人信息：但并没有修改任何数据";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改个人信息：但并没有修改任何数据";
					}
					break;
				case '6':
					$data['contentText'] = "添加新用户【".$data['content_user']['start']['nickname']."】，权限：[".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $data['content_user']['start']['permissions'])."]";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."],添加新用户【".$data['content_user']['start']['nickname']."】，权限：[".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $data['content_user']['start']['permissions'])."]";
					break;
				case '7':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['start']['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();
					if($data['content_user']['start']['nickname'] != $data['content_user']['end']['nickname']){
						$userIfo[0] = "昵称：(原)".$data['content_user']['start']['nickname']."=>".$data['content_user']['end']['nickname']."(改)";
					}
					if($data['content_user']['end']['password'] != ""){
						$userIfo[count($userIfo)] = "密码";
					}
					if($data['content_user']['start']['sex'] != $data['content_user']['end']['sex']){
						if($data['content_user']['start']['sex']==0 && $data['content_user']['end']['sex'] == 1){
							$userIfo[count($userIfo)] = "性别：(原)女=>男(改)";
						} else if($data['content_user']['start']['sex']=="1" && $data['content_user']['end']['sex'] == 0){
							$userIfo[count($userIfo)] = "性别：(原)男=>女(改)";
						} else if($data['content_user']['start']['sex']=="" && $data['content_user']['end']['sex'] == 0){
							$userIfo[count($userIfo)] = "性别：(原)未知=>女(改)";
						} else if($data['content_user']['start']['sex']=="" && $data['content_user']['end']['sex'] == 1){
							$userIfo[count($userIfo)] = "性别：(原)未知=>男(改)";
						}
					}
					if($data['content_user']['start']['permissions'] != $data['content_user']['end']['permissions']){
						$userIfo[count($userIfo)] = "权限：(原)".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $data['content_user']['start']['permissions'])."=>".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $data['content_user']['end']['permissions'])."(改)";
					}
					if($data['content_user']['start']['webShow'] != $data['content_user']['end']['webShow']){
						if($data['content_user']['start']['webShow'] == 0 && $data['content_user']['end']['webShow'] == 1){
							$userIfo[count($userIfo)] = "前台显示：(原)禁用=>启用(改)";
						} else if($data['content_user']['start']['webShow'] == 1 && $data['content_user']['end']['webShow'] == 0){
							$userIfo[count($userIfo)] = "前台显示：(原)启用=>禁用(改)";						
						}
					}
					if($data['content_user']['start']['state'] != $data['content_user']['end']['state']){
						if($data['content_user']['start']['state'] == 0 && $data['content_user']['end']['state'] == 1){
							$userIfo[count($userIfo)] = "账号状态：(原)启用=>禁用(改)";
						} else if($data['content_user']['start']['state'] == 1 && $data['content_user']['end']['state'] == 0){
							$userIfo[count($userIfo)] = "账号状态：(原)禁用=>启用(改)";						
						}
					}
					if(isset($data['content_user']['start']['shieldInfo']) && !isset($data['content_user']['end']['shieldInfo'])){
						$userIfo[count($userIfo)] = "屏蔽项目修改";
					} else if($data['content_user']['start']['shieldInfo'] != $data['content_user']['end']['shieldInfo']){
						$userIfo[count($userIfo)] = "屏蔽项目/类型修改";
					} else if($data['content_user']['start']['shieldInfo'] != $data['content_user']['end']['shieldInfo']){
						for($i=0;$i<=count($data['content_user']['start']['shieldInfo']);$i++){
							for($j=0;$j<=count($data['content_user']['start']['shieldInfo'][$i]['type']);$j++){
								if($data['content_user']['start']['shieldInfo'][$i]['type'][$j]['state'] != $data['content_user']['end']['shieldInfo'][$i]['type'][$j]['state']){
									$userIfo[count($userIfo)] = "屏蔽类型修改";
									return;
								}
							}
						}
					}
						
					if(count($userIfo)!=0){
						$data['contentText'] = "修改用户[".$data['content_user']['start']['nickname']."<".$userAuthGroupInfo2['title'].">]".implode(", ",$userIfo);
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改用户[".$data['content_user']['start']['nickname']."<".$userAuthGroupInfo2['title'].">]".implode(", ",$userIfo);
					} else {
						$data['contentText'] = "修改用户[".$data['content_user']['start']['nickname']."<".$userAuthGroupInfo2['title'].">]但并没有修改任何数据";
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改用户[".$data['content_user']['start']['nickname']."<".$userAuthGroupInfo2['title'].">]但并没有修改任何数据";
					}
					break;
				case '8':
					$data['contentText'] = "还原用户【".$data['content_user']['start']['nickname']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]还原用户【".$data['content_user']['start']['nickname']."】";
					break;
				case '9':
					$data['contentText'] = "添加用户组【".$data['content_auth_group']['start']['title']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加用户组【".$data['content_auth_group']['start']['title']."】";
					break;
				case '10':
					$data['contentText'] = "修改用户组【".$data['content_auth_group']['start']['title']."】=>id：".$data['content_auth_group']['start']['id'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改用户组【".$data['content_auth_group']['start']['title']."】=>id：".$data['content_auth_group']['start']['id'];
					break;
				case '11':
					$data['contentText'] = "删除用户组【".$data['content_auth_group']['start']['title']."】=>id：".$data['content_auth_group']['start']['id'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除用户组【".$data['content_auth_group']['start']['title']."】=>id：".$data['content_auth_group']['start']['id'];
					break;
				case '12':
					$data['contentText'] = "添加项目【".$data['content_project']['start']['xname']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加项目【".$data['content_project']['start']['xname']."】";
					break;
				case '13':
					$data['contentText'] = "修改项目【".$data['content_project']['start']['xname']."】=>id：".$data['content_project']['start']['pid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改项目【".$data['content_project']['start']['xname']."】=>id：".$data['content_project']['start']['pid'];
					break;
				case '14':
					$data['contentText'] = "删除项目【".$data['content_project']['start']['xname']."】=>id：".$data['content_project']['start']['pid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除项目【".$data['content_project']['start']['xname']."】=>id：".$data['content_project']['start']['pid'];
					break;
				case '15':
					$data['contentText'] = "添加类型【".$data['content_type']['start']['lname']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除类型【".$data['content_type']['start']['lname']."】=>id：".$data['content_type']['start']['tid'];
					break;
				case '16':
					$data['contentText'] = "修改类型【".$data['content_type']['start']['lname']."】=>id：".$data['content_type']['start']['tid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改类型【".$data['content_type']['start']['lname']."】=>id：".$data['content_type']['start']['tid'];
					break;
				case '17':
					$data['contentText'] = "删除类型【".$data['content_type']['start']['lname']."】=>id：".$data['content_type']['start']['tid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除类型【".$data['content_type']['start']['lname']."】=>id：".$data['content_type']['start']['tid'];
					break;
				case '18':
					$data['contentText'] = "添加分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】";
					break;
				case '19':
					$data['contentText'] = "修改分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】=>id：".$data['content_classification']['start']['did'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】=>id：".$data['content_classification']['start']['did'];
					break;
				case '20':
					$data['contentText'] = "删除分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】=>id：".$data['content_classification']['start']['did'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除分类【".$data['content_classification']['start']['dname']."】，上级类型【".$data['content_classification']['start']['typeName']."】=>id：".$data['content_classification']['start']['did'];
					break;
				case '21':
					$data['contentText'] = "添加标签组【".$data['content_group_label']['start']['name']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加标签组【".$data['content_group_label']['start']['name']."】";
					break;
				case '22':
					$data['contentText'] = "修改标签组【".$data['content_group_label']['start']['name']."】=>id：".$data['content_group_label']['start']['gid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改标签组【".$data['content_group_label']['start']['name']."】=>id：".$data['content_group_label']['start']['gid'];
					break;
				case '23':
					$data['contentText'] = "删除标签组【".$data['content_group_label']['start']['name']."】=>id：".$data['content_group_label']['start']['gid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除标签组【".$data['content_group_label']['start']['name']."】=>id：".$data['content_group_label']['start']['gid'];
					break;
				case '24':
					$data['contentText'] = "添加标签【".$data['content_label']['start']['name']."】,标签组【".$data['content_label']['start']['pname']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加标签【".$data['content_label']['start']['name']."】,标签组【".$data['content_label']['start']['pname']."】";
					break;
				case '25':
					$data['contentText'] = "修改标签【".$data['content_label']['start']['name']."】=>id：".$data['content_label']['start']['lid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改标签【".$data['content_label']['start']['name']."】=>id：".$data['content_label']['start']['lid'];
					break;
				case '26':
					$data['contentText'] = "删除标签【".$data['content_label']['start']['name']."】=>id：".$data['content_label']['start']['lid'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除标签【".$data['content_label']['start']['name']."】=>id：".$data['content_label']['start']['lid'];
					break;
				case '27':
					$data['contentText'] = "登录后台";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]登录后台";
					break;
				case '28':
					$data['contentText'] = "注销退出";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]注销退出";
					break;
				case '30':
					if($data['content_article']['start']['title'] != $data['content_article']['end']['title']){
						$userIfo[count($userIfo)] = "标题：(原)".$data['content_article']['start']['title']."=>".$data['content_article']['end']['title']."(改)";
					}
					if($data['content_article']['start']['projectid'] != $data['content_article']['end']['pid']){
						$userIfo[count($userIfo)] = "项目：(原)".$data['content_article']['start']['xname']."=>".$this->tool->getDataInfo('IMG_PROJECT', 'pid', 'xname', $data['content_article']['end']['pid'])."(改)";
					}
					if($data['content_article']['start']['typeid'] != $data['content_article']['end']['tid']){
						$userIfo[count($userIfo)] = "类型：(原)".$data['content_article']['start']['lname']."=>".$this->tool->getDataInfo('IMG_TYPE', 'tid', 'lname', $data['content_article']['end']['tid'])."(改)";
					}
					if($data['content_article']['start']['detailsid'] != $data['content_article']['end']['did']){
						$userIfo[count($userIfo)] = "分类：(原)".$data['content_article']['start']['dname']."=>".$this->tool->getDataInfo('IMG_DETAILS', 'did', 'dname', $data['content_article']['end']['did'])."(改)";
					}
					if($data['content_article']['start']['describe'] != $data['content_article']['end']['describe']){
						$userIfo[count($userIfo)] = "描述：(原)".$data['content_article']['start']['describe']."=>".$data['content_article']['end']['describe']."(改)";
					}
					if($data['content_article']['start']['keyword'] != $data['content_article']['end']['keyword']){
						$userIfo[count($userIfo)] = "关键词：(原)".$data['content_article']['start']['keyword']."=>".$data['content_article']['end']['keyword']."(改)";
					}
					if($data['content_article']['start']['typeFile'] == 'img'){
						if(json_encode($data['content_article']['start']['img']) != json_encode($data['content_article']['end']['img'])){
							$userIfo[count($userIfo)] = "上传文件img内容改动";
						}
					}
					if($data['content_article']['start']['typeFile'] == 'psd'){
						if(json_encode($data['content_article']['start']['psd']) != json_encode($data['content_article']['end']['psd'])){
							$userIfo[count($userIfo)] = "上传文件psd内容改动";
						}
					}
					if($data['content_article']['start']['typeFile'] == 'video'){
						if(json_encode($data['content_article']['start']['img']) != json_encode($data['content_article']['end']['img'])){
							$userIfo[count($userIfo)] = "上传文件img补充内容改动";
						}
						if(json_encode($data['content_article']['start']['video']) != json_encode($data['content_article']['end']['video'])){
							$userIfo[count($userIfo)] = "上传文件video内容改动";
						}
					}
					if($data['uId'] == $data['content_article']['start']['uId']){
						$data['contentText'] = "修改文章【".$data['content_article']['start']['title']."】".implode(", ",$userIfo);
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]发布文章【".$data['content_article']['start']['title']."】".implode(", ",$userIfo);
					} else {
						$data['contentText'] = "你修改了[".$this->tool->getDataInfo('IMG_USERS', 'uId', 'nickname', $data['content_article']['end']['uId'])."<".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $this->tool->getDataInfo('IMG_USERS', 'uId', 'permissions', $data['content_article']['end']['uId'])).">]发布的文章【".$data['content_article']['start']['title']."】".implode(", ",$userIfo);
						$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]修改了[".$this->tool->getDataInfo('IMG_USERS', 'uId', 'nickname', $data['content_article']['end']['uId'])."<".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $this->tool->getDataInfo('IMG_USERS', 'uId', 'nickname', $data['content_article']['end']['uId'])).">]发布的文章【".$data['content_article']['start']['title']."】".implode(", ",$userIfo);
						
						$data2 = $data;
						$data2['uId'] = $data["content_article"]['end']['uId'];
						$data2['contentText'] = "你的文章【".$data['content_article']['start']['title']."】被[".$userInfo['nickname']."<".$userAuthGroupInfo['title'].">]修改，内容：".implode(", ",$userIfo);
						$data2['content_groupText'] = $this->tool->getDataInfo('IMG_USERS', 'uId', 'nickname', $data['content_article']['end']['uId'])."<".$this->tool->getDataInfo('IMG_AUTH_GROUP', 'id', 'title', $this->tool->getDataInfo('IMG_USERS', 'uId', 'permissions', $data['content_article']['end']['uId']))."的文章被[".$articleUserInfo['nickname']."<".$articleUserAuthGroupInfo['title'].">]修改，内容：".implode(", ",$userIfo);
					}
					break;
				case '31':
					$data['contentText'] = "发布文章【".$data['content_article']['start']['title']."】";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]发布文章【".$data['content_article']['start']['title']."】";
					break;
				case '32':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();	
					$data['contentText'] = "屏蔽用户[".$userInfo2['nickname']."<".$userAuthGroupInfo2['title'].'>]查看项目:【'.$data['content_project'][0]['xname'].'】';
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]屏蔽用户[".$userInfo2['nickname']."<".$userAuthGroupInfo2['title'].'>]查看项目:【'.$data['content_project'][0]['xname'].'】';
					break;
				case '33':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();	
					for($i = 0;$i<=count($data['content_type'][0]['type']);$i++){
						if($data['content_type'][0]['tid'] == $data['content_type'][0]['type'][$i]['tid'] && $data['content_type'][0]['type'][$i]['state'] == '1'){
							$typeName = $data['content_type'][0]['type'][$i]['lname'];
						}
					}
					$data['contentText'] = "屏蔽用户[".$userInfo2['nickname']."<".$userAuthGroupInfo2['title'].'>]查看项目['.$data['content_type'][0]['xname'].']的类型:【'.$typeName.'】';
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]屏蔽用户[".$userInfo2['nickname']."<".$userAuthGroupInfo2['title'].'>]查看项目['.$data['content_type'][0]['xname'].']的类型:【'.$typeName.'】';
					break;
				case '34':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();	
					for($i = 0;$i<=count($data['content_type'][0]['type']);$i++){
						if($data['content_type'][0]['tid'] == $data['content_type'][0]['type'][$i]['tid'] && $data['content_type'][0]['type'][$i]['state'] == 1){
							$typeName = $data['content_type'][0]['type'][$i]['lname'];
						}
					}
					$data['contentText'] = "添加隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：".$data['content_privacy_type']['start']['userName']['name']."，绑定用户组：".$data['content_privacy_type']['start']['userGroupName']['name'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]添加隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：".$data['content_privacy_type']['start']['userName']['name']."，绑定用户组：".$data['content_privacy_type']['start']['userGroupName']['name'];
					break;
				case '35':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();	
					for($i = 0;$i<=count($data['content_type'][0]['type']);$i++){
						if($data['content_type'][0]['tid'] == $data['content_type'][0]['type'][$i]['tid'] && $data['content_type'][0]['type'][$i]['state'] == 1){
							$typeName = $data['content_type'][0]['type'][$i]['lname'];
						}
					}
					$data['contentText'] = "编辑隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：（原）".$data['content_privacy_type']['start']['userName']['name']."=>".$data['content_privacy_type']['end']['userName']['name']."（改）,绑定用户组：（原）".$data['content_privacy_type']['start']['userGroupName']['name']."=>".$data['content_privacy_type']['end']['userGroupName']['name']."（改）";
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]编辑隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：（原）".$data['content_privacy_type']['start']['userName']['name']."=>".$data['content_privacy_type']['end']['userName']['name']."（改）,绑定用户组：（原）".$data['content_privacy_type']['start']['userGroupName']['name']."=>".$data['content_privacy_type']['end']['userGroupName']['name']."（改）";
					break;
				case '36':
					$userInfo2 = db('users')->where(['uId' => (int)$data["content_user"]['uId']])->find();
					$userAuthGroupInfo2 = db('auth_group')->where(['id' => $userInfo2["permissions"]])->find();	
					for($i = 0;$i<=count($data['content_type'][0]['type']);$i++){
						if($data['content_type'][0]['tid'] == $data['content_type'][0]['type'][$i]['tid'] && $data['content_type'][0]['type'][$i]['state'] == 1){
							$typeName = $data['content_type'][0]['type'][$i]['lname'];
						}
					}
					$data['contentText'] = "删除隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：".$data['content_privacy_type']['start']['userName']['name'].",绑定用户组：".$data['content_privacy_type']['start']['userGroupName']['name'];
					$data['content_groupText'] = $userInfo['nickname']."[".$userAuthGroupInfo['title']."]删除隐私分类【".$data['content_privacy_type']['start']['privacyTypeName']['name']."】，绑定用户：".$data['content_privacy_type']['start']['userName']['name'].",绑定用户组：".$data['content_privacy_type']['start']['userGroupName']['name'];
					break;
			}
			$data = [
				"uId" 						=> $data['uId'],
				"type" 						=> $data['type'],
				"time" 						=> $data['time'],
				"contentText"				=> $data['contentText']."。",
				"content_groupText"			=> $data['content_groupText']."。",
				"content_user"				=> json_encode($data['content_user']),
				"content_article"			=> json_encode($data['content_article']),
				"content_auth_group"		=> json_encode($data['content_auth_group']),
				"content_project"			=> json_encode($data['content_project']),
				"content_type"				=> json_encode($data['content_type']),
				"content_classification"	=> json_encode($data['content_classification']),
				"content_group_label"		=> json_encode($data['content_group_label']),
				"content_label"				=> json_encode($data['content_label']),
				"content_article_type"		=> json_encode($data['content_article_type']),
				"content_privacy_type"		=> json_encode($data['content_privacy_type'])
			];
			
			if($data['type'] == '2' || $data['type'] == '30'){
				$rn = db('operationinfo')->insert($data);
				$rn2 = db('operationinfo')->insert($data2);
				if($rn && $rn2){
					return json(['code'=>$this->tool->success,'data'=>$rn,'msg'=>'已记录','status'=>true]);
				} else {
					return json(['code'=>$this->tool->success,'data'=>"",'msg'=>'记录失败','status'=>true,]);
				}
			} else {
				$rn = db('operationinfo')->insert($data);
				if($rn){
					return json(['code'=>$this->tool->success,'data'=>$rn,'msg'=>'已记录','status'=>true]);
				} else {
					return json(['code'=>$this->tool->success,'data'=>$rn,'msg'=>'记录失败','status'=>true,]);
				}
			}
		}
	}
	
	/**
	* 获取用户操作记录信息
	*/
	public function getOperationInfo(){
		if($this->request->isPost()){
			if(input("post.uId")!=""){
				$data = db('operationinfo')->where(['uId' => input("post.uId")])->order('id DESC')->limit(50)->select();
			} else {
				$data = db('operationinfo')->select();
			}
			return json(['code'=>$this->tool->success,'data'=>$data,'msg'=>'success','status'=>true,]);
		}
	}
	
	/**
	* 用户收藏文章操作：收藏/取消收藏
	*/
	public function collectArticle(){
		if($this->request->isPost()){
			if(input("post.mId")!="" && input("post.uId")!=""){
				// 查询是否有此记录
				$select = db('collect_article')->where(['collectMid' => input("post.mId"), 'collectUid' => input("post.uId")])->select();
				// 有则删除做取消收藏处理
				if($select){
					$where['collectMid']=input('post.mId');
					$where['collectUid']=input('post.uId');
					$delete = db('collect_article')->where($where)->delete();
					if($delete){
						return json(['code'=>$this->tool->success,'data'=>false,'msg'=>'取消收藏成功','status'=>true]);
					} else {
						return json(['code'=>$this->tool->fall,'data'=>true,'msg'=>'取消收藏失败','status'=>true,]);
					}
				} else {
					// 没有则添加收藏梳理
					$data = [
						"collectMid" 				=> input("post.mId"),
						"collectUid" 				=> input("post.uId"),
						"collectTime"				=> time()
					];
					$add = db('collect_article')->insert($data);
					if($add){
						return json(['code'=>$this->tool->success,'data'=>true,'msg'=>'收藏成功','status'=>true]);
					} else {
						return json(['code'=>$this->tool->fall,'data'=>false,'msg'=>'收藏失败','status'=>true,]);
					}
				}
				
		}else if(input("post.s") != "" ){
			$s = input("post.s");
			$log = [];
			for($a=0;$a<count($s);$a++){
				$select = db('collect_article')->where(['collectMid' => $s[$a]['mId'], 'collectUid' => $s[$a]['uId']])->select();
				if($select){
					$where['collectMid']=$s[$a]['mId'];
					$where['collectUid']=$s[$a]['uId'];
					$log[$a] = db('collect_article')->where($where)->delete();
				} else {
					// 没有则添加收藏梳理
					$data = [
						"collectMid" 				=> $s[$a]['mId'],
						"collectUid" 				=> $s[$a]['uId'],
						"collectTime"				=> time()
					];
					$log[$a] = db('collect_article')->insert($data);
				}
			}
			return json(['code'=>$this->tool->success,'data'=>$log,'msg'=>'取消收藏成功','status'=>true]);
		} else {
				return json(['code'=>$this->tool->fall,'data'=>false,'msg'=>'收藏失败','status'=>true,]);
			}
		} else {
			return json(['code'=>$this->tool->fall,'data'=>false,'msg'=>'收藏失败','status'=>true,]);
		}
	}
	
}
