<?php

/**
 * Convert blogs created from the original version of CS-Blogger (no version 
 * number, where it used filenames as the entry text).
 */

require_once(dirname(__FILE__) .'/../../siteConfig.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_globalFunctions.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_fileSystemClass.php');
require_once(dirname(__FILE__) .'/../abstract/csb_blog.abstract.class.php');



$oldDbParams = array(
	'dbname'	=> CS_BLOGDBNAME,
	'rwDir'		=> CS_SQLITEDBDIR
	
);
$newDbParams = array(
	'host'		=> constant('CSBLOG_DB_HOST'),
	'port'		=> constant('CSBLOG_DB_PORT'),
	'dbname'	=> constant('CSBLOG_DB_DBNAME'),
	'user'		=> constant('CSBLOG_DB_USER'),
	'password'	=> constant('CSBLOG_DB_PASSWORD'),
);

// LOOK TO THE BOTTOM FOR ACTUAL CODE....


//=============================================================================
//=============================================================================
//=============================================================================
//=============================================================================
class tmpConverter extends csb_blogAbstract {
	
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
		
		$this->fsObj = new cs_fileSystemClass(constant('CS_BLOGRWDIR'));
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		
		parent::__construct($newDbParms);
		$this->dbParams = $newDbParms;
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
		$this->convert_permissions();
		$this->convert_entries();
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
				unset($this->blogId);
				$this->initialize_locals($blogData['blog_name']);
				$this->update_blog_data(array('blog_display_name'=>ucfirst($blogData['blog_name']) ."'s Blog"));
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
	
	
	
	//-------------------------------------------------------------------------
	private function convert_permissions() {
		//retrieve the old permissions.
		$numrows = $this->oldDb->exec("SELECT * FROM cs_blog_access_table");
		$dberror = $this->oldDb->errorMsg();
		
		if($numrows > 0 && !strlen($dberror)) {
			$data = $this->oldDb->farray_fieldnames('blog_access_id', true);
			
			$numPerms = 0;
			$permObj = new csb_permission($this->dbParams);
			foreach($data as $blogAccessId => $permData) {
				$res = $permObj->add_permission($permData['blog_id'], $permData['uid']);
				$numPerms++;
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to get data (". $numrows .") or database error::: ". $dberror);
		}
		
		if($numPerms == count($data)) {
			$retval = $numPerms;
		}
		else {
			throw new exception(__METHOD__ .": failed to restore all permissions");
		}
		
		$this->gfObj->debug_print(__METHOD__ .": finished, converted ". $retval ." permission records");
		
		return($retval);
	}//end convert_permissions()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function convert_entries() {
		//retrieve the list of entries from the database.
		$numrows = $this->oldDb->exec("SELECT be.*, b.blog_name FROM cs_blog_entry_table AS be INNER JOIN " .
				"cs_blog_table AS b ON (b.blog_id=be.blog_id)");
		$dberror = $this->oldDb->errorMsg();
		
		if($numrows > 0 && !strlen($dberror)) {
			$data = $this->oldDb->farray_fieldnames('be.blog_entry_id', true);
			
			
			$numCreated = 0;
			foreach($data as $entryId=>$entryData) {
				unset($this->blogId);
				$this->initialize_locals($entryData['b.blog_name']);
				$entryText = $this->fsObj->read($entryData['be.permalink'] .'.blog');
				
				
				//remove doubled-up single quotes (problem with quoting in the old system)
				$fixRegex = "/[']{2,}/";
				$entryData['be.title'] = preg_replace($fixRegex, "'", $entryData['be.title']);
				
				//remove block row definitions from within the entry text
				$entryText = preg_replace("/<!-- BEGIN (.+) -->/", "", $entryText);
				$entryText = preg_replace("/<!-- END (.+) -->/", "", $entryText);
				
				//get blog data...
				
				//we've got everything; create the entry.
				$result = $this->create_entry(
					$entryData['be.blog_id'],
					$entryData['be.author_uid'],
					$entryData['be.title'],
					$entryText,
					array('post_timestamp'	=> $this->fix_timestamp($entryData['be.post_timestamp']))
				);
				$numCreated++;
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve any entries (". $numrows .") or dberror::: ". $dberror);
		}
		
		if($numCreated == count($data)) {
			$retval = $numCreated;
		}
		else {
			$this->rollbackTrans();
			throw new exception(__METHOD__ .": failed to convert all records");
		}
		
		$this->gfObj->debug_print(__METHOD__ .": finished, converted ". $retval ." records");
		
		return($retval);
	}//end convert_entries()
	//-------------------------------------------------------------------------
	
}//end converter{}

$obj = new tmpConverter($newDbParams, $oldDbParams);
$obj->db->beginTrans();
$obj->run_setup();
$obj->convert_users('cs_authentication_table');
$obj->run_conversion();
$obj->db->commitTrans();


?>