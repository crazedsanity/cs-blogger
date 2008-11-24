<?php

require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');

abstract class dataLayerAbstract{
	
	
	//-------------------------------------------------------------------------
   	function __construct($dbType='sqlite', array $dbParams) {
		$this->db = new cs_phpDB($dbType);
		$this->db->connect($dbParams);
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function run_setup() {
		
		
		$retval = false;
		if(CSBLOG__DBTYPE == 'sqlite') {
			//SQLite
			$cmd = 'cat '. dirname(__FILE__) .'/../schema/'. CSBLOG__DBTYPE .'.schema.sql | psql '. CSBLOG__RWDIR .'/'. CSBLOG__DBNAME;
			$this->gf->debug_print(__METHOD__ .": COMMAND: ". $cmd);
			system($cmd, $retval);
			
			if($retval !== 0) {
				throw new exception(__METHOD__ .": failed to create database with result (". $retval .")");
			}
			else {
				$retval = true;
			}
		}
		else {
			//PostgreSQL (or MySQL)
			$this->db->beginTrans();
			$fs = new cs_fileSystemClass(dirname(__FILE__) .'/../schema');
			$mySchema = $fs->read(CSBLOG__DBTYPE .'.schema.sql');
			
			#$this->gf->debug_print($mySchema);
			$retval = $this->db->exec($mySchema);
		}
		
		$retval = $this->db->exec("select * from cs_authentication_table");
		if($retval <= 0) {
			throw new exception(__METHOD__ .": no users created (". $retval ."), must have failed: ". $this->db->errorMsg());
		}
		return($retval);
	}//end run_setup()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_user($username, $password) {
		$pass = md5($username .'-'. $password);
		$sql = 'INSERT INTO cs_authentication_table (username, passwd) VALUES ' .
				"('". $username ."', '". $password ."')";
		$retval = $this->db->exec($sql);
		
		$this->gf->debug_print(__METHOD__ .": returned (". $retval .")");
		return($retval);
	}//end create_user()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function create_blog($blogName) {
	}//end create_blog()
	//-------------------------------------------------------------------------
	
	
}//end dataLayerAbstract{}
?>
