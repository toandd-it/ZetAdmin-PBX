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
	$eventsAllow = ['Newchannel', 'DialBegin', 'Hangup', 'DialEnd', 'QueueCallerLeave', 'AgentCalled', 'AgentConnect', 'AgentComplete']; 
	$ChannelStateDescAllow = ['Ring', 'Up']; 
	$VariableAllow = ['SIPURI', 'SIPDOMAIN', 'SIPCALLID', 'RINGTIME_MS', 'DIALSTATUS', 'ANSWEREDTIME', 'ANSWEREDTIME_MS', 'DIALEDTIME', 'DIALEDTIME_MS', 'DIALSTATUS', 'KEYPAD'];
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
				if($res['Event'] == 'Newchannel' && $res['Exten'] != 's')
				{
					unset($res['Event']);
					unset($res['Privilege']);
					$dataInsert = $res;
					$dataInsert['_id'] = (float)$_id;
					$dataInsert['t_create'] = (float)$_id;
					$dataInsert['t_ring'] = 0;
					$dataInsert['t_answer'] = 0;
					$dataInsert['t_hangup'] = 0;
					$dataInsert['Variable'] = [];

					$insert = $mgdb->insert($db_collection, $dataInsert);
					if($insert['status'] == false)
					{
						$res['Error'] = 'Insert Newchannel';
						$res['Msg'] = $insert['data']['msg'];
						$app->callLogSave($res);
					}
				}
				elseif($res['Event'] == 'DialBegin')
				{	
					unset($res['Event']);
					unset($res['Privilege']);
					$updateData['$set'] = $res;

					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
					if($insert['status'] == false)
					{
						$res['Error'] = 'Update DialBegin';
						$res['Msg'] = $insert['data']['msg'];
						$app->callLogSave($res);
					}
				}
				elseif($res['Event'] == 'AgentCalled')
				{
					$t_create_sub = microtime(true);
					
					unset($res['Event']);
					unset($res['Privilege']);
					$updateData['$set'] = $res;
					$updateData['$set']['t_create_sub'] = $t_create_sub;
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
					if($update['status'] == false)
					{
						$res['t_create_sub'] = microtime(true);
						$res['Error'] = 'Update AgentCalled';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
				}
				elseif($res['Event'] == 'DialEnd')
				{
					$t_end = microtime(true);
					unset($res['Event']);
					unset($res['Privilege']);
					$updateData['$set'] = $res;
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
					if($update['status'] == false)
					{
						$res['t_end'] = $t_end;
						$res['Error'] = 'Update DialEnd';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
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
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					else
					{
						$resCache[$channel]['t_up_sub'] = $t_up_sub;
					}
				}
				elseif($res['Event'] == 'AgentComplete')
				{
					$t_end_sub = microtime(true);
					unset($res['Event']);
					unset($res['Privilege']);
					$updateData['$set'] = $res;
					$updateData['$set']['t_end_sub'] = $t_end_sub;
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
					if($update['status'] == false)
					{
						$res['t_end_sub'] = $t_end_sub;
						$res['Error'] = 'Update AgentComplete';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					else
					{
						$resCache[$_id]['t_end_sub'] = $t_end_sub;
					}
				}
				elseif($res['Event'] == 'Hangup')
				{
					$t_hangup = microtime(true);
					if(!empty($resCache[$_id]['t_up_sub']))
					{
						$setHangup = ['$set' => ['t_hangup' => $t_hangup, 't_call_sub' => ($t_hangup - $resCache[$_id]['t_up_sub']), 'cause_txt' => $res['Cause-txt']]];
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
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					if(!empty($resCache[$_id]))
					{
						unset($resCache[$_id]);
						unset($updateVariableData[$_id]);
					}
				}
			}
			else
			{
				if(isset($res['Event']) && isset($res['Variable']) && $res['Event'] == 'VarSet' && isset($res['Value']))
				{
					$varNameUpdate = (string)$res['Variable'];
					$updateVariableData[$_id]['$set']['Variable'][$varNameUpdate] = [
						'Name' => $res['Variable'], 
						'Value' => $res['Value'], 
						'Time' => microtime(true)
					];
					
					if($res['Variable'] == 'RINGTIME_MS')
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_ring' => (float)$res['Value']]], []);
					}
					
					if($res['Variable'] == 'ANSWEREDTIME')
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_answer' => (float)$res['Value']]], []);
					}

					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateVariableData[$_id], []);
					if($update['status'] == false)
					{
						$res['Error'] = 'Update VarSet';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
				}
				if(isset($res['Event']) && $res['Event'] == 'Cdr'){ 
					$dataInsertCdr = $res;
					$dataInsertCdr['_id'] = (float)$_id;
					$mgdb->insert('call_cdr', $dataInsertCdr);
					$app->cdrSave($res); 
					$dataInsertCdr = [];
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