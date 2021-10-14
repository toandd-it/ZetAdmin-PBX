<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

$dir_root = explode($_SERVER['SCRIPT_NAME'], $_SERVER['SCRIPT_FILENAME'])[0];

include_once($dir_root."/config.php");
include_once($dir_root."/lib/class.action.php");
include_once($dir_root."/lib/class.mongodb.php");

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
	$eventsAllow = ['Newstate', 'Hangup']; 
	$ChannelStateDescAllow = ['Ring', 'Up']; 
	while(($buffer = fgets($socket, 4096)) !== false)
	{ 
		if(!empty(trim($buffer)))
		{
			$bufferData = explode(': ', str_replace("\r\n", "", $buffer));
			$res[$bufferData[0]] = $bufferData[1];
		}
		else
		{
			if(isset($res['Event']) && in_array($res['Event'], $eventsAllow))
			{
				$_mgid = (string)new \MongoDB\BSON\ObjectID;
				$dataFind = ['_id' => $res['Channel']];

				$dataInsert = $res;
				$dataInsert['_id'] = (string)$dataInsert['Channel'];
				$dataInsert['id'] = $_mgid;
				$dataInsert['t_create'] = time();
				$dataInsert['t_ring'] = 0;
				$dataInsert['t_up'] = 0;
				$dataInsert['t_hangup'] = 0;

				$insertChannel = $mgdb->insert($db_collection, $dataInsert);
				if($insertChannel['status'] == false)
				{
					if($res['Event'] == 'Newstate' && $res['ChannelStateDesc'] == 'Ring')
					{
						$mgdb->update($db_collection, $dataFind, ['t_ring' => (int)time()], []);
					}
					elseif($res['Event'] == 'Newstate' && $res['ChannelStateDesc'] == 'Up')
					{
						$mgdb->update($db_collection, $dataFind, ['t_up' => (int)time()], []);
					}
					elseif($res['Event'] == 'Hangup')
					{
						$mgdb->update($db_collection, $dataFind, ['t_hangup' => (int)time()], []);
					}
				}
				//save to log file when mongodb disconnect
				
				//$app->cdrSave($res);
				echo "<pre>";
				var_dump($res);
				echo "</pre>";
				
				echo "------------------------------------------------------------------------------------------------\r\n";
			}
			else
			{
				echo "<pre>";
				var_dump($res);
				echo "</pre>";
			}
			$res = array();
		}
		$app->errorSave();
	}
}
fclose($socket);
?>