<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

ob_start();
$dir_root = explode('/'.basename($_SERVER['SCRIPT_FILENAME']), $_SERVER['SCRIPT_FILENAME'])[0];

include $dir_root.'/config.php';
include $dir_root.'/lib/class.action.php';
include $dir_root.'/lib/class.mongodb.php';

$db_collection = 'call_log';
$app = new PbxApi();
$mgdb = new MGDB_Api($db_url, $db_name);

$socket = fsockopen("127.0.0.1", "5038", $error_code, $error_message, 10);
if (!$socket)
{
	$app->errorSave(['code' => $error_code, 'msg' => $error_message]);
}
else
{
	fputs($socket, "Action: login\r\n");
	fputs($socket, "UserName: zetadmin_api\r\n");
	fputs($socket, "Secret: zetadmin_api\r\n\r\n");
	
	$res = [];
	$eventsAllow = ['DialBegin', 'DialEnd', 'Hangup']; 
	$ChannelStateDescAllow = ['Ring', 'Up']; 
	while(($buffer = fgets($socket, 4096)) !== false)
	{ 
		if(!empty(trim($buffer)))
		{
			$bufferData = explode(': ', str_replace("\r\n", "", $buffer));
			if(!empty($bufferData[1]))
			{
				$res[$bufferData[0]] = $bufferData[1];
			}
		}
		else
		{
			if(empty($res['Channel'])) { $res['Channel'] = ''; }
			$channel = isset($res['DestChannel']) ? $res['DestChannel'] : $res['Channel'];
			$dataFind = ['_id' => $channel];
			if(isset($res['Event']) && in_array($res['Event'], $eventsAllow) && !empty($channel))
			{
				/*call Event*/
				
				if($res['Event'] == 'DialBegin')
				{
					$_mgid = (string)new \MongoDB\BSON\ObjectID;
					$dialData = explode("/", $res['DialString']);
					$dataInsert = array(
						'_id' => (string)$channel, 
						'CallerIDNum' => (string)$res['DestCallerIDNum'],
						'Exten' => (string)isset($dialData[1]) ? $dialData[1] : $dialData[0],
						'Context' => (string)$res['DestContext'],
						'ConnectedLineNum' => (string)$res['DestConnectedLineNum'],
						'ConnectedLineName' => (string)$res['DestConnectedLineName'],
						'DialString' => $res['DialString'],
						'Trunk' => (string)isset($dialData[1]) ? $dialData[0] : '',
						't_create' => microtime(true),  
						't_up' => 0, 
						't_hangup' => 0
					);
					//$app->cdrSave($dataInsert);

					$mgdb->insert($db_collection, $dataInsert);
				}
				elseif($res['Event'] == 'DialEnd')
				{
					$update = $mgdb->update($db_collection, ['_id' => $channel], ['$set' => ['t_up' => microtime(true), 'DialStatus' => $res['DialStatus']]], []);
					if($update['status'] == false)
					{
						$res['t_up'] = microtime(true);
						$app->cdrSave($res);
					}
				}
				elseif($res['Event'] == 'Hangup')
				{
					$update = $mgdb->update($db_collection, ['_id' => $channel], ['$set' => ['t_hangup' => microtime(true), 'cause_txt' => $res['Cause-txt']]], []);
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$app->cdrSave($res);
					}
				}
				//$app->cdrSave($res);
			}
			else
			{
				//$app->cdrSave($res);
				/*other Event*/
				// echo "<pre>";
				// var_dump($res);
				// echo "</pre>";
			}
			$res = array();
		}
		$app->errorSave();
	}
}
fclose($socket);
?>