<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

ob_start();
session_start();

$dir_root = explode('/'.basename($_SERVER['SCRIPT_FILENAME']), $_SERVER['SCRIPT_NAME'])[0];

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
	$eventsAllow = ['Newstate', 'Newchannel', 'Hangup']; 
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
			//$res = json_decode(json_encode($res), true);
			
			if(isset($res['Event']) && isset($res['Exten']) && in_array($res['Event'], $eventsAllow) && !empty($res['Channel']) && $res['Exten'] != 's')
			{
				/*call Event*/
				$dataFind = ['_id' => $res['Channel']];
				if($res['Event'] == 'Newchannel')
				{
					$_mgid = (string)new \MongoDB\BSON\ObjectID;
					
					$dataInsert = array(
						'_id' => (string)$res['Channel'], 
						'id' => (string)$_mgid,
						'CallerIDNum' => (string)$res['CallerIDNum'],
						'Exten' => (string)$res['Exten'],
						'Context' => (string)$res['Context'],
						'ConnectedLineNum' => (string)$res['ConnectedLineNum'],
						'ConnectedLineName' => (string)$res['ConnectedLineName'],
						't_create' => microtime(true),  
						't_up' => 0, 
						't_hangup' => 0
					);
					//$app->cdrSave($dataInsert);
					$mgdb->insert($db_collection, $dataInsert);
				}

				if($res['Event'] == 'Newstate' && $res['ChannelStateDesc'] == 'Up')
				{
					$update = $mgdb->update($db_collection, $dataFind, ['$set' => ['t_up' => microtime(true)]], []);
					if($update['status'] == false)
					{
						$res['t_up'] = microtime(true);
						$app->cdrSave($res);
					}
				}
				elseif($res['Event'] == 'Hangup')
				{
					$update = $mgdb->update($db_collection, $dataFind, ['$set' => ['t_hangup' => microtime(true)]], []);
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$app->cdrSave($res);
					}
				}
				$app->cdrSave($res);
			}
			else
			{
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