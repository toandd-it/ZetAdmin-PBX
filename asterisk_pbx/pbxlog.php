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
			$res['Channel'] = $channel;

			if(isset($res['Event']) && in_array($res['Event'], $eventsAllow) && !empty($channel))
			{
				if(isset($res['DialStatus']) && $res['DialStatus'] == 'ANSWER')
				{
					$resCache[$_id]['t_up'] = microtime(true);
				}
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
					$logCheck[$_id]['status'] = $insert['status'];
				}
				elseif($res['Event'] == 'DialBegin')
				{	
					unset($res['Event']);
					unset($res['Privilege']);
					$updateData['$set'] = $res;
					if(isset($res['DestCallerIDNum']) && empty($res['CallerIDNum']))
					{
						$updateData['$set']['CallerIDNum'] = $res['DestCallerIDNum'];
						$updateData['$set']['CallerIDName'] = '';
					}
					
					if(isset($logCheck[$_id]['status']) && $logCheck[$_id]['status'] == true)
					{
						/**/
					}
					else
					{
						$dialData = explode("/", $res['DialString']);
						$Exten = empty($dialData[1]) ? $dialData[0] : $dialData[1];
						$Trunk = isset($dialData[1]) ? $dialData[0] : '';
						
						$dataInsert = $res;
						$dataInsert['_id'] = (float)$_id;
						$dataInsert['t_create'] = (float)$_id;
						$dataInsert['t_ring'] = 0;
						$dataInsert['t_answer'] = 0;
						$dataInsert['t_hangup'] = 0;
						$dataInsert['Variable'] = [];
						$dataInsert['Exten'] = $Exten;
						$dataInsert['Trunk'] = $Trunk;
						$dataInsert['Context'] = empty($res['Context']) ? $res['DestContext'] : $res['Context'];
						$mgdb->insert($db_collection, $dataInsert);
					}
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
					if($update['status'] == false)
					{
						$res['Error'] = 'Update DialBegin';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
				}
				elseif($res['Event'] == 'AgentCalled')
				{
					$t_create_sub = microtime(true);

					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_create_sub' => $t_create_sub, 'DestChannel' => $res['DestChannel'], 'MemberName' => $res['MemberName']]], []);
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
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['DialStatus' => $res['DialStatus']]], []);
					if($update['status'] == false)
					{
						$res['t_end'] = $t_end;
						$res['Error'] = 'Update DialEnd';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					$mgdb->update('call_campaign_contacts', ['t_dial' => (float)$_id], ['$set' => ['t_end' => $t_end, 'DialStatus' => $res['DialStatus']]], []);
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
					$mgdb->update('call_campaign_contacts', ['t_dial' => (float)$_id], ['$set' => ['agent' => $res['MemberName']]], []);
					$mgdb->update('call_contacts', ['t_dial' => (float)$_id], ['$set' => ['agent' => $res['MemberName']]], []);
				}
				elseif($res['Event'] == 'AgentComplete')
				{
					$t_end_sub = microtime(true);
					//unset($res['Event']);
					//unset($res['Privilege']);
					//$updateData['$set'] = $res;
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['t_end_sub' => (float)$t_end_sub], []);
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
						$setHangup = ['$set' => ['t_hangup' => $t_hangup, 't_answer' => (float)round($t_hangup - $resCache[$_id]['t_up']), 't_answer_sub' => (float)round($t_hangup - $resCache[$_id]['t_up_sub']), 'cause_txt' => $res['Cause-txt']]];
					}
					else
					{
						if(!empty($resCache[$_id]['t_up']))
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 't_answer' => (float)round($t_hangup - $resCache[$_id]['t_up']), 'cause_txt' => $res['Cause-txt']]];
						}
						else
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 'cause_txt' => $res['Cause-txt']]];
						}
					}
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $setHangup, []);
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']) && !empty($resCache[$_id]['t_up']))
					{
                        $mgdb->update('call_campaign_contacts', ['_id' => $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']], ['$set' => ['t_answer' => (float)round($t_hangup - $resCache[$_id]['t_up'])]], []);
					}
					
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$res['Error'] = 'Update Hangup';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['campaign_id' => $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']['Value']]], []);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['USER_UID']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['uid' => explode(',',$updateVariableData[$_id]['$set']['Variable']['USER_UID']['Value'])]], []);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['campaign_contact_id' => (string)$updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']]], []);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['CONTACT_ID']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['contact_id' => (string)$updateVariableData[$_id]['$set']['Variable']['CONTACT_ID']['Value']]], []);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['RINGTIME']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_ring' => $updateVariableData[$_id]['$set']['Variable']['RINGTIME']['Value']]], []);
					}

					if(isset($updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']))
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['t_answer' => (float)$updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']['Value']]], []);
					}
					
					if(isset($updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']) && isset($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']))
					{
                        $mgdb->update('call_campaign_contacts', ['_id' => $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']], ['$set' => ['t_answer' => (float)$updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']['Value']]], []);
					}
					
					if(!empty($updateVariableData[$_id]))
					{
						$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateVariableData[$_id], []);
						if($update['status'] == false)
						{
							$res['Error'] = 'Update VarSet';
							$res['Msg'] = $update['data']['msg'];
							$app->callLogSave($res);
						}
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
					
					//update campaign id
					if($res['Variable'] == 'CAMPAIGN_ID')
					{
						$mgdb->update('call_campaigns', ['_id' => $res['Value']], ['$inc' => ['totalCalled' => 1]], []);
					}
					
					//update campaign contact id
					if($res['Variable'] == 'CAMPAIGN_CONTACT_ID')
					{
						$mgdb->update('call_campaign_contacts', ['_id' => $res['Value']], ['$set' => ['t_dial' => (float)$_id]], []);
					}
					
					//update contact id
					if($res['Variable'] == 'CONTACT_ID')
					{
						$mgdb->update('call_contacts', ['_id' => $res['Value']], ['$set' => ['t_dial' => (float)$_id]], []);
					}
					$app->callLogSave($updateVariableData);
				}
				if(isset($res['Event']) && $res['Event'] == 'Cdr')
				{ 
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