#!/usr/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require 'config.php';
require 'class.action.php';
require 'class.mongodb.php';
require 'phpagi.php';
$app = new PbxApi();
$mgdb = new MGDB_Api($db_url, $db_name);
$agi = new AGI();

$partnerId = '';
$_id = (string)new \MongoDB\BSON\ObjectID;

$prefixNetwork = [
	'vinaphone' => ['088', '091', '094', '083', '084', '085', '081', '082'],
	'viettel_mobile' => ['086', '096', '097', '098', '032', '033', '034', '035', '036', '037', '038', '039'],
	'mobifone' => ['089', '090', '093', '070', '079', '077', '076', '078'],
	'vietnamobile' => ['092', '056', '058'],
	'gmobile' => ['099', '059'],
	'itelecom' => ['087']
];

function floatArray($stringArray)
{
	return array_map(
		function($value) { return (float)$value; },
		$stringArray
	);
}

//Start debug
/*
$debugFile = '/var/lib/asterisk/agi-bin/debug.txt';
function array2String($data=[])
{
    foreach($data as $name => $value)
    {
        if(is_array($value))
        {
            $value = '['.array2String($value).']';
        }
        $msgDebug .= $name.": ".$value.", ";
    }
    return rtrim($msgDebug, ', ');
}
$msgDebug = array2String($agi);
$maxSize = 10485760; //10M
if(file_exists($debugFile))
{
    $fileSize = filesize($debugFile);
    if($fileSize >= $maxSize)
    {
        if(rename($debugFile, '/var/lib/asterisk/agi-bin/debug-'.date('Y_m_d_H_i_s', filemtime($debugFile)).'-to-'.date('Y_m_d_H_i_s', time()).'.txt'))
        {
	        file_put_contents($debugFile, $msgDebug, FILE_APPEND | LOCK_EX);
        }
    }
    else
    {
        file_put_contents($debugFile, "\n".$msgDebug, FILE_APPEND | LOCK_EX);
    }
}
else
{
    file_put_contents($debugFile, $msgDebug, FILE_APPEND | LOCK_EX);
}
//End debug
*/
$timenow = strtotime('now');
$agi_callerid = preg_replace("#[^0-9]#", "", $agi->request[agi_callerid]);
$agi_calleridname = $agi->request[agi_calleridname];
if(!empty($agi->request[agi_arg_2]))
{
	$agi_callerid = explode('@', $agi->request[agi_arg_2])[0];
}
$agi_channel = $agi->request[agi_channel];
$agi_context = $agi->request[agi_context];
$agi_extension = $agi->request[agi_extension];
$agi_uniqueid = $agi->request[agi_uniqueid]; 

$USER_UID = $agi->get_variable("USER_UID", true);
$CAMPAIGN_ID = $agi->get_variable("CAMPAIGN_ID", true);
$CAMPAIGN_CONTACT_NUM = $agi->get_variable("CAMPAIGN_CONTACT_NUM", true);
$CAMPAIGN_CONTACT_NAME = $agi->get_variable("CAMPAIGN_CONTACT_NAME", true);
$CAMPAIGN_CONTACT_ID = $agi->get_variable("CAMPAIGN_CONTACT_ID", true);
$AGI_TRUNK = $agi->get_variable("AGI_TRUNK", true);
$AGI_CALL_TYPE = $agi->get_variable("AGI_CALL_TYPE", true);

if(!empty($CAMPAIGN_CONTACT_NUM))
{
	$_CONTACT_NUM = $CAMPAIGN_CONTACT_NUM;
}
else
{
	$_CONTACT_NUM = $agi_extension;
}

$_CONTACT_NUM_PREFIX = str_split((string)$_CONTACT_NUM, 3)[0];
$network = '';
foreach($prefixNetwork as $keyNetwork => $networkData)
{
	if(in_array($_CONTACT_NUM_PREFIX, $networkData))
	{
		$network = $keyNetwork;
		break;
	}
}
$agi->set_variable('SOURCE_ID', (float)$agi_uniqueid);
$agi->set_variable('AGI_NETWORK', $network);
$agi->set_variable('AGI_CALLERID', $agi_callerid);
$contextData = $mgdb->select('call_contexts', ['_id' => $agi_context]);

if(empty($USER_UID))
{
	$agi->set_variable('USER_UID', implode(',', $contextData['data']['uid']));
}
if(!empty($contextData['data']['forward_trunks']) && !empty($contextData['data']['forward_phones']))
{
	$debugFwTrunk = '/var/lib/asterisk/agi-bin/caches/forward_context_'.$agi_context.'.txt';
	if(count($contextData['data']['forward_trunks']) == 1)
	{
		$AGI_FORWARD_TRUNK = $contextData['data']['forward_trunks'][0];
		$agi->set_variable('AGI_FORWARD_TRUNK', $AGI_FORWARD_TRUNK);
		if(file_exists($debugFwTrunk))
		{
			file_put_contents($debugFwTrunk, "\n".$AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
		}
		else
		{
			file_put_contents($debugFwTrunk, $AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
		}
	}
	else
	{
		if(file_exists($debugFwTrunk))
		{
			$getCacheTfw = file($debugFwTrunk);
			if(count($getCacheTfw) == count($contextData['data']['forward_trunks']))
			{
				$lastTrunkFw = $getCacheTfw[0];
				unset($getCacheTfw[0]);
				
				$AGI_FORWARD_TRUNK = $lastTrunkFw;
				$agi->set_variable('AGI_FORWARD_TRUNK', $AGI_FORWARD_TRUNK);
				file_put_contents($debugFwTrunk, "\n".$AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
			}
			else
			{
				foreach($contextData['data']['forward_trunks'] as $fwt)
				{
					if(!in_array($fwt, $getCacheTfw))
					{
						$AGI_FORWARD_TRUNK = $fwt;
						$agi->set_variable('AGI_FORWARD_TRUNK', $AGI_FORWARD_TRUNK);
						file_put_contents($debugFwTrunk, "\n".$AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
						break;
					}
				}
			}
		}
		else
		{
			$AGI_FORWARD_TRUNK = $contextData['data']['forward_trunks'][0];
			$agi->set_variable('AGI_FORWARD_TRUNK', $AGI_FORWARD_TRUNK);
			
			file_put_contents($debugFwTrunk, $AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
		}
	}
	
	$trunkFwData = $mgdb->select('call_sip_trunk', ['_id' => $AGI_FORWARD_TRUNK]);
	
	$agi->set_variable('AGI_FORWARD_TRUNK_TYPE', $trunkFwData['data']['type']);
	
	$debugFwNumber = '/var/lib/asterisk/agi-bin/caches/forward_number_'.$agi_context.'.txt';
	if(count($contextData['data']['forward_phones']) == 1)
	{
		$AGI_FORWARD_NUMBER = $contextData['data']['forward_phones'][0];
		$agi->set_variable('AGI_FORWARD_NUMBER', $trunkFwData['data']['prefix'].$AGI_FORWARD_NUMBER);
		if(file_exists($debugFwNumber))
		{
			file_put_contents($debugFwNumber, "\n".$AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
		}
		else
		{
			file_put_contents($debugFwNumber, $AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
		}
	}
	else
	{
		if(file_exists($debugFwNumber))
		{
			$getCacheNfw = file($debugFwNumber);
			if(count($getCacheNfw) == count($contextData['data']['forward_phones']))
			{
				$lastTrunkFw = $getCacheNfw[0];
				unset($getCacheNfw[0]);
				
				$AGI_FORWARD_NUMBER = $lastTrunkFw;
				$agi->set_variable('AGI_FORWARD_NUMBER', $trunkFwData['data']['prefix'].$AGI_FORWARD_NUMBER);
				file_put_contents($debugFwNumber, "\n".$AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
			}
			else
			{
				foreach($contextData['data']['forward_phones'] as $Nwt)
				{
					if(!in_array($Nwt, $getCacheNfw))
					{
						$AGI_FORWARD_NUMBER = $Nwt;
						$agi->set_variable('AGI_FORWARD_NUMBER', $trunkFwData['data']['prefix'].$AGI_FORWARD_NUMBER);
						file_put_contents($debugFwNumber, "\n".$AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
						break;
					}
				}
			}
		}
		else
		{
			$AGI_FORWARD_NUMBER = $contextData['data']['forward_phones'][0];
			$agi->set_variable('AGI_FORWARD_NUMBER', $trunkFwData['data']['prefix'].$AGI_FORWARD_NUMBER);
			file_put_contents($debugFwNumber, $AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
		}
	}
}

$recording = 'no';
if(isset($contextData['data']['recording']) && $contextData['data']['recording'] == 'yes' && !empty($agi_callerid) && !empty($agi_extension))
{
    $agi_uniqueid_md5 = md5((float)$agi_uniqueid);
	$agi->exec("MixMonitor", "/var/spool/asterisk/monitor/$agi_uniqueid_md5.wav,b");
    $recording = $contextData['data']['recording'];
}

$callback = 'no';
if(isset($contextData['data']['callback']) && $contextData['data']['callback'] == 'yes' && !empty($agi_callerid) && !empty($agi_extension))
{
	$callback = $contextData['data']['callback'];
}
//type: internal / outbound / inbound

$extData = $mgdb->select('call_sip_account', ['_id' => (float)$_CONTACT_NUM]);
if(empty($extData['data']['_id']))
{
	$trunkData = $mgdb->select('call_sip_trunk', ['_id' => $_CONTACT_NUM]);
	if(empty($trunkData['data']['_id']))
	{
		if(!empty($AGI_CALL_TYPE))
		{
			$agi->set_variable('AGI_CALL_TYPE', $AGI_CALL_TYPE);
		}
		else
		{
			$callType = 'outbound';
			$AGI_CALL_TYPE = $callType;
			$agi->set_variable('AGI_CALL_TYPE', 'outbound');
		}
		$trunkData = $mgdb->select('call_sip_trunk', ['_id' => $contextData['data']['sip_trunk']]);

		if(isset($trunkData['data']['prefix']))
		{
			$agi->set_variable('AGI_CALL_NUMBER', $trunkData['data']['prefix'].$_CONTACT_NUM);
		}
		else
		{
			$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
		}
	}
	else
	{
		if(!empty($AGI_CALL_TYPE))
		{
			$agi->set_variable('AGI_CALL_TYPE', $AGI_CALL_TYPE);
			$agi->set_variable('AGI_CALL_NUMBER', $agi_callerid);
		}
		else
		{
			$AGI_CALL_TYPE = 'inbound';
			$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
			$agi->set_variable('AGI_CALL_TYPE', 'inbound');
		}
		
	}
}
else
{
	$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
	if(!empty($AGI_CALL_TYPE))
	{
		$agi->set_variable('AGI_CALL_TYPE', $AGI_CALL_TYPE);
	}
	else
	{
		$AGI_CALL_TYPE = 'internal';
		$agi->set_variable('AGI_CALL_TYPE', $AGI_CALL_TYPE);
	}
}

if($AGI_CALL_TYPE == 'inbound' && $callback == 'yes')
{
	//call_back
	$callLogData = $mgdb->select('call_log', ['_id' => ['$gte' => (float)(time() - 7200)], 'Exten' => $_CONTACT_NUM, 'call_type' => 'outbound', 'Context' => $agi_context], ['sort' => ['_id' => -1]]);
	if(!empty($callLogData['data']['_id']))
	{
		$agi->set_variable('AGI_CALL_BACK_NUM', $callLogData['data']['CallerIDNum']);
	}
	else
	{
		$agi->set_variable('AGI_CALL_BACK_NUM', '');
	}
}
else
{
	$agi->set_variable('AGI_CALL_BACK_NUM', '');
}

$AGI_CALL_PREFIX = '';
$AGI_TRUNK_TYPE = '';
if($AGI_CALL_TYPE == 'outbound')
{
	if(!empty($contextData['data']['sip_trunk']))
	{
		if(count($contextData['data']['sip_trunk']) > 1)
		{
			$trunksData = $mgdb->selects('call_sip_trunk', ['network' => $network, '_id' => ['$in' => $contextData['data']['sip_trunk']]], []);
			
			//$agi->set_variable('AGI_TRUNK_BY_NETWORK', json_encode(floatArray($contextData['data']['sip_trunk'])));
			if($trunksData['status'] == true && !empty($trunksData['data']))
			{
				//check trunk live
				$checkTrunkStatus = false;
				foreach($trunksData['data'] as $trunkD)
				{
					$trunkCheck = $trunkD['_id'];
					$callsLogByTrunk = $mgdb->select('call_log', ['Trunk' => (string)$trunkCheck, 'cause_txt' => ['$exists' => false]]);
					if(empty($callsLogByTrunk['data']['_id']))
					{
						$AGI_CALL_PREFIX = $trunkD['prefix'];
						$AGI_TRUNK_TYPE = $trunkD['style'];
						$AGI_TRUNK = $trunkCheck;
						$checkTrunkStatus = true;
						break;
					}
				}
				
				if($checkTrunkStatus == false)
				{
					$checkTrunkStatus2 = false;
					foreach($contextData['data']['sip_trunk'] as $trunkId)
					{
						$callsLogByTrunk2 = $mgdb->select('call_log', ['Trunk' => (string)$trunkId, 'cause_txt' => ['$exists' => false]]);
						if(empty($callsLogByTrunk2['data']['_id']))
						{
							$AGI_TRUNK = $trunkId;
							$trunksData = $mgdb->select('call_sip_trunk', ['_id' => $AGI_TRUNK], []);
							$AGI_TRUNK_TYPE = $trunksData['data']['style'];
							$checkTrunkStatus2 = true;
							break;
						}
					}
					if($checkTrunkStatus2 = false)
					{
						$agi->hangup();
					}
				}
			}
			else
			{
				$debugTrunk = '/var/lib/asterisk/agi-bin/caches/context_'.$agi_context.'.txt';
				if(file_exists($debugTrunk))
				{
					$getCacheT = file($debugTrunk);
					if(count($getCacheT) == count($contextData['data']['sip_trunk']))
					{
						$lastTrunk = $getCacheT[0];
						unset($getCacheT[0]);
						
						$AGI_TRUNK = $lastTrunk;
						$trunksData = $mgdb->select('call_sip_trunk', ['_id' => $AGI_TRUNK], []);
						$AGI_TRUNK_TYPE = $trunksData['data']['style'];
						file_put_contents($debugTrunk, "\n".$AGI_TRUNK, FILE_APPEND | LOCK_EX);
					}
					else
					{
						foreach($contextData['data']['sip_trunk'] as $sipTrunk)
						{
							if(!in_array($sipTrunk, $getCacheT))
							{
								$AGI_TRUNK = $sipTrunk;
								$trunksData = $mgdb->select('call_sip_trunk', ['_id' => $AGI_TRUNK], []);
								$AGI_TRUNK_TYPE = $trunksData['data']['style'];
								file_put_contents($debugTrunk, "\n".$AGI_TRUNK, FILE_APPEND | LOCK_EX);
								break;
							}
						}
					}
				}
				else
				{
					$AGI_TRUNK = $contextData['data']['sip_trunk'][0];
					$trunksData = $mgdb->select('call_sip_trunk', ['_id' => $AGI_TRUNK], []);
					$AGI_TRUNK_TYPE = $trunksData['data']['style'];
					file_put_contents($debugTrunk, $AGI_TRUNK, FILE_APPEND | LOCK_EX);
				}
			}
		}
		else
		{
			$trunksData = $mgdb->select('call_sip_trunk', ['_id' => $contextData['data']['sip_trunk'][0]], []);
			$AGI_TRUNK_TYPE = $trunksData['data']['style'];
			$AGI_TRUNK = $contextData['data']['sip_trunk'][0];
		}
		$agi->set_variable('AGI_TRUNK', $AGI_TRUNK);
	}
	else
	{
		$agi->set_variable('AGI_TRUNK', $AGI_TRUNK);
	}
}
else
{
	$agi->set_variable('AGI_TRUNK', $AGI_TRUNK);
}

$agi->set_variable('AGI_TRUNK_TYPE', $AGI_TRUNK_TYPE);

if(!empty($AGI_TRUNK) && empty($AGI_CALL_PREFIX))
{
	$trunkDataPrefix = $mgdb->select('call_sip_trunk', ['_id' => $AGI_TRUNK], []);
	if(!empty($trunkDataPrefix['data']['prefix']))
	{
		$AGI_CALL_PREFIX = $trunkDataPrefix['data']['prefix'];
	}
}
$agi->set_variable('AGI_CALL_PREFIX', $AGI_CALL_PREFIX);

if(!empty($agi_callerid) && !empty($agi_extension))
{
	$extCheckData = $mgdb->selects('call_sip_account', ['_id' => ['$in' => [(float)$agi_callerid, (float)$_CONTACT_NUM]]]);
	$uid = [];
	if($extCheckData['status'] == true)
	{
		foreach($extCheckData['data'] as $udata)
		{
			$uid = array_merge($uid, (array)$udata['uid']);
		}
		$uid = array_unique($uid, 0);
	}

	$updateCallLog = ['$set' => ['Callback' => $callback, 'Recording' => $recording, 'CallerIDNum' => $agi_callerid, 'Exten' => $_CONTACT_NUM, 'Trunk' => $AGI_TRUNK, 'uid' => $uid]];
	
	if($AGI_CALL_TYPE == 'autodial' || $AGI_CALL_TYPE == 'click2call')
	{
		$updateCallLog['$set']['DialStatus'] = 'ANSWER';
	}
	
	$mgdb->update('call_log', ['_id' => (float)$agi_uniqueid], $updateCallLog);
	
	if(!empty($AGI_TRUNK) && !empty($CAMPAIGN_CONTACT_NUM) && !empty($CAMPAIGN_CONTACT_ID))
	{
		$updateCamContactCrm = ['$set' => [$CAMPAIGN_ID.'.Trunk' => $AGI_TRUNK, $CAMPAIGN_ID.'.t_dial' => (float)$agi_uniqueid]];
		$updateCamContact = ['$set' => ['Trunk' => $AGI_TRUNK]];
		
		if(!empty($CAMPAIGN_CONTACT_ID))
		{
			$mgdb->update('call_crm_customers', ['_id' => $CAMPAIGN_CONTACT_ID], $updateCamContactCrm);
			$mgdb->update('call_campaign_contacts', ['_id' => $CAMPAIGN_CONTACT_ID], $updateCamContact);
		}
	}
}

$agi->set_variable('AGI_TIME', microtime(true));

$agi->set_variable('PBX_AUTHOR', 'zetadmin.com');
$agi->set_variable('PBX_AUTHOR_EMAIL', 'info@zetadmin.com;toandd.it@gmail.com');

//CHECK UNAUTHORIZED
if(!empty($callType) && $callType == 'outbound')
{
	$extCheckData = $mgdb->select('call_sip_account', ['_id' => (float)$agi_callerid]);
	if(empty($extCheckData['data']['_id']))
	{
		$agi->hangup();
	}
	
	$timezone = $contextData['data']['timezone'];
	$option_time = $contextData['data']['option_time'];
	if(!empty($option_time))
	{
		$day_in_week = date('D');
		$timeByTimezone = $app->unixToLocal(time(), $timezone);
		//$timeByTimezone = time();
		if(!empty($timeByTimezone))
		{
			$continueTime = $timeByTimezone - strtotime(date('Y-m-d', $timeByTimezone));
			foreach($option_time as $opt)
			{
				if($opt['date'] == 'All' && $opt['sip'] == 'all')
				{
					if($opt['start'] > $continueTime || $opt['end'] < $continueTime)
					{
						$agi->hangup();
					}
					break;
				}
				elseif($opt['date'] == $day_in_week && $opt['sip'] == $AGI_CALLERID)
				{
					if($opt['start'] > $continueTime || $opt['end'] < $continueTime)
					{
						$agi->hangup();
					}
					break;
				}
			}
		}
	}
}

//$agi->hangup();
$app->errorSave();
?>