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
                                                where userAccount = '$client_account' ") ||
			-1 == $db->single("select reId from IdAccount where reAccount
					   = '$client_account' ")){
                        echo "登录 $client_id\n";
			//$re_client_id = $client_id + 1;
                 	$row_cout = $db->query("update IdAccount set reId = '$client_id'
                                                where reAccount = '$client_account' ");
			$res = $db->row("select userName, smallPhoto, gender, userPhone, userEmail, birth
					 from UserInf where userAccount = '$client_account'");
			$sendMessage = "{\"re_type\":\"0\",".
					"\"re_message\":\"true\",".
                                        "\"re_name\":\"$res[userName]\",".
                                        "\"re_photo\":\"$res[smallPhoto]\",".
                                        "\"re_gender\":\"$res[gender]\",".
                                        "\"re_phone\":\"$res[userPhone]\",".
                                        "\"re_email\":\"$res[userEmail]\",".
					"\"re_birth\":\"$res[birth]\"}+++++";
			Gateway::sendToCurrentClient($sendMessage);
		//	Gateway::sendToCurrentClient('{"re_type":"0","re_message":"true"}+++++');
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
                                                   birth, gender, signupTime, smallPhoto) values (
						'$message_data[account]','$message_data[password]',
					        '$message_data[name]','$message_data[photo]',
						'$message_data[phone]','$message_data[email]',
						'$message_data[birth]','$message_data[sex]',
						 SYSDATE(), '$message_data[sphoto]' ) ");
			$insert_Id = $db->query("insert into IdAccount (reId, reAccount)
						 values (-1,'$message_data[account]')");
	                $insert_Shake = $db->query("insert into ShakeList (shakeAccount, shakeTime)
				                    values ('$message_data[account]', 0)");
			$insert_GameOne = $db->query("insert into GameOne (gameAccount, grade, sum) values
						     ('$message_data[account]', 6, 20)");
			$insert_NearPeople = $db->query("insert into NearPeople (nearAccount, longtitude, latitude) 
							values ('$message_data[account]', 0, 0)");
			Gateway::sendToCurrentClient('{"re_type":"1","re_message":"true"}+++++');
		}
		else{
			Gateway::sendToCurrentClient('{"re_type":"1","re_message":"false"}+++++');
		}
	    	break;

            // Chat
            case '2':
		$reClientName = $message_data['re_account'];
		$reClientId = $db->single("select reId from IdAccount
                                           where reAccount = '$message_data[re_account]' ");
		echo "$reClientId 发给谁\n";
		if($reClientId != -1){
			$sendMessage = "{\"re_type\":\"2\",\"re_message\":\"$message_data[message]\",".
				       "\"re_sender\":\"$message_data[account]\",".
				       "\"re_date\":\"$message_data[date]\"}+++++";
	        	Gateway::sendToClient($reClientId, $sendMessage);
		}else{
			$db->query("insert into OffMessage (receiver, sender, message, date) values
				    ('$message_data[re_account]','$message_data[account]',
				     '$message_data[message]','$message_data[date]')");
		}
		break;

	    // Shake
	    case '3':
		$db->query("update ShakeList set shakeTime = SYSDATE() 
		            where shakeAccount = '$message_data[account]' ");
         	sleep(4);
		$res = $db->query("select userAccount, smallPhoto, gender, userName
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
							     "\"re_photo\":\"$k[smallPhoto]\",".
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
		$res = $db->query("select userAccount, smallPhoto, gender, userName
                                   from UserInf A,FriendList B where A.userAccount = B.friAccount
                                   and B.friUser = '$message_data[account]' ");
                if($res){
                //      print_r($res);
		/*
			$sendMessage = '{"re_type":"4","re_message":[';
			foreach($res as $key => $k){
				$sendMessage = $sendMessage."{\"re_account\":\"$k[userAccount]\",".
                                                             "\"re_photo\":\"$k[smallPhoto]\",".
                                                             "\"re_gender\":\"$k[gender]\",".
                                                             "\"re_name\":\"$k[userName]\"},";
			}
			$re_message = substr($sendMessage, 0, strlen($sendMessage)-1);
			$re_message = $re_message."]}+++++";
			echo "$re_message \n";
		*/
			foreach($res as $key => $k){
				$sendMessage = "{\"re_type\":\"4\",\"re_account\":\"$k[userAccount]\",".
						"\"re_photo\":\"$k[smallPhoto]\",".
						"\"re_gender\":\"$k[gender]\",".
						"\"re_name\":\"$k[userName]\",".
						"\"re_message\":\"true\"}+++++";
				Gateway::sendToCurrentClient($sendMessage);
				$sendMessage = "";
			}
			Gateway::sendToCurrentClient('{"re_type":"4","re_message":"finish"}+++++');
                }else{
			$re_message = '{"re_type":"4","re_message":"false"}+++++';
                        echo "并没有 \n";	
			Gateway::sendToCurrentClient($re_message);
		}
		break;

           //GameOneRecieve
	   case '5':
		$res = $db->row("select rock, scissors, paper from GameOne where
		        	   gameAccount = '$message_data[account]'");
		$res_grade =  $db->row("select grade, sum from GameOne where
					   gameAccount = '$message_data[friend]'");
		if($res && $res_grade){
			print_r($res);
		
			$sendMessage = "{\"re_type\":\"5\",".
				        "\"re_grade\":\"$res_grade[grade]\",".
					"\"re_sum\":\"$res_grade[sum]\",".
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
		$db->query("update GameOne set grade = '$message_data[grade]',
			    sum = '$message_data[sum]' where gameAccount = '$message_data[account]'");
           	break;
           
	   //AddFriend
           case '8':
		if($message_data['friend'] == $db->single("select friAccount from FriendList
	      						  where friUser = '$message_data[account]'")){
			Gateway::sendToCurrentClient('{"re_type":"8","re_message":"false"}+++++');
		}else{
                	$db->query("insert into FriendList (friUser, friAccount)
                        	    values ('$message_data[account]',
                                	    '$message_data[friend]')");
			$db->query("insert into FriendList (friUser, friAccount)
				    values ('$message_data[friend]',
					    '$message_data[account]')");
			$res = $db->row("select userAccount, userName, smallPhoto, gender
					 from UserInf where userAccount = '$message_data[account]'");
			$reClientId = $db->single("select reId from IdAccount
						   where reAccount = '$message_data[friend]'");
			if($reClientId != -1){
	        		Gateway::sendToClient($reClientId,"{\"re_type\":\"8\",".
							    "\"re_message\":\"true\",".
                                	                    "\"re_account\":\"$res[userAccount]\",".
                                        	            "\"re_photo\":\"$res[smallPhoto]\",".
                                                	    "\"re_gender\":\"$res[gender]\",".
                                                    	    "\"re_name\":\"$res[userName]\"}+++++");
			}else{
				Gateway::sendToCurrentClient('{"re_type":"8","re_message":"false"}+++++');
			}
		}
		break;

	    //SendVoice
	    case '9':
		$reClientName = $message_data['re_account'];
                $reClientId = $db->single("select reId from IdAccount
                                           where reAccount = '$message_data[re_account]' ");
		if($reClientId != -1){
                	$sendMessage = "{\"re_type\":\"9\",\"re_message\":\"$message_data[message]\",".
                        	       "\"re_sender\":\"$message_data[account]\",".
                               	       "\"re_date\":\"$message_data[date]\",".
				       "\"re_time\":\"$message_data[time]\"}+++++";
			Gateway::sendToClient($reClientId, $sendMessage);
		}else{
			$db->query("insert into OffMessage (receiver, sender, message, date, time) values
                                    ('$message_data[re_account]','$message_data[account]',
                                     '$message_data[message]','$message_data[date]','$message_data[time]')");	
		}
		break;

            //NearBy
	    case '10':
		$db->query("update NearPeople set longtitude = '$message_data[longtitude]',
			    latitude = '$message_data[latitude]' where 
			    nearAccount = '$message_data[account]'");
		$res = $db->query("SELECT userAccount,userName,gender,smallPhoto,longtitude,latitude,
			  (6378.138*2*ASIN(SQRT(POW(SIN(('$message_data[latitude]' *PI()/180-latitude*PI()/180)/2),2)+
			  COS('$message_data[latitude]' *PI()/180)*COS(latitude*PI()/180)*
			  POW(SIN(('$message_data[longtitude]' *PI()/180-longtitude*PI()/180)/2),2)))*1000) as distance
			  from UserInf A,NearPeople B where A.userAccount=B.nearAccount and
			  userAccount <> '$message_data[account]' and userAccount in(
			  select nearAccount from NearPeople where(6378.138*2*ASIN(SQRT(POW(
		          SIN(('$message_data[latitude]' *PI()/180-latitude*PI()/180)/2),2) + 
			  COS('$message_data[latitude]' *PI()/180)*COS(latitude*PI()/180)*
			  POW(SIN(('$message_data[longtitude]' *PI()/180-longtitude*PI()/180)/2),2)))*1000) < 1000)");
	        print_r($res);
		if($res){
			foreach($res as $key => $k){
                                $sendMessage = "{\"re_type\":\"10\",\"re_account\":\"$k[userAccount]\",".
                                                "\"re_photo\":\"$k[smallPhoto]\",".
                                                "\"re_gender\":\"$k[gender]\",".
                                                "\"re_name\":\"$k[userName]\",".
						"\"re_longtitude\":\"$k[longtitude]\",".
						"\"re_latitude\":\"$k[latitude]\",".
						"\"re_distance\":\"$k[distance]\",".
                                                "\"re_message\":\"true\"}+++++";
                                Gateway::sendToCurrentClient($sendMessage);
                                $sendMessage = "";
                        }
                        Gateway::sendToCurrentClient('{"re_type":"10","re_message":"finish"}+++++');
                }else{
                        $re_message = '{"re_type":"10","re_message":"false"}+++++';
                        echo "并没有 \n";
                        Gateway::sendToCurrentClient($re_message);
                }	
		break;

	    //ChangeInf
	    case '11':
		$db->query("update UserInf set userName = '$message_data[cname]'
			    where userAccount = '$message_data[account]'");
		Gateway::sendToCurrentClient('{"re_type":"11","re_message":"true"}+++++');
		break;

            //Image
	    case '12':
		$reClientName = $message_data['re_account'];
                $reClientId = $db->single("select reId from IdAccount
                                           where reAccount = '$message_data[re_account]' ");
                echo "$reClientId 发给谁\n";
                if($reClientId != -1){
                        $sendMessage = "{\"re_type\":\"12\",\"re_message\":\"$message_data[message]\",".
                                       "\"re_sender\":\"$message_data[account]\",".
                                       "\"re_date\":\"$message_data[date]\"}+++++";
                        Gateway::sendToClient($reClientId, $sendMessage);
                }else{
                        $db->query("insert into OffMessage (receiver, sender, message, date) values
                                    ('$message_data[re_account]','$message_data[account]',
                                     '$message_data[message]','$message_data[date]')");
                }
                break;

	    //DelFriend	
	    case '13':
		$db->query("delete from FriendList where friUser = '$message_data[account]' 
			    and friAccount = '$message_data[delaccount]'");
		$db->query("delete from FriendList where friUser = '$message_data[delaccount]'
			    and friAccount = '$message_data[account]'");
		$reClientId = $db->single("select reId from IdAccount
                                           where reAccount = '$message_data[delaccount]' ");
		Gateway::sendToClient($reClientId,"{\"re_type\":\"13\",".
					     "\"re_message\":\"$message_data[account]\"}+++++");
		break;

	    //ShowInf
	    case '14':
		$res = $db->row("select userName, userPhone, userEmail, birth, gender
                                 from UserInf where userAccount = '$message_data[account]'");
		if($res){
			$sendMessage = "{\"re_type\":\"14\",".
                                        "\"re_message\":\"true\",".
                                        "\"re_name\":\"$res[userName]\",".
                                        "\"re_gender\":\"$res[gender]\",".
                                        "\"re_phone\":\"$res[userPhone]\",".
                                        "\"re_email\":\"$res[userEmail]\",".
                                        "\"re_birth\":\"$res[birth]\"}+++++";
                        Gateway::sendToCurrentClient($sendMessage);

		}else{
			Gateway::sendToCurrentClient('{"re_type":"14","re_message":"false"}+++++');
                        echo "失败";
		}
		break;
	    default:
		break;	
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
       //$re_client_id = $client_id + 1;
       $row_cout = $db->query("update IdAccount set reId = -1
                               where reId = '$client_id' ");	
  //     GateWay::sendToAll("{\"re_type\":\"close\",\"id\":\"'$client_id'\"}+++++");
   }
}
