<?php
if(isset($action))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    $ext_conf = '/etc/asterisk/pjsip_conference.conf';
    $conference_colection = 'call_conference';
    
    if($action == 'getListConference')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $dataReturn = $mgdb->selects($conference_colection, $query, $option);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getConference')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($conference_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
}
?>