<?php

/*SVN INFORMATION::::
 * --------------------------
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedRevision$
 * $LastChangedBy$
 */

require_once(dirname(__FILE__) .'/blog.class.php');

class blogList extends blog {
	
	private $location;
	protected $validBlogs;
	protected $blogs;
	
	//-------------------------------------------------------------------------
	public function __construct($location) {
		
		$this->_initialize_locals();
		if(!is_null($location) && strlen($location)) {
			$this->get_blogs_for_location($location);
		}
		else {
			throw new exception(__METHOD__ .": invalid blog location (". $location .")");
		}
		
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
}//end blogList{}

