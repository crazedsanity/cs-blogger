<?php


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
			
			$bits = $this->parse_full_permalink($fullPermalink);
			$this->blogLocation = $bits['location'];
			$this->blogName = $bits['blogName'];
			$this->permalink = $bits['permalink'];
			
			$data = $this->get_blog_entry($fullPermalink);
			$this->blogEntryId = $data['entry_id'];
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
		//TODO: this is very redundant; since this object already has the full permalink, why pass it again?
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
				'content'			=> 'sql',
				'is_draft'			=> 'boolean'
			);
			$updateThis = array_intersect_key($updates, $validFields);
			if(is_array($updateThis) && count($updateThis)) {
				
				//encode the content as before.
				//TODO: stop encoding (shouldn't be necessary with prepared statements)
				if(isset($updateThis['content'])) {
					$updateThis['content'] = $this->encode_content($updateThis['content']);
				}
				$updateThis['entryId'] = $blogEntryId;
				
				$updateString = "";
				foreach(array_keys($updates) as $key) {
					$updateString = $this->gfObj->create_list($updateString, $key .'=:'. $key);
				}
				$sql = "UPDATE csblog_entry_table SET ". $updateString
					." WHERE entry_id=:entryId";
				
				$this->db->beginTrans();
				$numrows = $this->db->run_query($sql, $updateThis);
				
				if($numrows == 1) {
					$this->update_blog_last_post_timestamps();
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
