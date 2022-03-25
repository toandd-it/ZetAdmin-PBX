<?php
if(isset($action))
{
    $_id = (string)new \MongoDB\BSON\ObjectID;
    $ext_conf = '/etc/asterisk/pjsip_account.conf';
    $conference_colection = 'call_pjsip';
}
?>