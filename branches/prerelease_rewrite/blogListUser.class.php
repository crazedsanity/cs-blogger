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

class blogListUser extends blog {
	
	private $location;
	protected $validBlogs;
	protected $blogs;
	
	//-------------------------------------------------------------------------
	public function __construct($username) {
		
		parent::__construct($username);
		$this->_initialize_locals();
		if(!is_null($username) && strlen($username)) {
			$this->userBlogs = $this->get_blog_data(array('a.username' => $username));
		}
		else {
			throw new exception(__METHOD__ .": no username given!");
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_blog_list($numPerBlog=10) {
		if(is_array($this->userBlogs) && count($this->userBlogs)) {
			$retval = array();
			foreach($this->userBlogs as $blogName=>$blogData) {
				$blog = new blog($blogName);
				$retval[$blogName] = $blog->get_recent_blogs();
			}
		}
		else {
			throw new exception(__METHOD__ .": no valid blogs to handle");
		}
		
		return($retval);
	}//end get_blog_list()
	//-------------------------------------------------------------------------
}//end blog{}

