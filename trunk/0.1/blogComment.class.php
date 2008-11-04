<?php

require_once(dirname(__FILE__) .'/blog.class.php');

class blogComment extends blog {
	
	private $location;
	private $templateFile;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName, array $sectionArr) {
		parent::__construct($blogName);
		$this->sectionArr = $sectionArr;
		
	}//end __construct()	
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Create comment directory for a given blog; the number returned indicates 
	 * a number that is safe to use as the suffix for the next comment.
	 */
	private function make_comment_dir($dirName) {
		
		$lsData = $this->fsObj->ls();
		if($lsData[$dirName]) {
			$this->gfObj->debug_print(__METHOD__ .": myDir exists",1);
			#$retval = FALSE;
			
			//okay, see how many files there are.
			$lsData = $this->fsObj>ls($dirName);
			$retval = count($lsData) +1;
		}
		else {
			$retval = $createDirRes = $this->fsObj->mkdir($dirName);
			$this->gfObj->debug_print(__METHOD__ .": couldn't find myDir, result of creating=(". $createDirRes .")!",1);
			#$retval = TRUE;
			$retval = 1;
		}
		
		return($retval);
	}//end make_comment_dir()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Create a new comment (author is optional; ommitting makes it anonymous)
	 */
	function add_comment($title, $body, $authorName=NULL, $authorEmail=NULL) {
		if(is_null($authorName) || !strlen($authorName)) {
			$authorName='Anonymous Coward';
		}
		
		//get information about this blog entry.
		$myData = $this->display_blog($this->sectionArr);
		$this->gfObj->debug_print(array_keys($myData),1);
		$this->gfObj->debug_print(__METHOD__ .": blog_entry_id=(". $myData['blog_entry_id'] ."), filename=(". $myData['filename'] .")",1);
		
		$parts = explode('.', $myData['filename']);
		$myDir = $parts[0];
		
		//interpretting this result assumes that some terrible error would be thrown if the call failes.
		$newCommentId = $this->make_comment_dir($myDir);
		
		//now let's figure out the filename.
		$filename = $myDir .'/comment_'. $newCommentId;
		
	}//end add_comment()
	//-------------------------------------------------------------------------
}//end blogComment{}

