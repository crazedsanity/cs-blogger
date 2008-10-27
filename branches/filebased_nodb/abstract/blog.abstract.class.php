<?php


abstract class blogAbstract {
	
	#protected $dbObj;
	
	protected $fsObj;
	protected $gfObj;
	protected $dbObj;
	
	protected $user;
	protected $uid;
	protected $blogId;
	protected $blogName;
	
	protected $validBlogs;
	
	protected $defaultLocation = '/blog';
	
	protected $isInitialized = FALSE;
	
	//-------------------------------------------------------------------------
	function __construct($blogName) {
		$this->_initialize_locals();
		if(!is_null($blogName) && strlen($blogName)) {
			$this->blogName = $blogName;
			$this->fsObj->cd($blogName);
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": no blogName set (". $blogName .")");
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function _initialize_locals() {
		if($this->isInitialized !== TRUE) {
			$this->fsObj = new cs_fileSystemClass(CS_BLOGRWDIR);
			$this->gfObj = new cs_globalFunctions;
			$this->dbObj = new cs_phpDB('sqlite');
			$parameters = array(
				'dbname'	=> CS_BLOGDBNAME,
				'rwDir'		=> CS_SQLITEDBDIR
			);
			$this->dbObj->connect($parameters);
			$this->isInitialized = TRUE;
		}
	}//end _initialize_locals()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function run_sql($sql) {
		$numrows = $this->dbObj->exec($sql);
		$dberror = $this->dbObj->errorMsg();
		
		if(strlen($dberror) || !is_numeric($numrows) || $numrows < 0) {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows .") or database error: ". $dberror ."<BR>\nSQL: ". $sql);
		}
		else {
			$retval = $numrows;
		}
		
		return($retval);
	}//end run_sql()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_blog_exists(array $criteria) {
		$sql = "SELECT * FROM cs_blog_table WHERE "
			. $this->gfObj->string_from_array($criteria, 'select', NULL, 'sql');
		$numrows = $this->run_sql($sql);
		
		$retval = TRUE;
		if($numrows == 0) {
			$retval = FALSE;
		}
		
		return($retval);
	}//end check_blog_exists()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_id_from_name($blogName) {
		$sql = "SELECT * FROM cs_blog_table WHERE blog_name='". $blogName ."'";
		$numrows = $this->run_sql($sql);
		
		if($numrows == 1) {
			$data = $this->dbObj->farray_fieldnames();
			$retval = $data['blog_id'];
		}
		else {
			throw new exception(__METHOD__ .": failed to record for (". $blogName .")");
		}
		
		return($retval);
	}//end get_id_from_name()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function can_access_blog($blogName, $username=NULL) {
		if(!is_null($username) && strlen($username)) {
			$uid = $username;
			if(strlen($username) && !is_numeric($username)) {
				$uid = $this->get_my_uid($username);
			}
			
			$sql = "SELECT * FROM cs_blog_access_table WHERE uid=". $uid 
					." AND blog_id=". $this->get_id_from_name($blogName);
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = TRUE;
			}
			elseif($numrows > 1) {
				throw new exception(__METHOD__ .": multiple records (". $numrows .") returned " .
						"for blogName=(". $blogName .") and username=(". $username .")");
			}
			else {
				$retval = FALSE;
			}
		}
		else {
			$retval = FALSE;
		}
		
		return($retval);
	}//end can_access_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_age_hype($timestamp, $addBrackets=FALSE) {
		$age = time() - $timestamp;
		switch($age) {
			case ($age <= 1800): {
				$extraText = '<font color="red"><b>Ink\'s still WET!</b></font>';
			} break;
			
			case ($age <= 3600): {
				//modified less than an hour ago!
				$extraText = '<font color="red"><b>Hot off the press!</b></font>';
			} break;
			
			case ($age <= 86400): {
				//modified less than 24 hours ago.
				$extraText = '<font color="red"><b>New!</b></font>';
			} break;
			
			case ($age <= 604800): {
				//modified this week.
				$extraText = '<font color="red">Less than a week old</font>';
			} break;
			
			case ($age <= 2592000): {
				//modified this month.
				$extraText = '<b>Updated this month</b>';
			} break;
			
			case ($age <= 5184000): {
				//modified in the last 2 months
				$extraText = '<b>Updated last month</b>';
			} break;
			
			case ($age <= 7776000): {
				$extraText = '<i>Updated 3 months ago</i>';
			} break;
			
			case ($age <= 10368000): {
				$extraText = '<i>Updated 4 months ago</i>';
			} break;
			
			case ($age <= 12960000): {
				$extraText = '<i>Updated 5 months ago</i>';
			} break;
			
			case ($age <= 15552000): {
				$extraText = '<i>Updated in the last 6 months</i>';
			} break;
			
			default: {
				$extraText  = '<i>pretty old</i>';
			}
		}
		
		if(strlen($extraText) && $addBrackets) {
			$extraText = '['. $extraText .']';
		}
		
		return($extraText);
	}//end get_age_hype()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_permalink_from_filename($filename) {
		if(!is_null($filename) && strlen($filename) >= 10) {
			if(preg_match('/\//', $filename)) {
				$pieces = explode('/', $filename);
				$username = $pieces[0];
				$filename = $pieces[1];
			}
			
			$dataPieces = explode('.', $filename);
			$retval = $this->blogName .'/'. $dataPieces[0];
		}
		else {
			throw new exception(__METHOD__ .": invalid filename given (". $filename .")");
		}
		
		return($retval);
	}//end get_permalink_from_filename()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function fix_db_array_indexes(array $a) {
		$retval = array();
		foreach($a as $index=>$value) {
			$myIndex = $index;
			if(preg_match('/\./', $index)) {
				$pieces = explode('.', $index);
				if(count($pieces) > 2) {
					throw new exception(__METHOD__ .": too many pieces in (". $index .")");
				}
				$myIndex = $pieces[1];
			}
			$retval[$myIndex] = $value;
		}
		return($retval);
	}//end fix_db_array_indexes()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------\
	protected function filename_from_title($title, $suffix=NULL) {
		//now build the real filename.
		$filename = strtolower($title);
		
		$filename = preg_replace('/!/', '', $filename);
		$filename = preg_replace('/&\+/', '-', $filename);
		$filename = preg_replace('/\'/', '', $filename);
		$filename = preg_replace("/[^a-zA-Z0-9_]/", "_", $filename);
		
		if(!strlen($filename)) {
			throw new exception(__METHOD__ .": invalid filename (". $filename .") from title=(". $title .")");
		}
		
		//consolidate multiple underscores... (" . . ." becomes "______", after this becomes just "_")
		$filename = preg_replace('/__*/', '_', $filename);
		
		//now add an extension.
		if(!is_null($suffix)) {
			$filename .= "-". $suffix;
		}
		$filename .= '.blog';
		
		return($filename);
	}//end filename_from_title()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_blogs_for_location($location=NULL) {
		$sql = "SELECT * FROM cs_blog_table";
		if(!is_null($location) && strlen($location)) {
			$sql .= " WHERE location = '". $location ."'";
		}
		$sql .= " ORDER BY blog_id";
		$numrows = $this->run_sql($sql);
		
		if($numrows > 0) {
			$data = $this->dbObj->farray_nvp('blog_id', 'blog_name');
			$this->validBlogs = $data;
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve any blogs for location (". $location .")");
		}
		return($data);
	}//end get_blogs_for_location()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_blog_data(array $criteria) {
		$sql = "SELECT a.username, b.* FROM cs_blog_table AS b INNER JOIN " .
				"cs_authentication_table AS a ON (a.uid=b.uid) WHERE ";
		$sql .= $this->gfObj->string_from_array($criteria, 'select', NULL, 'sql');
		
		$numrows = $this->run_sql($sql);
		
		if($numrows >= 1) {
			$retval = $this->fix_db_array_indexes($this->dbObj->farray_fieldnames('b.blog_name', NULL, FALSE));
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve data, SQL::: ". $sql);
		}
		
		return($retval);
	}//end get_blog_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function set_internal_var($varName, $value=NULL) {
		if(property_exists($this, $varName)) {
			$this->$varName = $value;
		}
		else {
			throw new exception(__METHOD__ .": property (". $varName .") doesn't exist, value given was (". $value .")");
		}
	}//end set_internal_var()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_entry($nameOrId) {
		$retval = NULL;
		
		if(is_numeric($nameOrId)) {
			$criteria = "blog_entry_id=". $nameOrId;
		}
		elseif(is_string($nameOrId) && strlen($nameOrId)) {
			if(!is_numeric($this->blogId)) {
				throw new exception(__METHOD__ .": invalid blogId (". $this->blogId .")");
			}
			$criteria = "be.blog_id=". $this->blogId ." AND permalink='". $this->blogName .'/'. $nameOrId ."'";
		}
		else {
			throw new exception(__METHOD__ .": invalid criteria (". $nameOrId .")");
		}
		$sql = "SELECT be.*, b.blog_name, a.username FROM cs_blog_entry_table AS be INNER JOIN " .
				"cs_blog_table AS b ON (b.blog_id=be.blog_id) INNER JOIN " .
				"cs_authentication_table AS a ON (a.uid=b.uid) WHERE ". 
				$criteria ." ORDER BY post_timestamp " .
				"DESC LIMIT 1";
		$numrows = $this->run_sql($sql);
		
		if($numrows == 1) {
			$retval = $this->fix_db_array_indexes($this->dbObj->farray_fieldnames());
			$retval['formatted_username'] = ucwords($retval['username']);
			$retval['formatted_post_timestamp'] = strftime('%Y/%m/%d %I:%M %p', $retval['post_timestamp']);
			$retval['formatted_post_timestamp_time'] = strftime('%I:%M %p', $retval['post_timestamp']);
			$retval['contents'] = $this->fsObj->read($retval['filename']);
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve data for entry (". $nameOrId ."), numrows=(". $numrows .")<BR>\nSQL::: ". $sql);
		}
		
		return($retval);
	}//end find_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_my_uid($uidOrUsername=NULL, $setInternal=FALSE) {
		if(!is_null($uidOrUsername) && strlen($uidOrUsername)) {
			if(is_numeric($uidOrUsername)) {
				$criteria = "uid=". $uidOrUsername;
			}
			elseif(is_string($uidOrUsername) && !is_numeric($uidOrUsername) && strlen($uidOrUsername)) {
				$criteria = "username='". $uidOrUsername ."'";
			}
			else {
				cs_debug_backtrace(1);
				throw new exception(__METHOD__ .": unknown data in uid or username (". $uidOrUsername .")");
			}
			$sql = "SELECT * FROM cs_authentication_table WHERE ". $criteria;
			$retval = $this->run_sql($sql);
			
			if($retval == 1) {
				$data = $this->dbObj->farray_fieldnames();
				$retval = $data['uid'];
				if($setInternal) {
					$this->uid = $retval;
				}
				
				if(!is_numeric($retval)) {
					cs_debug_backtrace(1);
					throw new exception(__METHOD__ .": couldn't find uid in data array::: ". $this->gfObj->debug_print($data,0),1);
				}
			}
			else {
				cs_debug_backtrace(1);
				throw new exception(__METHOD__ .": failed to retrieve uid for (". $criteria .")");
			}
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": no uid or username given (". $uidOrUsername .")");
		}
		
		return($retval);
	}//end get_my_uid()
	//-------------------------------------------------------------------------
}