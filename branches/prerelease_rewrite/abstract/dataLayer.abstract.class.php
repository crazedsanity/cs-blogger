<?php

require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');
require_once(dirname(__FILE__) .'/csblog_version.abstract.class.php');

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

abstract class dataLayerAbstract extends csblog_versionAbstract {
	
	/**  */
	protected $gfObj;
	
	/**  */
	private $isConnected=false;
	
	//-------------------------------------------------------------------------
	/**
	 * Constructor (must be called from extending class)
	 * 
	 * @param $dbType	(str) type of database to connect to (pgsql/mysql/sqlite)
	 * @param $dbParams	(array) list of connection parameters for selected db.
	 */
   	function __construct(array $dbParams=NULL) {
   		$this->connect_db($dbParams);
		$this->gfObj = new cs_globalFunctions();
		$this->gfObj->debugPrintOpt=1;
		
		//check that some required constants exist.
		if(!defined('CSBLOG_TITLE_MINLEN')) {
			define('CSBLOG_TITLE_MINLEN', 4);
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function connect_db(array $dbParams=NULL) {
		if(is_array($dbParams)) {
			$this->db = new cs_phpDB('pgsql');
			$this->db->connect($dbParams);
			
			//NOTE: if the call to "connect()" fails, it should throw an exception.
			$this->isConnected=true;
		}
		else {
			//no parameters passed.  Try using constants.
		
			$constantList = array(
				'CSBLOG_DB_HOST'		=> 'host',
				'CSBLOG_DB_PORT'		=> 'port',
				'CSBLOG_DB_DBNAME'		=> 'dbname',
				'CSBLOG_DB_USER'		=> 'user',
				'CSBLOG_DB_PASSWORD'	=> 'password'
			);
			
			$dbParams = array();
			foreach($constantList as $constant=>$index) {
				$value = '';
				if(defined($constant)) {
					$value = constant($constant);
				}
				$dbParams[$index] = $value;
			}
			$this->db = new cs_phpDB('pgsql');
			$this->db->connect($dbParams);
			
			//NOTE: if the call to "connect()" fails, it should throw an exception.
			$this->isConnected = true;
		}
	}//end connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function run_sql($sql, $exceptionOnNoRows=true) {
		//TODO: make EVERYTHING use this method, so it can automatically connect to the database.
		if($this->isConnected !== true) {
			$this->connect_db();
		}
		
		if($this->isConnected === true) {
			if(is_string($sql) && strlen($sql)) {
				
				//run the statement & check the output.
				$numrows = $this->db->exec($sql);
				$dberror = $this->db->errorMsg();
				
				if($exceptionOnNoRows === true && $numrows <= 0 && !strlen($dberror)) {
					cs_debug_backtrace(1);
					throw new exception(__METHOD__ .": no rows returned (". $numrows .")");
				}
				elseif(is_numeric($numrows) && !strlen($dberror)) {
					$retval = $numrows;
				}
				else {
					$this->gfObj->debug_print($this->db);
					cs_debug_backtrace(1);
					throw new exception(__METHOD__ .": invalid numrows (". $numrows ."), failed to run SQL... DBERROR: ". $dberror);
				}
				
			}
			else {
				throw new exception(__METHOD__ .": no sql to run");
			}
		}
		else {
			throw new exception(__METHOD__ .": database isn't connected");
		}
		
		return($retval);
	}//end run_sql()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Loads data into blank (or existing) database.
	 * 
	 * @param void			(void) none
	 * 
	 * @return exception	throws exception on error
	 * @return (int)		Number of records existing in auth table.
	 */
	public function run_setup() {
		$retval = false;
		//PostgreSQL (or MySQL?)
		$this->db->beginTrans();
		$fs = new cs_fileSystemClass(dirname(__FILE__) .'/../schema');
		$mySchema = $fs->read(CSBLOG__DBTYPE .'.schema.sql');
		
		$retval = $this->db->exec($mySchema);
		
		$internalValues = $this->get_version(true);
		foreach($internalValues as $name=>$value) {
			$sql = "INSERT INTO cs_blog_internal_data_table (internal_name, internal_value) " .
					"VALUES ('". $name ."', '". $value ."')";
			$this->run_sql($sql);
		}
		
		$retval = 1;
		$this->db->commitTrans();
		return($retval);
	}//end run_setup()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Create a user in the database.  This assumes that the blogger is in 
	 * complete control of authentication, so this doesn't work if an existing 
	 * mechanism is already in place.
	 * 
	 * @param $username		(str) username to create
	 * @param $password		(str) unencrypted password for the user
	 * 
	 * @return exception	throws exceptions on error
	 * @return (int)		UID of new user
	 */
	public function create_user($username, $password) {
		
		$existingUser = $this->get_uid($username);
		
		ob_start();
		var_dump($existingUser);
		$debugThis = ob_get_contents();
		ob_end_clean();
		
		if($existingUser === false) {
			$pass = md5($username .'-'. $password);
			$sql = 'INSERT INTO cs_authentication_table (username, passwd) VALUES ' .
					"('". $username ."', '". $password ."')";
			
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$sql = "SELECT currval('cs_authentication_table_uid_seq')";
				
				$numrows = $this->run_sql($sql);
				if($numrows == 1) {
					$data = $this->db->farray();
					$retval = $data[0];
				}
				else {
					throw new exception(__METHOD__ .": invalid numrows (". $numrows .") while retrieving last inserted uid");
				}
			}
			else {
				throw new exception(__METHOD__ .": failed insert (". $numrows ."): ");
			}
		}
		else {
			throw new exception(__METHOD__ .": user exists (". $username .")");
		}
		
		return($retval);
	}//end create_user()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieve UID for the given username (i.e. for checking dups)
	 * 
	 * @param $username		(str) username to check
	 * 
	 * @return exception	throws exception on error
	 * @return false		boolean FALSE returned if no user
	 * @return (int)		UID for existing user
	 */
	public function get_uid($username) {
		
		if(strlen($username) && is_string($username) && !is_numeric($username)) {
			$username = $this->gfObj->cleanString($username, 'email');
			$sql = "SELECT uid FROM cs_authentication_table WHERE username='". $username ."'";
			$numrows = $this->run_sql($sql, false);
			
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
	/**
	 * Creates new blog entry.
	 * 
	 * @param $blogName		(str) Display name of blog.
	 * @param $owner		(str/int) UID of owner (username converted to uid)
	 * @param $location		(str) location of blog
	 * 
	 * @return exception	throws exception on error
	 * @return (int)		Newly created blog_id
	 */
	function create_blog($blogName, $owner, $location) {
		if(!strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blogName (". $blogName .")");
		}
		elseif(!strlen($owner)) {
			throw new exception(__METHOD__ .": invalid owner (". $owner .")");
		}
		
		if(!is_numeric($owner)) {
			$username = $owner;//for later
			$owner = $this->get_uid($owner);
			if(!is_numeric($owner) || $owner < 1) {
				throw new exception(__METHOD__ .": unable to find UID for user (". $username .")");
			}
		}
		
		//attempt to get/create the location...
		$locationId = $this->get_location_id($location);
		if(!is_numeric($locationId) || $locationId < 1) {
			//TODO: should we really be creating this automatically?
			$locationId = $this->add_location($location);
		}
		
		$formattedBlogName = $this->create_permalink_from_title($blogName);
		$sql = "INSERT INTO cs_blog_table ". $this->gfObj->string_from_array(
			array(
				'blog_display_name'		=> $blogName,
				'blog_name'				=> $formattedBlogName,
				'uid'					=> $owner,
				'blog_location_id'		=> $locationId
			),
			'insert',
			NULL,
			'sql_insert'
		);
		
		$numrows = $this->run_sql($sql);
		
		if($numrows == 1) {
			//pull the blogId.
			$retval = $this->db->get_currval('cs_blog_table_blog_id_seq');
			
			if(is_numeric($retval) && $retval > 0) {
				//Initialize locals now, if it hasn't been done yet.
				if(defined('CSBLOG_SETUP_PENDING') && !$this->is_initialized()) {
					$this->initialize_locals($formattedBlogName);
				}
			}
			else {
				throw new exception(__METHOD__ .": new blog_id (". $retval .") is invalid");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows .") returned, ERROR: ". $this->db->errorMsg());
		}
		
		return($retval);
	}//end create_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Create new entry for existing blog.
	 * 
	 * @param $blogId		(int) blog_id to associate entry with.
	 * @param $authorUid	(int) UID of author
	 * @param $title		(str) Title of blog.
	 * @param $content		(str) Contents of blog.
	 * @param $optionalData	(array) optional items to specify (i.e. 
	 * 							post_timestamp)
	 * 
	 * @return exception	throws an exception on error
	 * @return (array)		Array of data, indexes explain values
	 */
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
		
		
		//lets check to see that there is NOT already a blog like this...
		$permalink = $this->create_permalink_from_title($title);
		$checkLink = $this->check_permalink($blogId, $title);
		
		if($checkLink != $permalink) {
			$permalink = $checkLink;
		}
		//set some fields that can't be specified...
		$sqlArr['permalink'] = $permalink;
		$sqlArr['content'] = $this->encode_content($content);
		
		//build the SQL statement.
		$sql = "INSERT iNTO cs_blog_entry_table ". $this->gfObj->string_from_array($sqlArr, 'insert', NULL, $cleanStringArr);
		
		//run the statement & check the output.
		$numrows = $this->run_sql($sql);
		
		if(is_numeric($numrows) && $numrows == 1) {
			$retval = array(
				'blog_entry_id'		=> $this->db->get_currval('cs_blog_entry_table_blog_entry_id_seq'),
				'full_permalink'	=> $this->blogLocation ."/". $sqlArr['permalink']
			);
		}
		else {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows ."), failed to insert data");
		}
		
		return($retval);
	}//end create_entry)
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Creates a "permalink" just from title (does NOT include blog location):
	 * lowercases, strips special characters, uses "_" in place of spaces and 
	 * special characters (NEVER creates more than one "_" in a row)
	 * 
	 * @param $title		(str) string to create permalink from.
	 * 
	 * @return exception	throws exception on error
	 * @return (string)		permalink
	 */
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
	/**
	 * Encodes content using base64
	 * 
	 * @param $content		(str) content to encode
	 * 
	 * @return (string)		encoded content.
	 */
	public function encode_content($content) {
		//make it base64 data, so it is easy to insert.
		$retval = base64_encode($content);
		return($retval);
	}//end encode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Decoded content (reverse of encode_content())
	 * 
	 * @param $content		(str) Encoded content to decode
	 * 
	 * @return (string)		Decoded content.
	 */
	public function decode_content($content) {
		$retval = base64_decode($content);
		return($retval);
	}//end decode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieve a blog entry based on the FULL permalink (location included)
	 * 
	 * @param $fullPermalink	(str) Permalink (blog location + permalink) for
	 * 								entry.
	 * 
	 * @return exception		throws exception on error
	 * @return (array)			Returns array of data, includes decoded content
	 */
	public function get_blog_entry($fullPermalink) {
		//the total permalink length should be at least double the minimum title length to include a path.
		if(strlen($fullPermalink) > (CSBLOG_TITLE_MINLEN *2)) {
			//now get the permalink separate from the title.
			$parts = explode('/', $fullPermalink);
			$permalink = $parts[(count($parts)-1)];
			$location = preg_replace('/'. $permalink .'$/', '', $fullPermalink);
			$location = preg_replace('/\/+$/', '', $location);
			
			$sql = "SELECT be.*, bl.blog_location FROM cs_blog_entry_table AS be INNER JOIN cs_blog_table AS b " .
					"ON (be.blog_id=b.blog_id) INNER JOIN cs_blog_location_table AS bl ON " .
					"(b.blog_location_id=bl.blog_location_id) " .
					"WHERE bl.blog_location='". $location ."' AND be.permalink='". $permalink ."'";
			
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
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
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to meet length requirement of ". (CSBLOG_TITLE_MINLEN *2));
		}
		
		return($retval);
	}//end get_blog_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves all data about a blog (the main entry only) by its name.
	 * 
	 * @param $blogName		(str) name of blog (displayable or proper)
	 * 
	 * @return exception	throws exceptions on error
	 * @return (array)		array of data about the blog.
	 */
	public function get_blog_data_by_name($blogName) {
		if(strlen($blogName) > 3) {
			$blogName = $this->gfObj->cleanString($this->create_permalink_from_title($blogName), 'sql');
			$sql = "SELECT b.*, bl.blog_location FROM cs_blog_table AS b INNER JOIN " .
					"cs_blog_location_table AS bl USING (blog_location_id) WHERE blog_name='". $blogName ."'";
			
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = $this->db->farray_fieldnames();
			}
			else {
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog name (". $blogName .")");
		}
		
		return($retval);
	}//end get_blog_data_by_name()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Same as get_blog_data_by_name(), but use blog_id to find it.
	 * 
	 * @param $blogId		(int) blog_id to retrieve info for.
	 * 
	 * @return exception	throws exception on error
	 * @return (array)		array of data about the blog.
	 */
	public function get_blog_data_by_id($blogId) {
		if(is_numeric($blogId) && $blogId > 0) {
			$sql = "SELECT * FROM cs_blog_table WHERE blog_id=". $blogId;
			
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = $this->db->farray_fieldnames();
			}
			else {
				throw new exception(__METHOD__ .": invalid num rows (". $numrows .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog id (". $blogId .")");
		}
		
		return($retval);
	}//end get_blog_data_by_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Updates a single entry (within a transaction)
	 * 
	 * @param $blogEntryId		(int) blog_entry_id to update.
	 * @param $updates			(array) array of field=>value updates
	 * 
	 * @return exception		throws exception on error.
	 * @return true				returns boolean TRUE on success.
	 */
	public function update_entry($blogEntryId, array $updates) {
		if(is_numeric($blogEntryId) && $blogEntryId > 0 && is_array($updates) && count($updates)) {
			$validFields = array(
				'post_timestamp'	=> 'datetime',
				'content'			=> 'sql92_insert'
			);
			$updateThis = array_intersect_key($updates, $validFields);
			if(is_array($updateThis) && count($updateThis)) {
				
				//encode teh content as before.
				if(isset($updateThis['content'])) {
					$updateThis['content'] = $this->encode_content($updateThis['content']);
				}
				
				$sql = "UPDATE cs_blog_entry_table SET ". $this->gfObj->string_from_array($updateThis, 'update', NULL, $validFields)
					." WHERE blog_entry_id=". $blogEntryId;
				
				$this->db->beginTrans();
				$numrows = $this->run_sql($sql);
				
				if($numrows == 1) {
					$this->db->commitTrans();
					$retval = true;
				}
				else {
					$this->db->abortTrans();
					throw new exception(__METHOD__ .": update failed, numrows=(". $numrows ."), dberror");
				}
			}
			else {
				throw new exception(__METHOD__ .": no valid fields in updates array");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid data passed");
		}
		
		return($retval);
	}//end update_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Determines if there is one or more matching permalinks for the given 
	 * blog; duplicates are given a suffix of "-N" (where "N" is the number of
	 * matching entries; first dup is given "-1", and so on).
	 * 
	 * @param $blogId		(int) blog_id for the permalink 
	 * @param $permaLink	(str) permalink to check
	 * 
	 * @return exception	thrown on error.
	 * @return 
	 */
	public function check_permalink($blogId, $permalink) {
		if(is_string($permalink) && strlen($permalink) >= CSBLOG_TITLE_MINLEN && is_numeric($blogId) && $blogId > 0) {
			#if($permalink == $this->create_permalink_from_title($permalink)) {
			$permalink = $this->create_permalink_from_title($permalink);
			$sql = "SELECT * FROM cs_blog_entry_table WHERE blog_id=". $blogId 
				." AND permalink='". $permalink ."' OR permalink LIKE '". $permalink ."-%'";
			
			$numrows = $this->run_sql($sql, false);
			
			if($numrows >= 0) {
				if($numrows >= 1) {
					//got a record, give 'em the data back.
					$retval = $permalink ."-". $numrows;
				}
				elseif($numrows == 0) {
					$retval = $permalink;
				}
				else {
					throw new exception(__METHOD__ .": unknown error, numrows=(". $numrows ."), dberror");
				}
			}
			else {
				throw new exception(__METHOD__ .": invalid numrows (". $numrows .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid permalink (". $permalink .") or blog_id (". $blogId .")");
		}
		
		return($retval);
	}//end check_permalink()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_location($location) {
		if(is_string($location) && strlen($location) > 3) {
			$location = $this->gfObj->cleanString($location, "sql_insert");
			$numrows = $this->run_sql("INSERT INTO cs_blog_location_table (blog_location) " .
					"VALUES ('". $location ."')");
			
			if($numrows == 1) {
				//okay, retrieve the id inserted.
				$retval = $this->db->get_currval('cs_blog_location_table_blog_location_id_seq');
			}
			else {
				throw new exception(__METHOD__ .": failed to create location (". $location ."), " .
						"numrows=(". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid location (". $location .")");
		}
		
		return($retval);
	}//end add_location()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_location_id($location) {
		if(is_string($location) && strlen($location) > 3) {
			$location = $this->gfObj->cleanString($location, "sql_insert");
			$sql = "SELECT blog_location_id FROM cs_blog_location_table " .
					"WHERE blog_location='". $location ."'";
			$numrows = $this->run_sql($sql, false);
			
			if($numrows == 0) {
				$retval = false;
			}
			elseif($numrows == 1) {
				$retval = $this->db->farray();
				$retval = $retval[0];
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve location (". $location ."), " .
						"invalid numrows (". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid location (". $location .")");
		}
		
		return($retval);
	}//end get_location_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_entries(array $criteria, $orderBy, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
		$sql = "SELECT be.*, bl.blog_location FROM cs_blog_entry_table AS be INNER JOIN " .
				"cs_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN " .
				"cs_blog_location_table AS bl ON (b.blog_location_id=bl.blog_location_id) WHERE ";
		
		//add stuff to the SQL...
		foreach($criteria as $field=>$value) {
			if(!preg_match('/^[a-z]{1,}\./', $field)) {
				unset($criteria[$field]);
				$field = "be.". $field;
				$criteria[$field] = $value;
			}
		}
		$sql .= $this->gfObj->string_from_array($criteria, 'select', NULL, 'sql');
		
		if(strlen($orderBy)) {
			$sql .= " ORDER BY ". $orderBy;
		}
		
		if(is_numeric($limit) && $limit > 0) {
			$sql .= " LIMIT ". $limit;
		}
		if(is_numeric($offset) && $limit > 0) {
			$sql .= " OFFSET ". $offset;
		}
		
		$numrows = $this->run_sql($sql);
		
		$retval = $this->db->farray_fieldnames('blog_entry_id');
		
		return($retval);
	}//end get_blog_entries()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blogs(array $criteria, $orderBy, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
		$sql = "SELECT b.*, bl.blog_location FROM cs_blog_table AS b INNER JOIN " .
				"cs_blog_location_table AS bl ON (b.blog_location_id=bl.blog_location_id) WHERE ";
		
		//add stuff to the SQL...
		foreach($criteria as $field=>$value) {
			if(!preg_match('/^[a-z]{1,}\./', $field)) {
				unset($criteria[$field]);
				$field = "b.". $field;
				$criteria[$field] = $value;
			}
		}
		$sql .= $this->gfObj->string_from_array($criteria, 'select', NULL, 'sql');
		
		if(strlen($orderBy)) {
			$sql .= " ORDER BY ". $orderBy;
		}
		
		if(is_numeric($limit) && $limit > 0) {
			$sql .= " LIMIT ". $limit;
		}
		if(is_numeric($offset) && $limit > 0) {
			$sql .= " OFFSET ". $offset;
		}
		
		$numrows = $this->run_sql($sql);
		
		$retval = $this->db->farray_fieldnames('blog_id');
		
		return($retval);
	}//end get_blogs()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blog() {
		$criteria = array(
			'blog_id'	=> $this->blogId
		);
		$retval = $this->get_blog_entries($criteria, 'post_timestamp DESC', 1);
		return($retval);
	}//end get_most_recent_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_recent_blogs($limit=5, $offset=0) {
		if(is_numeric($limit) && $limit > 0) {
			if(is_numeric($offset) && $offset >= 0) {
				$retval = $this->get_blog_entries(array('blog_id'=>$this->blogId), 'post_timestamp DESC', $limit, $offset);
			}
			else {
				throw new exception(__METHOD__ .": invalid offset (". $offset .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid limit (". $limit .")");
		}
		
		return($retval);
	}//end get_recent_blogs()
	//-------------------------------------------------------------------------
	
	
}//end dataLayerAbstract{}
?>
