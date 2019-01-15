<?php
/*******************************************************
filename:   	start_server.php
create:     	2018-08-09
revised: 		2019-01-15 laizhengxi;
revision:   	1.0;
description:    iot hub, support tcp and udp
*******************************************************/
use Workerman\Worker;
use Workerman\Lib\Timer;

require_once __DIR__ . '/vendor/workerman/Autoloader.php';
require_once __DIR__ . '/vendor/mysql-master/src/Connection.php';

require_once 'Common.php';

date_default_timezone_set('Asia/ShangHai');

// 通信协议
$ws_worker = new Worker("websocket://0.0.0.0:8080");

// 启动1个进程对外提供服务
$ws_worker->count = 1;

$ws_worker->onWorkerStart = function () {
	Common::cmdOutput("服务器TCP端口:9001");
	Common::cmdOutput("服务器UDP端口:9002");
	Common::cmdOutput("服务器WS 端口:8080");
    require_once __DIR__ . '/Applications/Config/config.php';
    global $db,$ws_worker;
    $db = new Workerman\MySQL\Connection($config['host'], $config['port'], $config['user'], $config['password'], $config['db_name']);
		
    $inner_udp_worker = new Worker('udp://0.0.0.0:9002');
	$inner_tcp_worker = new Worker('Hardware://0.0.0.0:9001');

    // 设置端口复用，可以创建监听相同端口的Worker（需要PHP>=7.0）
    $inner_udp_worker->reusePort = true;
	$inner_tcp_worker->reusePort = true;
	
	// 每10秒执行一次
    $time_interval = 10*60;
    // 给connection对象临时添加一个timer_id属性保存定时器id   $ws_worker->aliveAddressTime[$header_address_str] = date("Y-m-d H:i:s");
	//定时对内存进行回收
    $ws_worker->timer_id = Timer::add($time_interval, function()use($ws_worker,$time_interval)
    {
		Common::cmdOutput("内存回收定时器触发了~".$time_interval."S");		
		//遍历所有的连接时间
		foreach($ws_worker->aliveAddressTime as $k_address => $v_time)
		{
			//该连接最新的连接时间小于指定时间
			if($v_time <= date("Y-m-d H:i:s",strtotime("- 24 hours")))
			{
				//删除对应的键值对,回收内存
				unset($ws_worker->aliveAddressTime[$k_address]);
				unset($ws_worker->udpAddressHandle[$k_address]);
				unset($ws_worker->tcpAddressHandle[$k_address]);
				Common::cmdOutput("被清理的设备IMEI=".$k_address);
			}
		}	
    });

	
	// 一、UDP协议处理
    $inner_udp_worker->onMessage = function ($udp_connection, $data) 
	{
		Common::cmdOutput("UDP onMessage IP=". $udp_connection->getRemoteIp().Common::getCity($udp_connection->getRemoteIp())." data:".$data);
		global $ws_worker;
		//udp协议解析	
		if($data)
		{
			Common::cmdOutput("udp data:".Common::stringToHexStringSeparator($data," "));
			//帧头
			$header = unpack('Chead', $data);
			if($header['head'] != 0x68)
			{
				$ackerror =  "udp error 1!";
				$udp_connection->send($ackerror);
				Common::cmdOutput("UDP数据格式错误");
				/*
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:UDP数据帧头格式错误!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				//*/
				return;
			}
					
			//判定地址是否合法
			//获取地址
			$header_address_hex = substr($data,1,8);
			$header_address_str = Common::stringToHexString($header_address_hex);
			//查询数据库
			global $db;
			$query_address = $db->select('devinfo_mac,devinfo_comment')->from('iot_device_info')->where(" devinfo_mac = '$header_address_str' limit 1 ")->query();
			//是否存在该地址,不存在则退出
			if(!$query_address)
			{
				Common::cmdOutput("数据库没有该UDP设备地址:".$header_address_str);
				$ackerror =  "udp error 2!";
				$udp_connection->send($ackerror);
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:数据库没有该TCP设备!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				return;
			}
			//存在则取出地址
			foreach ($query_address as $item) {
				$devinfo_mac = $item['devinfo_mac'];
				$devinfo_comment = $item['devinfo_comment'];
			}
			//存储连接
			$ws_worker->udpAddressHandle[$header_address_str] = $udp_connection;
			//刷新活跃时间
			$ws_worker->aliveAddressTime[$header_address_str] = date("Y-m-d H:i:s");
			//Common::cmdOutput("ws_worker->udpAddressHandle");
			//判定类型是否合法		
			$type = substr($data,9,1);
			$type = Common::stringToHexArray($type);
			if($type[0] != 0x81)//0x81硬件向服务器发送
			{
				Common::cmdOutput("数据类型不合法:".$header_address_str);
				$ackerror =  "udp error 3!";
				$udp_connection->send($ackerror);
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:UDP数据类型不合法!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				return;
			}
			//数据库存数据	
			//数据总长度		
			$entity_len = hexdec(bin2hex(substr($data,10,1)));	//转为10进制
			//获取上行内容
			$entity_content = substr($data,11,$entity_len - 1);//unpack('Cvalue', $data);
			if($entity_len - 1!= strlen($data) - 11)
			{
				Common::cmdOutput("数据长度异常!");
				$ackerror =  "udp error 4!";
				$udp_connection->send($ackerror);
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:UDP硬件协议数据长度异常!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				return;
			}
			//插入数据库
			$db->insert('iot_device_msg')->cols([
				'devmsg_mac' => $header_address_str,
				'devmsg_len' => $entity_len,
				'devmsg_msg' => trim(Common::stringToHexStringSeparator($entity_content,"-"),"-"),
				'devmsg_orient' => 1,
				'devmsg_status' => 10,
				'devmsg_time' => date("Y-m-d H:i:s")
			])->query();
			//推向WS
			//判定是否有WS用户订阅了该UDP设备
			if(isset($ws_worker->wsAddressToken[$header_address_str]))
			{
				$token = $ws_worker->wsAddressToken[$header_address_str];
				$content = trim(Common::stringToHexStringSeparator($entity_content," ")," ");
				Common::cmdOutput("WS用户订阅了该UDP设备!address:".$header_address_str);
				//向对应的websocket发送接收到的数据
				$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","comment"=>"$devinfo_comment","token"=>"$token");
				$ws_worker->wsTokenHandle[$token]->send(json_encode($ack));
			}
			else
			{
				Common::cmdOutput("WS用户没有订阅该UDP设备!address:".$header_address_str);
				//var_dump($ws_worker->macWsConnections);
			}		
		}
    };
	
	// 二、TCP协议处理
	$inner_tcp_worker->onMessage = function ($tcp_connection, $data)
	{
		Common::cmdOutput("TCP onMessage IP=". $tcp_connection->getRemoteIp().Common::getCity($tcp_connection->getRemoteIp())." data:".$data);
		global $ws_worker;
		global $db;
		if($data)
		{
			Common::cmdOutput("tcp data:".Common::stringToHexStringSeparator($data," "));
			//帧头
			$header = unpack('Chead', $data);
			if($header['head'] != 0x68)
			{
				$ackerror =  "tcp error 1!";
				$tcp_connection->send($ackerror);
				Common::cmdOutput("TCP数据帧头格式错误");
				/*
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:TCP数据帧头格式错误!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				//*/
				return;
			}
			
			//判定类型是否合法		
			$type = substr($data,9,1);
			$type = Common::stringToHexArray($type);
			if($type[0] != 0x81)//0x81硬件向服务器发送
			{
				Common::cmdOutput("数据类型不合法:");
				$ackerror =  "tcp error 2!";
				$tcp_connection->send($ackerror);
				/*
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:TCP数据类型不合法!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				//*/
				return;
			}
			
			//判定地址是否合法
			//获取地址
			$header_address_hex = substr($data,1,8);
			$header_address_str = Common::stringToHexString($header_address_hex);
			//查询数据库
			$query_address = $db->select('devinfo_mac,devinfo_comment')->from('iot_device_info')->where(" devinfo_mac = '$header_address_str' limit 1 ")->query();
			//是否存在该地址,不存在则退出
			if(!$query_address)
			{
				Common::cmdOutput("数据库没有该TCP设备地址:".$header_address_str);
				$ackerror =  "tcp error 3!";
				$tcp_connection->send($ackerror);
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:数据库没有该TCP设备!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				return;
			}		
			//存在则取出地址
			foreach ($query_address as $item) {
				$devinfo_mac = $item['devinfo_mac'];
				$devinfo_comment = $item['devinfo_comment'];
			}
			
			//存储连接
			$ws_worker->tcpAddressHandle[$header_address_str] = $tcp_connection;
			//刷新活跃时间
			$ws_worker->aliveAddressTime[$header_address_str] = date("Y-m-d H:i:s");
			//Common::cmdOutput("ws_worker->macTcpConnections");
			
			//数据总长度		
			$entity_len = hexdec(bin2hex(substr($data,10,1)));	//转为10进制
			//获取上行内容
			$entity_content = substr($data,11,$entity_len - 1);//unpack('Cvalue', $data);
			if($entity_len - 1!= strlen($data) - 11)
			{
				Common::cmdOutput("数据长度异常!");
				$ackerror =  "tcp error 4!";
				$tcp_connection->send($ackerror);
				//判定是否有WS用户订阅了该TCP设备,通过WS告知用户错误
				if(isset($ws_worker->wsAddressToken[$header_address_str]))
				{
					//向对应的websocket发送错误提示
					$token = $ws_worker->wsAddressToken[$header_address_str];
					$content = "ERROR:TCP硬件协议数据长度异常!";
					$ack = array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","token"=>"$token");
					$ws_worker->wsTokenHandle[$token]->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				}
				return;
			}
			//判定是否有WS用户订阅了该TCP设备,通过WS上行数据到用户
			if(isset($ws_worker->wsAddressToken[$header_address_str]))
			{
				//向对应的websocket发送接收到的数据
				$token = $ws_worker->wsAddressToken[$header_address_str];
				$content = trim(Common::stringToHexStringSeparator($entity_content," ")," ");
				$ack = json_encode(array("type"=>"content","address"=>"$header_address_str","msg"=>"$content","comment"=>"$devinfo_comment","token"=>"$token"));							
				$ws_worker->wsTokenHandle[$token]->send($ack);
				Common::cmdOutput("WS用户订阅了该TCP设备!json=".$ack);
				
				
				
				//插入数据库
				/*
				$db->insert('iot_device_msg')->cols([
					'devmsg_mac' => $header_address_str,
					'devmsg_len' => $entity_len,
					'devmsg_msg' => trim(Common::stringToHexStringSeparator($entity_content,"-"),"-"),
					'devmsg_orient' => 1, 	//上行
					'devmsg_status' => 11,	//已接收
					'devmsg_time' => date("Y-m-d H:i:s")
				])->query();
				*/
			}
			else
			{
				Common::cmdOutput("WS用户没有订阅该TCP设备!address:".$header_address_str);
				//插入数据库
				$db->insert('iot_device_msg')->cols([
					'devmsg_mac' => $header_address_str,
					'devmsg_len' => $entity_len,
					'devmsg_msg' => trim(Common::stringToHexStringSeparator($entity_content,"-"),"-"),
					'devmsg_orient' => 1, 	//上行
					'devmsg_status' => 10,	//待接收
					'devmsg_time' => date("Y-m-d H:i:s")
				])->query();	
			}
		}
	}; 
	$inner_tcp_worker->onClose = function ($tcp_connection)
	{
		global $ws_worker;
		Common::cmdOutput("TCP设备主动断开:ip:".$tcp_connection->getRemoteIp().Common::getCity($tcp_connection->getRemoteIp()).",设备IMEI清单如下:");	
		//通过句柄查找对应的address(一个网关可以带多个设备,所以可以有多个映射)
		foreach($ws_worker->tcpAddressHandle as $k_address => $v_handle)
		{
			//该句柄对应的设备IMEI
			if($v_handle == $tcp_connection)
			{
				Common::cmdOutput($k_address);
				//删除,回收内存
				unset($ws_worker->tcpAddressHandle[$k_address]);
			}
		}
	};
	$inner_tcp_worker->listen();	
	$inner_udp_worker->listen();
};

// 新增加属性，用来保存各个映射
//websocket映射
$ws_worker->wsTokenHandle  		= array();		//下标:token;值:websocket句柄
$ws_worker->wsAddressToken 		= array();		//下标:硬件地址;值:token
//udp映射
$ws_worker->udpAddressMsg  		= array();		//下标:硬件地址;值:要下发给硬件的内容
$ws_worker->udpAddressHandle 	= array();		//下标:硬件地址;值:udp句柄
//tcp映射
$ws_worker->tcpAddressMsg 		= array();		//下标:硬件地址;值:要下发给硬件的内容
$ws_worker->tcpAddressHandle 	= array();		//下标:硬件地址;值:tcp句柄
//设备活跃的地址时间表
$ws_worker->aliveAddressTime    = array();		//下标:硬件地址;值最新的上传数据时间

$ws_worker->onConnect = function($ws_connection){
	Common::cmdOutput("WebSocket第三方应用[接入] IP=". $ws_connection->getRemoteIp()." Port=" . $ws_connection->getRemotePort());
};

//收到websocket数据
$ws_worker->onMessage = function ($ws_connection, $data) {
	global $ws_worker;
	global $db;	   
	Common::cmdOutput("WS onMessage IP=". $ws_connection->getRemoteIp().Common::getCity($ws_connection->getRemoteIp())." data:".$data);
	$data_array =  json_decode($data);
	//不是json就退出
	if(!$data_array)
	{
		Common::cmdOutput("WS发过来的不是json!");
		return;
	}
	/*
	//类型:register设备注册、delete设备删除、update设备修改、address和content设备数据订阅与发布
	if(!isset($data['type']))
	{
		Common::cmdOutput("json key错!");
		return;
	} 
	*/
	$type = $data_array->type;
	
	if($type == "heart")
	{
		Common::cmdOutput($ws_connection->getRemoteIp()." 心跳!");
		$ack = array("type"=>"heart");
		$ws_connection->send(json_encode($ack));
		return;
	}
	
	//ws客户端发来查看是否有设备
	if($type == "check")
	{
		Common::cmdOutput("check设备是否存在!");
		//获取设备imei
		$imei = $data_array->msg;

		//检索数据库是否存在
		global $db;
		$query_imei = $db->select('devinfo_mac')->from('iot_device_info')->where(" devinfo_mac = '$imei' limit 1 ")->query();
		//是否存在该地址,不存在则添加
		if(!$query_imei)
		{
			Common::cmdOutput("check无此设备!");
			$ack = array("type"=>"check","msg"=>"0","address"=>$imei);
			$ws_connection->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
			return;
		}
		else
		{
			Common::cmdOutput("check有此设备!");
			//应答给ws客户端,设备已经被注册
			$ack = array("type"=>"check","msg"=>"1","address"=>$imei);
			$ws_connection->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
			return;
		}
	}
	
	
	//ws客户端发来register设备注册
	if($type == "register")
	{		
		//获取设备imei
		$imei = $data_array->msg;
		Common::cmdOutput("register设备注册!");
		//没有添加设备备注
		if(!$data_array->comment)
		{
			Common::cmdOutput("register抱歉~需要添加备注信息!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"register","msg"=>"2","address"=>$imei);
			$ws_connection->send(json_encode($ack));
			return;
		}

		//判定地址是否合法
		$pattern = '/^[0-9a-fA-F]*$/';  
		if(!preg_match($pattern,$imei))
		{
			Common::cmdOutput("register抱歉~设备地址不合法!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"register","msg"=>"3","address"=>$imei);
			$ws_connection->send(json_encode($ack));
			return;
		}
		//检索数据库是否存在
		global $db;
		$query_imei = $db->select('devinfo_mac')->from('iot_device_info')->where(" devinfo_mac = '$imei' limit 1 ")->query();
		//是否存在该地址,不存在则添加
		if(!$query_imei)
		{
			//$token = md5(date("Y-m-d H:i:s")."*#06@".$imei);
			$token = md5($imei);
			//插入数据库
			$db->insert('iot_device_info')->cols([
				'devinfo_mac' => $imei,
				'devinfo_mac_token' => $token,
				'devinfo_create_ip' => "192.168.0.110",
				'devinfo_create_time' => date("Y-m-d H:i:s"),
				'devinfo_update_time' => "-",
				'devinfo_is_dele' => 0,
				'devinfo_dele_time' => "+",
				'devinfo_comment' => trim($data_array->comment)
			])->query();
			Common::cmdOutput("register设备注册成功!");
			$ack = array("type"=>"register","msg"=>"0","address"=>$imei,"token"=>"$token","comment"=>$data_array->comment);
			$ws_connection->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
			return;
		}
		else
		{
			Common::cmdOutput("register抱歉~设备已经被注册!");
			//应答给ws客户端,设备已经被注册
			$ack = array("type"=>"register","msg"=>"1","address"=>$imei);
			$ws_connection->send(json_encode($ack));
			return;
		}
	}
	
	//ws客户端发来delete设备删除
	if($type == "delete")
	{
		Common::cmdOutput("delete设备删除!");
		//获取设备imei
		$imei = $data_array->msg;
		//获取设备token
		$token = $data_array->token;
		//判定地址是否合法
		$pattern = '/^[0-9a-fA-F]*$/';  
		if(!preg_match($pattern,$imei))
		{
			Common::cmdOutput("delete抱歉~设备地址不合法!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"delete","address"=>"$imei","msg"=>"3");
			$ws_connection->send(json_encode($ack));
			return;
		}
		//检索数据库是否存在
		global $db;
		$query_imei = $db->select('devinfo_mac,devinfo_mac_token')->from('iot_device_info')->where(" devinfo_mac = '$imei' limit 1 ")->query();
		//是否存在该地址,不存在则提示
		if(!$query_imei)
		{
			Common::cmdOutput("delete抱歉~无此设备!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"delete","address"=>"$imei","msg"=>"1");
			$ws_connection->send(json_encode($ack));
			return;
		}
		else
		{
			//存在则取出token
			foreach ($query_imei as $item) {
				$devinfo_mac_token = $item['devinfo_mac_token'];
			}
			//token错误
			if($devinfo_mac_token != $token)
			{
				Common::cmdOutput("delete抱歉~token错误!");
				//应答给ws客户端-设备需要添加备注
				$ack = array("type"=>"delete","msg"=>"4","address"=>"$imei");
				$ws_connection->send(json_encode($ack));
				return;
			}
			else
			{
				//删除设备
				$row_count = $db->delete('iot_device_info')->where("devinfo_mac='$imei'")->query();
				if($row_count != 1)
				{
					Common::cmdOutput("delete设备删除未知错误!");
					$ack = array("type"=>"delete","msg"=>"2","address"=>"$imei","token"=>"$token");
					$ws_connection->send(json_encode($ack));
					return;
				}
				unset($ws_worker->wsTokenHandle[$token]);
				unset($ws_worker->wsAddressToken[$imei]);
		
				Common::cmdOutput("delete设备删除成功!");
				$ack = array("type"=>"delete","msg"=>"0","address"=>"$imei","token"=>"$token");
				$ws_connection->send(json_encode($ack));
				return;
			}
		}
	}
	//ws客户端发来update设备修改
	if($type == "update")
	{
		Common::cmdOutput("update设备修改!");
		//获取需要修改的设备imei
		$src_imei = $data_array->src_msg;			
		//获取需要修改的设备token
		$src_token = $data_array->src_token;
		//获取目的设备imei
		$dst_imei = $data_array->dst_msg;
		//获取目的设备的备注
		$dst_comment = $data_array->dst_comment;
		//判定地址是否合法
		$pattern = '/^[0-9a-fA-F]*$/';  
		if(!preg_match($pattern,$src_imei))
		{
			Common::cmdOutput("update抱歉~src设备地址不合法!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"update","src_msg"=>"2","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
			$ws_connection->send(json_encode($ack));
			return;
		}
		if(!preg_match($pattern,$dst_imei))
		{
			Common::cmdOutput("update抱歉~dst设备地址不合法!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"update","dst_msg"=>"6","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
			$ws_connection->send(json_encode($ack));
			return;
		}
		if(!$dst_comment)
		{
			Common::cmdOutput("update抱歉~dst设备备注不能为空!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"update","dst_msg"=>"7","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
			$ws_connection->send(json_encode($ack));
			return;
		}
		//检索数据库是否存在src_imei
		global $db;
		$query_imei = $db->select('devinfo_mac,devinfo_mac_token')->from('iot_device_info')->where(" devinfo_mac = '$src_imei' limit 1 ")->query();
		//是否存在该地址,不存在则提示
		if(!$query_imei)
		{
			Common::cmdOutput("update~无此src设备!");
			//应答给ws客户端-设备需要添加备注
			$ack = array("type"=>"update","src_msg"=>"1","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
			$ws_connection->send(json_encode($ack));
			return;
		}
		else
		{
			//存在则取出token
			foreach ($query_imei as $item) {
				$devinfo_mac_token = $item['devinfo_mac_token'];
			}
			//token错误
			if($devinfo_mac_token != $src_token)
			{
				Common::cmdOutput("update~src设备的token错误!");
				//应答给ws客户端-设备需要添加备注
				$ack = array("type"=>"update","src_msg"=>"3","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
				$ws_connection->send(json_encode($ack));
				return;
			}
			else
			{
				//如果仅仅是修改备注
				if($src_imei == $dst_imei)
				{
					$row_count = $db->update('iot_device_info')->cols(array('devinfo_comment'=>"$dst_comment",'devinfo_update_time'=>date("Y-m-d H:i:s")))->where("devinfo_mac='$src_imei'")->query();
				
					if($row_count != 1)
					{
						Common::cmdOutput("update设备修改备注未知错误!");
						$ack = array("type"=>"delete","src_msg"=>"2","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
						$ws_connection->send(json_encode($ack));
						return;
					}
					Common::cmdOutput("update设备修改备注成功!");
					$ack = array("type"=>"update","src_msg"=>"0","src_token"=>"$src_token","dst_msg"=>"$dst_imei","dst_token"=>"$src_token","dst_comment"=>"$dst_comment");
					$ws_connection->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
					return;					
				}
				
				//修改设备
				$query_dst_imei = $db->select('devinfo_mac,devinfo_mac_token')->from('iot_device_info')->where(" devinfo_mac = '$dst_imei' limit 1 ")->query();
				//所修改的dst_imei已经被注册了
				if($query_dst_imei)
				{
					Common::cmdOutput("update~dst设备已存在!");
					$ack = array("type"=>"update","src_msg"=>"4","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
					$ws_connection->send(json_encode($ack));
					return;
				}
				//$dst_token = md5(date("Y-m-d H:i:s")."*#06@".$dst_imei);
				$dst_token = md5($dst_imei);
				$row_count = $db->update('iot_device_info')->cols(array('devinfo_mac'=>"$dst_imei",'devinfo_mac_token'=>"$dst_token",'devinfo_comment'=>"$dst_comment",'devinfo_update_time'=>date("Y-m-d H:i:s")))->where("devinfo_mac='$src_imei'")->query();
				
				if($row_count != 1)
				{
					Common::cmdOutput("update设备修改未知错误!");
					$ack = array("type"=>"delete","src_msg"=>"2","dst_msg"=>"$dst_imei","src_token"=>"$src_token");
					$ws_connection->send(json_encode($ack));
					return;
				}
				Common::cmdOutput("update设备修改成功!");
				$ack = array("type"=>"update","src_msg"=>"0","src_token"=>"$src_token","dst_msg"=>"$dst_imei","dst_token"=>"$dst_token","dst_comment"=>"$dst_comment");
				$ws_connection->send(json_encode($ack, JSON_UNESCAPED_UNICODE));
				return;
			}
		}
	}
	
	//WS客户端发来[订阅数据](设置地址)
	if($type == "address")
	{
		//取出地址
		$imei = $data_array->msg;
		//取出token
		$token = $data_array->token;
		//查询数据库,地址和token是否合法
		$query = $db->select('devinfo_id')->from('iot_device_info')->where(" devinfo_mac = '$imei' and devinfo_mac_token = '$token' limit 1 ")->query();
		//devinfo不存在
		if(!$query)
		{
			Common::cmdOutput("IMEI或Token有误!");
			$ack = array("type"=>"address","address"=>"$imei","msg"=>"1","token"=>"$token");
			$ws_connection->send(json_encode($ack));
			return;
		}
		$ws_worker->wsTokenHandle[$token] = $ws_connection;
		$ws_worker->wsAddressToken[$imei] = $token;
		//应答给ws客户端设置地址
		$ack = array("type"=>"address","msg"=>"0","address"=>"$imei","token"=>"$token");
		$ws_connection->send(json_encode($ack));			
		Common::cmdOutput("订阅数据成功: IMEI=".$data_array->msg);
	}
	//ws客户端发来[发布数据](需要转发到硬件)
	if($type == "content")
	{
		//取出地址
		$imei = $data_array->address;
		//取出token
		$token = $data_array->token;
		//查询数据库,地址和token是否合法
		$query = $db->select('devinfo_id')->from('iot_device_info')->where(" devinfo_mac = '$imei' and devinfo_mac_token = '$token' limit 1 ")->query();
		//var_dump($query);
		//devinfo不存在
		if(!$query)
		{
			Common::cmdOutput("IMEI或Token有误!");
			//$ack = array("type"=>"content","msg"=>"1","token"=>"$token");
			//$ws_connection->send(json_encode($ack));
			return;
		}
		
		//$ws_worker->msgUdpConnections[$data_array->address] = $data_array->msg;
		//判定是否有指定的TCP设备与服务器相连时
		if(isset($ws_worker->tcpAddressHandle[$data_array->address]))
		{
			$string = $data_array->msg;
			$string = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$string);
			$string = "68" . $data_array->address . "01" . sprintf("%02d",strlen($string)/2+1) . $string;
			$str = pack("H*", $string);
			//发送到硬件
			$ws_worker->tcpAddressHandle[$data_array->address]->send($str);
			Common::cmdOutput("数据已经通过TCP下行到设备，address : ".$data_array->address);
		}
		//判定是否有指定的UDP设备与服务器相连时 2018年5月9日
		if(isset($ws_worker->udpAddressHandle[$data_array->address]))
		{
			$string = $data_array->msg;
			$string = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$string);
			$string = "68" . $data_array->address . "01" . sprintf("%02d",strlen($string)/2+1) . $string;
			$str = pack("H*", $string);
			//发送到硬件
			$ws_worker->udpAddressHandle[$data_array->address]->send($str);
			Common::cmdOutput("数据已经通过UDP下行到设备，address : ".$data_array->address." content : ".$string);
		}
	}
	
};

// 当有客户端连接断开时
$ws_worker->onClose = function($ws_connection)
{	
	global $ws_worker;
	//待删除的token_handle键值对
	$token_array = array();
	//通过句柄查找对应的token(WebSockets可以订阅多个设备,所以可以有多个映射)
	foreach($ws_worker->wsTokenHandle as $k_token => $v_handle)
	{
		//该句柄订阅过设备数据
		if($v_handle == $ws_connection)
		{
			//取出需要删除的键值对
			$token_array[$k_token] = $v_handle;
			//删除,回收内存
			unset($ws_worker->wsTokenHandle[$k_token]);
		}
	}
	$token_array_length = count($token_array);
	//通过token查找对应的address(WebSockets可以订阅多个设备,所以可以有多个映射)
	if($token_array_length > 0)
	{
		foreach($token_array as $k_token => $v_handle)
		{
			//根据token删除元素
			Common::removeArrayElement($ws_worker->wsAddressToken,$k_token);
		}
	}
	
	Common::cmdOutput("第三方应用[离开]资源已释放 IP=" . $ws_connection->getRemoteIp().Common::getCity($ws_connection->getRemoteIp()));
};
// 如果不是在根目录启动，则运行runAll方法
if (!defined('GLOBAL_START')) {
    Worker::runAll();
}