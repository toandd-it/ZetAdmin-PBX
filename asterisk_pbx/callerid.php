#!/usr/local/lsws/lsphp73/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

include('phpagi.php');
$agi = new AGI();
$data = $agi->get_variable();

//get_variable
//set_variable
?>