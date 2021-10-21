<?php
if(isset($action) && isset($mgdb))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    if($action == 'Click2Call')
    {
        $strUser = 'zetadmin_api';
        $strSecret = 'zetadmin_api';
        if(!empty($_POST['number']) && !empty($_POST['number']))
        {
            $ext = $_POST['exten'];
            $ext = filter_var($ext, FILTER_SANITIZE_NUMBER_INT);
            $ext = preg_replace("/[^0-9,.]/", "", $ext);

            $strChannel = "SIP/".$ext;
            $strContext = isset($_POST['context']) ? $_POST['context'] : "from-internal";
            #specify the amount of time you want to try calling the specified channel before hangin up
            $strWaitTime = 30;
            #specify the priority you wish to place on making this call
            $strPriority = 1;
            #specify the maximum amount of retries
            $strMaxRetry = 2;
            
            $number = strtolower($_POST['number']);
            $number = filter_var($number, FILTER_SANITIZE_NUMBER_INT);
            $number = preg_replace("/[^0-9,.]/", "", $number);
            
            $callerid = isset($_POST['number_name']) ? $_POST['number_name'] : "Click to dial";
            $strCallerId = $callerid." <$number>";
            
            $oSocket = fsockopen("127.0.0.1", 5038, $errno, $errstr, 20);
            if (!$oSocket) 
            {
                $status = false;
                $msg = "$errstr ($errno)";
            } 
            else 
            {
                fputs($oSocket, "Action: login\r\n");
                fputs($oSocket, "Events: off\r\n");
                fputs($oSocket, "Username: $strUser\r\n");
                fputs($oSocket, "Secret: $strSecret\r\n\r\n");
                fputs($oSocket, "Action: originate\r\n");
                fputs($oSocket, "Channel: $strChannel\r\n");
                fputs($oSocket, "WaitTime: $strWaitTime\r\n");
                fputs($oSocket, "CallerId: $strCallerId\r\n");
                fputs($oSocket, "Exten: $number\r\n");
                fputs($oSocket, "Context: $strContext\r\n");
                fputs($oSocket, "Priority: $strPriority\r\n\r\n");
                fputs($oSocket, "Action: Logoff\r\n\r\n");
                sleep(2);
                fclose($oSocket);
                $status = true;
                $msg = sprintf($app->_lang('msg_011'), $strChannel, $number);
            }
        }
        else
        {
            $status = false;
			$msg = $app->_lang('msg_012');
        }
        $insertLogs = array(
			'id' => (array)$api_id,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => '',
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $api_id,
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);
        
		$returnData = array(
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> array(), 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
    }
}
?>