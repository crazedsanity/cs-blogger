<?php



class csb_blogLocation extends csb_blogAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	/** List of blogs available for the given location. */
	protected $blogs = array();
	
	//-------------------------------------------------------------------------
    function __construct(cs_phpDB $db, $location) {
    	parent::__construct($db);
    	
    	$loc = new csb_location($db);
    	$location = $loc->fix_location($location);
    	
    	$criteria = array(
			'isActive'		=>"t",
			'location'	=> $location
		);
		
		try {
    		$this->validBlogs = $this->get_blogs($criteria, 'blog_display_name DESC');
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
				try {
					$this->blogs[$blogName] = new csb_blog($this->db, $blogName);
					if(!$this->blogs[$blogName]->is_initialized()) {
						$this->blogs[$blogName]->initialize_locals($blogName);
					}
					$retval[$blogName] = $this->blogs[$blogName]->get_recent_blogs($numPerBlog);
					if($numPerBlog == 1) {
						$keys = array_keys($retval[$blogName]);
						$retval[$blogName] = $retval[$blogName][$keys[0]];
					}
				}
				catch(exception $e) {
					throw new exception(__METHOD__ .": unable to retrieve most recent blogs... ". $e->getMessage());
					//nothing to see here, move along.
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