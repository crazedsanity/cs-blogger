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
   	function __construct(cs_phpDB $db) {
		
		$this->set_version_file_location(dirname(__FILE__) . '/../VERSION');
		$this->gfObj = new cs_globalFunctions();
		
		//check that some required constants exist.
		if(!defined('CSBLOG_TITLE_MINLEN')) {
			define('CSBLOG_TITLE_MINLEN', 4);
		}
		
		$this->db = $db;
	}//end __construct()
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
		$this->db->beginTrans();
		
		$this->db->run_sql_file(dirname(__FILE__) .'/../schema');
		
		// TODO: actually evaluate the result of running the schema file.
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
			$sql = 'INSERT INTO cs_authentication_table (username, passwd) VALUES (:user, :pass)';
			
			try {
				$retval = $this->db->run_insert(
						$sql, 
						array('user'=>$username, 'pass'=>$encryptedPass),
						'cs_authentication_table_uid_seq'
				);
			}
			catch(Exception $ex) {
				throw new exception(__METHOD__ .": failed to create new user::: ". $ex->getMessage());
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
			$sql = "SELECT * FROM cs_authentication_table WHERE username=:user";
			
			try {
				$numrows = $this->run_sql($sql, array('user'=>$username));
			}
			catch(Exception $ex) {
				if($numrows == 1) {
					$retval = $this->db->get_single_record();
				}
				elseif($numrows == 0) {
					throw new exception(__METHOD__ .": invalid user (". $username .")");
				}
				else {
					throw new exception(__METHOD__ .": invalid numrows (". $numrows .")");
				}	
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
		$sql = 'INSERT INTO csblog_blog_table (blog_display_name, blog_name, '
			. 'uid, location_id) VALUES (:display, :name, :uid, :location)';
		$params = array(
			'display'		=> $blogName,
			'name'			=> $formattedBlogName,
			'uid'			=> $owner,
			'location_id'	=> $locationId
		);
		
		try {
			$retval = $this->run_insert($sql, $params, 'csblog_blog_table_blog_id_seq');
			$this->initialize_locals($formattedBlogName);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to create blog::: ". $e->getMessage());
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
	public function create_entry($blogId, $authorUid, $title, $content, $postTimestamp, $isDraft=False) {
		
		if(is_string($title) && strlen($title) > constant('CSBLOG_TITLE_MINLEN')) {
			
		}
		else {
			throw new exception(__METHOD__ .": title is not ");
		}
		
		$sql = 'INSERT INTO csblog_entry_table'
			.' (blog_id, author_uid, title, content, permalink, post_timestamp, is_draft) '
			.' VALUES '
			.'(:blogId,:uid, :title, :content, :permalink, : postTimestamp, :isDraft)';
		$params = array(
			'blogId'		=> $blogId,
			'uid'			=> $authorUid,
			'title'			=> $title,
			'content'		=> $content,
			'postTimestamp'	=> $postTimestamp,
			'isDraft'		=> $isDraft
		);
		
		//lets check to see that there is NOT already a blog like this...
		$permalink = $this->create_permalink_from_title($title);
		$checkLink = $this->check_permalink($blogId, $title);
		
		if($checkLink != $permalink) {
			$permalink = $checkLink;
		}
		//set some fields that can't be specified...
		$params['permalink'] = $permalink;
		$params['content'] = $this->encode_content($content);
		
		try {
			$newId = $this->db->run_insert($sql, $params, 'csblog_entry_table_entry_id_seq');
			$retval = array(
				'entry_id'		=> $newId,
				'full_permalink'=> $this->get_full_permalink($permalink)
			);
			$this->update_blog_last_post_timestamps();
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to create new entry::: ". $e->getMessage());
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
		//the total permalink length should be at least double the minimum title length to include a path.
		if(strlen($fullPermalink) > (CSBLOG_TITLE_MINLEN *2)) {
			
			$sql = "SELECT be.*, bl.location, b.blog_display_name, " .
				"be.post_timestamp::date as date_short, " .
				"b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN " .
				"csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN " .
				"csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN " .
				"cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE 
					(bl.location=:location OR :location IS NULL) 
					AND (b.blog_name=:blogName OR :blogName IS NULL) 
					AND (be.permalink=:permalink OR :permalink IS NULL) 
					AND (be.author_uid = :authorUid OR :authorUid IS NULL)";
			
			try {
				//now get the permalink separate from the title.
				$criteria = $this->parse_full_permalink($fullPermalink);

				$numRows = $this->db->run_query($sql, $criteria);
				
				if($numRows == 1) {
					$retval = $this->db->get_single_record();
				}
				elseif($numRows > 1) {
					throw new exception(__METHOD__ .': too many records returned ('. $numRows .')');
				}
				else {
					throw new exception(__METHOD__ .': no records found');
				}
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .': failed to retrieve blog entry::: '. $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to meet length requirement of ". (CSBLOG_TITLE_MINLEN *2));
		}
		
		return($retval);
	}//end get_blog_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/*
	 * SELECT be.*, bl.location, b.blog_display_name, be.post_timestamp::date as date_short, b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE bl.location='/blog' AND b.blog_name='slaughter' AND be.permalink='what_i_want-1' ORDER BY be.entry_id

SELECT be.*, bl.location, b.blog_display_name, be.post_timestamp::date as date_short, b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE bl.location='/blog' AND b.blog_name='slaughter' AND be.permalink='what_i_want-1' ORDER BY be.entry_id

SELECT be.*, bl.location, b.blog_display_name, be.post_timestamp::date as date_short, b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE bl.location='/blog' AND b.blog_name='slaughter' AND be.permalink='what_i_want-1' ORDER BY be.entry_id


	 */
	public function get_blog_entries(array $criteria, $orderBy, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
			
			$sql = "SELECT be.*, bl.location, b.blog_display_name, " .
				"be.post_timestamp::date as date_short, " .
				"b.blog_name, a.username FROM csblog_entry_table AS be INNER JOIN " .
				"csblog_blog_table AS b ON (be.blog_id=b.blog_id) INNER JOIN " .
				"csblog_location_table AS bl ON (b.location_id=bl.location_id) INNER JOIN " .
				"cs_authentication_table AS a ON (a.uid=be.author_uid) WHERE 
					(bl.location=:location OR :location IS NULL) 
					AND (b.blog_name=:blogName OR :blogName IS NULL) 
					AND (be.permalink=:permalink OR :permalink IS NULL) 
					AND (be.author_uid = :authorUid OR :authorUid IS NULL)";
		
		if(strlen($orderBy)) {
			$sql .= " ORDER BY ". $orderBy;
		}
		
		if(is_numeric($limit) && $limit > 0) {
			$sql .= " LIMIT ". $limit;
		}
		if(is_numeric($offset) && $limit > 0) {
			$sql .= " OFFSET ". $offset;
		}
		
		
		
		$numrows = $this->db->run_query($sql, $criteria);
		
		$retval = $this->db->farray_fieldnames('entry_id');
		
		
		/// TODO: Fix from here
		
		$keys = array_keys($retval);
		$tempData = $retval[$keys[0]];
		$this->blogLocation = $tempData['location'];
		$this->blogName = $tempData['blog_name'];
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
