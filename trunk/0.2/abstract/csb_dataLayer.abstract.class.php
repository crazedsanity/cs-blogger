<?php



abstract class csb_dataLayerAbstract extends cs_versionAbstract {
	
	/**  */
	protected $gfObj;
	
	/**  */
	private $isConnected=false;
	
	/**  */
	protected $dbParams;
	
	const DBTYPE='pgsql';
	
	//-------------------------------------------------------------------------
	/**
	 * Constructor (must be called from extending class)
	 * 
	 * @param $dbType	(str) type of database to connect to (pgsql/mysql/sqlite)
	 * @param $dbParams	(array) list of connection parameters for selected db.
	 */
   	function __construct(array $dbParams=NULL) {
		$this->set_version_file_location(dirname(__FILE__) . '/../VERSION');
		$this->gfObj = new cs_globalFunctions();
		
		//check that some required constants exist.
		if(!defined('CSBLOG_TITLE_MINLEN')) {
			define('CSBLOG_TITLE_MINLEN', 4);
		}
		
		$this->dbParams = $dbParams;
   		$this->connect_db();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function connect_db() {
		if($this->isConnected === false) {
			if(is_array($this->dbParams)) {
				$this->db = new cs_phpDB('pgsql');
				try {
					$this->db->connect($this->dbParams);
				}
				catch(exception $e) {
					throw new exception(__METHOD__ .": failed to connect to database, DETAILS: ". $e->getMessage());
				}
				
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
					else {
						if($index != 'password') {
							throw new exception(__METHOD__ .": missing one or more constants for database connectivity (". $constant .")");
						}
					}
					$dbParams[$index] = $value;
				}
				$this->db = new cs_phpDB('pgsql');
				try {
					$this->db->connect($dbParams);
				}
				catch(exception $e) {
					throw new exception(__METHOD__ .": failed to connect to database using constants, DETAILS: ". $e->getMessage());
				}
				
				//NOTE: if the call to "connect()" fails, it should throw an exception.
				$this->isConnected = true;
			}
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
					throw new exception(__METHOD__ .": no rows returned (". $numrows .")");
				}
				elseif(is_numeric($numrows) && !strlen($dberror)) {
					$retval = $numrows;
				}
				else {
					throw new exception(__METHOD__ .": invalid numrows (". $numrows ."), failed to run SQL... " .
							"DBERROR: ". $dberror ."<BR>\nSQL::: ". $sql);
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
		$this->connect_db();
		$this->db->beginTrans();
		$fs = new cs_fileSystem(dirname(__FILE__) .'/../schema');
		$mySchema = $fs->read(self::DBTYPE .'.schema.sql');
		
		$retval = $this->db->exec($mySchema);
		
		#$internalValues = $this->get_version(true);
		#foreach($internalValues as $name=>$value) {
		#	$sql = "INSERT INTO csblog_internal_data_table (internal_name, internal_value) " .
		#			"VALUES ('". $name ."', '". $value ."')";
		#	$this->run_sql($sql);
		#}
		
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
		
		$username = $this->gfObj->cleanString($username, 'email');
		if($username != func_get_arg(0)) {
			throw new exception(__METHOD__ .": username contained invalid characters (". $username ." != ". func_get_arg(0) .")");
		}
		
		$existingUser = $this->get_uid($username);
		
		if($existingUser === false) {
			$encryptedPass = md5($username .'-'. $password);
			$sql = 'INSERT INTO cs_authentication_table (username, passwd) VALUES ' .
					"('". $username ."', '". $encryptedPass ."')";
			
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
	public function get_user($username) {
		
		if(strlen($username) && is_string($username) && !is_numeric($username)) {
			$username = $this->gfObj->cleanString($username, 'email');
			if($username != func_get_arg(0)) {
				throw new exception(__METHOD__ .": username contained invalid characters (". $username ." != ". func_get_arg(0) .")");
			}
			$sql = "SELECT * FROM cs_authentication_table WHERE username='". $username ."'";
			$numrows = $this->run_sql($sql, false);
			
			if($numrows == 1) {
				$retval = $this->db->farray_fieldnames();
			}
			elseif($numrows == 0) {
				throw new exception(__METHOD__ .": invalid user (". $username .")");
			}
			else {
				throw new exception(__METHOD__ .": invalid numrows (". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid data for username (". $username .")");
		}
		
		return($retval);
	}//end get_user()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_uid($username) {
		try {
			$data = $this->get_user($username);
		}
		catch(exception $e) {
			$data = false;
		}
		if(is_bool($data) && $data === false) {
			$retval = $data;
		}
		else {
			if(is_array($data) && isset($data['uid']) && is_numeric($data['uid'])) {
				$retval = $data['uid'];
			}
			else {
				throw new exception(__METHOD__ .": failed to locate uid column in return DATA::: ". $this->gfObj->debug_print($data,0));
			}
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
		$loc = new csb_location($this->dbParams);
		$locationId = $loc->get_location_id($location);
		if(!is_numeric($locationId) || $locationId < 1) {
			//TODO: should we really be creating this automatically?
			$locationId = $loc->add_location($location);
		}
		
		$formattedBlogName = $this->create_permalink_from_title($blogName);
		$sql = "INSERT INTO csblog_blog_table ". $this->gfObj->string_from_array(
			array(
				'blog_display_name'		=> $blogName,
				'blog_name'				=> $formattedBlogName,
				'uid'					=> $owner,
				'location_id'			=> $locationId
			),
			'insert',
			NULL,
			'sql_insert'
		);
		
		$numrows = $this->run_sql($sql);
		
		if($numrows == 1) {
			//pull the blogId.
			$retval = $this->db->get_currval('csblog_blog_table_blog_id_seq');
			
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
			'title'			=> "sql",
			'content'		=> "sql",
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
			
			//Valid optional fields are defined here.
			$validOptionalFields = array(
				'post_timestamp'	=> 'datetime',
				'is_draft'			=> 'boolean'
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
		$sql = "INSERT INTO csblog_entry_table ". $this->gfObj->string_from_array($sqlArr, 'insert', NULL, $cleanStringArr);
		
		//run the statement & check the output.
		$numrows = $this->run_sql($sql);
		
		if(is_numeric($numrows) && $numrows == 1) {
			$blogData = $this->get_blog_data_by_id($blogId);
			$retval = array(
				'entry_id'		=> $this->db->get_currval('csblog_entry_table_entry_id_seq'),
				'full_permalink'	=> $this->get_full_permalink($sqlArr['permalink'])
			);
			
			//one final thing: update the main blog table with the newest post_timestamp.
			$this->update_blog_last_post_timestamps();
		}
		else {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows ."), failed to insert data");
		}
		
		return($retval);
	}//end create_entry)
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
		//TODO: have this use get_blog_entries()
		//the total permalink length should be at least double the minimum title length to include a path.
		if(strlen($fullPermalink) > (CSBLOG_TITLE_MINLEN *2)) {
			//now get the permalink separate from the title.
			$parts = $this->parse_full_permalink($fullPermalink);
			$permalink = $parts['permalink'];
			$blogName = $parts['blogName'];
			$location = $parts['location'];
			
			$criteria = array('bl.location'=>$location, 'b.blog_name'=>$blogName,'be.permalink'=>$permalink);
			$data = $this->get_blog_entries($criteria,'be.entry_id');
			
			if(count($data) == 1) {
				$keys = array_keys($data);
				$retval = $data[$keys[0]];
			}
			elseif(count($data) > 1) {
				throw new exception(__METHOD__ .": multiple records returned for same location (". count($data) .")");
			}
			else {
				throw new exception(__METHOD__ .": invalid number of records (". count($data) .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to meet length requirement of ". (CSBLOG_TITLE_MINLEN *2));
		}
		
		return($retval);
	}//end get_blog_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_entries(array $criteria, $orderBy, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
		$sql = "SELECT be.*, bl.location, b.blog_display_name, be.post_timestamp::date as date_short, " .
				"b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN " .
				"csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN " .
				"csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN " .
				"cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE ";
		
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
		
		$retval = $this->db->farray_fieldnames('entry_id', true, false);
		foreach($retval as $entryId=>$data) {
			$retval[$entryId]['age_hype'] = $this->get_age_hype($data['post_timestamp']);
			$retval[$entryId]['content'] = $this->decode_content($data['content']);
			$retval[$entryId]['full_permalink'] = $this->get_full_permalink($data['permalink']);
			
			//make a formatted post_timestamp index.
			$retval[$entryId]['formatted_post_timestamp'] = 
					strftime('%A, %B %d, %Y %I:%M %p', strtotime($data['post_timestamp']));
			
			//format the username...
			$retval[$entryId]['formatted_author_name'] = ucwords($data['username']);
			
			//make "is_draft" a real boolean.
			$retval[$entryId]['is_draft'] = $this->gfObj->interpret_bool($data['is_draft']);
		}
		
		return($retval);
	}//end get_blog_entries()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blogs(array $criteria, $orderBy=NULL, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
		$sql = "SELECT b.*, bl.location FROM csblog_blog_table AS b INNER JOIN " .
				"csblog_location_table AS bl ON (b.location_id=bl.location_id) WHERE ";
		
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
		else {
			$sql .= " ORDER BY b.blog_id";
		}
		
		if(is_numeric($limit) && $limit > 0) {
			$sql .= " LIMIT ". $limit;
		}
		if(is_numeric($offset) && $limit > 0) {
			$sql .= " OFFSET ". $offset;
		}
		
		$numrows = $this->run_sql($sql);
		
		$retval = $this->db->farray_fieldnames('blog_id', true, false);
		
		return($retval);
	}//end get_blogs()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function update_blog_data(array $updates) {
		$sql = "UPDATE csblog_blog_table SET ". 
				$this->gfObj->string_from_array($updates, 'update', null, 'sql') .
				" WHERE blog_id=". $this->blogId;
		
		try {
			$this->db->beginTrans();
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = $numrows;
				$this->db->commitTrans();
			}
			else {
				throw new exception(__METHOD__ .": updated invalid number of rows (". $numrows .") - transaction aborted");
			}
		}
		catch(exception $e) {
			$this->db->rollbackTrans();
			throw new exception(__METHOD__ .": failed to update blog (". $numrows .")");
		}
		
		return($retval);
	}//end update_blog_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected  function update_blog_last_post_timestamps() {
		$sql = "update csblog_blog_table AS b SET last_post_timestamp=" .
				"(SELECT post_timestamp FROM csblog_entry_table WHERE " .
				"blog_id=b.blog_id ORDER BY post_timestamp DESC limit 1)";
		
		try {
			$retval = $this->run_sql($sql);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to update last_post_timestamp for blogs, DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end update_blog_last_post_timestamps()
	//-------------------------------------------------------------------------
	
	
}//end dataLayerAbstract{}
?>
