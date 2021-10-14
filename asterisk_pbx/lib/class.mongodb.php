<?php
class MGDB_Api
{
	protected $db_url;
	protected $db_name;
	
	public function __construct($db_url, $db_name)
	{
		$this->url = $db_url;
		$this->name = $db_name;
	}
	
	public function isValid($value=NULL)
    {
		if($value != ''){
			if ($value instanceof \MongoDB\BSON\ObjectID) {
				return true;
			}
			try {
				new \MongoDB\BSON\ObjectID($value);
				return true;
			} catch (\Exception $e) {
				return false;
			}
		} else {
			return false;
		}
    }
	
    public function createIndex($collection=NULL, $data = [])
    {
        $data['ns'] = $this->name.'.'.$collection;
        $CommandQuery = ['createIndexes' => $collection, 'indexes' => [$data]];
        try 
        {
			$manager = new \MongoDB\Driver\Manager($this->url); 
			$cmd = new \MongoDB\Driver\Command( $CommandQuery );
			$rows = $manager->executeCommand( $this->name, $cmd );
			$res = $rows->toArray();
			$dataReturn = array('status' => true, 'data' => $res);
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
    }
    
    public function dropIndex($collection=NULL, $indexName)
    {
        $CommandQuery = ['dropIndexes' => $collection, 'index' => $indexName];
        try 
        {
			$manager = new \MongoDB\Driver\Manager($this->url); 
			$cmd = new \MongoDB\Driver\Command( $CommandQuery );
			$rows = $manager->executeCommand( $this->name, $cmd );
			$res = $rows->toArray();
			$dataReturn = array('status' => true, 'data' => $res);
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
    }
    
	public function select($collection=NULL, $filter = [])
    { 
		$db_tab = $this->name.'.'.$collection; 
		try { 
			$db = new \MongoDB\Driver\Manager( $this->url ); 
			$query = new \MongoDB\Driver\Query($filter, ['limit' => 1]); 
			$cursor = $db->executeQuery($db_tab, $query); 
			$cursorArray = $cursor->toArray();
			$content = array(); 
			if(isset($cursorArray[0])) {
				$content = (array)$cursorArray[0];
			}
			return $dataReturn = array('status' => true, 'data' => $content); 
		} catch (\MongoDB\Driver\Exception\Exception $e) { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
	}
	
	public function selects($collection=NULL, $filter = [], $option = [])
    { 
		$db_tab = $this->name.'.'.$collection; 
		try 
        { 
			$db = new \MongoDB\Driver\Manager( $this->url ); 
			$query = new \MongoDB\Driver\Query($filter, $option); 
			$rows = $db->executeQuery($db_tab, $query); 
			$content = array(); 
			foreach($rows as $row){ 
				$content[] = (array)$row;
			} 
			return $dataReturn = array('status' => true, 'data' => $content); 
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
	}
	
	public function insert($collection=NULL, $data = [])
    { 
		$db_tab = $this->name.'.'.$collection; 
		try 
        { 
            $_id = new \MongoDB\BSON\ObjectId();
            if(!isset($data['_id']))
            {
                $data['_id'] = (string)$_id;
            }
			$bulk = new \MongoDB\Driver\BulkWrite(); 
			$bulk->insert($data); 
			$db = new \MongoDB\Driver\Manager( $this->url ); 
			$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000); 
			$result = $db->executeBulkWrite($db_tab, $bulk, $writeConcern);
			$dataReturn = array('status' => true, 'data' => $result);
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn; 
	}
	
	public function update($collection=NULL, $filter = [], $data = [], $option = [])
    { 
		$db_tab = $this->name.'.'.$collection; 
		try 
        { 
			$bulk = new \MongoDB\Driver\BulkWrite; 
			$result = $bulk->update($filter, $data, $option); 
			$db = new \MongoDB\Driver\Manager( $this->url ); 
			$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000); 
			$db->executeBulkWrite($db_tab, $bulk, $writeConcern); 
			$dataReturn = array('status' => true, 'data' => $result); 
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn; 
	}
	
	public function delete($collection=NULL, $filter = [])
    { 
		$db_tab = $this->name.'.'.$collection; 
		try 
        { 
			$bulk = new \MongoDB\Driver\BulkWrite; 
			$bulk->delete($filter); 
			$db = new \MongoDB\Driver\Manager($this->url); 
			$writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 1000); 
			$db->executeBulkWrite($db_tab, $bulk, $writeConcern); 
			$dataReturn = array('status' => true, 'data' => array()); 
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
	}
	
	public function count($collection=NULL, $filter = [])
    {
		$db_tab = $this->name.'.'.$collection;
		try 
        {
			if($filter == null){
				$CommandQuery = array( 'count' => $collection );
			} else {
				$CommandQuery = array( 'count' => $collection, 'query' => $filter );
			}
			$manager = new \MongoDB\Driver\Manager($this->url); 
			$cmd = new \MongoDB\Driver\Command( $CommandQuery );
			$rows = $manager->executeCommand( $this->name, $cmd );
			$res = $rows->toArray();
			$dataReturn = array('status' => true, 'data' => array('n' => $res[0]->n, 'msg' => ''));
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
	}
	
	public function command($CommandQuery = [])
    {
		try 
        {
			$manager = new \MongoDB\Driver\Manager($this->url); 
			$cmd = new \MongoDB\Driver\Command( $CommandQuery );
			$rows = $manager->executeCommand( $this->name, $cmd );
			$res = $rows->toArray();
			$dataReturn = array('status' => true, 'data' => $res);
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
		
	}
	
	public function join($collection=NULL, $filter = [])
    {
		try 
        {
			$manager = new \MongoDB\Driver\Manager($this->url); 
			$cmd = new \MongoDB\Driver\Command( ['aggregate' => $collection, 'pipeline' => $filter, 'cursor' => new \stdClass] );
			$rows = $manager->executeCommand( $this->name, $cmd );
			$content = array(); 
			foreach($rows as $row){ 
				$content[] = (array)$row;
			} 
			$dataReturn = array('status' => true, 'data' => $content);
		} 
        catch (\MongoDB\Driver\Exception\Exception $e) 
        { 
			$dataReturn = array('status' => false, 'data' => array('msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine())); 
		} 
		return $dataReturn;
	}
}
?>
