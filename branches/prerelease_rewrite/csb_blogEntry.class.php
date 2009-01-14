<?php

require_once(dirname(__FILE__) .'/abstract/csb_blog.abstract.class.php');
require_once(dirname(__FILE__) .'/csb_blogComment.class.php');

class csb_blogEntry extends csb_blogAbstract {
	
	/** Internal name of blog (looks like a permalink) */
	protected $blogName;
	
	/** Numeric ID of blog */
	protected $blogEntryId=false;
	
	/** Location of blog */
	protected $blogLocation;
	
	/** Permalink of current entry (just the title portion) */
	protected $permalink;
	
	/** Full permalink (location + blogName + permalink) */
	protected $fullPermalink;
	
	/** csb_blogComment{} object */
	protected $blogCommentObj;
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $fullPermalink	(str) FULL Permalink.
	 * @param $dbParams			(array) connection options for database
	 * 
	 * @return exception	throws an exception on error.
	 */
	public function __construct($fullPermalink, array $dbParams=null) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct($dbParams);
		
		if(isset($fullPermalink) && strlen($fullPermalink)) {
			$data = $this->get_blog_entry($fullPermalink);
			$this->blogEntryId = $data['entry_id'];
			
			$bits = $this->parse_full_permalink($fullPermalink);
			$this->blogLocation = $bits['location'];
			$this->permalink = $bits['permalink'];
			
			$this->blogCommentObj = new csb_blogComment($this->blogEntryId, $dbParams);
			$this->numComments = $this->blogCommentObj->get_num_comments();
		}
		else {
			throw new exception(__METHOD__ .": invalid permalink (". $fullPermalink .")");
		}
		
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Takes an array for URL, like what contentSystem{} builds, and return the 
	 * contents for the proper blog.
	 */
	public function display_blog(array $url) {
		$fullPermalink = "/". $this->gfObj->string_from_array($url, null, '/');
		$retval = $this->get_blog_entry($fullPermalink);
		
		return($retval);
	}//end display_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Updates a single entry (within a transaction)
	 * 
	 * @param $blogEntryId		(int) entry_id to update.
	 * @param $updates			(array) array of field=>value updates
	 * 
	 * @return exception		throws exception on error.
	 * @return true				returns boolean TRUE on success.
	 */
	public function update_entry($blogEntryId, array $updates) {
		if(is_numeric($blogEntryId) && $blogEntryId > 0 && is_array($updates) && count($updates)) {
			$validFields = array(
				'post_timestamp'	=> 'datetime',
				'content'			=> 'sql'
			);
			$updateThis = array_intersect_key($updates, $validFields);
			if(is_array($updateThis) && count($updateThis)) {
				
				//encode teh content as before.
				if(isset($updateThis['content'])) {
					$updateThis['content'] = $this->encode_content($updateThis['content']);
				}
				
				$sql = "UPDATE csblog_entry_table SET ". $this->gfObj->string_from_array($updateThis, 'update', NULL, $validFields)
					." WHERE entry_id=". $blogEntryId;
				
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
	
	
	
}// end blog{}
?>
