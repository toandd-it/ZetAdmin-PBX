<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

ob_start();
//session_start();

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
	$eventsAllow = ['DialBegin', 'Hangup', 'DialEnd', 'QueueCallerLeave', 'AgentCalled', 'AgentConnect', 'AgentComplete']; 
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
			if(isset($res['Uniqueid']))
			{
				$_id = $res['Uniqueid'];
			}
			elseif(isset($res['DestUniqueid']))
			{
				$_id = $res['DestUniqueid'];
			}
			if(isset($res['Channel']) && isset($res['DestChannel']))
			{
				$channel = $res['Channel'];
			}
			else
			{
				if(!isset($res['Channel'])) { $res['Channel'] = ''; }
				$channel = isset($res['DestChannel']) ? $res['DestChannel'] : $res['Channel'];
			}

			if(isset($res['Event']) && in_array($res['Event'], $eventsAllow) && !empty($channel))
			{
				/*call Event*/
				if($res['Event'] == 'DialBegin')
				{
					$dialData = explode("/", $res['DialString']);
					$Exten = empty($dialData[1]) ? $dialData[0] : $dialData[1];
					
					$dataInsert = $res;
					$dataInsert['_id'] = (float)$_id;
					$dataInsert['Caller'] = '';
					$dataInsert['Called'] = (string)$Exten;
					$dataInsert['t_create'] = $_id;
					$dataInsert['t_call'] = 0;
					$dataInsert['t_up'] = 0;
					$dataInsert['t_hangup'] = 0;

					//$app->cdrSave($dataInsert);
					$mgdb->insert($db_collection, $dataInsert);
				}
				elseif($res['Event'] == 'AgentCalled')
				{
					$t_create_sub = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_create_sub' => $t_create_sub, 'DestChannel' => $res['DestChannel'], 'MemberName' => $res['MemberName']]], []);
					if($update['status'] == false)
					{
						$res['t_create_sub'] = microtime(true);
						$res['Error'] = 'Update AgentCalled';
						$app->cdrSave($res);
					}
				}
				elseif($res['Event'] == 'DialEnd')
				{
					$t_up = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_up' => $t_up, 'DialStatus' => $res['DialStatus']]], []);
					if($update['status'] == false)
					{
						$res['t_up'] = microtime(true);
						$res['Error'] = 'Update DialEnd';
						$app->cdrSave($res);
					}
					else
					{
						$resCache[$_id]['t_up'] = $t_up;
					}
				}
				elseif($res['Event'] == 'AgentConnect')
				{
					$t_up_sub = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_up_sub' => $t_up_sub]], []);
					if($update['status'] == false)
					{
						$res['t_up_sub'] = microtime(true);
						$res['Error'] = 'Update AgentConnect';
						$app->cdrSave($res);
					}
					else
					{
						$resCache[$channel]['t_up_sub'] = $t_up_sub;
					}
				}
				elseif($res['Event'] == 'Hangup')
				{
					$t_hangup = microtime(true);
					if(!empty($resCache[$_id]['t_up']))
					{
						if(!empty($resCache[$channel]['t_up_sub']))
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 't_call' => ($t_hangup - $resCache[$_id]['t_up']), 't_call_sub' => ($t_hangup - $resCache[$channel]['t_up_sub']), 'cause_txt' => $res['Cause-txt']]];
						}
						else
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 't_call' => ($t_hangup - $resCache[$_id]['t_up']), 'cause_txt' => $res['Cause-txt']]];
						}
					}
					else
					{
						$setHangup = ['$set' => ['t_hangup' => $t_hangup, 'cause_txt' => $res['Cause-txt']]];
					}
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $setHangup, []);
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$res['Error'] = 'Update Hangup';
						$app->cdrSave($res);
					}
					if(!empty($resCache[$_id]))
					{
						unset($resCache[$_id]);
					}
				}
			}
			else
			{
				if(isset($res['Event']) && isset($res['Variable']) && $res['Event'] == 'VarSet' && isset($res['Value']))
				{
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['Variable' => (string)$res['Variable'], 'Value' => (string)$res['Value']]], []);
					if($update['status'] == false)
					{
						$res['Error'] = 'Update Keypad';
						$app->cdrSave($res);
					}
				}
				/* $app->cdrSave($res); */
			}
			$res = array();
		}
		$app->errorSave();
	}
}
fclose($socket);
?>