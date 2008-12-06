<?php

require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');

/**
 * TASKS:::
 * [_] Create abstraction layers for multiple data sets
 * 		[_] PostgreSQL (pgsql)
 * 		[_] Mysql
 * 		[_] SQLite
 * 		[_] file-based (nodb)
 * [_] Abstract user authentication
 * 		[_] Change authentication layer to just link usernames to internal user id's
 */

abstract class dataLayerAbstract{
	
	/**  */
	protected $gfObj;
	
	//-------------------------------------------------------------------------
   	function __construct($dbType='sqlite', array $dbParams) {
		$this->db = new cs_phpDB($dbType);
		$this->db->connect($dbParams);
		$this->gfObj = new cs_globalFunctions();
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function run_setup() {
		$retval = false;
		if(CSBLOG__DBTYPE == 'sqlite') {
			//SQLite
			$cmd = 'cat '. dirname(__FILE__) .'/../schema/'. CSBLOG__DBTYPE .'.schema.sql | psql '. CSBLOG__RWDIR .'/'. CSBLOG__DBNAME;
			$this->gfObj->debug_print(__METHOD__ .": COMMAND: ". $cmd);
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
			
			#$this->gfObj->debug_print($mySchema);
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
		
		$existingUser = $this->get_uid($username);
		
		ob_start();
		var_dump($existingUser);
		$debugThis = ob_get_contents();
		ob_end_clean();
		$this->gfObj->debug_print(__METHOD__ .": existing user=(". $debugThis .")");
		
		if($existingUser === false) {
			$pass = md5($username .'-'. $password);
			$sql = 'INSERT INTO cs_authentication_table (username, passwd) VALUES ' .
					"('". $username ."', '". $password ."')";
			$numrows = $this->db->exec($sql);
			$dberror = $this->db->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$sql = "SELECT currval('cs_authentication_table_uid_seq')";
				$numrows = $this->db->exec($sql);
				if($numrows == 1) {
					$data = $this->db->farray();
					$retval = $data[0];
				}
				else {
					throw new exception(__METHOD__ .": invalid numrows (". $numrows .") while retrieving last inserted uid");
				}
			}
			else {
				throw new exception(__METHOD__ .": failed insert (". $numrows ."): ". $dberror);
			}
		}
		else {
			$this->gfObj->debug_print(__METHOD__ .": existing user=(". $existingUser .")");
			throw new exception(__METHOD__ .": user exists (". $username .")");
		}
		
		return($retval);
	}//end create_user()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_uid($username) {
		
		if(strlen($username) && is_string($username) && !is_numeric($username)) {
			$username = $this->gfObj->cleanString($username, 'email');
			$sql = "SELECT uid FROM cs_authentication_table WHERE username='". $username ."'";
			$numrows = $this->db->exec($sql);
			
			if($numrows == 1) {
				$data = $this->db->farray();
				$retval = $data[0];
			}
			elseif($numrows == 0) {
				$retval = false;
			}
			else {
				throw new exception(__METHOD__ .": invalid numrows (". $numrows .")");
			}
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": invalid data for username (". $username .")");
		}
		
		return($retval);
	}//end get_uid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function create_blog($blogName, $owner) {
		if(!strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blogName (". $blogName .")");
		}
		elseif(!strlen($owner)) {
			throw new exception(__METHOD__ .": invalid owner (". $owner .")");
		}
		
		if(!is_numeric($owner)) {
			$owner = $this->get_uid($owner);
		}
		$sql = "INSERT INTO cs_blog_table ". $this->gfObj->string_from_array(
			array(
				'blog_name'		=> $blogName,
				'uid'			=> $owner
			),
			'insert',
			NULL,
			'sql_insert'
		);
		
		$this->gfObj->debug_print(__METHOD__ .": SQL::: ". $sql);
		
		$numrows = $this->db->exec($sql);
		
		if($numrows == 1) {
			#throw new exception(__METHOD__ .": PUT SOMETHING HERE (". __FILE__ .": ". __LINE__ .")");
			//pull the blogId.
			$numrows = $this->db->exec("SELECT currval('cs_blog_table_blog_id_seq')");
			$dberror = $this->db->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$data = $this->db->farray();
				$retval = $data[0];
				$this->gfObj->debug_print(__METHOD__ .": new blog_id=(". $retval .")");
			}
			else {
				throw new exception(__METHOD__ .": failed to get new blog_id, numrows=(". $numrows ."): ". $dberror);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows .") returned, ERROR: ". $this->db->errorMsg());
		}
		
		return($retval);
	}//end create_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function post_entry() {
	}//end post_entry)
	//-------------------------------------------------------------------------
	
	
}//end dataLayerAbstract{}
?>
