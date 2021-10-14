<?php
if(isset($action))
{
	$_id = (string)new \MongoDB\BSON\ObjectID;
    $trunk_conf = '/etc/asterisk/sip_trunk_api.conf';
    $trunk_colection = 'call_sip_trunk';
    
    if($action == 'getListSipTrunk')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $count = $mgdb->count($trunk_colection, $query)['data']['n'];
        $dataReturn = $mgdb->selects($trunk_colection, $query, $option);
        $dataReturn['count'] = (int)$count;
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getSipTrunk')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($trunk_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'deleteMultiSipTrunk')
	{
        $ids = json_decode($_POST['_id'], true);
        foreach($ids as $id)
        {
            $idFind = array('_id' => $id);
            $checkTrunk = $mgdb->select($trunk_colection, $idFind);
            if(!empty($checkTrunk['data']['_id']))
            {
                $deleteStatus = $mgdb->delete($trunk_colection, $idFind);
                $status = $deleteStatus['status'];
                if($status == true)
                {
                    $msg = 'Xóa SIP Trunk <strong>'.$checkTrunk['data']['callerid'].'</strong> thành công!';
                } 
                else 
                {
                    $status = false;
                    $msg = 'Máy chủ đang bận vui lòng thử lại sau! '.$deleteStatus['data']['msg'];
                }
            } 
            else 
            {
                $status = false;
                $msg = 'Không tồn tại dữ liệu trên hệ thống!';
            }
        }
        
        $trunks = $mgdb->selects($trunk_colection, array('status' => 'Enable'));
        $trunkData = '';
        foreach($trunks['data'] as $sip)
        {
            if(!empty($sip['callerid']))
            {
                $trunkData .= "[".$sip['callerid']."]\n";
                $trunkData .= "canreinvite=no\n";
                $trunkData .= "host=".$sip['host']."\n";
                $trunkData .= "type=peer\n";
                $trunkData .= "callerid=".$sip['callerid']."\n";
                $trunkData .= "dtmfmode=rfc2833\n";
                $trunkData .= "qualify=yes\n";
                $trunkData .= "nat=yes\n";
                $trunkData .= "context=".$sip['context']."\n";
                $trunkData .= "disallow=all\n";
                $trunkData .= "allow=ulaw,alaw,g711\n\n";
            }
        }
        $app->updateFile($trunkData, $trunk_conf);
        
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
    
	if($action == 'deleteSipTrunk')
	{
        $id = $_POST['_id'];
        $idFind = array('_id' => $id);
        $checkTrunk = $mgdb->select($trunk_colection, $idFind);
        if(!empty($checkTrunk['data']['_id']))
        {
            $deleteStatus = $mgdb->delete($trunk_colection, $idFind);
            $status = $deleteStatus['status'];
            if($status == true)
            {
                $msg = 'Xóa SIP Trunk <strong>'.$checkTrunk['data']['callerid'].'</strong> thành công!';
                $trunks = $mgdb->selects($trunk_colection, array('status' => 'Enable'));

                $trunkData = '';
                foreach($trunks['data'] as $sip)
                {
                    if(!empty($sip['callerid']))
                    {
                        $trunkData .= "[".$sip['callerid']."]\n";
                        $trunkData .= "canreinvite=no\n";
                        $trunkData .= "host=".$sip['host']."\n";
                        $trunkData .= "type=peer\n";
                        $trunkData .= "callerid=".$sip['callerid']."\n";
                        $trunkData .= "dtmfmode=rfc2833\n";
                        $trunkData .= "qualify=yes\n";
                        $trunkData .= "nat=yes\n";
                        $trunkData .= "context=".$sip['context']."\n";
                        $trunkData .= "disallow=all\n";
                        $trunkData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($trunkData, $trunk_conf);
            } 
            else 
            {
                $status = false;
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
		
	if($action == 'updateSipTrunk')
	{
        $id = $_POST['_id'];
        $dataUpdate = array(
            'name' 		=> $app->formDataName($data, 'name'),
            'context' 	=> $app->formDataName($data, 'context'),
            'callerid' 	=> $app->formDataName($data, 'callerid'),
            'host'		=> $app->formDataName($data, 'host'),
            'status' 	=> $app->formDataName($data, 'status')
        );
        $dataSetUpdate = array(
            '$set' => array(
                'name' 		=> $dataUpdate['name'],
                'context' 	=> $dataUpdate['context'],
                'callerid' 	=> $dataUpdate['callerid'],
                'host'		=> $dataUpdate['host'],
                'status' 	=> $dataUpdate['status']
            )
        );
        $option = array();
        $checkTrunk = $mgdb->select($trunk_colection, array('callerid' => $dataUpdate['callerid']));
        if(!empty($checkTrunk['data']['_id']) && $checkTrunk['data']['_id'] == $id)
        {
            $statusUpdate = $mgdb->update($trunk_colection, array('_id' => $id), $dataSetUpdate, $option);
            $status = $statusUpdate['status'];
            if($status == true)
            { 
                $msg = 'Cập nhật SIP Trunk <strong>'.$dataUpdate['callerid'].'</strong> thành công!';
                $trunks = $mgdb->selects($trunk_colection, array('status' => 'Enable'));
                $trunkData = '';
                foreach($trunks['data'] as $sip)
                {
                    if(!empty($sip['callerid']))
                    {
                        $trunkData .= "[".$sip['callerid']."]\n";
                        $trunkData .= "canreinvite=no\n";
                        $trunkData .= "host=".$sip['host']."\n";
                        $trunkData .= "type=peer\n";
                        $trunkData .= "callerid=".$sip['callerid']."\n";
                        $trunkData .= "dtmfmode=rfc2833\n";
                        $trunkData .= "qualify=yes\n";
                        $trunkData .= "nat=yes\n";
                        $trunkData .= "context=".$sip['context']."\n";
                        $trunkData .= "disallow=all\n";
                        $trunkData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($trunkData, $trunk_conf);
            } 
            else 
            { 
                $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$statusUpdate['data']['msg'];
            }
        } 
        else 
        { 
            $status = false;
            $msg = 'SIP trunk <strong>'.$updateData['name'].'</strong> chưa tồn tại trên hệ thống!';
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
		
	if($action == 'insertSipTrunk')
	{
        $id = $_id;
        $dataInsert = array(
            '_id' => $id,
            'name' => $app->formDataName($data, 'name'),
            'context' => $app->formDataName($data, 'context'),
            'secret' => '',
            'callerid' => $app->formDataName($data, 'callerid'),
            'type' => 'peer',
            'host' => $app->formDataName($data, 'host'),
            'tcreate' => (int)time(),
            'status' => $app->formDataName($data, 'status')
        );
        $checkTrunk = $mgdb->select($trunk_colection, array('callerid' => $dataInsert['callerid']));
        if(!empty($checkTrunk['data']['_id']))
        { 
            $status = false;
            $msg = 'SIP trunk <strong>'.$dataInsert['callerid'].'</strong> đã tồn tại trên hệ thống!';
        } 
        else 
        { 
            $statusInsert = $mgdb->insert($trunk_colection, $dataInsert);
            $status = $statusInsert['status'];
            if($statusInsert['status'] == true)
            { 
                $msg = 'Thêm SIP trunk <strong>'.$dataInsert['callerid'].'</strong> thành công!';
                $trunks = $mgdb->selects($trunk_colection, array('status' => 'Enable'));
                $trunkData = '';
                foreach($trunks['data'] as $sip)
                {
                    if(!empty($sip['callerid']))
                    {
                        $trunkData .= "[".$sip['callerid']."]\n";
                        $trunkData .= "canreinvite=no\n";
                        $trunkData .= "host=".$sip['host']."\n";
                        $trunkData .= "type=peer\n";
                        $trunkData .= "callerid=".$sip['callerid']."\n";
                        $trunkData .= "dtmfmode=rfc2833\n";
                        $trunkData .= "qualify=yes\n";
                        $trunkData .= "nat=yes\n";
                        $trunkData .= "context=".$sip['context']."\n";
                        $trunkData .= "disallow=all\n";
                        $trunkData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($trunkData, $trunk_conf);
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
	
    $sip_account_conf = '/etc/asterisk/sip_account_api.conf';
    $sip_account_colection = 'call_sip_account';
    
    if($action == 'getListSipAccount')
	{
        $type = $data['type'];
        $query = $data['query'];
        $option = $data['option'];
        $count = $mgdb->count($sip_account_colection, $query)['data']['n'];
        $dataReturn = $mgdb->selects($sip_account_colection, $query, $option);
        $dataReturn['count'] = (int)$count;
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'getSipAccount')
	{
        $type = $data['type'];
        $query = $data['query'];
        $dataReturn = $mgdb->select($sip_account_colection, $query);
        $app->returnDataJson( $dataReturn );
    }
    
    if($action == 'deleteMultiSipAccount')
	{
        $ids = json_decode($_POST['_id'], true);
        foreach($ids as $id)
        {
            $idFind = ['_id' => $id];
            $checkSip = $mgdb->select($sip_account_colection, $idFind);
            if(!empty($checkSip['data']['_id']))
            {
                $deleteStatus = $mgdb->delete($sip_account_colection, $idFind);
                $status = $deleteStatus['status'];
                if($status == true)
                {
                    $msg = 'Xóa tài khoản SIP <strong>'.$checkSip['id'].'</strong> thành công!';
                } 
                else 
                {
                    $status = false;
                    $msg 	= 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$deleteStatus['data']['msg'];
                }
            } 
            else 
            {
                $status = false;
                $msg = 'Không tồn tại dữ liệu trên hệ thống!';
            }
        }
        $sips = $mgdb->selects($sip_account_colection, array('status' => 'Enable'));

        $lineData = '';
        foreach($sips['data'] as $sip)
        {
            if(!empty($sip['id']))
            {
                $lineData .= "[".$sip['id']."]\n";
                $lineData .= "host=dynamic\n";
                $lineData .= "type=peer\n";
                $lineData .= "secret=".$sip['secret']."\n";
                $lineData .= "username=".$sip['id']."\n";
                $lineData .= "dtmfmode=rfc2833\n";
                $lineData .= "canreinvite=no\n";
                $lineData .= "context=".$sip['context']."\n";
                $lineData .= "nat=force_rport,comedia\n";
                $lineData .= "transport=".implode(',', $sip['transport'])."\n";
                $lineData .= "dial=SIP/".$sip['id']."\n";
                $lineData .= "mailbox=".$sip['id']."@device\n";
                $lineData .= "callerid=".$sip['name']." <".$sip['id'].">\n";
                $lineData .= "videosupport=".$sip['videosupport']."\n";
                $lineData .= "disallow=all\n";
                $lineData .= "allow=ulaw,alaw,g711\n\n";
            }
        }
        $app->updateFile($lineData, $sip_account_conf);
        
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
    
	if($action == 'deleteSipAccount')
	{
        $id = $_POST['_id'];
        $idFind = array('_id' => $id);
        $checkSip = $mgdb->select($sip_account_colection, $idFind);
        if(!empty($checkSip['data']['_id']))
        {
            $deleteStatus = $mgdb->delete($sip_account_colection, $idFind);
            $status = $deleteStatus['status'];
            if($status == true)
            {
                $msg = 'Xóa tài khoản SIP <strong>'.$checkSip['id'].'</strong> thành công!';
                $sips = $mgdb->selects($sip_account_colection, array('status' => 'Enable'));

                $lineData = '';
                foreach($sips['data'] as $sip)
                {
                    if(!empty($sip['id']))
                    {
                        $lineData .= "[".$sip['id']."]\n";
                        $lineData .= "host=dynamic\n";
                        $lineData .= "type=peer\n";
                        $lineData .= "secret=".$sip['secret']."\n";
                        $lineData .= "username=".$sip['id']."\n";
                        $lineData .= "dtmfmode=rfc2833\n";
                        $lineData .= "canreinvite=no\n";
                        $lineData .= "context=".$sip['context']."\n";
                        $lineData .= "nat=force_rport,comedia\n";
                        $lineData .= "transport=".implode(',', $sip['transport'])."\n";
                        $lineData .= "dial=SIP/".$sip['id']."\n";
                        $lineData .= "mailbox=".$sip['id']."@device\n";
                        $lineData .= "callerid=".$sip['name']." <".$sip['id'].">\n";
                        $lineData .= "videosupport=".$sip['videosupport']."\n";
                        $lineData .= "disallow=all\n";
                        $lineData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($lineData, $sip_account_conf);
            } 
            else 
            {
                $status = false;
                $msg 	= 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$deleteStatus['data']['msg'];
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
		
	if($action == 'updateSipAccount')
	{
        $id = $_POST['_id'];
        $sip_id 		= $app->formDataName($data, 'sip_id');
        $sipName		= $app->formDataName($data, 'name');
        $sipUsername	= $app->formDataName($data, 'username');
        $sipSecret 		= $app->formDataName($data, 'secret');
        $sipTransport 	= $app->formDataArrayName($data, 'transport');
        $sipContext		= $app->formDataName($data, 'context');
        $sipDevice		= $app->formDataName($data, 'device');
        $videosupport	= $app->formDataName($data, 'videosupport');
        $sipStatus		= $app->formDataName($data, 'status');

        $dataUpdate = array(
            '$set' => array(
                'name' 			=> trim($sipName), 
                'username'		=> $sipUsername,
                'sip'			=> 'SIP/'.$sipUsername, 
                'secret' 		=> $sipSecret, 
                'transport' 	=> $sipTransport, 
                'context' 		=> $sipContext, 
                'device' 		=> $sipDevice, 
                'videosupport' 	=> $videosupport, 
                'mailbox'		=> $sipUsername.'@device',
                'tupdate'		=> (int)time(), 
                'status' 		=> $sipStatus
            )
        );
        $option = array();
        $checkSip = $mgdb->select($sip_account_colection, array('_id' => $id));
        if(!empty($checkSip['data']['_id']) && $checkSip['data']['id'] == $sipUsername)
        {
            $statusUpdate = $mgdb->update($sip_account_colection, array('_id' => $id), $dataUpdate, $option);
            $status = $statusUpdate['status'];
            if($status == true)
            { 
                $msg = 'Cập nhật tài khoản SIP <strong>'.$sipUsername.'</strong> thành công!';

                $sips = $mgdb->selects($sip_account_colection, array('status' => 'Enable'));
                $lineData = '';
                foreach($sips['data'] as $sip)
                {
                    if(!empty($sip['id']))
                    {
                        $lineData .= "[".$sip['id']."]\n";
                        $lineData .= "host=dynamic\n";
                        $lineData .= "type=peer\n";
                        $lineData .= "secret=".$sip['secret']."\n";
                        $lineData .= "username=".$sip['id']."\n";
                        $lineData .= "dtmfmode=rfc2833\n";
                        $lineData .= "canreinvite=no\n";
                        $lineData .= "context=".$sip['context']."\n";
                        $lineData .= "nat=force_rport,comedia\n";
                        $lineData .= "transport=".implode(',', $sip['transport'])."\n";
                        $lineData .= "dial=SIP/".$sip['id']."\n";
                        $lineData .= "mailbox=".$sip['id']."@device\n";
                        $lineData .= "callerid=".$sip['name']." <".$sip['id'].">\n";
                        $lineData .= "videosupport=".$sip['videosupport']."\n";
                        $lineData .= "disallow=all\n";
                        $lineData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($lineData, $sip_account_conf);
            } else { 
                $msg = 'Máy chủ đang bận vui lòng thử lại sau!<br>'.$statusUpdate['data']['msg'];
            }
        } else { 
            $status = $checkStatus;
            $msg = 'Tài khoản SIP <strong>'.$sipUsername.'</strong> đã tồn tại trên hệ thống!';
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
		
	if($action == 'insertSipAccount')
	{
        $sipName		= $app->formDataName($data, 'name');
        $sipUsername	= $app->formDataName($data, 'username');
        $sipSecret 		= $app->formDataName($data, 'secret');
        $sipTransport 	= $app->formDataArrayName($data, 'transport');
        $sipContext		= $app->formDataName($data, 'context');
        $sipDevice		= $app->formDataName($data, 'device');
        $videosupport	= $app->formDataName($data, 'videosupport');
        $sipStatus		= $app->formDataName($data, 'status');

        $dataInsert = array(
            '_id' 			=> $_id, 
            'id' 			=> (string)$sipUsername, 
            'name' 			=> trim($sipName), 
            "type" 			=> 'peer', 
            'host' 			=> 'dynamic', 
            'sip'			=> 'SIP/'.$sipUsername, 
            'secret' 		=> $sipSecret, 
            'transport' 	=> $sipTransport, 
            'context' 		=> $sipContext, 
            'dtmfmode'		=> 'rfc2833',
            'canreinvite'	=> 'no',
            'disallow'		=> 'all',
            'nallow'		=> 'ulaw', 
            'insecure'		=> 'port,invite',
            'nat'			=> 'force_rport,comedia', 
            'device' 		=> $sipDevice, 
            'videosupport' 	=> $videosupport, 
            'mailbox'		=> $sipUsername.'@device', 
            'tcreate'		=> (int)time(), 
            'tupdate'		=> (int)0, 
            'status' 		=> $sipStatus
        );
        $checkSipAccount = $mgdb->select($sip_account_colection, array('id' => $dataInsert['id']));
        if(!empty($checkSipAccount['data']['id']))
        { 
            $status = false;
            $msg = 'Tài khoản SIP <strong>'.$dataInsert['id'].'</strong> đã tồn tại trên hệ thống!';
        } 
        else 
        { 
            $statusInsert = $mgdb->insert($sip_account_colection, $dataInsert);
            $status = $statusInsert['status'];
            if($status == true)
            { 
                $msg = 'Tạo tài khoản SIP <strong>'.$dataInsert['id'].'</strong> thành công!';
                $sips = $mgdb->selects($sip_account_colection, array('status' => 'Enable'));
                $lineData = '';
                foreach($sips['data'] as $sip)
                {
                    if(!empty($sip['id']))
                    {
                        $lineData .= "[".$sip['id']."]\n";
                        $lineData .= "host=dynamic\n";
                        $lineData .= "type=peer\n";
                        $lineData .= "secret=".$sip['secret']."\n";
                        $lineData .= "username=".$sip['id']."\n";
                        $lineData .= "dtmfmode=rfc2833\n";
                        $lineData .= "canreinvite=no\n";
                        $lineData .= "context=".$sip['context']."\n";
                        $lineData .= "nat=force_rport,comedia\n";
                        $lineData .= "transport=".implode(',', $sip['transport'])."\n";
                        $lineData .= "dial=SIP/".$sip['id']."\n";
                        $lineData .= "mailbox=".$sip['id']."@device\n";
                        $lineData .= "callerid=".$sip['name']." <".$sip['id'].">\n";
                        $lineData .= "videosupport=".$sip['videosupport']."\n";
                        $lineData .= "disallow=all\n";
                        $lineData .= "allow=ulaw,alaw,g711\n\n";
                    }
                }
                $app->updateFile($lineData, $sip_account_conf);
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