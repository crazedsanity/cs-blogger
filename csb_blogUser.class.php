<?php

/*
 * This is for displaying blogs for which a user has access.  The name is a bit
 * of a misnomer, but I couldn't really think of a better name at the time.
 */



class csb_blogUser extends csb_blogAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct(cs_phpDB $db, $user, $location=null) {
    	if(strlen($user) > 2) {
	    	parent::__construct($db);
	    	
	    	$criteria = array(
				'isActive'=>"t"
			);
			
			if(is_string($location) && strlen($location)) {
				$criteria['location'] = $location;
			}
			$uid = $this->get_uid($user);
			if(is_numeric($uid)) {
		    	$this->validBlogs = $this->get_blogs($criteria, 'last_post_timestamp DESC');
		    	$permObj = new csb_permission($db);
		    	foreach($this->validBlogs as $blogId=>$data) {
		    		$obj = new csb_blog($this->db, $data['blog_name']);
		    		$canAccess = $permObj->can_access_blog($blogId, $uid);
		    		if(!$canAccess) {
		    			unset($this->validBlogs[$blogId]);
		    		}
		    	}
			}
			else {
				throw new exception(__METHOD__ .": unable to retrieve uid for (". $user .")");
			}
    	}
    	else {
    		throw new exception(__METHOD__ .": no username set (". $user .")");
    	}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blogs($numPerBlog=1) {
		if(is_array($this->validBlogs) && count($this->validBlogs)) {
			$retval = array();
			$this->blogs = array();
			foreach($this->validBlogs as $blogId=>$blogData) {
				$blogName = $blogData['blog_name'];
				$this->blogs[$blogName] = new csb_blog($this->db, $blogName);
				if(!$this->blogs[$blogName]->is_initialized()) {
					$this->blogs[$blogName]->initialize_locals($blogName);
				}
				
				$recentBlogs = array();
				try {
					$recentBlogs = $this->blogs[$blogName]->get_recent_blogs($numPerBlog, 0, true);
					if($numPerBlog == 1) {
						$keys = array_keys($retval[$blogName]);
						$recentBlogs = $retval[$blogName][$keys[0]];
					}
				}
				catch(exception $e) {
					$this->gfObj->debug_print(__METHOD__ .": no blogs for (". $blogName .")");
				}
				$retval[$blogName] = $recentBlogs;
			}
		}
		else {
			throw new exception(__METHOD__ .": no valid blogs to handle");
		}
		
		return($retval);
	}//end get_most_recent_blogs()
	//-------------------------------------------------------------------------
	
	
	
    
}
?>
