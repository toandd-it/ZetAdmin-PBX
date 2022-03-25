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
	$eventsAllow = ['Newchannel', 'DialBegin', 'Hangup', 'DialEnd', 'QueueCallerLeave', 'AgentCalled', 'AgentConnect', 'AgentComplete', 'Newexten']; 
	$ChannelStateDescAllow = ['Ring', 'Up']; 
	$VariableAllow = ['SIPURI', 'SIPDOMAIN', 'SIPCALLID', 'RINGTIME_MS', 'DIALSTATUS', 'ANSWEREDTIME', 'ANSWEREDTIME_MS', 'DIALEDTIME', 'DIALEDTIME_MS', 'DIALSTATUS', 'KEYPAD'];
	$resDebug = [];
	$updateVariableData = [];
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
			$_id = 'null';
			if(isset($res['Linkedid']))
			{
				$_id = $res['Linkedid'];
			}
			elseif(isset($res['DestUniqueid']))
			{
				$_id = $res['DestUniqueid'];
			}
			
			if(!empty($res['Event']))
			{
				$resDebug[$_id][$res['Event']][] = $res;
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
			
			//$app->callLogSave($res);
			
			if(isset($res['Event']) && in_array($res['Event'], $eventsAllow) && !empty($channel))
			{
				if(isset($res['DialStatus']) && $res['DialStatus'] == 'ANSWER')
				{
					$resCache[$_id]['t_up'] = microtime(true);
				}
				if(!empty($res['DialStatus']))
				{
					if($res['DialStatus'] == 'CHANUNAVAIL')
					{
						$res['DialStatus'] = 'DONOTCONTACT';
					}
					$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = ['Name' => 'DIALSTATUS', 'Value' => $res['DialStatus'], 'Time' => microtime(true)];
				}
				/*call Event*/
				
				if($res['Event'] == 'Newexten' && !empty($res['AppData']) && $res['AppData'] == '(Outgoing Line)')
				{
					$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = ['Name' => 'DIALSTATUS', 'Value' => 'NOANSWER', 'Time' => microtime(true)];
				}
				
				if($res['Event'] == 'Newchannel' && $res['Exten'] != 's')
				{
					//$app->callLogSave($res);
					//unset($res['Event']);
					//unset($res['Privilege']);
					$dataInsert = $res;
					$dataInsert['_id'] = (float)$_id;
                    if(empty($res['CallerIDNum']) && !empty($res['DestCallerIDNum']) && is_numeric($res['DestCallerIDNum']))
                    {
                      	$dataInsert['CallerIDNum'] = empty($res['DestCallerIDNum']) ? $res['DestConnectedLineNum'] : $res['DestCallerIDNum'];
                    }
					elseif(empty($res['CallerIDNum']) && !empty($res['DestConnectedLineNum']) && is_numeric($res['DestConnectedLineNum']))
					{
						$dataInsert['CallerIDNum'] = !empty($res['DestConnectedLineNum']) ? $res['DestConnectedLineNum'] : $res['DestCallerIDNum'];
					}
					$dataInsert['t_create'] = (float)$_id;
					$dataInsert['t_ring'] = 0;
					$dataInsert['t_answer'] = 0;
					$dataInsert['t_hangup'] = 0;
					$dataInsert['Variable'] = [];
					$dataInsert['DialStatus'] = 'DONOTCONTACT';

					$insert = $mgdb->insert($db_collection, $dataInsert);
					if($insert['status'] == false)
					{
						$res['Error'] = 'Insert Newchannel';
						$res['Msg'] = $insert['data']['msg'];
						$app->callLogSave($res);
					}
					$logCheck[$_id]['status'] = $insert['status'];
				}
				elseif($res['Event'] == 'Newchannel' && $res['Exten'] == 's')
				{
					//$app->callLogSave($res);
					if($res['Linkedid'] == $res['Uniqueid'])
					{
						//unset($res['Event']);
						//unset($res['Privilege']);

						$dataInsert['_id'] = (float)$_id;
						$dataInsert['Context'] = $res['Context'];
						$dataInsert['Channel'] = $res['Channel'];
						$dataInsert['t_create'] = (float)$_id;
						$dataInsert['t_ring'] = 0;
						$dataInsert['t_answer'] = 0;
						$dataInsert['t_hangup'] = 0;
						$dataInsert['Variable'] = [];
						$dataInsert['DialStatus'] = 'DONOTCONTACT';

						$insert = $mgdb->insert($db_collection, $dataInsert);
						if($insert['status'] == false)
						{
							$res['Error'] = 'Insert Newchannel';
							$res['Msg'] = $insert['data']['msg'];
							$app->callLogSave($res);
						}
						$logCheck[$_id]['status'] = $insert['status'];
					}
				}
				elseif($res['Event'] == 'DialBegin')
				{	
					if($res['DestUniqueid'] == $res['DestLinkedid'])
					{
						//$app->callLogSave($res);
						
						//unset($res['Event']);
						//unset($res['Privilege']);

						$dialData = explode("/", $res['DialString']);
						$Exten = empty($dialData[1]) ? $dialData[0] : $dialData[1];
						$Trunk = isset($dialData[1]) ? $dialData[0] : '';

						$res['Exten'] = $Exten;
						$res['Trunk'] = $Trunk;
						
						/*
						if(isset($logCheck[$_id]['status']) && $logCheck[$_id]['status'] == true)
						{
							$updateData[ '$set' ] = $res;
						}
						else
						{
							$dataInsert = $res;
							if(empty($res['CallerIDNum']) && !empty($res['DestConnectedLineNum']) && is_numeric($res['DestConnectedLineNum']))
							{
								$dataInsert['CallerIDNum'] = !empty($res['DestConnectedLineNum']) ? $res['DestConnectedLineNum'] : $res['DestCallerIDNum'];
							}
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
						*/
						$updateData[ '$set' ] = $res;
						
						if(!empty($res['Context']) && !empty($res['ChannelStateDesc']) && !empty($res['ConnectedLineNum']) && is_numeric($res['ConnectedLineNum']))
						{
							$updateData['$set']['CallerIDNum'] = $res['ConnectedLineNum'];
							$updateData['$set']['CallerIDName'] = '';
						}
						elseif(empty($res['CallerIDNum']) && !empty($res['DestConnectedLineNum']) && is_numeric($res['DestConnectedLineNum']))
						{
							$updateData['$set']['CallerIDNum'] = !empty($res['DestConnectedLineNum']) ? $res['DestConnectedLineNum'] : $res['DestCallerIDNum'];
						}

						if(!empty($updateData))
						{
							$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $updateData, []);
							if($update['status'] == false)
							{
								$res['Error'] = 'Update DialBegin';
								$res['Msg'] = $update['data']['msg'];
								$app->callLogSave($res);
							}
						}
					}
					else
					{
						if(!empty($res['DialString']))
						{
							$dialData = explode("/", $res['DialString']);
							$MemberName = empty($dialData[1]) ? $dialData[0] : $dialData[1];
							$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['MemberName' => $MemberName]], []);
							if($update['status'] == false)
							{
								$res['Error'] = 'Update DialBegin MemberName';
								$res['Msg'] = $update['data']['msg'];
								$app->callLogSave($res);
							}
						}
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
					
					$DataCRMCustomer = $mgdb->select('call_crm_customers', ['t_dial' => (float)$_id]);
					if(!empty($DataCRMCustomer['data']['_id']))
					{
						$mgdb->update('call_crm_customers', ['_id' => $DataCRMCustomer['data']['_id']], ['$set' => [$DataCRMCustomer['data']['campaign'].'.DialStatus' => $res['DialStatus']]], []);
					}
				}
				elseif($res['Event'] == 'AgentConnect')
				{
					$t_up_sub = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['HoldTime' => $res['HoldTime'], 'RingTime' => $res['RingTime'], 't_up_sub' => $t_up_sub]], []);
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
					
					$DataCRMCustomer = $mgdb->select('call_crm_customers', ['t_dial' => (float)$_id]);
					if(!empty($DataCRMCustomer['data']['_id']))
					{
						$mgdb->update('call_crm_customers', ['_id' => $DataCRMCustomer['data']['_id']], ['$set' => [$DataCRMCustomer['data']['campaign'].'.agent' => $res['MemberName']]], []);
					}
				}
				elseif($res['Event'] == 'AgentComplete')
				{
					$t_end_sub = microtime(true);
					
					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['TalkTime' => $res['TalkTime'], 't_end_sub' => (float)$t_end_sub]], []);
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
					//$app->callLogSave($res);
					$t_hangup = microtime(true);
					
					$logData = $mgdb->select('call_log', ['_id' => (float)$_id]);
					
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
					
					if(!empty($res['Cause']))
					{
						$setHangup['$set']['Cause'] = $res['Cause'];
					}
					
					if(empty($updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']))
					{
						if(!empty($res['Cause-txt']) && $res['Cause-txt'] == 'Unknown')
						{
							$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = ['Name' => 'DIALSTATUS', 'Value' => 'CANCEL', 'Time' => microtime(true)];
						}
						
						if(!empty($res['Cause']) && $res['Cause'] == 16 && empty($updateVariableData[$_id]['$set']['Variable']['KEYPAD']))
						{
							$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = ['Name' => 'DIALSTATUS', 'Value' => 'DONOTCONTACT', 'Time' => microtime(true)];
						}
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['KEYPAD']))
					{
						$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = ['Name' => 'DIALSTATUS', 'Value' => 'ANSWER', 'Time' => microtime(true)];
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']['Value']))
					{
						$setHangup['$set']['t_answer'] = (float)$updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']['Value'];
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_NUM']['Value']))
					{
						$setHangup['$set']['Exten'] = $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_NUM']['Value'];
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']))
					{
						$setHangup['$set']['campaign_contact_id'] = $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value'];
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['RINGTIME']))
					{
						$setHangup['$set']['t_ring'] = $updateVariableData[$_id]['$set']['Variable']['RINGTIME']['Value'];
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']))
					{
						if(!empty($logData['data']['DialStatus']) && $logData['data']['DialStatus'] != 'ANSWER')
						{
							$setHangup['$set']['DialStatus'] = $updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value'];
						}
					}
					
					if(empty($res['Exten']))
					{
						$setHangup['$set']['DialStatus'] = 'DONOTCONTACT';
						$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value'] = 'DONOTCONTACT';
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['USER_UID']))
					{
						$setHangup['$set']['uid'] = explode(',', $updateVariableData[$_id]['$set']['Variable']['USER_UID']['Value']);
					}
					
					if(!empty($updateVariableData[$_id]['$set']['Variable']['KEYPAD']))
					{
						$setHangup['$set']['DialStatus'] = 'ANSWER';
						$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value'] = 'ANSWER';
						$setHangup['$set']['KEYPAD'] = (array)$updateVariableData[$_id]['$set']['Variable']['KEYPAD'];
					}

					$update = $mgdb->update($db_collection, ['_id' => (float)$_id], $setHangup, []);
					
					/* */
					if(isset($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']))
					{
						$DataCRMCustomer = $mgdb->select('call_crm_customers', ['t_dial' => (float)$_id]);
						
						//ANSWEREDTIME
						if(!empty($resCache[$_id]['t_up']) || !empty($updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']))
						{
							if(!empty($updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME']))
							{
								$t_answer = (float)$updateVariableData[$_id]['$set']['Variable']['ANSWEREDTIME'];
							}
							else 
							{
								$t_answer = (float)round($t_hangup - $resCache[$_id]['t_up']);
							}
							if(!empty($DataCRMCustomer['data']['_id']))
							{
								$setCrmUpdate['$set'][$DataCRMCustomer['data']['campaign'].'.t_answer'] = $t_answer;
							}
							$setCamUpdate['$set']['t_answer'] = $t_answer;
						}
						
						//DIALSTATUS
						if(!empty($updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value']))
						{
							$setCamUpdate['$set']['DialStatus'] = $updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value'];
							if(!empty($DataCRMCustomer['data']['_id']))
							{
								$setCrmUpdate['$set'][$DataCRMCustomer['data']['campaign'].'.DialStatus'] = $updateVariableData[$_id]['$set']['Variable']['DIALSTATUS']['Value'];
							}
						}
						
						//KEYPAD
						if(!empty($updateVariableData[$_id]['$set']['Variable']['KEYPAD']))
						{
							$setCamUpdate['$set']['KEYPAD'] = (array)$updateVariableData[$_id]['$set']['Variable']['KEYPAD'];
							if(!empty($DataCRMCustomer['data']['_id']))
							{
								$setCrmUpdate['$set'][$DataCRMCustomer['data']['campaign'].'.KEYPAD'] = (array)$updateVariableData[$_id]['$set']['Variable']['KEYPAD'];
							}
						}
						
						if(!empty($DataCRMCustomer['data']['_id']))
						{
							$mgdb->update('call_crm_customers', ['_id' => $DataCRMCustomer['data']['_id']], $setCrmUpdate, []);
						}
						
						if(!empty($setCamUpdate))
						{
							$mgdb->update('call_campaign_contacts', ['_id' => $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']], $setCamUpdate, []);
						}
					}
					
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$res['Error'] = 'Update Hangup';
						$res['Msg'] = $update['data']['msg'];
						$app->callLogSave($res);
					}
					
					//$app->callLogSave($resDebug[$_id]);
					
					if(!empty($updateVariableData[$_id]))
					{
						$update = $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['Variable' => $updateVariableData[$_id]['$set']['Variable']]], []);
						if($update['status'] == false)
						{
							$res['Error'] = 'Update VarSet';
							$res['Msg'] = $update['data']['msg'];
							$app->callLogSave($res);
						}
						if(!empty($resCache[$_id]))
						{
							unset($resCache[$_id]);
						}
						if(!empty($updateVariableData[$_id]))
						{
							unset($updateVariableData[$_id]);
						}
					}
				}
			}
			else
			{
				//$app->callLogSave($res);
				if(!empty($res['Event']) && $res['Event'] == 'RTCPSent')
				{
					$updateVariableData[$_id]['$set']['Variable']['DIALSTATUS'] = [
						'Name' => 'DIALSTATUS', 
						'Value' => 'NOANSWER', 
						'Time' => microtime(true)
					];
				}
				if(isset($res['Event']) && isset($res['Variable']) && $res['Event'] == 'VarSet' && isset($res['Value']))
				{
					$varNameUpdate = (string)$res['Variable'];
					if($res['Variable'] == 'KEYPAD' || $res['Variable'] == 'keypad')
					{
						$keyOne = ['A', 'B', 'C', 'a', 'b', 'c'];
						$keyTwo = ['D', 'E', 'F', 'd', 'e', 'f'];
						if(in_array($res['Value'], $keyOne))
						{
							$res['Value'] = 1;
						}
						elseif(in_array($res['Value'], $keyTwo))
						{
							$res['Value'] = 2;
						}
						$updateVariableData[$_id]['$set']['Variable']['KEYPAD'][] = [
							'Name' => 'KEYPAD', 
							'Value' => $res['Value'], 
							'Time' => microtime(true)
						];
					}
					else
					{
						if($res['Variable'] == 'DIALSTATUS' && $res['Value'] == 'CHANUNAVAIL')
						{
							$res['Value'] = 'DONOTCONTACT';
						}
						$res['Value'] = (string)$res['Value'];
						$updateVariableData[$_id]['$set']['Variable'][$varNameUpdate] = [
							'Name' => $res['Variable'], 
							'Value' => $res['Value'], 
							'Time' => microtime(true)
						];
					}
					
					//update campaign id
					if($res['Variable'] == 'CAMPAIGN_ID')
					{
						$mgdb->update('call_campaigns', ['_id' => $res['Value']], ['$inc' => ['totalCalled' => 1]], []);
                        $mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['campaign_id' => $res['Value']]], []);
						
						if(!empty($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']))
						{
							$mgdb->update('call_crm_customers', ['_id' => $updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_CONTACT_ID']['Value']], ['$set' => [$res['Value'].'.t_dial' => (float)$_id]], []);
						}
						
						if(!empty($updateVariableData[$_id]['$set']['Variable']['AGI_CALL_TYPE_DES']) && $updateVariableData[$_id]['$set']['Variable']['AGI_CALL_TYPE_DES']['Value'] == 're-dial')
						{
							$mgdb->update('call_crm_customers', ['_id' => $res['Value']], ['$set' => [$res['Value'].'.t_redial' => (float)$_id]], []);
						}
					}
					
					//update campaign contact id
					if($res['Variable'] == 'CAMPAIGN_CONTACT_ID')
					{
						$mgdb->update('call_campaign_contacts', ['_id' => $res['Value']], ['$set' => ['t_dial' => (float)$_id]], []);
						
						if(!empty($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']))
						{
							$mgdb->update('call_crm_customers', ['_id' => $res['Value']], ['$set' => [$updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']['Value'].'.t_dial' => (float)$_id]], []);
						}
					}
					
					if($res['Variable'] == 'AGI_CALL_TYPE_DES' && $res['Value'] == 're-dial')
					{
						if(!empty($updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']))
						{
							$mgdb->update('call_crm_customers', ['_id' => $res['Value']], ['$set' => [$updateVariableData[$_id]['$set']['Variable']['CAMPAIGN_ID']['Value'].'.t_redial' => (float)$_id]], []);
						}
						$mgdb->update('call_campaign_contacts', ['_id' => $res['Value']], ['$set' => ['t_redial' => (float)$_id]], []);
					}
					
					if($res['Variable'] == 'AGI_CALL_TYPE')
					{
						$mgdb->update($db_collection, ['_id' => (float)$_id], ['$set' => ['call_type' => $res['Value']]], []);
					}
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
            //$app->callLogSave($res);
			$res = array();
		}
		$app->errorSave();
	}
}
fclose($socket);
?>