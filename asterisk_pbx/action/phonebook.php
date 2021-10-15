<?php
if(isset($action))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    $phonebook_colection = 'call_phonebook';
    
    if($action == 'getListPhonebook')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $count = $mgdb->count($phonebook_colection, $query)['data']['n'];
        $dataReturn = $mgdb->selects($phonebook_colection, $query, $option);
        $dataReturn['count'] = (int)$count;
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getPhonebook')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($phonebook_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
}
?>