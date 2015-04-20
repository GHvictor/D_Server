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
/**
 *Code by Feng
 */
class Event
{
   /**
    * 有消息时
    * @param int $client_id
    * @param string $message
    */
   private static $db;
   private static $client_account;
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
	global $client_account;
	global $client_pass;
        $db = Db::instance('DecipherDb');
        switch($message_data['type'])
        {   // Login   
	    case '0':
		echo "成功";
		$client_account = $message_data['account'];
		$client_pass = $message_data['password']; 

                if($client_pass == $db->single("select password from UserInf
                                                where userAccount = '$client_account' ")){
                        echo "登录";
			$re_client_id = $client_id + 1;
                 	$row_cout = $db->query("update IdName set clientId = '$re_client_id'
                                                where clientName = '$client_account' ");
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"true"}');
		}
                else{
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"false"}');
			echo "失败";
		}
	    break;
/*          // Register
            case '1':
		
		$insert_User = $db->query("insert into UserInf
                              (password,userName,birth,gender,avatarId,isOnline,gameInf)
                              values ('','','','','','','')");
		$insert_Id = $db->query("insert into IdName
					 (clientId,clientName) values (-1,'')");
		$insert_Shake = $db->query("insert into ShakeList 
		                          (clientAccount,shakeTime) values ('$',0)");
*/
            // Chat
            case '2':
		$reClientName = $message_data['re_account'];
		$reClientId = $db->single("select clientId from IdName
                                           where clientName = '$reClientName' ");
	        return Gateway::sendToClient($reClientId,$message_data['message']);
	    // Shake
	    case '3':
	    //	$client_account = $message_data['account'];
		$db->query("update ShakeList set shakeTime = SYSDATE() 
		            where clientAccount = '$client_account' ");
		$res = $db->query("select userAccount,userPhoto,gender,userName
				   from UserInf where userAccount = (select clientAccount
                                   from ShakeList where clientAccount<>'$client_account' 
                                   and ABS(TIMEDIFF( (select shakeTime from ShakeList
                                                      where clientAccount='$client_account'),
                                                      shakeTime) )<3 order by ABS(
                                                      TIMEDIFF( (select shakeTime from ShakeList
			              			        where clientAccount='$client_account'),
				                      shakeTime) ) ASC limit 1 offset 0)");

                if($res){
			print_r($res);
			$sendMessage = '{"re_type":"3","re_message":[';
                	foreach($res as $key => $k){
                        	$sendMessage = $sendMessage."{\"re_account\":\"$k[userAccount]\",".
							     "\"re_photo\":\"$k[userPhoto]\",".
						             "\"re_gender\":\"$k[gender]\",".
							     "\"re_name\":\"$k[userName]\"},";
                	}
                	$re_message = substr($sendMessage, 0, strlen($sendMessage)-1);
                	$re_message = $re_message."]}";
                	echo "$re_message \n";
		}else{
		     $re_message = '{"re_type":"3","re_message":"false"}';
		}
		Gateway::sendToCurrentClient($re_message);
		break;
	   case '4':
//		$client_account = $message_data['acount'];
//		$db->query("");
        }
   }
   
   /**
    * 当用户断开连接时
    * @param integer $client_id 用户id
    */
   public static function onClose($client_id)
   {
       // 广播 xxx 退出了
       global $client_account;
       global $db;
       
       echo $client_account;
       $db = Db::instance('DecipherDb');
       $row_cout = $db->query("update IdName set clientId = -1
                               where clientName = '$client_account' ");	
       GateWay::sendToAll(json_encode(array('type'=>'closed', 'id'=>$client_id)));
   }
}
