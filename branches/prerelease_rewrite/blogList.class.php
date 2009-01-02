<?php


require_once(dirname(__FILE__) .'/blog.class.php');

class blogList extends dataLayerAbstract {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct($location) {
    	parent::__construct();
    	
    	$this->validBlogs = $this->get_blogs(array('is_active'=>"t"), 'last_post_timestamp DESC');
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blogs($numPerBlog=1) {
		if(is_array($this->validBlogs) && count($this->validBlogs)) {
			$retval = array();
			if(is_null($numPerBlog) || !is_numeric($numPerBlog) || $numPerBlog <= 1) {
				$methodName = 'get_most_recent_blog';
			}
			else {
				$methodName = 'get_recent_blogs';
			}
			foreach($this->validBlogs as $blogId=>$blogData) {
				$blogName = $blogData['blog_name'];
				$this->blogs[$blogName] = new blog($blogName);
				if(!$this->blogs[$blogName]->is_initialized()) {
					$this->blogs[$blogName]->initialize_locals($blogName);
				}
				$retval[$blogName] = $this->blogs[$blogName]->$methodName();
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