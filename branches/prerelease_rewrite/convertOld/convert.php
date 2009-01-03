<?php

/**
 * Convert blogs created from the original version of CS-Blogger (no version 
 * number, where it used filenames as the entry text).
 */

require_once(dirname(__FILE__) .'/../../siteConfig.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_globalFunctions.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_fileSystemClass.php');
require_once(dirname(__FILE__) .'/../abstract/dataLayer.abstract.class.php');



$oldDbParams = array(
	'dbname'	=> CS_BLOGDBNAME,
	'rwDir'		=> CS_SQLITEDBDIR
	
);
$newDbParams = array(
	'host'		=> "localhost",
	'port'		=> 5432,
	'dbname'	=> "cs",
	'user'		=> "postgres",
	'password'	=> ""
);

// LOOK TO THE BOTTOM FOR ACTUAL CODE....


//=============================================================================
//=============================================================================
//=============================================================================
//=============================================================================
class tmpConverter extends dataLayerAbstract {
	
	/** Connection to the old SQLite database */
	private $oldDb;
	
	/** Object to interact with the filesystem. */
	private $fsObj;
	
	/** cs_globalFunctions{} object. */
	protected $gfObj;
	
	
	//-------------------------------------------------------------------------
	public function __construct($newDbParms, $oldDbParms) {
		$this->oldDb = new cs_phpDB('sqlite');
		$this->oldDb->connect($oldDbParms);
		
		$this->fsObj = new cs_fileSystemClass($oldDbParms['rwDir']);
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		
		parent::__construct($newDbParms);
		$this->db->beginTrans();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Convert users from the old blog database to the new one.  This isn't
	 * specifically called during the normal conversion, as users might already 
	 * exist in the new database.
	 * 
	 * NOTE::: users in the new database would have to have the same uid's as 
	 * those in the old database in order for this to work (running an update on 
	 * the old database, maybe in a transaction, would probably do the trick).
	 */
	public function convert_users($newTable) {
		//retrieve a list of users.
		$numrows = $this->oldDb->exec("SELECT * FROM cs_authentication_table");
		$dberror = $this->oldDb->errorMsg();
		
		if($numrows > 0 && !strlen($dberror)) {
			$convertUsers = $this->oldDb->farray_fieldnames('uid', true, false);
			
			$this->db->beginTrans();
			$createdUsers = 0;
			foreach($convertUsers as $uid=>$userData) {
				//make the date for last_login properly formatted.
				$userData['last_login'] = $this->fix_timestamp($userData['last_login']);
				
				$sql = "INSERT INTO ". $newTable ." ". 
					$this->gfObj->string_from_array($userData, 'insert', null, 'sql_insert');
				
				$numrows = $this->db->exec($sql);
				$dberror = $this->db->errorMsg();
				
				if($numrows == 1 && !strlen($dberror)) {
					$createdUsers++;
				}
				else {
					throw new exception(__METHOD__ .": failed to add user with uid=(". $uid ."), " .
							"invalid numrows (". $numrows .") or dberror::: ". $dberror );
				}
			}
			
			if($createdUsers == count($convertUsers)) {
				$retval = $createdUsers;
			}
			else {
				$this->db->rollbackTrans();
				throw new exception(__METHOD__ .": failed to create all users!");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve any users (". $numrows .") or " .
					"database error::: ". $dberror);
		}
		
		$this->gfObj->debug_print(__METHOD__ .": converted ". $retval ." users");
		
		return($retval);
		
	}//end convert_users()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function fix_timestamp($timestamp) {
		$dateBits = getdate($timestamp);
		$timestamp = $dateBits['year'] ."-". $dateBits['mon'] ."-". $dateBits['mday'] 
			." ". $dateBits['hours'] .":". $dateBits['minutes'] .":". $dateBits['seconds'];
		
		return($timestamp);
	}//end fix_timestamp()
	//-------------------------------------------------------------------------
	
	
		
	//-------------------------------------------------------------------------
	public function run_conversion() {
		$this->convert_blogs();
	}//end run_conversion()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function convert_blogs() {
		//retrieve the list of old blogs.
		$numrows = $this->oldDb->exec("SELECT * FROM cs_blog_table");
		$dberror = $this->oldDb->errorMsg();
		
		if($numrows > 0 && !strlen($dberror)) {
			$data = $this->oldDb->farray_fieldnames('blog_id', true);
			
			$blogsCreated = 0;
			foreach($data as $blogId=>$blogData) {
				$newBlogId = $this->create_blog($blogData['blog_name'], $blogData['uid'], $blogData['location']);
				$blogsCreated++;
			}
		}
		else {
			throw new exception(__METHOD__ .": unable to retrieve list of existing blogs");
		}
		
		if($blogsCreated == count($data)) {
			$retval = $blogsCreated;
		}
		else {
			throw new exception(__METHOD__ .": failed to create all blogs");
		}
		
		$this->gfObj->debug_print(__METHOD__ .": finished, converted ". $retval ." blogs");
		
		return($retval);
		
	}//end convert_blogs()
	//-------------------------------------------------------------------------
	
}//end converter{}

$obj = new tmpConverter($newDbParams, $oldDbParams);
$obj->run_setup();
$obj->convert_users('cs_authentication_table');
$obj->run_conversion();


?>