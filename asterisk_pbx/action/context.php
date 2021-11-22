<?php
if(isset($action))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    $ext_conf = '/etc/asterisk/extensions_api.conf';
    $context_colection = 'call_context';
    
    if($action == 'getListContext')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $count = $mgdb->count($context_colection, $query)['data']['n'];
        $dataReturn = $mgdb->selects($context_colection, $query, $option);
        $dataReturn['count'] = (int)$count;
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getContext')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($context_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'deleteMultiContext')
	{
        $ids = json_decode($_POST['_id'], true);
		if(!empty($ids))
        {
			foreach($ids as $id)
			{
				$idFind = array('_id' => $id);
				$checkExtension = $mgdb->select($context_colection, $idFind);
				if(!empty($checkExtension['data']['_id']))
				{
					$deleteStatus = $mgdb->delete($context_colection, $idFind);
					$status = $deleteStatus['status'];
					if($status == true)
					{
						//$msg = sprintf($app->_lang('msg_018'), $checkExtension['name']);
						$deleteSuccess = $deleteSuccess + 1;
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
        
        $extensions = $mgdb->selects($context_colection, array('status' => 'Enable'));

        $lineData = '';
        foreach($extensions['data'] as $ext)
        {
            if(!empty($ext['context']))
            {
				$_AGI_text = 'callerid.php';
                
            }
        }
        $app->updateFile($lineData, $ext_conf);
        
        $insertLogs = array(
			'id' => (array)$ids,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => $authorAdmin['email'],
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $authorAdmin['id'],
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$dataReturn = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $dataReturn );
    }
    
	if($action == 'deleteContext')
	{
        $id = $_POST['_id'];
        $idFind = array('_id' => $id);
        $checkExtension = $mgdb->select($context_colection, $idFind);
        if(!empty($checkExtension['data']['_id']))
        {
            $deleteStatus = $mgdb->delete($context_colection, $idFind);
            $status = $deleteStatus['status'];
            if($status == true)
            {
				$msg = sprintf($app->_lang('msg_018'), $checkExtension['name']);
                
                $extensions = $mgdb->selects($context_colection, array('status' => 'Enable'));

                $lineData = '';
                foreach($extensions['data'] as $ext)
                {
                    if(!empty($ext['context']))
                    {
						$_AGI_text = 'callerid.php';
                    }
                }
                $app->updateFile($lineData, $ext_conf);
            } 
            else 
            {
                $status = false;
				$msg = sprintf($app->_lang('msg_015'), $deleteStatus['data']['msg']);
            }
        } 
        else 
        {
            $status = false;
            $msg = $app->_lang('msg_013');
        }
		$insertLogs = array(
			'id' => (array)$id,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => $authorAdmin['email'],
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $authorAdmin['id'],
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$dataReturn = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $dataReturn );
	}
		
	if($action == 'updateContext')
    {
        $id = $_POST['_id'];
        
        $readFile 		= $app->formDataName($data, 'read');
        $configure 		= $app->formDataArrayName($data, 'configure');
        $option_times 	= $app->formDataArrayNames($data, ['time_option', 'tstart_option', 'tend_option', 'sip_time_option']);
        $option_keypad 	= $app->formDataArrayNames($data, ['key_option', 'sip_option']);

        $option_time = array();
        foreach($option_times as $toption)
        {
            $thisDate 	= (int)strtotime(date('Y-m-d')." 00:00:00");
            $timeStart 	= (int)strtotime(date('Y-m-d')." ".$toption['tstart_option']);
            $timeEnd 	= (int)strtotime(date('Y-m-d')." ".$toption['tend_option']);

            $option_time[] = array(
                'date' 	=> $toption['time_option'],
                'start' => (int)($timeStart - $thisDate),
                'end' 	=> (int)($timeEnd - $thisDate),
                'sip' 	=> $toption['sip_time_option']
            );
        }

        $updateData = array(
            'config' 		=> $configure,
            'name' 			=> $app->formDataName($data, 'name'),
            'context' 		=> $app->formDataName($data, 'context'),
            'sip_trunk' 	=> $app->formDataName($data, 'siptrunk'),
            'queue' 		=> $app->formDataName($data, 'queue'),
            'read' 			=> $readFile,
            'recording' 	=> $app->formDataName($data, 'recording'),
            'timezone' 		=> $app->formDataName($data, 'timezone'),
            'option_time' 	=> $option_time,
            'option_keypad' => $option_keypad,
            'ext_code'		=> $app->formDataName($data, 'ext_code'),
            'status' 		=> $app->formDataName($data, 'status')
        );
        $updateSetData = array(
            '$set' => array(
                'config' 		=> $configure,
                'name' 			=> $updateData['name'],
                'context' 		=> $updateData['context'],
                'sip_trunk' 	=> $updateData['sip_trunk'],
                'queue' 		=> $updateData['queue'],
                'read' 			=> $readFile,
                'recording' 	=> $updateData['recording'],
                'timezone' 		=> $updateData['timezone'],
                'option_time' 	=> $option_time,
                'option_keypad' => $option_keypad,
                'ext_code'		=> $updateData['ext_code'],
                'status' 		=> $updateData['status']
            )
        );
        $option = array();
        $checkExtension = $mgdb->select($context_colection, array('_id' => $id));
        if(!empty($checkExtension['data']['_id']))
        {
            $statusUpdate = $mgdb->update($context_colection, array('_id' => $id), $updateSetData, $option);
            $status = $statusUpdate['status'];
            if($status == true)
            { 
                $extensions = $mgdb->selects($context_colection, array('status' => 'Enable'));

                $lineData = '';
                foreach($extensions['data'] as $ext)
                {
                    if(!empty($ext['context']))
                    {
						$_AGI_text = 'callerid.php';
                    }
                }
                $app->updateFile($lineData, $ext_conf);
                $msg = sprintf($app->_lang('msg_019'), $updateData['name']);
            } 
            else 
            { 
                $msg = sprintf($app->_lang('msg_015'), $statusUpdate['data']['msg']);
            }
        } 
        else 
        { 
            $status = $checkStatus;
            $msg = sprintf($app->_lang('msg_020'), $updateData['name']);
        }
        
		$insertLogs = array(
			'id' => (array)$id,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => $authorAdmin['email'],
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $authorAdmin['id'],
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$dataReturn = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $dataReturn );
	}
		
	if($action == 'insertContext')
	{ 
        $id = $_id;
        $readFile 		= $app->formDataName($data, 'read');
        $configure 		= $app->formDataArrayName($data, 'configure');
        $option_times 	= $app->formDataArrayNames($data, ['time_option', 'tstart_option', 'tend_option', 'sip_time_option']);
        $option_keypad 	= $app->formDataArrayNames($data, ['key_option', 'sip_option']);

        $option_time = array();
        foreach($option_times as $toption){

            $thisDate 	= (int)strtotime(date('Y-m-d')." 00:00:00");
            $timeStart 	= (int)strtotime(date('Y-m-d')." ".$toption['tstart_option']);
            $timeEnd 	= (int)strtotime(date('Y-m-d')." ".$toption['tend_option']);

            $option_time[] = array(
                'date' 	=> $toption['time_option'],
                'start' => (int)($timeStart - $thisDate),
                'end' 	=> (int)($timeEnd - $thisDate),
                'sip' 	=> $toption['sip_time_option']
            );

        }

        $dataInsert = array(
            '_id' 			=> $id,
            'config' 		=> $configure,
            'name' 			=> $app->formDataName($data, 'name'),
            'context' 		=> $app->formDataName($data, 'context'),
            'sip_trunk' 	=> $app->formDataName($data, 'siptrunk'),
            'queue' 		=> $app->formDataName($data, 'queue'),
            'read' 			=> $readFile,
            'recording' 	=> $app->formDataName($data, 'recording'),
            'timezone' 		=> $app->formDataName($data, 'timezone'),
            'option_time' 	=> $option_time,
            'option_keypad' => $option_keypad,
            'ext_code'		=> $app->formDataName($data, 'ext_code'),
            'tcreate' 		=> (int)time(),
            'status' 		=> $app->formDataName($data, 'status')
        );
        $checkExtension = $mgdb->select($context_colection, array('context' => $dataInsert['context']));
        if(!empty($checkExtension['data']['_id']))
        { 
            $status = false;
			$msg = sprintf($app->_lang('msg_020'), $dataInsert['context']);
        } 
        else 
        { 
            $statusInsert = $mgdb->insert($context_colection, $dataInsert);
            $status = $statusInsert['status'];
            if($status == true)
            { 
                $extensions = $mgdb->selects($context_colection, array('status' => 'Enable'));
                $lineData = '';
                foreach($extensions['data'] as $ext)
                {
                    if(!empty($ext['context']))
                    {
						$_AGI_text = 'callerid.php';
                    }
                }
                $app->updateFile($lineData, $ext_conf);

                $msg = sprintf($app->_lang('msg_021'), $dataInsert['context']);
            } 
            else 
            {
				$msg = sprintf($app->_lang('msg_015'), $statusInsert['data']['msg']);
            }
        }

		$insertLogs = array(
			'id' => (array)$id,
			'tcreate' => time(),
			'account' => '',
			'action' => $action,
			'email' => $authorAdmin['email'],
			'ip' => $ip,
			'uag' => $uag,
			'detail' => $msg,
			'uid' => $authorAdmin['id'],
			'module' => '',
			'route' => '',
			'status' => $status
		);
		$mgdb->insert('logs', $insertLogs);

		$dataReturn = array(
			'status' => $status,
			'action' => $action,
			'msg' => $msg,
            'data' => array(),
			'lang' => $langCode,
			'time' => time()
		);
		$app->returnDataJson( $dataReturn );
	}
}
?>