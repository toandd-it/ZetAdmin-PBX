<?php
if(isset($action))
{
    $_id = (string)new \MongoDB\BSON\ObjectID;
    $queue_conf = '/etc/asterisk/queues_api.conf';
    $queue_colection = 'call_queue';
    
    if($action == 'getListQueue')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $count = $mgdb->count($queue_colection, $query)['data']['n'];
        $dataReturn = $mgdb->selects($queue_colection, $query, $option);
        $dataReturn['count'] = (int)$count;
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getQueue')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($queue_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'deleteMultiQueue')
	{
        $ids = json_decode($_POST['_id'], true);
        foreach($ids as $id)
        {
            $idFind = array('_id' => $id);
            $checkQueue = $mgdb->select($queue_colection, $idFind);
            if(!empty($checkQueue['data']['_id']))
            {
                $deleteStatus = $mgdb->delete($queue_colection, $idFind);
                $status = $deleteStatus['status'];
                if($status == true)
                {
                    $msg = 'Xóa hàng đợi <strong>'.$checkQueue['name'].'</strong> thành công!';
                } 
                else 
                {
                    $status = false;
                    $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$deleteStatus['data']['msg'];
                }
            } 
            else 
            {
                $status = false;
                $msg = 'Không tồn tại dữ liệu trên hệ thống!';
            }
        }
        
        $queues = $mgdb->selects($queue_colection, array('status' => 'Enable'));
        $lineData = '';
        foreach($queues['data'] as $queue)
        {
            if(!empty($queue['_id']))
            {
                $lineData .= "[".$queue['_id']."]\n";
                $lineData .= "joinempty=".$queue['joinempty']."\n";
                $lineData .= "autofill=".$queue['autofill']."\n";
                $lineData .= "timeout=".$queue['timeout']."\n";
                $lineData .= "strategy=".$queue['strategy']."\n";
                $lineData .= "autopause=".$queue['autopause']."\n";
                $lineData .= "maxlen=".$queue['maxlen']."\n";
                $lineData .= "retry=1\n";
                $lineData .= "wrapuptime=0\n";
                $lineData .= "queue-thankyou=".$queue['queue_thankyou']."\n\n";
            }
        }
        $app->updateFile($lineData, $queue_conf);
        
        $insertLogs = array(
			'id' => (array)$ids,
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
    
    if($action == 'deleteQueue')
    {
        $id = $_POST['_id'];
        $idFind = array('_id' => $id);
        $checkQueue = $mgdb->select($queue_colection, $idFind);
        if(!empty($checkQueue['data']['_id']))
        {
            $deleteStatus = $mgdb->delete($queue_colection, $idFind);
            $status = $deleteStatus['status'];
            if($status == true)
            {
                $msg = 'Xóa hàng đợi <strong>'.$checkQueue['name'].'</strong> thành công!';
                $queues = $mgdb->selects($queue_colection, array('status' => 'Enable'));
                
                $lineData = '';
                foreach($queues['data'] as $queue)
                {
                    if(!empty($queue['_id']))
                    {
                        $lineData .= "[".$queue['_id']."]\n";
                        $lineData .= "joinempty=".$queue['joinempty']."\n";
                        $lineData .= "autofill=".$queue['autofill']."\n";
                        $lineData .= "timeout=".$queue['timeout']."\n";
                        $lineData .= "strategy=".$queue['strategy']."\n";
                        $lineData .= "autopause=".$queue['autopause']."\n";
                        $lineData .= "maxlen=".$queue['maxlen']."\n";
                        $lineData .= "retry=1\n";
                        $lineData .= "wrapuptime=0\n";
                        $lineData .= "queue-thankyou=".$queue['queue_thankyou']."\n\n";
                    }
                }
                $app->updateFile($lineData, $queue_conf);
            } 
            else 
            {
                $status = false;
                $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$deleteStatus['data']['msg'];
            }
        } 
        else 
        {
            $status = false;
            $msg = 'Không tồn tại dữ liệu trên hệ thống!';
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

    if($action == 'updateQueue')
    {
        $id = $_POST['_id'];
        $dataUpdate = array(
            'name' 			=> $app->formDataName($data, 'name'),
            'joinempty' 	=> $app->formDataName($data, 'joinempty'),
            'autofill'		=> $app->formDataName($data, 'autofill'),
            'strategy' 		=> $app->formDataName($data, 'strategy'),
            'autopause'		=> $app->formDataName($data, 'autopause'),
            'queue_thankyou'=> "",
            'maxlen'		=> (int)$app->formDataName($data, 'maxlen'),
            'timeout'		=> (int)$app->formDataName($data, 'timeout'),
            'status' 		=> $app->formDataName($data, 'status')
        );
        $dataSetUpdate = array(
            '$set' => array(
                'name' 			=> $dataUpdate['name'],
                'joinempty' 	=> $dataUpdate['joinempty'],
                'autofill'		=> $dataUpdate['autofill'],
                'strategy' 		=> $dataUpdate['strategy'],
                'autopause'		=> $dataUpdate['autopause'],
                'queue_thankyou'=> "",
                'maxlen'		=> (int)$dataUpdate['maxlen'],
                'timeout'		=> (int)$dataUpdate['timeout'],
                'status' 		=> $dataUpdate['status']
            )
        );
        $option = array();
        $checkQueue = $mgdb->select($queue_colection, array('_id' => $id));
        if(!empty($checkQueue['data']['_id']))
        {
            $statusUpdate = $mgdb->update($queue_colection, array('_id' => $id), $dataSetUpdate, $option);
            $status = $statusUpdate['status'];
            if($status == true)
            { 
                $msg = 'Cập nhật hàng đợi <strong>'.$dataUpdate['name'].'</strong> thành công!';
                $queues = $mgdb->selects($queue_colection, array('status' => 'Enable'));
                $lineData = '';
                foreach($queues['data'] as $queue)
                {
                    if(!empty($queue['_id']))
                    {
                        $lineData .= "[".$queue['_id']."]\n";
                        $lineData .= "joinempty=".$queue['joinempty']."\n";
                        $lineData .= "autofill=".$queue['autofill']."\n";
                        $lineData .= "timeout=".$queue['timeout']."\n";
                        $lineData .= "strategy=".$queue['strategy']."\n";
                        $lineData .= "autopause=".$queue['autopause']."\n";
                        $lineData .= "maxlen=".$queue['maxlen']."\n";
                        $lineData .= "retry=1\n";
                        $lineData .= "wrapuptime=0\n";
                        $lineData .= "queue-thankyou=".$queue['queue_thankyou']."\n\n";
                    }
                }
                $app->updateFile($lineData, $queue_conf);
            } 
            else 
            { 
                $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$statusUpdate['data']['msg'];
            }
        } 
        else 
        { 
            $status = false;
            $msg = 'Không tồn tại dữ liệu trên hệ thống!';
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

    if($action == 'insertQueue')
    {
        $id = $_id;
        $dataInsert = array(
            '_id' 			=> $id,
            'name' 			=> $app->formDataName($data, 'name'),
            'joinempty' 	=> $app->formDataName($data, 'joinempty'),
            'autofill'		=> $app->formDataName($data, 'autofill'),
            'strategy' 		=> $app->formDataName($data, 'strategy'),
            'autopause'		=> $app->formDataName($data, 'autopause'),
            'queue_thankyou'=> "",
            'maxlen'		=> (int)$app->formDataName($data, 'maxlen'),
            'timeout'		=> (int)$app->formDataName($data, 'timeout'),
            'tcreate' 		=> (int)time(),
            'status' 		=> $app->formDataName($data, 'status')
        );
        $checkQueue = $mgdb->select($queue_colection, array('name' => $dataInsert['name']));
        if(!empty($checkQueue['data']['_id']))
        { 
            $status = false;
            $msg = 'Hàng đợi <strong>'.$dataInsert['name'].'</strong> đã tồn tại trên hệ thống!';
        } 
        else 
        { 
            $statusInsert = $mgdb->insert($queue_colection, $dataInsert);
            $status = $statusInsert['status'];
            if($status == true)
            { 
                $queues = $mgdb->selects($queue_colection, array('status' => 'Enable'));
                $lineData = '';
                foreach($queues['data'] as $queue)
                {
                    if(!empty($queue['_id']))
                    {
                        $lineData .= "[".$queue['_id']."]\n";
                        $lineData .= "joinempty=".$queue['joinempty']."\n";
                        $lineData .= "autofill=".$queue['autofill']."\n";
                        $lineData .= "timeout=".$queue['timeout']."\n";
                        $lineData .= "strategy=".$queue['strategy']."\n";
                        $lineData .= "autopause=".$queue['autopause']."\n";
                        $lineData .= "maxlen=".$queue['maxlen']."\n";
                        $lineData .= "retry=1\n";
                        $lineData .= "wrapuptime=0\n";
                        $lineData .= "queue-thankyou=".$queue['queue_thankyou']."\n\n";
                    }
                }
                $app->updateFile($lineData, $queue_conf);
                $msg = 'Thêm mới hàng đợi <strong>'.$dataInsert['name'].'</strong> thành công!';
            } 
            else 
            {
                $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$statusInsert['data']['msg'];
            }
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