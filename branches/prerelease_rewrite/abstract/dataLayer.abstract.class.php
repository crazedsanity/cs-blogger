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
		
		//check that some required constants exist.
		if(!defined('CSBLOG_TITLE_MINLEN')) {
			define('CSBLOG_TITLE_MINLEN', 4);
		}
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
	function create_blog($blogName, $owner, $location) {
		if(!strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blogName (". $blogName .")");
		}
		elseif(!strlen($owner)) {
			throw new exception(__METHOD__ .": invalid owner (". $owner .")");
		}
		
		if(!is_numeric($owner)) {
			$owner = $this->get_uid($owner);
		}
		$formattedBlogName = $this->create_permalink_from_title($blogName);
		$sql = "INSERT INTO cs_blog_table ". $this->gfObj->string_from_array(
			array(
				'blog_display_name'		=> $blogName,
				'blog_name'				=> $formattedBlogName,
				'uid'					=> $owner,
				'blog_location'			=> $location
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
				
				//Initialize locals now, if it hasn't been done yet.
				if(defined('CSBLOG_SETUP_PENDING')) {
					$this->initialize_locals($formattedBlogName);
				}
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
	public function create_entry($blogId, $authorUid, $title, $content, array $optionalData=NULL) {
		
		//check to make sure we've got all the proper fields and they're formatted appropriately.
		$sqlArr = array();
		$cleanStringArr = array(
			'blog_id'		=> "integer",
			'author_uid'	=> "integer",
			'title'			=> "sql92_insert",
			'content'		=> "sql92_insert",
			'permalink'		=> "email"
		);
		if(is_numeric($blogId) && $blogId > 0) {
			$sqlArr['blog_id'] = $blogId;
		}
		else {
			throw new exception(__METHOD__ .": invalid data for blogId (". $blogId .")");
		}
		if(is_numeric($authorUid) && $authorUid > 0) {
			$sqlArr['author_uid'] = $authorUid;
		}
		else {
			throw new exception(__METHOD__ .": invalid data for authorUid (". $authorUid .")");
		}
		if(is_string($title) && strlen($title) > CSBLOG_TITLE_MINLEN) {
			$sqlArr['title'] = $title;
		}
		else {
			throw new exception(__METHOD__ .": invalid data for title (". $title .")");
		}
		
		//only allow a few other optional fields (make sure they're the appropriate data type).
		if(is_array($optionalData) && count($optionalData)) {
			
			//there's only one option right now... but this makes it easy to update later.
			$validOptionalFields = array(
				'post_timestamp'	=> 'datetime'
			);
			
			$intersectedArray = array_intersect_key($optionalData, $validOptionalFields);
			
			if(is_array($intersectedArray) && count($intersectedArray)) {
				foreach($intersectedArray as $fieldName => $value) {
					if(!isset($sqlArr[$fieldName])) {
						$sqlArr[$fieldName] = $value;
						$cleanStringArr[$fieldName] = $validOptionalFields[$fieldName];
					}
					else {
						throw new exception(__METHOD__ .": index (". $fieldName .") already exists");
					}
				}
			}
		}
		
		//set some fields that can't be specified...
		$sqlArr['permalink'] = $this->create_permalink_from_title($title);
		$sqlArr['content'] = $this->encode_content($content);
		
		//build the SQL statement.
		$sql = "INSERT iNTO cs_blog_entry_table ". $this->gfObj->string_from_array($sqlArr, 'insert', NULL, $cleanStringArr);
		
		$this->gfObj->debug_print($sql);
		
		//run the statement & check the output.
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(is_numeric($numrows) && $numrows == 1 && !strlen($dberror)) {
			$retval = array(
				'blog_entry_id'		=> $this->db->get_currval('cs_blog_entry_table_blog_entry_id_seq'),
				'full_permalink'	=> $this->blogLocation ."/". $sqlArr['permalink']
			);
		}
		else {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows ."), failed to insert data (". $dberror .")");
		}
		
		return($retval);
	}//end create_entry)
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_permalink_from_title($title) {
		if(is_string($title) && strlen($title) >= CSBLOG_TITLE_MINLEN) {
			
			$permalink = strtolower($title);
			$permalink = preg_replace('/!/', '', $permalink);
			$permalink = preg_replace('/&\+/', '-', $permalink);
			$permalink = preg_replace('/\'/', '', $permalink);
			$permalink = preg_replace("/[^a-zA-Z0-9_]/", "_", $permalink);
			
			if(!strlen($permalink)) {
				throw new exception(__METHOD__ .": invalid filename (". $permalink .") from title=(". $title .")");
			}
			
			//consolidate multiple underscores... (" . . ." becomes "______", after this becomes just "_")
			$permalink = preg_replace('/__*/', '_', $permalink);
		}
		else {
			throw new exception(__METHOD__ .": invalid title (". $title .")");
		}
		
		return($permalink);
	}//end create_permalink_from_title()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function encode_content($content) {
		//make it base64 data, so it is easy to insert.
		$retval = base64_encode($content);
		return($retval);
	}//end encode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function decode_content($content) {
		$retval = base64_decode($content);
		return($retval);
	}//end decode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_entry($fullPermalink) {
		//the total permalink length should be at least double the minimum title length to include a path.
		if(strlen($fullPermalink) > (CSBLOG_TITLE_MINLEN *2)) {
			//now get the permalink separate from the title.
			$parts = explode('/', $fullPermalink);
			$permalink = $parts[(count($parts)-1)];
			$location = preg_replace('/'. $permalink .'$/', '', $fullPermalink);
			$location = preg_replace('/\/+$/', '', $location);
			
			$this->gfObj->debug_print("Location: (". $location ."), permalink: (". $permalink .")");
			
			$sql = "SELECT be.* FROM cs_blog_entry_table AS be INNER JOIN cs_blog_table AS b ON (be.blog_id=b.blog_id) " .
					"WHERE b.blog_location='". $location ."' AND be.permalink='". $permalink ."'";
			
			$this->gfObj->debug_print($sql);
			
			$numrows = $this->db->exec($sql);
			$dberror = $this->db->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$retval = $this->db->farray_fieldnames();
				if(isset($retval['content'])) {
					$retval['content'] = $this->decode_content($retval['content']);
					$retval['full_permalink'] = $fullPermalink;
				}
				else {
					throw new exception(__METHOD__ .": can't find 'content' section for decoding");
				}
			}
			elseif($numrows > 1) {
				throw new exception(__METHOD__ .": multiple records returned for same location (". $numrows .")");
			}
			else {
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror (". $dberror .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to meet length requirement of ". (CSBLOG_TITLE_MINLEN *2));
		}
		
		return($retval);
	}//end get_blog_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_data_by_name($blogName) {
		if(strlen($blogName) > 3) {
			$blogName = $this->gfObj->cleanString($this->create_permalink_from_title($blogName), 'sql');
			$sql = "SELECT * FROM cs_blog_table WHERE blog_name='". $blogName ."'";
			
			$numrows = $this->db->exec($sql);
			$dberror = $this->db->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$retval = $this->db->farray_fieldnames();
			}
			else {
				$this->gfObj->debug_print($sql);
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror (". $dberror .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog name (". $blogName .")");
		}
		
		return($retval);
	}//end get_blog_data_by_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_data_by_id($blogId) {
		if(is_numeric($blogId) && $blogId > 0) {
			$sql = "SELECT * FROM cs_blog_table WHERE blog_id=". $blogId;
			
			$numrows = $this->db->exec($sql);
			$dberror = $this->db->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$retval = $this->db->farray_fieldnames();
			}
			else {
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror (". $dberror .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog id (". $blogId .")");
		}
		
		return($retval);
	}//end get_blog_data_by_id()
	//-------------------------------------------------------------------------
	
	
}//end dataLayerAbstract{}
?>
