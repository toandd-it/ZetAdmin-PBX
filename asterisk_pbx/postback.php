<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

ob_start();
session_start();

$session_id = session_id();

$dir_root = explode($_SERVER['SCRIPT_NAME'], $_SERVER['SCRIPT_FILENAME'])[0];

$host_name = $_SERVER['HTTP_HOST'];
$uag = '';
if(isset($_SERVER['HTTP_USER_AGENT']))
{
    $uag = $_SERVER['HTTP_USER_AGENT'];
}
$_languageDefault = 'en';

include("config.php");
include_once("phpagi-asmanager.php");

if(isset($_POST['action']) && !empty($_POST['action']) && !empty($_POST['api_id']) && !empty($_POST['api_key']))
{
    $api_id = $_POST['api_id'];
	$action = $_POST['action'];
	if(!empty($_POST['data']))
	{
		$data = json_decode($_POST['data'], true);
	}
	else
	{
		$data = array();
	}
    if(!empty($_POST['lang']))
    {
        $langCode = $_POST['lang'];
    }
    else
    {
        $langCode = 'en';
    }
    
	$msgData = array();

    foreach (glob($dir_root."/lib/*.php") as $filename)
    {
        include_once $filename;
    }
    
    if($_POST['api_key'] == $api_key)
    {
        $app = new PbxApi();
        $ip = $app->ip();
        $_langLoad = $app->_langLoad();
        
        if(!empty($ipsAlow))
        {
            if(!in_array($ip, $ipsAlow))
            {
                $returnData = array(
                    'status' => false,
                    'action' => $action,
                    'data' => array(),
                    'msg' => $app->_lang('msg_000'),
                    'time' => time()
                );
                $app->returnDataJson( $returnData );
            }
        }

        if(isset($db_url))
        {
            $mgdb = new MGDB_Api($db_url, $db_name);
        }

        foreach (glob($dir_root."/action/*.php") as $actionfile)
        {
            include $actionfile;
        }

        $returnData = array(
            'status' => false,
            'action' => $action,
            'data' => array(),
            'msg' => $app->_lang('msg_001'),
            'time' => time()
        );
        $app->returnDataJson( $returnData );
    }
    else
    {
        $returnData = array(
            'status' => false,
            'action' => $action,
            'data' => array(),
            'msg' => $app->_lang('msg_002'),
            'time' => time()
        );
        $app->returnDataJson( $returnData );
    }
} 
else 
{
	$returnData = array(
		'status' => false,
		'action' => NULL,
        'data' => array(),
		'msg' => 'Forbidden, API Action does not exist!',
		'time' => time()
	);
	$app->returnDataJson( $returnData );
}
?>