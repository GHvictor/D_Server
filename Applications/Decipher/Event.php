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

        switch($message_data['type']){ 
            // Login   
	    case '0':
		$client_account = $message_data['account'];
		$client_pass = $message_data['password']; 
		echo "$client_account\n";

                if($client_pass == $db->single("select password from UserInf
                                                where userAccount = '$client_account' ")){
                        echo "登录";
			$re_client_id = $client_id + 1;
                 	$row_cout = $db->query("update IdAccount set reId = '$re_client_id'
                                                where reAccount = '$client_account' ");
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"true"}+++++');
		}
                else{
			Gateway::sendToCurrentClient('{"re_type":"0","re_message":"false"}+++++');
			echo "失败";
		}
	    	break;

           // Register
           case '1':	
		if(!$db->single("select userAccount from UserInf
				 where userAccount = '$message_data[account]'")){
			$insert_User = $db->query("insert into UserInf (userAccount, password,
						   userName, userPhoto, userPhone, userEmail,
                                                   birth, gender, signupTime) values (
						'$message_data[account]','$message_data[password]',
					        '$message_data[name]','$message_data[photo]',
						'$message_data[phone]','$message_data[email]',
						'$message_data[birth]','$message_data[sex]',
						 SYSDATE() ) ");
			$insert_Id = $db->query("insert into IdAccount (reId, reAccount)
						 values (-1,'$message_data[account]')");
	                $insert_Shake = $db->query("insert into ShakeList (shakeAccount,shakeTime)
				                    values ('$message_data[account]',0)");
			$insert_GameOne = $db->query("insert into GameOne (gameAccount) values
						     ('$message_data[account]')");
			Gateway::sendToCurrentClient('{"re_type":"1","re_message":"true"}+++++');
		}
		else{
			Gateway::sendToCurrentClient('{"re_type":"1","re_message":"false"}+++++');
		}
	    	break;

            // Chat
            case '2':
/*		$reClientName = $message_data['re_account'];
		$reClientId = $db->single("select clientId from IdAccount
                                           where reAccount = '$message_data[re_account]' ");
	        return Gateway::sendToClient($reClientId,$message_data['message']);  */

	    // Shake
	    case '3':
		$db->query("update ShakeList set shakeTime = SYSDATE() 
		            where shakeAccount = '$message_data[account]' ");
         	sleep(4);
		$res = $db->query("select userAccount,userPhoto,gender,userName
				   from UserInf where userAccount = (select shakeAccount
                                   from ShakeList where shakeAccount <> '$message_data[account]' 
                                   and ABS(TIMEDIFF( (select shakeTime from ShakeList
                                                      where shakeAccount = '$message_data[account]'),
                                                      shakeTime) )<30 order by ABS(
                                                      TIMEDIFF( (select shakeTime from ShakeList
			              			      where shakeAccount='$message_data[account]'),
				                      shakeTime) ) ASC limit 1 offset 0)");

                if($res){
		//	print_r($res);
			$sendMessage = '{"re_type":"3","re_message":[';
                	foreach($res as $key => $k){
                        	$sendMessage = $sendMessage."{\"re_account\":\"$k[userAccount]\",".
							     "\"re_photo\":\"$k[userPhoto]\",".
						             "\"re_gender\":\"$k[gender]\",".
							     "\"re_name\":\"$k[userName]\"},";
                	}
                	$re_message = substr($sendMessage, 0, strlen($sendMessage)-1);
                	$re_message = $re_message."]}+++++";
                	echo "$re_message \n";
		}else{
		     $re_message = '{"re_type":"3","re_message":"false"}+++++';
		}
		Gateway::sendToCurrentClient($re_message);
	   	break;

	   //FriendList
	   case '4':
		$res = $db->query("select userAccount,userPhoto,gender,userName
                                   from UserInf A,FriendList B where A.userAccount = B.friAccount
                                   and B.friUser = '$message_data[account]' ");
                if($res){
                //      print_r($res);
			$sendMessage = '"re_type":"4","re_message":[';
			foreach($res as $key => $k){
				$sendMessage = $sendMessage."{\"re_account\":\"$k[userAccount]\",".
                                                             "\"re_photo\":\"$k[userPhoto]\",".
                                                             "\"re_gender\":\"$k[gender]\",".
                                                             "\"re_name\":\"$k[userName]\"},";
			}
			$re_message = substr($sendMessage, 0, strlen($sendMessage)-1);
			$re_message = $re_message."]}+++++";
			echo "$re_message \n";
                }else{
			$re_message = '{"re_type":"4","re_message":"false"}+++++';
                        echo "并没有 \n";
                }
		Gateway::sendToCurrentClient($re_message);
		break;

           //GameOneRecieve
	   case '5':
		$res = $db->row("select rock,scissors,paper from GameOne where
		        	   gameAccount = '$message_data[account]'");
		$res_grade =  $db->single("select grade from GameOne where
					   gameAccount = '$message_data[friend]'");
		if($res && $res_grade){
			print_r($res);
		
			$sendMessage = "{\"re_type\":\"5\",".
				        "\"re_grade\":\"$res_grade\",".
					"\"re_rock\":\"$res[rock]\",".
					"\"re_scissors\":\"$res[scissors]\",".
					"\"re_paper\":\"$res[scissors]\"}+++++";
			echo "$sendMessage \n";
		}else{
		     $sendMessage = '{"re_type":"5","re_message":"false"}+++++';
		}
		Gateway::sendToCurrentClient($sendMessage);
	   	break;

	   //GameOneSend
	   case '6':
		$db->query("update GameOne set rock = '$message_data[rock]',
                            scissors = '$message_data[scissors]', 
			    paper = '$message_data[paper]'
                            where gameAccount = '$message_data[account]'");
	   	break;

	   //GameOneGrade
	   case '7':
		$db->query("update GameOne set grade = '$message_data[grade]'
			    where gameAccount = '$message_data[account]'");
		
        }
   }
   
   /**
    * 当用户断开连接时
    * @param integer $client_id 用户id
    */
   public static function onClose($client_id)
   {
       // 广播 xxx 退出了
       global $db;
       $db = Db::instance('DecipherDb');

       echo "$client_id \n";
       $re_client_id = $client_id + 1;
       $row_cout = $db->query("update IdAccount set reId = -1
                               where reId = '$re_client_id' ");	
       GateWay::sendToAll("{\"re_type\":\"close\",\"id\":\"'$client_id'\"}+++++");
   }
}
