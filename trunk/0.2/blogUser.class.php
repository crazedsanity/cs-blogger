<?php

/*SVN INFORMATION::::
 * --------------------------
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedRevision$
 * $LastChangedBy$
 */

require_once(dirname(__FILE__) .'/abstract/editBlog.abstract.class.php');

class blogUser extends editBlogAbstract {
	
	protected $validBlogs;
	protected $blogs;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName) {
		
		parent::__construct($blogName);
		
	}//end __construct()
	//-------------------------------------------------------------------------
}//end blog{}

