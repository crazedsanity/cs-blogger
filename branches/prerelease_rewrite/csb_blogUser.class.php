<?php

/*
 * This is for displaying blogs for which a user has access.  The name is a bit
 * of a misnomer, but I couldn't really think of a better name at the time.
 */


require_once(dirname(__FILE__) .'/csb)blog.class.php');

class csb_blogUser extends csb_blogAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct($user, $location=null, array $dbParams=null) {
    	if(strlen($user) > 2) {
	    	parent::__construct($location, $dbParams);
	    	
	    	$criteria = array(
				'is_active'=>"t"
			);
			
			if(is_string($location) && strlen($location)) {
				$criteria['location'] = $location;
			}
	    	$this->validBlogs = $this->get_blogs($criteria, 'last_post_timestamp DESC');
	    	foreach($this->validBlogs as $blogId=>$data) {
	    		$obj = new csb_blog($data['blog_name']);
	    		if(!$obj->can_access_blog($data['blog_name'], $user)) {
	    			unset($this->validBlogs[$blogId]);
	    		}
	    	}
    	}
    	else {
    		throw new exception(__METHOD__ .": no username set (". $user .")");
    	}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
    
}
?>