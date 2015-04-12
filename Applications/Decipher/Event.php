<?php
/**
 * 
 * 主逻辑
 * 主要是处理 onMessage onClose 三个方法
 * @author walkor <walkor@workerman.net>
 * 
 */

use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;
class Event
{
   /**
    * 有消息时
    * @param int $client_id
    * @param string $message
    */
   private static $db;
   private static $client_name;
   private static $client_pass;
   public static function onMessage($client_id, $message)
   {
        // 获取客户端请求
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }
	global $db;
        $db = Db::instance('DecipherDb');
        switch($message_data['type'])
        {      
	    case '0':
		echo "成功";
                global $client_name;
		global $client_pass;

		$client_name = $message_data['account'];
		$client_pass = $message_data['password']; 

                if($client_pass == $db->single("select password from UserInf
                                                where userName = '$client_name' ")){
                        echo "登录";
			$re_client_id = $client_id + 1;
                 	$row_cout = $db->query("update Id_Name set clientId = '$re_client_id'
                                                where clientName = '$client_name' ");
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"true"}');
		}
                else{
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"false"}');
			echo "失败";
		}
	    break;
            case '1':
		
		$insert_User = $db->query("insert into UserInf
                              (password,userName,birth,gender,avatarId,isOnline,gameInf)
                              values ('','','','','','','')");
		$insert_Id = $db->query("insert into Id_Name
					 (clientId,clientName) values (-1,'')");
            // 聊天
            case '2':
		$reClientName = $message_data['re_account'];
		$reClientId = $db->single("select clientId from Id_Name
                                           where clientName = '$reClientName' ");
	
	        return Gateway::sendToClient($reClientId,$message_data['message']); 
        }
   }
   
   /**
    * 当用户断开连接时
    * @param integer $client_id 用户id
    */
   public static function onClose($client_id)
   {
       // 广播 xxx 退出了
       global $client_name;
       global $db;
       
       echo $client_name;
       $db = Db::instance('DecipherDb');
       $row_cout = $db->query("update Id_Name set clientId = -1
                               where clientName = '$client_name' ");	
       GateWay::sendToAll(json_encode(array('type'=>'closed', 'id'=>$client_id)));
   }
}
