#!/usr/local/lsws/lsphp73/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

include('phpagi.php');
$agi = new AGI();
$data = $agi->get_variable();


//Start debug
$debugFile = '/var/lib/asterisk/agi-bin/debug.txt';
$msgDebug = "";
foreach($data as $name => $value)
{
    $msgDebug .= ucfirst($name).": ".$value." | ";
}
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


//get_variable
//set_variable
?>