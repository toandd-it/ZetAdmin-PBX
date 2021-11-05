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
					$_mgid = (string)new \MongoDB\BSON\ObjectID;
					$dialData = explode("/", $res['DialString']);
					$Exten = empty($dialData[1]) ? $dialData[0] : $dialData[1];
					$Trunk = isset($dialData[1]) ? $dialData[0] : '';
					$campaign_id = '';
					if(!empty($res['DestConnectedLineNum']))
					{
						//$campaignData = $mgdb->select('call_campaigns', ['context' => (string)$res['DestConnectedLineNum']]);
						//if(!empty($campaignData['data']['_id']))
						//{
							//$campaign_id = (string)$campaignData['data']['_id'];
						//}
					}
					
					$trunkData = $mgdb->select('call_sip_trunk', ['_id' => (string)$Trunk]);
					if(isset($trunkData['data']['prefix']))
					{
						$Exten = ltrim($Exten, $trunkData['data']['prefix']);
					}
					
					$t_create = microtime(true);
					$dataInsert = array(
						'_id' => (string)$_id, 
						'CallerIDNum' => (string)isset($res['CallerIDName']) ? $res['CallerIDName'] : $res['DestCallerIDNum'],
						'Exten' => (string)$Exten,
						'Context' => (string)$res['DestContext'],
						'ConnectedLineNum' => (string)$res['DestConnectedLineNum'],
						'ConnectedLineName' => (string)$res['DestConnectedLineName'],
						'DialString' => $res['DialString'],
						'Trunk' => (string)$Trunk,
						'Uniqueid' => (float)$_id,
						'campaign_id' => $campaign_id,
						't_create' => $t_create, 
						't_call' => 0,
						't_up' => 0, 
						't_hangup' => 0
					);
					//$app->cdrSave($dataInsert);
					$mgdb->insert($db_collection, $dataInsert);
					if(!empty($campaign_id))
					{
						//$mgdb->update('call_campaign_contacts', ['campaign' => (string)$campaign_id, 'phone' => (string)$Exten], ['$set' => ['t_create' => $t_create, 'Uniqueid' => (string)$_id, 'channel' => (string)$channel]], []);
						
						$mgdb->update('call_campaigns', ['_id' => (string)$campaign_id], ['$inc' => ['totalCalled' => 1]], []);
						$resCache[$channel]['campaign'] = $campaign_id;

					    $app->cdrSave($dataInsert);
					}
				}
				elseif($res['Event'] == 'AgentCalled')
				{
					$t_create_sub = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => $channel], ['$set' => ['t_create_sub' => $t_create_sub, 'DestChannel' => $res['DestChannel'], 'MemberName' => $res['MemberName']]], []);
					if($update['status'] == false)
					{
						$res['t_create_sub'] = microtime(true);
						$app->cdrSave($res);
					}
					//$mgdb->update('call_campaign_contacts', ['Uniqueid' => (string)$_id], ['$set' => ['agent' => $res['MemberName']]], []);
				}
				elseif($res['Event'] == 'DialEnd')
				{
					$t_up = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (string)$_id], ['$set' => ['t_up' => $t_up, 'DialStatus' => $res['DialStatus']]], []);
					if($update['status'] == false)
					{
						$res['t_up'] = microtime(true);
						$app->cdrSave($res);
					}
					else
					{
						$resCache[$channel]['t_up'] = $t_up;
					}
					//$mgdb->update('call_campaign_contacts', ['Uniqueid' => (string)$_id], ['$set' => ['t_up' => $t_up, 'DialStatus' => $res['DialStatus']]], []);
				}
				elseif($res['Event'] == 'AgentConnect')
				{
					$t_up_sub = microtime(true);
					$update = $mgdb->update($db_collection, ['_id' => (string)$_id], ['$set' => ['t_up_sub' => $t_up_sub]], []);
					if($update['status'] == false)
					{
						$res['t_up_sub'] = microtime(true);
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
					if(!empty($resCache[$channel]['t_up']))
					{
						if(!empty($resCache[$channel]['t_up_sub']))
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 'Context' => $res['Context'], 't_call' => ($t_hangup - $resCache[$channel]['t_up']), 't_call_sub' => ($t_hangup - $resCache[$channel]['t_up_sub']), 'cause_txt' => $res['Cause-txt']]];
						}
						else
						{
							$setHangup = ['$set' => ['t_hangup' => $t_hangup, 'Context' => $res['Context'], 't_call' => ($t_hangup - $resCache[$channel]['t_up']), 'cause_txt' => $res['Cause-txt']]];
						}
					}
					else
					{
						$setHangup = ['$set' => ['t_hangup' => $t_hangup, 'Context' => $res['Context'], 'cause_txt' => $res['Cause-txt']]];
					}
					$update = $mgdb->update($db_collection, ['_id' => (string)$_id], $setHangup, []);
					if($update['status'] == false)
					{
						$res['t_hangup'] = microtime(true);
						$app->cdrSave($res);
					}
					//$mgdb->update('call_campaign_contacts', ['Uniqueid' => (string)$_id], $setHangup, []);
					if(!empty($resCache[$channel]))
					{
						unset($resCache[$channel]);
					}
				}
				$app->cdrSave($res);
			}
			else
			{
				if(isset($res['Event']) && isset($res['Variable']) && $res['Event'] == 'VarSet' && $res['Variable'] == 'keypad')
				{
					if(!isset($res['Value']))
					{
						$res['Value'] = 0;
					}
					$mgdb->update($db_collection, ['_id' => (string)$_id], ['$set' => ['keypad' => (string)$res['Value']]], []);
					$mgdb->update('call_campaign_contacts', ['Uniqueid' => (string)$_id], ['$set' => ['keypad' => (string)$res['Value']]], []);
					
					$app->cdrSave($res);
				}
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