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
        $ids = json_decode($_POST['_id'], true);
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
                    $msg = 'Xóa lịch sử cuộc gọi <strong>'.$checkLog['data']['channel'].'</strong> thành công!';
                } 
                else 
                {
                    $msg = 'Máy chủ đang bận vui lòng thử lại sau! '.$deleteStatus['data']['msg'];
                }
            } 
            else 
            {
                $status = false;
                $msg = 'Không tồn tại dữ liệu trên hệ thống!';
            }
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
                $msg = 'Xóa lịch sử cuộc gọi <strong>'.$checkLog['data']['channel'].'</strong> thành công!';
            } 
            else 
            {
                $msg 	= 'Máy chủ đang bận vui lòng thử lại sau! '.$deleteStatus['data']['msg'];
            }
        } 
        else 
        {
            $status = false;
            $msg 	= 'Không tồn tại dữ liệu trên hệ thống!';
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