<?php
class PbxApi
{
	public function ip()
    { 
		if (!empty($_SERVER['HTTP_CLIENT_IP']))   
		{
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
		{
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else
		{
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}
		return $ip_address;
	}

  	public function formDataName($data = [], $name='')
    { 
		$data_value = ''; 
		if(is_array($data) || is_object($data))
        {
			foreach($data as $data_check)
            { 

				if($data_check['name'] == $name)
                { 
					$data_value = $data_check['value']; 
				} 

			} 
		}
		return $data_value; 
	}
	
	public function formDataArrayName($data = [], $name='')
    { 
		$data_array = array(); 
		if(is_array($data) || is_object($data))
        {
			foreach($data as $data_check)
            { 
				if($data_check['name'] == $name)
                { 
					$data_array[] = $data_check['value']; 
				} 
			} 
		}
		return $data_array; 
	}
	
	public function formDataArrayNames($data = [], $dataName=[])
	{
		$dataC = [];
		foreach ($data as $key => $value) {
			foreach((array)$dataName as $name){
				if ($name == $value['name']) {
					$dataC[$name][] = $value['value'];
				}
			}
		}
		$dataR= [];
		if(!empty($dataC)):
			foreach (reset($dataC) as $i => $val){
				foreach ($dataC as $key => $item){
					$dataR[$i][$key]=  $item[$i];
				}
			}
		endif;
	 	return ($dataR);
	}
	
	public function formGroupDataArrayNames($data = [], $dataName=[], $groupName=NULL)
	{
		$dataC = [];
		foreach ($data as $key => $value) {
			foreach((array)$dataName as $name){
				if ($name == $value['name']) {
					$dataC[$name][] = $value['value'];
				}
			}
		}
		$dataR= [];
		if(!empty($dataC)):
			foreach (reset($dataC) as $i => $val){
				foreach ($dataC as $key => $item){
					$dataR[$i][$key]=  $item[$i];
				}
			}
		endif;
		if($groupName == NULL){
	 		return ($dataR);
		} else {
			$dataG = array();
			foreach($dataR as $item){
				$dataG[$item[$groupName]] = $item;
			}
			return ($dataG);
		}
	}
	
	public function sortArrayByField($array=[], $field='', $sortType = 'ASC')
	{
		$sortarray = array();
		if($array != NULL)
        {
            $array = (array)$array;
			foreach ($array as $key => $row)
			{
                $row = (array)$row;
				$sortarray[$key] = $row[$field];
			}
			if($sortType == 'ASC' || $sortType == 'asc')
            {
				array_multisort($sortarray, SORT_ASC, $array);
			} 
            else 
            {
				array_multisort($sortarray, SORT_DESC, $array);
			}
			return $array;
		} 
        else 
        {
			return array();
		}
	}
	
	public function groupByKeyArray($data=[], $key=NULL)
    {
		$result = array();
		foreach ((array)$data as $element) 
        {
			$element = (array)$element;
			$result[$element[$key]][] = $element;
		}
		
		$result1 = array();
		foreach($result as $key1 => $data1)
        {
			if(count($data1) > 1){
				foreach($data1 as $data)
                {
					$result1[$key1][] = $data;
				}
			} 
            else 
            {
				$result1[$key1] = $data1[0];
			}
		}
		return $result1; 
	}
	
	public function createDir($directory='', $index=false)
	{
		if (!file_exists($directory)) 
        {
			if(mkdir($directory, 0777, true))
            {
                if($index == true)
                {
                    $this->updateFile('pageok!', rtrim($directory, '/').'/index.html');
                }
				return true;
			} 
            else 
            {
				return false;
			}
		} 
        else 
        {
			return false;
		}
	}
	
	public function updateFile($data='', $file='')
	{
		$cacheFile = @fopen("$file", "w"); 
		if($cacheFile)
        { 
			fwrite($cacheFile, $data); 
			fclose($cacheFile); 
			return true; 
		} 
        else 
        { 
			if($this->deleteFile($file) == true)
            {
				return $this->updateFile($data, $file);
			} 
            else 
            {
				return false;
			}
		}
	}
	
	public function selectFile($file='')
	{
		$cacheFile = @fopen("$file", "r"); 
		if($cacheFile)
        { 
			return @fread($cacheFile, filesize("$file")); 
			fclose($cacheFile); 
		} 
        else 
        { 
			return false; 
		}
	}

	public function curlPost($url=NULL, $data=NULL, $headers = NULL) 
    { 
		$ch = curl_init($url); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		if(!empty($data))
        { 
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); 
		} 
		if (!empty($headers)) 
        { 
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		} 
		$response = curl_exec($ch); 
		if (curl_error($ch)) 
        { 
			$returnData = array(
				'status' => false,
				'data' => array(),
				'msg' => 'Curl Error:' . curl_error($ch), 
				'time' => time() 
			); 
		} 
        else 
        { 
			$returnData = json_decode($response, true);
		} 
		curl_close($ch); 
		return $returnData; 
	}
	
	public function getListFile($Directory=NULL, $type='*')
    {
		$dataReturn = array();
		global $dir_root;
		if($Directory != NULL)
        {
			if (substr($Directory, strlen($Directory) - 1, 1) != '/') 
            {
				$Directory .= '/';
			}
			foreach (glob($Directory."*.".$type) as $filename)
			{
				$dataTemp = explode($dir_root, $filename);
				if($dataTemp[1] != '')
                {
					$dataReturn[] = $dataTemp[1];
				} 
                else 
                {
					$dataReturn[] = $dataTemp[0];
				}
			}
		}
		return $dataReturn;
	}
	
	public function getListDir($Directory=NULL)
    {
		if (substr($Directory, strlen($Directory) - 1, 1) != '/') 
        {
			$Directory .= '/';
		}
		$dir = new DirectoryIterator($Directory);
		$lists = array();
		foreach ($dir as $fileinfo) 
        {
			if ($fileinfo->isDir() && !$fileinfo->isDot()) 
            {
				$lists[] = $fileinfo->getFilename();
			}
		}
		return $lists;
	}
    
    public function getDirAndFile($Directory=NULL) 
    {
  		if (substr($Directory, strlen($Directory) - 1, 1) != '/') 
        {
			$Directory .= '/';
		}
	   	$dir = new DirectoryIterator($Directory);
		$lists = array();
		foreach ($dir as $fileinfo) 
        {
			if($fileinfo->getFilename() != '.')
            {
				if($fileinfo->getFilename() != '..')
                {
                    $dirPath = $Directory.$fileinfo->getFilename();
					if ($fileinfo->isDir() && !$fileinfo->isDot()) 
                    {
                        $lists[$dirPath] = $this->getDirAndFile($dirPath);
					} 
                    else 
                    {
                        $lists[] = $dirPath;
					}
				}
			}
		}
		return $lists;
	}
    
    public function removeShell($Directory)
    {
        $dir_root = $_SERVER['DOCUMENT_ROOT'];
  		if (substr($Directory, strlen($Directory) - 1, 1) != '/') 
        {
			$Directory .= '/';
		}
	   	$dir = new DirectoryIterator($Directory);
		$lists = array();

		foreach ($dir as $fileinfo) 
        {
			if($fileinfo->getFilename() != '.')
            {
				if($fileinfo->getFilename() != '..')
                {
                    $dirPath = $Directory.$fileinfo->getFilename();
					if(preg_match('/.htaccess/i', $dirPath))
					{
						if($dirPath != $dir_root.'/'.$_SERVER['HTTP_HOST'].'/.htaccess')
						{
                            deleteFile($dirPath);
						}
					}
					
					if(preg_match('/lock360.php/i', $dirPath) || preg_match('/radio.php/i', $dirPath) || preg_match('/content.php/i', $dirPath) || preg_match('/about.php/i', $dirPath))
					{
						deleteFile($dirPath);
					}
					
					if ($fileinfo->isDir() && !$fileinfo->isDot()) 
                    {
                        $lists[$dirPath] = getDirAndFile($dirPath);
						if(!file_exists($dirPath.'/index.html'))
						{
							updateFile('pageok!', $dirPath.'/index.html');
						}
					} 
                    else 
                    {
                        $lists[] = $dirPath;
					}
				}
			}
		}
    }
	
	public function getDirData($Directory=NULL) 
    {
  		if (substr($Directory, strlen($Directory) - 1, 1) != '/') 
        {
			$Directory .= '/';
		}
	   	$dir = new DirectoryIterator($Directory);
		$lists = array();
		$lists['root'] = $Directory;
		foreach ($dir as $fileinfo) 
        {
			if($fileinfo->getFilename() != '.')
            {
				if($fileinfo->getFilename() != '..')
                {
					if ($fileinfo->isDir() && !$fileinfo->isDot()) 
                    {
						$dirPath = $Directory.$fileinfo->getFilename();
                        if($this->serverInfo()['OS'] == 'Windows')
                        {
                            $bytestotal = 0;
                            $path = realpath($dirPath);
                            if($path!==false && $path!='' && file_exists($path)){
                                foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                                    $bytestotal += $object->getSize();
                                }
                            }
                            $directoryize = $bytestotal;
                        } 
                        else
                        {
				            $output = exec('du -sk ' . $dirPath);
				            $directoryize = trim(str_replace($dirPath, '', $output)) * 1024;
                        }
						
                        $group = 'n/a';
                        $owner = 'n/a';
                        if(function_exists('posix_getgrgid'))
                        {
                            $group = posix_getgrgid(filegroup($dirPath))['name'];
                        }
                        if(function_exists('posix_getpwuid'))
                        {
                            $owner = posix_getpwuid(filegroup($dirPath))['name'];
                        }
                        
						$lists['folder'][] = array('name' => $fileinfo->getFilename(), 'type' => 'folder', 'path' => $Directory.$fileinfo->getFilename().'/', 'pathinfo' => '', 'size' => $directoryize, 'time' => filemtime($dirPath), 'permissions' => substr(sprintf('%o', fileperms($Directory.$fileinfo->getFilename().'/')), -4), 'group' => $group, 'owner' => $owner);
					} 
                    else 
                    {
						$filePath = $Directory.$fileinfo->getFilename();
                        
                        $group = 'n/a';
                        $owner = 'n/a';
                        if(function_exists('posix_getgrgid'))
                        {
                            $group = posix_getgrgid(filegroup($filePath))['name'];
                        }
                        if(function_exists('posix_getpwuid'))
                        {
                            $owner = posix_getpwuid(filegroup($filePath))['name'];
                        }
						
						$lists['file'][] = array('name' => $fileinfo->getFilename(), 'type' => 'file', 'path' => $Directory.$fileinfo->getFilename(), 'pathinfo' => strtolower(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION)), 'size' => filesize($filePath), 'time' => filemtime($filePath), 'permissions' => substr(sprintf('%o', fileperms($Directory.$fileinfo->getFilename())), -4), 'group' => $group, 'owner' => $owner);
					}
				}
			}
		}
		return $lists;
	}
	
	public function deleteFile($file=NULL) 
    {
		if(file_exists($file))
        {
			if(@unlink($file))
            {
				return true;
			} 
            else 
            {
				return false;
			}
		} 
        else 
        {
			return false;
		}
	}
	
	public function deleteDir($dirPath=NULL) 
    {
		if (!is_dir($dirPath)) 
        {
			$status = false;
		} 
        else 
        {
			if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') 
            {
				$dirPath .= '/';
			}
			if($dirPath != NULL && $dirPath != '/' && $dirPath != $_SERVER['DOCUMENT_ROOT'])
            {
				system('rm -rf -- ' . escapeshellarg($dirPath), $status);
				return $status = true;
			} 
            else 
            {
				$status = false;
			}
		}
		return $status;
	}
	
	public function formatSize($bytes,$decimals=2)
    {
		$size=array(' B',' KB',' MB',' GB',' TB',' PB',' EB',' ZB',' YB');
		$factor=floor((strlen($bytes)-1)/3);
		return sprintf("%.{$decimals}f",$bytes/pow(1024,$factor)).@$size[$factor];
	}
	
	public function is_connected($host=NULL) 
    {
		exec("ping -c 1 $host", $output, $status);
		if($status == 0)
        {       
		   return true;
		}
        else
        {
            return false;
        }
	}
	
	public function ipData($ip=NULL)
    {
		if($this->is_connected('ip-api.com') == true)
        {
			$query = unserialize(file_get_contents('http://ip-api.com/php/'.$ip.'?fields=status,continent,continentCode,country,countryCode,city,timezone,currency,isp,asname'));
			if ( empty($query) ) 
            {
				$returnData = array('status' => false, 'data' => array());
			} 
            else 
            {
				if($query['status'] == "success")
                {
					$status = true;
				} 
                else 
                { 
                    $status = false; 
                }
				$returnData = array('status' => $status, 'data' => $query);
			}
		} 
        else 
        {
			$returnData = array('status' => false, 'data' => array());
		}
		return $returnData;
	}
	
	public function encodeStr($data=NULL, $key=NULL)
    { 
        $l = strlen($key);
        if ($l < 16)
            $key = str_repeat($key, ceil(16/$l));

        if ($m = strlen($data)%8)
            $data .= str_repeat("\x00",  8 - $m);
        $val = openssl_encrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

        return base64_encode($val);
	}
	
	public function decodeStr($data=NULL, $key=NULL)
    { 
        $data = base64_decode($data);
		$l = strlen($key);
        if ($l < 16)
            $key = str_repeat($key, ceil(16/$l));

        $val = openssl_decrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        return $val;
	}
	
	public function cutStr($string, $min, $max)
    { 
		if($string != '')
        {
			$str_descf =  strip_tags(html_entity_decode(trim($string), ENT_QUOTES, 'UTF-8')); 
			$str_descf = trim(strip_tags($str_descf)); 
			if(strlen($str_descf) > $min) 
            { 
				$str_desc_Cutf = substr($str_descf, 0, $max); 
				$data = substr($str_desc_Cutf, 0, strrpos($str_desc_Cutf, ' ')) . ' ...'; 
			} 
            else 
            { 
				$data = $str_descf; 
			} 
		} 
        else 
        {
			$data = '...';
		}
		return $data; 
	}
	
	public function randomStr($length) 
    { 
		$keys = array_merge(range(0,9), range('A', 'Z'), range('a', 'z')); 
		$key = ''; 
		for($i=0; $i < $length; $i++) 
        { 
			$key .= $keys[array_rand($keys)]; 
		} 
		$key .= ''; 
		return $key; 
	}
	
	public function randomNum($length) 
    { 
		$keys = array_merge(range(0,9)); 
		$key = ''; 
		for($i=0; $i < $length; $i++) { 
			$key .= $keys[array_rand($keys)]; 
		} 
		$key .= ''; 
		return $key; 
	}

	public function symbolByQuantity($bytes) 
    {
		$symbols = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$exp = floor(log($bytes)/log(1024));

		return sprintf('%.2f '.$symbols[$exp], ($bytes/pow(1024, floor($exp))));
	}

	public function unixToLocal($timestamp=0, $timezone='Atlantic/Reykjavik')
    {
		$local_timezone = new DateTimeZone($timezone);
		$date_time = new DateTime('now', $local_timezone);
		$offset = $date_time->format('P');
		$offset = explode(':', $offset);
		if($offset[1] == 00){ $offset2 = .0; }
		if($offset[1] == 30){ $offset2 = .5; }
		if($offset[1] == 45){ $offset2 = .75; }
		$hours = $offset[0].$offset2 + 0;
		$seconds = $hours * 3600;
		$result = floor( $timestamp + $seconds );
		return $result;
	}
	
	public function convertTimeData($data=NULL)
    {
		if($data != '')
        {
			$time_option_array = explode(' - ', $data);
			
			$timeOption0 = $this->unixToLocal(strtotime($time_option_array[0]));
			$timeOption1 = $this->unixToLocal(strtotime($time_option_array[1]));
			
			$time_start = (int)$this->unixToLocal(strtotime($time_option_array[0]. " 00:00:01"));
			$time_end = (int)$this->unixToLocal(strtotime($time_option_array[1]. " 23:59:59"));

			if($timeOption0 == $timeOption1){
				$totalDay = 0;
			} else if($timeOption1 > $timeOption0){
				$totalDay = ($timeOption1 - $timeOption0)/86400;
			}
			if($totalDay >= 1){

				$outputCheck = [];
				$timeCheck   = $timeOption0;
				$lastCheck   = date('Y-m', $timeOption1);
				do {
					$monthCheck = date('Y-m', $timeCheck);
					$totalCheck = date('t', $timeCheck);

					$outputCheck[] = array(
						'month' => $monthCheck,
						'total' => $totalCheck
					);

					$timeCheck = strtotime('+1 month', $timeCheck);
				} while ($monthCheck != $lastCheck);

				$time_array = array();
				if(count($outputCheck) == 1){

					foreach($outputCheck as $output){
						for($i = 1; $i <= $output['total']; $i++){
							if($i < 10){
								$i = '0'.$i;
							}

							if((int)$i >= (int)date('d', $time_start) && (int)$i <= (int)date('d', $time_end)){
								$time_array[] = (string)($output['month'].'-'.$i);
							}

						}
					}

				} else {

					foreach($outputCheck as $output){
						for($i = 1; $i <= $output['total']; $i++){
							if($i < 10){
								$i = '0'.$i;
							}

							if((int)$i >= (int)date('d', $time_start) && date('Y-m', $time_start) == $output['month']){
								$time_array[] = (string)($output['month'].'-'.$i);
							} else if((int)$i <= (int)date('d', $time_end) && date('Y-m', $time_end) == $output['month']){
								$time_array[] = (string)($output['month'].'-'.$i);
							} else if(date('Y-m', $time_start) != $output['month'] && date('Y-m', $time_end) != $output['month']){
								$time_array[] = (string)($output['month'].'-'.$i);
							}

						}
					}

				}
				$time_check_value = 'date';
			} else if($totalDay == 0){
				for($i = 0; $i <= 23; $i++)
                {
					if($i < 10)
                    {
						$i = '0'.$i;
					}
					$time_array[] = date('Y-m-d', $time_end).' '.(string)$i;
				}
				$time_check_value = 'hours';
			}
			$dataReturn = array(
				'list_time' => $time_array,
				'type_time' => $time_check_value,
				'time_start' => $time_start, 
				'time_end' => $time_end, 
				'data_input' => $data
			);
		} 
        else 
        {
			return false;
		}
		return $dataReturn;
	}

    public function _langLoad($code='')
    {
        global $langCode, $dir_root, $_languageDefault;
        $langFileDefault = $dir_root.'/language/'.$_languageDefault.'.php';
        if($code != ''){ $langCode = $code; }
        $langFile = $dir_root.'/language/'.$langCode.'.php';
        if(file_exists($langFile))
        {
            include $langFile;
        }
        else
        {
            include $langFileDefault;
        }
        return $_text;
    }
    
    public function _lang($text='text_null')
    {
        global $_langLoad;
        $_text = $_langLoad;
        if(!empty($_text[$text]))
        {
            return $_text[$text] ? $_text[$text] : $text;
        }
        else
        {
            return $text;
        }
    }
	
	public function mergeArray($data=[])
    {
		$dataMerge = [];
		foreach ($data as $item){
			$dataMerge = array_merge($dataMerge,$item);
		}
		return $dataMerge;
	}
	
	public function returnDataJson( $data ) 
    {
		echo json_encode( $data );
        $this->errorSave();
        exit();
	}
	
	public function errorSave($data=[]) 
    {
        global $dir_root;
        if(empty($data))
        {
            $data = error_get_last();
        }
        if(!empty($data))
        {
            $msg = "Time: ".date('Y-m-d H:i:s')." | ";
            foreach($data as $name => $value)
            {
                $msg .= "".ucfirst($name).": ".$value." | ";
            }
            $msg = $msg."IP: ".$this->ip();
            $maxSize = 10485760; //10M
            $logFile = $dir_root.'/logs/error.txt';
            if(file_exists($logFile))
            {
                $fileSize = filesize($logFile);
                if($fileSize >= $maxSize)
                {
                    if(rename($logFile, $dir_root.'/logs/error-'.date('Y_m_d_H_i_s', filemtime($logFile)).'-to-'.date('Y_m_d_H_i_s', time()).'.txt'))
                    {
						file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
                    }
                }
                else
                {
                    file_put_contents($logFile, "\n".$msg, FILE_APPEND | LOCK_EX);
                }
            }
            else
            {
                file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
            }
        }
    }
	
	public function cdrSave($data=[]) 
	{
		global $dir_root;

		if(!empty($data))
		{
			$msg = "";
			foreach($data as $name => $value)
			{
				$msg .= "".ucfirst($name).": ".$value." | ";
			}
			$msg = rtrim($msg, ' | ');
			$maxSize = 10485760; //10M
			$logFile = $dir_root.'/logs/cdr.txt';
			if(file_exists($logFile))
			{
				$fileSize = filesize($logFile);
				if($fileSize >= $maxSize)
				{
					if(rename($logFile, $dir_root.'/logs/cdr-'.date('Y_m_d_H_i_s', filemtime($logFile)).'-to-'.date('Y_m_d_H_i_s', time()).'.txt'))
					{
						file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
					}
				}
				else
				{
					file_put_contents($logFile, "\n".$msg, FILE_APPEND | LOCK_EX);
				}
			}
			else
			{
				file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
			}
		}
	}
}
?>