<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

$timeStart = microtime(true);

ob_start();
session_start();

$session_id = session_id();

$dir_root = explode('/'.basename($_SERVER['SCRIPT_FILENAME']), $_SERVER['SCRIPT_FILENAME'])[0];

$host_name = $_SERVER['HTTP_HOST'];
$uag = '';
if(isset($_SERVER['HTTP_USER_AGENT']))
{
    $uag = $_SERVER['HTTP_USER_AGENT'];
}
$_languageDefault = 'en';

include("config.php");
if(empty($_POST))
{
	$json = file_get_contents('php://input');
	if(!empty($json))
	{
		foreach(json_decode($json, true) as $name => $value)
        {
            if(is_array($value)){ $value = json_encode($value); }
            $_POST[$name] = $value;
        }
	}
}
if(isset($_POST['action']) && !empty($_POST['action']) && !empty($_POST['api_id']) && !empty($_POST['api_key']))
{
	$action = $_POST['action'];

    $data = array();
	if(!empty($_POST['data']))
	{
		$data = json_decode($_POST['data'], true);
	}

    $langCode = 'en';
    if(!empty($_POST['lang']))
    {
        $langCode = $_POST['lang'];
    }

    $authorAdmin = array();
    if(!empty($_POST['authorAdmin']))
    {
        $authorAdmin = $_POST['authorAdmin'];
    }
    
	$msgData = array();

    foreach (glob($dir_root."/lib/*.php") as $filename)
    {
        include_once $filename;
    }
    $app = new PbxApi();
    if($_POST['api_key'] == $api_key)
    {
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

        include("phpagi-asmanager.php");

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
	echo json_encode($returnData);
}
?>