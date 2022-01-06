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

$_id = (string)new \MongoDB\BSON\ObjectID;

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
$agi_channel = $agi->request[agi_channel];
$agi_context = $agi->request[agi_context];
$agi_extension = $agi->request[agi_extension];
$agi_uniqueid = $agi->request[agi_uniqueid]; 

$USER_UID = $agi->get_variable("USER_UID", true);
$CAMPAIGN_ID = $agi->get_variable("CAMPAIGN_ID", true);
$CAMPAIGN_CONTACT_NUM = $agi->get_variable("CAMPAIGN_CONTACT_NUM", true);
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
$contextData = $mgdb->select('call_contexts', ['_id' => $agi_context]);
if(!empty($contextData['data']['sip_trunk']))
{
	$agi->set_variable('AGI_TRUNK', $contextData['data']['sip_trunk']);
}
else
{
	$agi->set_variable('AGI_TRUNK', $AGI_TRUNK);
}

if(!empty($contextData['data']['forward_trunks']) && !empty($contextData['data']['forward_phones']))
{
	$debugFwTrunk = '/var/lib/asterisk/agi-bin/caches/forward_context_'.$agi_context.'.txt';
	if(count($contextData['data']['forward_trunks']) == 1)
	{
		$AGI_FORWARD_TRUNK = $contextData['data']['forward_trunks'][0];
		$agi->set_variable('AGI_FORWARD_TRUNK', $AGI_FORWARD_TRUNK);
		file_put_contents($debugFwTrunk, $AGI_FORWARD_TRUNK, FILE_APPEND | LOCK_EX);
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
	
	$trunkFwData = $mgdb->select('call_sip_trunk', ['_id' => (float)$AGI_FORWARD_TRUNK]);
	
	$debugFwNumber = '/var/lib/asterisk/agi-bin/caches/forward_number_'.$agi_context.'.txt';
	if(count($contextData['data']['forward_phones']) == 1)
	{
		$AGI_FORWARD_NUMBER = $contextData['data']['forward_phones'][0];
		$agi->set_variable('AGI_FORWARD_NUMBER', $trunkFwData['data']['prefix'].$AGI_FORWARD_NUMBER);
		file_put_contents($debugFwNumber, $AGI_FORWARD_NUMBER, FILE_APPEND | LOCK_EX);
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
if(isset($contextData['data']['recording']) && $contextData['data']['recording'] == 'yes')
{
    $agi_uniqueid_md5 = md5($agi_uniqueid);
	$agi->exec("MixMonitor", "/var/spool/asterisk/monitor/$agi_uniqueid_md5.wav,b");
    $recording = $contextData['data']['recording'];
}

$callback = 'no';
if(isset($contextData['data']['callback']) && $contextData['data']['callback'] == 'yes')
{
	$callback = $contextData['data']['callback'];
}

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
	
	if(!empty($AGI_FORWARD_NUMBER) && !empty($AGI_FORWARD_TRUNK))
	{
		$updateCallLog = ['$set' => ['TrunkFw' => $AGI_FORWARD_TRUNK, 'ExtenFw' => $AGI_FORWARD_NUMBER, 'MemberName' => $AGI_FORWARD_NUMBER, 'Callback' => $callback, 'Recording' => $recording, 'CallerIDNum' => $agi_callerid, 'Exten' => $_CONTACT_NUM, 'uid' => $uid]];
	}
	else
	{
		$updateCallLog = ['$set' => ['Callback' => $callback, 'Recording' => $recording, 'CallerIDNum' => $agi_callerid, 'Exten' => $_CONTACT_NUM, 'uid' => $uid]];
	}
	
	$mgdb->update('call_log', ['_id' => (float)$agi_uniqueid], $updateCallLog);
	
	if(!empty($AGI_TRUNK) && !empty($CAMPAIGN_CONTACT_NUM))
	{
		if(!empty($AGI_FORWARD_NUMBER) && !empty($AGI_FORWARD_TRUNK))
		{
			$updateCamContact = ['$set' => ['TrunkFw' => $AGI_FORWARD_TRUNK, 'ExtenFw' => $AGI_FORWARD_NUMBER, 'agent' => $AGI_FORWARD_NUMBER, 'Trunk' => $AGI_TRUNK]];
		}
		else
		{
			$updateCamContact = ['$set' => ['Trunk' => $AGI_TRUNK]];
		}
		$mgdb->update('call_campaign_contacts', ['phone' => $CAMPAIGN_CONTACT_NUM], $updateCamContact);
	}
}
//type: internal / outbound / inbound

$extData = $mgdb->select('call_sip_account', ['_id' => (float)$_CONTACT_NUM]);
if(empty($extData['data']['_id']))
{
	$trunkData = $mgdb->select('call_sip_trunk', ['_id' => (float)$_CONTACT_NUM]);
	if(empty($trunkData['data']['_id']))
	{
		if(!empty($AGI_CALL_TYPE))
		{
			$agi->set_variable('AGI_CALL_TYPE', $AGI_CALL_TYPE);
		}
		else
		{
			$callType = 'outbound';
			$agi->set_variable('AGI_CALL_TYPE', 'outbound');
		}
		$trunkData = $mgdb->select('call_sip_trunk', ['_id' => (float)$contextData['data']['sip_trunk']]);

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
		}
		else
		{
			$agi->set_variable('AGI_CALL_TYPE', 'inbound');
		}
		$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
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
		$agi->set_variable('AGI_CALL_TYPE', 'internal');
	}
}

$agi->set_variable('PBX_AUTHOR', 'zetadmin.com');
$agi->set_variable('PBX_AUTHOR_EMAIL', 'info@zetadmin.com;toandd.it@gmail.com');

//CHECK UNAUTHORIZED
if(!empty($callType) && $callType == 'outbound')
{
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
				elseif($opt['date'] == $day_in_week && $opt['sip'] == $agi_callerid)
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
//$agi->say_number();
//$agi->hangup();
$app->errorSave();
?>