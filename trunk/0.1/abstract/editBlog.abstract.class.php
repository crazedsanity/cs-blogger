<?php

require_once(dirname(__FILE__) .'/blog.abstract.class.php');

abstract class editBlogAbstract extends blogAbstract {
	
	#protected $dbObj;
	
	protected $fsObj;
	protected $gfObj;
	
	protected $user;
	protected $uid;
	
	protected $validUsers;
	
	protected $allowDupEntries = FALSE;
	
	//-------------------------------------------------------------------------
	function __construct($blogName) {
		parent::__construct($blogName);
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_new_blog($blogName, $username, $location=NULL) {
		
		$this->get_my_uid($username, TRUE);
		
		if(is_null($location) || !strlen($location)) {
			$location = $this->defaultLocation;
		}
		
		$retval = FALSE;
		if(!$this->check_blog_exists(array('blog_name'=>$blogName))) {
			if(!is_numeric($this->uid)) {
				throw new exception(__METHOD__ .": invalid uid (". $this->uid .")");
			}
			$insertArr = array(
				'uid'		=> $this->uid,
				'blog_name'=> $blogName,
				'location'	=> $location
			);
			$sql = 'INSERT INTO cs_blog_table '. $this->gfObj->string_from_array($insertArr, 'insert', NULL, 'sql92_insert');
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				//ok, give some permissions and return the blog_id.
				$retval = $this->dbObj->lastOID();
				$this->blogId = $retval;
				
				if(!is_numeric($retval)) {
					throw new exception(__METHOD__ .": failed to retrieve last inserted record (". $retval .")");
				}
				
				$insertArr = array(
					'blog_id'	=> $retval,
					'uid'		=> $this->uid
				);
				$this->add_user_to_blog($blogName, $username);
			}
			else {
				throw new exception(__METHOD__ .": invalid number of rows returned (". $numrows .")");
			}
		}
		else {
			$retval = $this->get_id_from_name($blogName);
		}
		
		return($retval);
	}//end create_new_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_user_to_blog($blogName, $username) {
		if(!strlen($blogName) || !strlen($username)) {
			throw new exception(__METHOD__ .": missing blogName (". $blogName .") or username (". $username .")");
		}
		
		if($this->can_access_blog($blogName, $username)) {
			$retval = TRUE;
		}
		else {
			$insertArr = array(
				'blog_id'	=> $this->get_id_from_name($blogName),
				'uid'		=> $this->get_my_uid($username)
			);
			$sql = 'INSERT INTO cs_blog_access_table '. $this->gfObj->string_from_array($insertArr, 'insert', NULL, 'sql92_insert');
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = TRUE;
			}
			else {
				throw new exception(__METHOD__ .": invalid return from sql (". $numrows .")...");
			}
		}
		
		return($retval);
	}//end add_user_to_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_entry($blogId, $title, $contents, $postDate=NULL) {
		//TODO: this should create the blog file!!!
		if(!is_numeric($this->uid)) {
			throw new exception(__METHOD__ .": no uid set");
		}
		
		if(is_null($postDate)) {
			$postDate = time();
		}
		elseif(is_numeric($postDate) && strlen($postDate) == 10) {
			//do nothing!!!
		}
		else {
			//format it as a unix timestamp.
			$obj = date_create($postDate);
			$postDate = $obj->format('U');
		}
		
		$this->blogId = $blogId;
		
		$filename = $this->filename_from_title($title);
		
		$insertArr = array(
			'blog_id'			=> $blogId,
			'author_uid'		=> $this->uid,
			'filename'			=> $filename,
			'title'				=> $title,
			'post_timestamp'	=> $postDate,
			'create_date'		=> time(),
			'permalink'		=> $this->get_permalink_from_filename($filename)
		);
		$cleanStringArr = array(
			'blog_id'			=> 'integer',
			'author_uid'		=> 'integer',
			'filename'			=> 'sql92_insert',
			'title'				=> 'sql92_insert',
			'post_timestamp'	=> 'integer',
			'create_date'		=> 'integer',
			'permalink'		=> 'sql92_insert'
		);
		
		$permalinkExists = $this->permalink_exists($insertArr['permalink']);
		if($permalinkExists) {
			if($this->allow_dup_entries()) {
				//add the suffix.
				$insertArr['filename'] = $this->filename_from_title($title,$permalinkExists);
				$insertArr['permalink'] = $this->get_permalink_from_filename($insertArr['filename']);
			}
			else {
				//no dups allowed, and the entry already exists.
				throw new exception(__METHOD__ .": permalink already exists (". $permalinkExists ."), and no dups allowed");
			}
		}
		
		//create the filename; if that works, do the insert.
		$myFilename = $insertArr['filename'];
		$createFileRes = $this->fsObj->create_file($myFilename);
		if($createFileRes) {
			
			//now write the file's contents.
			$this->fsObj->openFile($myFilename, 'w+');
			$writeFile = $this->fsObj->write($contents);
			
			if($writeFile) {
				$sql = 'INSERT INTO cs_blog_entry_table '. $this->gfObj->string_from_array($insertArr, 'insert', NULL, $cleanStringArr);
				$numrows = $this->run_sql($sql);
				
				if($numrows == 1) {
					//get the last inserted blog_entry_id (yeah, sorry it's confusing).
					$retval = $this->dbObj->lastOID();
				}
				else {
					throw new exception(__METHOD__ .": record unable to be inserted (". $numrows .")");
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to write file contents (". $writeFile .") after creating it (". $createFileRes .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to create file (". $createFileRes .")");
		}
		
		return($retval);
	}//end add_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function allow_dup_entries($newValue=NULL) {
		if(!is_null($newValue) && is_bool($newValue)) {
			$this->allowDupEntries = $newValue;
		}
		
		return($this->allowDupEntries);
	}//end allow_dup_entries()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Determines if the given permalink exists, and returns the numbered prefix
	 * to use if it does, or zero if it doesn't.
	 */
	protected function permalink_exists($permalink) {
		if(!is_null($permalink) && strlen($permalink)) {
			if(is_null($this->blogId) || !is_numeric($this->blogId)) {
				throw new exception(__METHOD__ .": no blogId set (". $this->blogId .")");
			}
			if(preg_match('/\'/', $permalink)) {
				throw new exception(__METHOD__ .": permalink (". $permalink .") contains single quotes");
			}
			$sql = "SELECT * FROM cs_blog_entry_table WHERE blog_id='". $this->blogId 
					."' AND permalink='". $permalink ."'";
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				//check for more blogs...?
				$sql = "SELECT * FROM cs_blog_entry_table WHERE blog_id=". $this->blogId .
					" AND permalink like '". $permalink ."-%' ORDER BY blog_entry_id" .
					" DESC LIMIT 1";
					
				$numrows = $this->run_sql($sql);
				
				if($numrows == 1) {
					//determine the number for the next dup.
					$data = $this->dbObj->farray_fieldnames();
					$pieces=explode('-', $data['permalink']);
					
					$lastNum = $pieces[1];
					$retval = $lastNum +1;
				}
				else {
					$retval = 1;
				}
			}
			elseif($numrows == 0) {
				$retval = 0;
			}
			else {
				throw new exception(__METHOD__ .": invalid numrows returned (". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid permalink given (". $permalink .")");
		}
		
		return($retval);
	}//end permalink_exists()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function update_entry($entryId, array $changes=NULL, $contents=NULL) {
		if(isset($entryId) && is_numeric($entryId)) {
			$totalChanges = 0;
			$retval = 0;
			$this->dbObj->beginTrans();
			$myData = $this->get_entry($entryId);
			if(is_array($changes) && count($changes)) {
				$totalChanges++;
				if(isset($changes['post_timestamp'])) {
					$changes['post_timestamp'] = strtotime($changes['post_timestamp']);
				}
				if($changes['post_timestamp'] > time()) {
					//TODO: consider allowing this, and only showing logs with past timestamps (while showing users future-dated entries).
					$changes['post_timestamp'] = time();
				}
				$sql = 'UPDATE cs_blog_entry_table SET '. $this->gfObj->string_from_array($changes, 'update', NULL, 'sql92_insert')
						. ' WHERE blog_entry_id='. $entryId;
				$numrows = $this->run_sql($sql);
				if($numrows == 1) {
					$retval++;
				}
				else {
					throw new exception(__METHOD__ .": failed to update entryId=(". $entryId .") with result: (". $retval .")");
				}
			}
			if(!is_null($contents) && strlen($contents)) {
				$totalChanges++;
				if($this->fsObj->truncate_file($myData['filename'])) {
					$writeRes = $this->fsObj->write(stripslashes($contents), $myData['filename']);
				}
				else {
					throw new exception(__METHOD__ .": failed to truncate file");
				}
				$retval ++;
			}
			$this->dbObj->commitTrans();
			if($totalChanges < 1) {
				throw new exception(__METHOD__ .": no changes given");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid entryId (". $entryId .")");
		}
		
		return($retval);
	}//end update_entry()
	//-------------------------------------------------------------------------
}