<?php


require_once(dirname(__FILE__) .'/csb_blog.class.php');

class csb_blogLocation extends csb_blogAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct($location, array $dbParams=null) {
    	parent::__construct($dbParams);
    	
    	$loc = new csb_location();
    	$location = $loc->fix_location($location);
    	
    	$criteria = array(
			'is_active'		=>"t",
			'bl.location'	=> $location
		);
		
		try {
    		$this->validBlogs = $this->get_blogs($criteria, 'last_post_timestamp DESC');
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve any valid blogs for location=(". $location .")... " .
					"MORE INFO::: ". $e->getMessage());
		}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blogs($numPerBlog=1) {
		if(is_array($this->validBlogs) && count($this->validBlogs)) {
			$retval = array();
			foreach($this->validBlogs as $blogId=>$blogData) {
				$blogName = $blogData['blog_name'];
				$this->blogs[$blogName] = new csb_blog($blogName, $this->dbParams);
				if(!$this->blogs[$blogName]->is_initialized()) {
					$this->blogs[$blogName]->initialize_locals($blogName);
				}
				$retval[$blogName] = $this->blogs[$blogName]->get_recent_blogs($numPerBlog);
				if($numPerBlog == 1) {
					$keys = array_keys($retval[$blogName]);
					$retval[$blogName] = $retval[$blogName][$keys[0]];
				}
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