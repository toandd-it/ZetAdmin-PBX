<?php
if(isset($action) && isset($mgdb))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    $calllog_colection = 'call_log';
    
    if($action == 'getListCallLog')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $dataReturn = $mgdb->selects($calllog_colection, $query, $option);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getCallLog')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($calllog_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'deleteMultiCallLog')
	{
        $ids = (array)json_decode($_POST['_id'], true);
        if(!empty($ids))
        {
            foreach($ids as $id)
            {
                $idFind = array('_id' => $id);
                $checkLog = $mgdb->select($calllog_colection, $idFind);
                if(!empty($checkLog['data']['_id']))
                {
                    $deleteStatus = $mgdb->delete($calllog_colection, $idFind);
                    $status = $deleteStatus['status'];
                    if($status == true)
                    {
                        $deleteSuccess = $deleteSuccess + 1;
                        //$msg = sprintf($app->_lang('msg_014'), $checkLog['data']['channel']);
                    } 
                    else 
                    {
                        $deleteError = $deleteError + 1;
                        //$msg = sprintf($app->_lang('msg_015'), $deleteStatus['data']['msg']);
                    }
                } 
                else 
                {
                    $status = false;
                    //$msg = $app->_lang('msg_013');
                    $deleteNull = $deleteNull + 1;
                }
            }
            $status = true;
            $msg = sprintf($app->_lang('msg_016'), $deleteSuccess, $deleteError, $deleteNull, count($ids));
        }
        else
        {
            $status = false;
			$msg = $app->_lang('msg_017');
        }

        $insertLogs = array(
			'id' => (array)$ids,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => '',
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $api_id,
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$returnData = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $returnData );
    }
    
	if($action == 'deleteCallLog')
	{
        $id = $_POST['_id'];
        $idFind = array('_id' => $id);
        $checkLog = $mgdb->select($calllog_colection, $idFind);
        if(!empty($checkLog['data']['_id']))
        {
            $deleteStatus = $mgdb->delete($calllog_colection, $idFind);
            $status = $deleteStatus['status'];
            if($status == true)
            {
                $msg = sprintf($app->_lang('msg_014'), $checkLog['data']['channel']);
            } 
            else 
            {
                $msg = sprintf($app->_lang('msg_015'), $deleteStatus['data']['msg']);
            }
        } 
        else 
        {
            $status = false;
            $msg 	= $app->_lang('msg_013');
        }

        $insertLogs = array(
			'id' => (array)$id,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => '',
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => '',
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$returnData = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $returnData );
	}
}
?>