#!/usr/local/lsws/lsphp73/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require_once 'config.php';
require_once 'class.mongodb.php';
require_once 'phpagi.php';

$mgdb = new MGDB_Api($db_url, $db_name);
$agi = new AGI();
$data = $agi->get_variable();
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
$msgDebug = array2String($data);
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

//$callerid = $agi->get_variable("CALLERID(name)");
$timenow = strtotime('now');
$agi_callerid = preg_replace("#[^0-9]#", "", $agi->request[agi_callerid]);
$agi_calleridname = $agi->request[agi_calleridname];
$agi_channel = $agi->request[agi_channel];
$agi_context = $agi->request[agi_context];
$agi_extension = $agi->request[agi_extension];
$agi_uniqueid = $agi->request[agi_uniqueid]; 

//type: internal / outbound / inbound
$agi->set_variable('type', '');
$agi->set_variable('lookup-phone', '');
$agi->set_variable('call-to', '');
$agi->set_variable('phone', '');
$agi->set_variable('playback', '');
$agi->set_variable('log-id', $_id);

//$agi->say_number();
//$agi->hangup();
//request
//get_variable
//set_variable
?>
