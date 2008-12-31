<?php


require_once(dirname(__FILE__) .'/blog.class.php');

class blogList extends blog {
	
	/** An array of blog{} objects. */
	protected $blogObjList;
	
	/** An array with sub-arrays listing by blog_id, name, etc. */
	protected $blogIndex;
	
	//-------------------------------------------------------------------------
    function __construct($blogName, $dbType, array $dbParams) {
    	
    	$this->get_blog_list();
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blogs($numPerBlog=1) {
		if(is_array($this->validBlogs) && count($this->validBlogs)) {
			$retval = array();
			if(is_null($numPerBlog) || !is_numeric($numPerBlog) || $numPerBlog <= 1) {
				foreach($this->validBlogs as $blogId=>$blogName) {
					$this->blogs[$blogName] = new blog($blogName);
					$retval[$blogName] = $this->blogs[$blogName]->get_most_recent_blog();
				}
			}
			else {
				foreach($this->validBlogs as $blogId=>$blogName) {
					$this->blogs[$blogName] = new blog($blogName);
					$retval[$blogName] = $this->blogs[$blogName]->get_recent_blogs();
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