<?php
class Common
{
	public static $debugMode = true;

	/**
	* 说明:写日志文件
	* 参数:$str,日志内容
	*/
	public static function logOutput($str)
	{
		//写到当前目录下
		$logfile = date("Y-m-d")."debug.log";
		file_put_contents($logfile,"\r\n".date("Y-m-d H:i:s")."#".$str."\r\n", FILE_APPEND);
	}

	/**
	 * 说明:获取文件的行数
	 * 参数:$file,需要获取的文件
	 */
	public static function getFileLines($file_name)
	{
		$lines = 0;
		//打开文件
		$fileHandle = $fh = fopen($file_name,'r');
		if ($fileHandle) 
		{
			//判断是否已经达到文件底部
			while (! feof($fileHandle)) 
			{
				//读取一行内容
				if (fgets($fileHandle)) 
				{
					$lines++;
				}
			}
			return array($fileHandle,$lines);
		}
		else
		{
			return array($fileHandle,$lines);
		}
	}
	
 
	public static function hexToStr($hex)//十六进制转字符串
	{   
		$string=""; 
		for($i=0;$i<strlen($hex)-1;$i+=2)
		$string.=chr(hexdec($hex[$i].$hex[$i+1]));
		return  $string;
	}
	
	public static 	function StrToHex($string)//字符串转十六进制
	{ 

		$hex="";
		for($i=0;$i<strlen($string);$i++)
		{
			$hex.=dechex(ord($string[$i]));
		}
		$hex=strtoupper($hex);
		return $hex;
	}  
	

	/**
	 * 说明:在CMD窗口输出中文调试信息
	 * 参数:$string,需要显示的中文信息
	 */
	public static function cmdOutput($string)
	{
		if(Common::$debugMode)
		{
			//$str = mb_convert_encoding("\r\n".date("Y-m-d H:i:s")."#".$string."\r\n", "UTF-8", array('Unicode','ASCII','GB2312','GBK','UTF-8')); //未知原编码，通过auto自动检测后，转换编码为utf-8  
			$str = @mb_convert_encoding("\r\n".date("Y-m-d H:i:s")."#".$string."\r\n", "GB2312", "auto"); //未知原编码，通过auto自动检测后，转换编码为utf-8 
			echo $str;
			//写到当前目录下
			$logfile = date("Y-m-d")."debug.log";
			file_put_contents($logfile,$str, FILE_APPEND);
		}
	}
	
	/**
	 * 说明:十六进制字符转十六进制
	 * 参数:$string,需要转换的字符 eg:"EE 52 5A"
	 * 返回:十六进制. eg:"0xAA 0x52 0x5A"
	 */
	 public static function hexStringToHex($string)
	 {
		$my_string = "";
		$array_string = explode("-",$string);
		for($i=0;isset($array_string[$i]);$i++)  
		{  
			//先将"EE"变为238,再将238变为ascii
			$my_string.= chr(hexdec($array_string[$i]));  
		}  
		return($my_string);  
	 }
	  public static function hexStringToHexSeparator($string,$separator)
	 {
		$my_string = "";
		$array_string = explode($separator,$string);
		for($i=0;isset($array_string[$i]);$i++)  
		{  
			//先将"EE"变为238,再将238变为ascii
			$my_string.= chr(hexdec($array_string[$i]));  
		}  
		return($my_string);  
	 }
	 
	/**
	 * 说明:十六进制数组转十六进制字符串
	 * 参数:$string,需要转换的数组 h (0x68); 
	 * 返回:十六进制. eg:"68"
	 */
	public static 	function stringToHexString($string)//字符串转十六进制
	{ 
		$hex="";
		for($i=0;$i<strlen($string);$i++)
		{
			$hex.=sprintf("%02s",dechex(ord($string[$i])));
		}
		$hex=strtoupper($hex);
		//$hex=sprintf("%02s",$hex);
		return $hex;
	}  
	/**
	 * 说明:十六进制数组转十六进制字符串
	 * 参数:$string,需要转换的数组 h (0x68); 
	 * 返回:十六进制. eg:"68"
	 */
	public static 	function stringToHexStringSeparator($string,$separator)//字符串转十六进制
	{ 
		$hex="";
		for($i=0;$i<strlen($string);$i++)
		{
			$hex.=sprintf("%02s",dechex(ord($string[$i]))).$separator;
		}
		$hex=strtoupper($hex);
		//$hex=sprintf("%02s",$hex);
		return $hex;
	}  
	
	/**
	 * 说明:数组转字符串
	 * 参数:$array,需要转换的数组
	 */
	public static function arrayToString($array)
	{
		$str = "";
		if(isset($array))
		{
			$len = count($array);
			for($i = 0 ; $i < $len ; $i++)  
			{  
				$str.= ($array[$i])." ";  
			} 
		}
		return $str;
	}
	/**
	 * 说明:字符串转十六进制
	 * 参数:字符串，例如：$string = "cfg_power";  
	 * 返回：十六进制,返回0x63,0x66,0x67,0x5f,0x70,0x6f,0x77,0x65,0x72,0x62,0x79
	 */
	public static function stringToHexArray($string)
	{	
		$arr1 = str_split($string, 1);  
		foreach($arr1 as $akey=>$aval){  
			$arr1[$akey]="0x".bin2hex($aval);  
		}  
		return($arr1); 
	}

	/**
	 * 说明:十六进制转字符串
	 * 参数:十六进制，例如：$hex = array(0x63,0x66,0x67,0x5f,0x70,0x6f,0x77,0x65,0x72,0x62,0x79); 
	 * 返回：字符串 ,例如: $myStr = "cfg_power"
	 */
	public static function hexToString($hex)
	{
		$myStr="";  
		for($i=0;isset($hex[$i]);$i++)  
		{  
			$myStr.= chr($hex[$i]);  
		}  
		return($myStr);  
	}
	
	/**
	 * 说明:根据值删除数组相应的元素
	 * 参数:数组,需要删除的值
	 * 返回：数组
	 */
	public static function removeArrayElement(&$ar,$val)
	{
		$tmp = array();
		foreach($ar as $k => $arc)
		{
			if($arc!=$val)
			{
				$tmp[$k]=$arc;
			}
		}
		$ar = $tmp;
		unset($tmp);
	} 

	/**
	 * 说明:根据ip获取城市
	 * 参数:ip
	 * 返回：城市
	 */
	public static function getCity($ip)
	{
		/*
		$url = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
		$ipinfo = json_decode(file_get_contents($url)); 
		//var_dump( $ipinfo);
		if($ipinfo->code=='1'){
			return '未知区域';
		}
		$city = $ipinfo->data->country.$ipinfo->data->region.$ipinfo->data->city;
		
		return $city; 
		*/
		return " ";
	} 

}






