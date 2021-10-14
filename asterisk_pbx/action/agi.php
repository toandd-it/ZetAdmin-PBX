<?php
if(isset($action) && isset($mgdb))
{
	if($action == 'QueueShow')
	{
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$queue = $_POST['queue'];
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect())
			{
				$result = $asm->Command("queue show $queue");
				$status = true;
				$msg	= "queue show $queue";
				if(!strpos($result['data'], ':'))
				{
					$data = $result['data'];
				} 
				else 
				{ 
					$sipData 		= array();
					$talktimeData 	= array();
					$waitData 		= array();
					$inuseData 		= array();
					$incallData 	= array();
					$busyData 		= array();
					$onhold 		= array();
					$notinuseData 	= array();
					$pauseData 		= array();
					
					foreach(explode("\n", $result['data']) as $line)
					{
						$pieces = array();
						$pieces2 = array();
						$pieces3 = array();
						if (preg_match("/talktime/i", $line) && !preg_match("/default/i", $line)) 
						{
							$pieces = explode(" ", $line);
							$pieces2 = preg_split('/ +/', $line);
							$qcalls = $qcalls + $pieces[2];
							$talktimeData[] = array(
								'name' => $pieces[0],
								'calls' => $pieces[2],
								'answered' => trim($pieces[14], "C:,"),
								'abandoned' => trim($pieces[15], "A:,"),
								'average_hold_time' => trim($pieces[9], "(s"),
								'average_talk_time' => trim($pieces[11], "s")
							);
						}
						
						if (preg_match("/wait/i", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$waitData[] = array(
								'name' => $pieces2[2],
								'queue' => $queue,
								'position' => trim($pieces2[1], "."),
								'call_wait_time' => trim($pieces2[4], ",")
							);
						}
						
						if(preg_match("/talktime/i", $line))
						{
							$pieces3 = preg_split('/ +/', $line);
							if(isset($pieces3[0]))
							{
								$queue = $pieces3[0];
							}
						}
						
						if(preg_match("/paused/i", $line))
						{
							$status = 'paused';
						} 
						elseif(preg_match("/in call/i", $line))
						{
							$status = 'busy';
						} 
						else 
						{
							$status = 'ready';
						}
						
						if (preg_match("/In use/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$inuseData[] = array(
								'status' => $pieces2[2]." ".$pieces2[3],
								'agent_id' => $pieces2[1],
								'name' => str_replace('SIP/', '', $pieces2[1]),
								'queue' => $queue,
								'calls_today' => $pieces2[10]
							);
						}
						elseif (preg_match("/in call/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$incallData[] = array(
								'status' => $status,
								'agent_id' => $pieces2[1],
								'name' => str_replace('SIP/', '', $pieces2[1]),
								'queue' => $queue,
								'calls_today' => $pieces2[12]
							);
						}
						elseif (preg_match("/Busy/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$busyData[] = array(
								'status' => $pieces2[2]." ".$pieces2[3],
								'agent_id' => $pieces2[1],
								'name' => str_replace('SIP/', '', $pieces2[1]),
								'calls_today' => $pieces2[10]
							);
						}
						elseif (preg_match("/On Hold/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$onhold[] = array(
								'status' => $pieces2[2]." ".$pieces2[3],
								'agent_id' => $pieces2[1],
								'name' => str_replace('SIP/', '', $pieces2[1]),
								'calls_today' => $pieces2[11]
							);
						}
						elseif (preg_match("/Not in use/", $line) || preg_match("/Unavailable/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$notinuseData[] = array(
								'status' => $status, 
								'agent_id' => $pieces2[1], 
								'name' => str_replace('SIP/', '', $pieces2[1]), 
								'queue' => $queue, 
								'calls_today' => $pieces2[10], 
								'calls_last' => $pieces2[14]." ".$pieces2[15]." ".trim($pieces2[16], ')')
							);
						}
						elseif (preg_match("/paused/", $line)) 
						{
							$pieces2 = preg_split('/ +/', $line);
							$pauseData[] = array(
								'status' => 'paused',
								'agent_id' => $pieces2[1],
								'name' => str_replace('SIP/', '', $pieces2[1]),
								'queue' => $queue,
								'calls_today' => $pieces2[11]
							);
						}
					}
					$data = array(
						'sip' 			=> $sipData, 
						'talktime' 		=> $talktimeData, 
						'wait' 			=> $waitData, 
						'in_use' 		=> $inuseData, 
						'in_call' 		=> $incallData, 
						'busy' 			=> $busyData, 
						'on_hold' 		=> $onhold, 
						'not_in_use'	=> $notinuseData, 
						'pause' 		=> $pauseData
					);
				}
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_003');
				$data 	= array();
			}
		  	$asm->disconnect();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'QueueAddMember')
	{ 
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$queue   = $_POST['queue'];
			$channel = $_POST['channel'];
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect())
			{
				$result = $asm->Command("queue add member SIP/$channel to $queue");
				$status = true;
				$msg	= sprintf($app->_lang('msg_010'), $channel, $queue);
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_003');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}

		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'QueueRemoveMember')
	{ 
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$queue   = $_POST['queue'];
			$channel = $_POST['channel'];
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect()){
				$result = $asm->Command("queue remove member SIP/$channel from $queue");
				$status = true;
				$msg	= sprintf($app->_lang('msg_009'), $channel, $queue);
		  	} else {
				$status = false;
				$msg	= $app->_lang('msg_003');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}

		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'QueuePauseMember')
	{ 
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$queue   = $_POST['queue'];
			$channel = $_POST['channel'];
			$asm = new AGI_AsteriskManager();
			
		  	if($asm->connect())
			{
				$result = $asm->Command("queue pause member SIP/$channel queue $queue");
				$status = true;
				$msg	= sprintf($app->_lang('msg_008'), $channel, $queue);
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_003');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'QueueUnpauseMember')
	{ 
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$queue   = $_POST['queue'];
			$channel = $_POST['channel'];
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect())
			{
				$result = $asm->Command("queue unpause member SIP/$channel queue $queue");
				$status = true;
				$msg	= sprintf($app->_lang('msg_007'), $channel, $queue);
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_003');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'SipReload')
	{
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect())
			{
				$result = $asm->Command("sip reload");
				$status = true;
				$msg	= $app->_lang('msg_006');
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_004');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
	
	if($action == 'ModuleReload')
	{
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);
			
			$asm = new AGI_AsteriskManager();
		  	if($asm->connect())
			{
				$result = $asm->Command("module reload");
				$status = true;
				$msg	= $app->_lang('msg_005');
		  	} 
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_004');
			}
		  	$asm->disconnect();
			$data 	= array();
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}

	if($action == 'SipShowPeers')
	{
		if($api_key == $_POST['api_key'])
		{
			unset($_POST['action']);
			unset($_POST['api_key']);
			unset($_POST['api_id']);

			$asm = new AGI_AsteriskManager();
			if($asm->connect())
			{
				$peer = $asm->command("sip show peers");
				if(!strpos($peer['data'], ':'))
				{
					$status 	= false;
					$data 		= $peer['data'];
					$lines 		= array();
				} 
				else 
				{
					$data 		= array();
					$lines 		= array();
					$countLine 	= 0;
					foreach(explode("\n", $peer['data']) as $line)
					{
						$countLine = $countLine + 1;
						$a = strpos('z'.$line, ':') - 1;
						if($a >= 0)
						{
							$data[trim(substr($line, 0, $a))] = trim(substr($line, $a + 1));
						} 
						else 
						{
							if($countLine > 2 && $line != '')
							{
								$dataUser = explode(' ', preg_replace('/\s+/', ' ', $line));
								$nameUsername = explode('/', $dataUser[0]);
								$lines[] = array(
									'Name' 			=> $nameUsername[0], 
									'Username' 		=> $nameUsername[1], 
									'Host' 			=> str_replace(array('(', ')'), array('', ''), htmlentities($dataUser[1])), 
									'Dyn' 			=> $dataUser[2], 
									'Force_rport' 	=> $dataUser[3], 
									'Comedia' 		=> $dataUser[4], 
									'ACL_port' 		=> $dataUser[5], 
									'Status' 		=> trim($dataUser[6].' '.$dataUser[7]), 
									'Description' 	=> ''
								);
							}
						}
					}
					$data 	= $lines;
					$status = true;
					$msg	= '';
				}
				$asm->disconnect();
			}
			else 
			{
				$status = false;
				$msg	= $app->_lang('msg_003');
				$data 	= array();
			}
		} 
		else 
		{
			$status = false;
			$msg 	= $app->_lang('msg_004');
			$data 	= array();
		}
		$insertLogs = array(
			'id' => (array)$api_id,
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
			'action' 	=> $action, 
			'status' 	=> $status, 
			'msg' 		=> $msg, 
			'data' 		=> $data, 
			'time' 		=> date('Y-m-d H:i:s'), 
			'unix_time' => (int)time()
		);
		$app->returnDataJson( $returnData );
	}
}
?>