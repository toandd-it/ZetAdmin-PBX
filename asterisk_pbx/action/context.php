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
				$_AGI_text = '/usr/local/lsws/lsphp73/bin/php,/var/lib/asterisk/agi-bin/callerid.php';
                if(in_array('option', $ext['config']))
                {
                    $lineData .= "[".$ext['context']."]\n";
                } 
                else 
                {
                    $ifInternal = '"${type}"="internal"';
                    $ifOutbound = '"${type}"="outbound"';
                    $ifInbound 	= '"${type}"="inbound"';
                    $textType 	= '${type}';
                    $textTo 	= '${to}';
                    $textLogid 	= '${logid}';

                    $textLookup_phone 	= '${lookup-phone}';
                    $textLog_id 		= '${log-id}';
                    $textCall_to 		= '${call-to}';
                    $textPhone 			= '${phone}';

                    $textExt 			= '${EXTEN}';
                    $textHead_from		= '${SIP_HEADER(From)}';
                    $textHead_cut_from	= '${CUT(CUT(SIP_HEADER(From),>,1),:,2)}';
                    $textSip_head_via 	= '${SIP_HEADER(Via)}';
                    $textExt_name 		= '${extName}';

                    $queueData = array();
                    if(!empty($ext['queue']))
                    { 
                        $queueData = $mgdb->select('call_queue', array('_id' => $ext['queue'], 'status' => 'Enable'))['data'];
                    }

                    if(empty($ext['sip_trunk']))
                    {
                        $textSet_CallerId = 'NoOp()';
                        $trunkData = array();
                    } 
                    else 
                    {
                        $textSet_CallerId = 'Set(CALLERID(num)='.$ext['sip_trunk'].')';
                        $trunkData = $mgdb->select('call_sip_trunk', array('callerid' => $ext['sip_trunk'], 'status' => 'Enable'))['data'];
                    }

                    if($ext['recording'] == 'yes')
                    {
                        $textRecording = '';
                    } 
                    else 
                    {
                        $textRecording = ';';
                    }

                    if(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,".$textSet_CallerId."\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ;same => n,Read(keypad)\n";
						$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/100)\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ;same => n,Read(keypad)\n";
						$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/100)\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ;same => n,Read(keypad)\n";
						$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/100)\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,".$textSet_CallerId."\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    ;same => n,Read(keypad)\n";
						$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/100)\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(!in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,".$textSet_CallerId."\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
                    {
                        $lineData .= "[".$ext['context']."]\n";
						$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
						$lineData .= "    same => n,Set(extName=outbound-call)\n";
						$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
						$lineData .= "    same => n,Set(type-call=".$textType.")\n";
						$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
						$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
						$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
						$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
						$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallOutbound,1,NoOp()\n";
						$lineData .= "    same => n,".$textSet_CallerId."\n";
						$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInbound,1,NoOp()\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => CallInternal,1,NoOp()\n";
						$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
						$lineData .= "    same => n,Monitor(wav,".$textLog_id.",m)\n";
						$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
						$lineData .= "    same => n,Goto(disconnect,1)\n\n";
						$lineData .= "exten => disconnect,1,NoOp()\n";
						$lineData .= "    same => n,Hangup()\n\n"; 
                    } 
                    else 
                    {
                        $lineData .= "[".$ext['context']."]\n\n";
                    }
                }
            }
        }
        $app->updateFile($lineData, $ext_conf);
        
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
						$_AGI_text = '/usr/local/lsws/lsphp73/bin/php,/var/lib/asterisk/agi-bin/callerid.php';
                        if(in_array('option', $ext['config']))
                        {
                            $lineData .= "[".$ext['context']."]\n";
                        } 
                        else 
                        {
                            $ifInternal = '"${type}"="internal"';
                            $ifOutbound = '"${type}"="outbound"';
                            $ifInbound 	= '"${type}"="inbound"';
                            $textType 	= '${type}';
                            $textTo 	= '${to}';
                            $textLogid 	= '${logid}';

                            $textLookup_phone 	= '${lookup-phone}';
                            $textLog_id 		= '${log-id}';
                            $textCall_to 		= '${call-to}';
                            $textPhone 			= '${phone}';

                            $textExt 			= '${EXTEN}';
                            $textHead_from		= '${SIP_HEADER(From)}';
                            $textHead_cut_from	= '${CUT(CUT(SIP_HEADER(From),>,1),:,2)}';
                            $textSip_head_via 	= '${SIP_HEADER(Via)}';
                            $textExt_name 		= '${extName}';

                            $queueData = array();
                            if(!empty($ext['queue']))
                            { 
                                $queueData = $mgdb->select('call_queue', array('_id' => $ext['queue'], 'status' => 'Enable'))['data'];
                            }

                            if(empty($ext['sip_trunk']))
                            {
                                $textSet_CallerId = 'NoOp()';
                                $trunkData = array();
                            } 
                            else 
                            {
                                $textSet_CallerId = 'Set(CALLERID(num)='.$ext['sip_trunk'].')';
                                $trunkData = $mgdb->select('call_sip_trunk', array('callerid' => $ext['sip_trunk'], 'status' => 'Enable'))['data'];
                            }
                            
                            if($ext['recording'] == 'yes')
                            {
                                $textRecording = '';
                            } 
                            else 
                            {
                                $textRecording = ';';
                            }

                            if(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['_id'].",tT,,,".$queueData['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							else 
							{
								$lineData .= "[".$ext['context']."]\n\n";
							}
                        }
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
						$_AGI_text = '/usr/local/lsws/lsphp73/bin/php,/var/lib/asterisk/agi-bin/callerid.php';
						
                        if(in_array('option', $ext['config']))
                        {
                            $lineData .= "[".$ext['context']."]\n";
							if(!empty($ext['ext_code']))
							{
								$lineData .= $ext['ext_code']."\n\n";
							}
                        } 
                        else 
                        {
                            $ifInternal = '"${type}"="internal"';
                            $ifOutbound = '"${type}"="outbound"';
                            $ifInbound 	= '"${type}"="inbound"';
                            $textType 	= '${type}';
                            $textTo 	= '${to}';
                            $textLogid 	= '${logid}';

                            $textLookup_phone 	= '${lookup-phone}';
                            $textLog_id 		= '${log-id}';
                            $textCall_to 		= '${call-to}';
                            $textPhone 			= '${phone}';

                            $textExt 			= '${EXTEN}';
                            $textHead_from		= '${SIP_HEADER(From)}';
                            $textHead_cut_from	= '${CUT(CUT(SIP_HEADER(From),>,1),:,2)}';
                            $textSip_head_via 	= '${SIP_HEADER(Via)}';
                            $textExt_name 		= '${extName}';

                            $queueData = array();
                            if(!empty($ext['queue']))
                            { 
                                $queueData = $mgdb->select('call_queue', array('_id' => $ext['queue'], 'status' => 'Enable'))['data'];
                            }

                            if(empty($ext['sip_trunk']))
                            {
                                $textSet_CallerId = 'NoOp()';
                                $trunkData = array();
                            } 
                            else 
                            {
                                $textSet_CallerId = 'Set(CALLERID(num)='.$ext['sip_trunk'].')';
                                $trunkData = $mgdb->select('call_sip_trunk', array('callerid' => $ext['sip_trunk'], 'status' => 'Enable'))['data'];
                            }
                            
                            if($ext['recording'] == 'yes')
                            {
                                $textRecording = '';
                            } 
                            else 
                            {
                                $textRecording = ';';
                            }

                            if(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							else 
							{
								$lineData .= "[".$ext['context']."]\n\n";
							}
                        }
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
						$_AGI_text = '/usr/local/lsws/lsphp73/bin/php,/var/lib/asterisk/agi-bin/callerid.php';
						
                        if(in_array('option', $ext['config']))
                        {
                            $lineData .= "[".$ext['context']."]\n";
							if(!empty($ext['ext_code']))
							{
								$lineData .= $ext['ext_code']."\n\n";
							}
                        } 
                        else 
                        {
                            $ifInternal = '"${type}"="internal"';
                            $ifOutbound = '"${type}"="outbound"';
                            $ifInbound 	= '"${type}"="inbound"';
                            $textType 	= '${type}';
                            $textTo 	= '${to}';
                            $textLogid 	= '${logid}';

                            $textLookup_phone 	= '${lookup-phone}';
                            $textLog_id 		= '${log-id}';
                            $textCall_to 		= '${call-to}';
                            $textPhone 			= '${phone}';

                            $textExt 			= '${EXTEN}';
                            $textHead_from		= '${SIP_HEADER(From)}';
                            $textHead_cut_from	= '${CUT(CUT(SIP_HEADER(From),>,1),:,2)}';
                            $textSip_head_via 	= '${SIP_HEADER(Via)}';
                            $textExt_name 		= '${extName}';

                            $queueData = array();
                            if(!empty($ext['queue']))
                            { 
                                $queueData = $mgdb->select('call_queue', array('_id' => $ext['queue'], 'status' => 'Enable'))['data'];
                            }

                            if(empty($ext['sip_trunk']))
                            {
                                $textSet_CallerId = 'NoOp()';
                                $trunkData = array();
                            } 
                            else 
                            {
                                $textSet_CallerId = 'Set(CALLERID(num)='.$ext['sip_trunk'].')';
                                $trunkData = $mgdb->select('call_sip_trunk', array('callerid' => $ext['sip_trunk'], 'status' => 'Enable'))['data'];
                            }
                            
                            if($ext['recording'] == 'yes')
                            {
                                $textRecording = '';
                            } 
                            else 
                            {
                                $textRecording = ';';
                            }

                            if(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && !in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    ;same => n,Read(keypad)\n";
								$lineData .= "    ;same => n,Queue(".$queueData['data']['_id'].",tT,,,".$queueData['data']['timeout'].")\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/100)\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(!in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							elseif(in_array('internal', $ext['config']) && !in_array('callin', $ext['config']) && in_array('callout', $ext['config']))
							{
								$lineData .= "[".$ext['context']."]\n";
								$lineData .= "exten => _X.,1,".$textSet_CallerId."\n";
								$lineData .= "    same => n,Set(extName=outbound-call)\n";
								$lineData .= "    same => n,AGI($_AGI_text, ".$textExt.", ".$textHead_from.", ".$textHead_cut_from.", ".$textSip_head_via.", ".$textExt_name.")\n";
								$lineData .= "    same => n,Set(type-call=".$textType.")\n";
								$lineData .= "    same => n,Set(call-to=".$textTo.")\n";
								$lineData .= "    same => n,Set(log-id=".$textLogid.")\n";
								$lineData .= "    same => n,Set(phone=".$textLookup_phone.")\n";
								$lineData .= "    same => n,GotoIf($[".$ifInternal."]?CallInternal,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifOutbound."]?CallOutbound,1) else\n";
								$lineData .= "    same => n,GotoIf($[".$ifInbound."]?CallInbound,1) else\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallOutbound,1,NoOp()\n";
								$lineData .= "    same => n,".$textSet_CallerId."\n";
								$lineData .= "    ".$textRecording."same => n,Monitor(wav,".$textLog_id."},m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to."@".$trunkData['host'].")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInbound,1,NoOp()\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => CallInternal,1,NoOp()\n";
								$lineData .= "    same => n,Set(CALLERID(num)=".$textPhone.")\n";
								$lineData .= "    same => n,Monitor(wav,".$textLog_id.",m)\n";
								$lineData .= "    same => n,Dial(SIP/".$textCall_to.")\n";
								$lineData .= "    same => n,Goto(disconnect,1)\n\n";
								$lineData .= "exten => disconnect,1,NoOp()\n";
								$lineData .= "    same => n,Hangup()\n\n"; 
							} 
							else 
							{
								$lineData .= "[".$ext['context']."]\n\n";
							}
                        }
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