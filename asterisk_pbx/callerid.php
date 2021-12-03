#!/usr/local/lsws/lsphp73/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require_once 'config.php';
require_once 'class.action.php';
require_once 'class.mongodb.php';
require_once 'phpagi.php';
$app = new PbxApi();
$mgdb = new MGDB_Api($db_url, $db_name);
$agi = new AGI();
//$data = $agi->get_variable();
$_id = (string)new \MongoDB\BSON\ObjectID;

//Start debug
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
	$mgdb->update('call_log', ['_id' => (float)$agi_uniqueid], ['$set' => ['CallerIDNum' => $agi_callerid, 'Exten' => $agi_extension, 'uid' => $uid]]);
}
//type: internal / outbound / inbound

$extData = $mgdb->select('call_sip_account', ['_id' => (float)$_CONTACT_NUM]);
if(empty($extData['data']['_id']))
{
	$trunkData = $mgdb->select('call_sip_trunk', ['_id' => (float)$_CONTACT_NUM]);
	if(empty($trunkData['data']['_id']))
	{
		$agi->set_variable('AGI_CALL_TYPE', 'outbound');
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
		$agi->set_variable('AGI_CALL_TYPE', 'inbound');
		$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
	}
}
else
{
	$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
	$agi->set_variable('AGI_CALL_TYPE', 'internal');
}

$agi->set_variable('PBX_AUTHOR', 'zetadmin.com');
$agi->set_variable('PBX_AUTHOR_EMAIL', 'info@zetadmin.com;toandd.it@gmail.com');

if(isset($contextData['data']['recording']) && $contextData['data']['recording'] == 'yes')
{
	$agi->exec("MixMonitor", "/var/spool/asterisk/monitor/$agi_uniqueid.wav,b");
}
//$agi->say_number();
//$agi->hangup();
//request
//get_variable
//set_variable
$app->errorSave();
?>