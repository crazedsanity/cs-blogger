<?php


require_once(dirname(__FILE__) .'/blog.class.php');

class blogList extends dataLayerAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct($location=null, array $dbParams=null) {
    	parent::__construct($dbParams);
    	
    	$criteria = array(
			'is_active'=>"t"
		);
		if(strlen($location)) {
			$criteria['bl.location'] = $location;
		}
    	$this->validBlogs = $this->get_blogs($criteria, 'last_post_timestamp DESC');
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blogs($numPerBlog=1) {
		if(is_array($this->validBlogs) && count($this->validBlogs)) {
			$retval = array();
			foreach($this->validBlogs as $blogId=>$blogData) {
				$blogName = $blogData['blog_name'];
				$this->blogs[$blogName] = new blog($blogName, $this->dbParams);
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