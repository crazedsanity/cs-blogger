<?php

require_once(dirname(__FILE__) .'/blog.abstract.class.php');


//TODO: determine if there's a difference between permalink & filename; only use one or the other(?).

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
		
		//TODO: this should just create a new blog directory with proper permissions.
		
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
			//TODO: modify file that specifies user access to blogs.
		}
		
		return($retval);
	}//end add_user_to_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_entry($blogId, $title, $contents, $postDate=NULL) {
		
		
		//TODO: don't worry about "blogId" (should be "blogName")
		
		
		if(!is_numeric($this->uid)) {
			//TODO: is uid really required, or just a leftover from the database days?
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
		
		$filename = $this->filename_from_title($title);
		
		$permalinkExists = $this->permalink_exists($this->get_permalink_from_filename($filename));
		if($permalinkExists) {
			if($this->allow_dup_entries()) {
				//add the suffix.
				$filename = $this->filename_from_title($title,$permalinkExists);
				$insertArr['permalink'] = $this->get_permalink_from_filename($filename);
			}
			else {
				//no dups allowed, and the entry already exists.
				throw new exception(__METHOD__ .": permalink already exists (". $permalinkExists ."), and no dups allowed");
			}
		}
		
		//create the filename; if that works, do the insert.
		$createFileRes = $this->fsObj->create_file($filename);
		if($createFileRes) {
			
			//now write the file's contents.
			$this->fsObj->openFile($filename, 'w+');
			$retval = $this->fsObj->write($contents);
			
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
			
			//TODO: look through the current blog (currently "blogId") to see if that permalink/filename exists.
		}
		else {
			throw new exception(__METHOD__ .": invalid permalink given (". $permalink .")");
		}
		
		return($retval);
	}//end permalink_exists()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function update_entry($entryId, array $changes=NULL, $contents=NULL) {
		//TODO: stop referring to entryId; instead, refer to the filename.
		if(isset($entryId) && is_numeric($entryId)) {
			//TODO: use this to update the file.
			//TODO: update any indices that might be affected.
		}
		else {
			throw new exception(__METHOD__ .": invalid entryId (". $entryId .")");
		}
		
		return($retval);
	}//end update_entry()
	//-------------------------------------------------------------------------
}