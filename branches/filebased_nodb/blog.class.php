<?php

/*SVN INFORMATION::::
 * --------------------------
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedRevision$
 * $LastChangedBy$
 */

require_once(dirname(__FILE__) .'/abstract/blog.abstract.class.php');

class blog extends blogAbstract {
	
	protected $blogId;
	protected $uid;
	protected $user;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName=NULL) {
		parent::__construct($blogName);
		$data = $this->get_blog_data(array('blog_name' => $blogName));
		$this->blogId = $data['blog_id'];
		$this->uid = $data['uid'];
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Takes an array for URL, like what contentSystem{} builds, and return the 
	 * contents for the proper blog (index or single entry).
	 */
	public function display_blog(array $url) {
		$url = $this->parse_blog_url($url);
		
		switch(count($url)) {
			case 1: {
				//should be a specific entry.
				$retval = $this->get_entry($url[0]);
				break;
			}//end case 1
			
			default: {
				//show the default index.
				$retval = $this->get_recent_blogs(5);
			}//end default
		}
		
		return($retval);
	}//end display_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function parse_blog_url(array $url) {
		if(count($url)) {
			if(preg_match('/^blog/', $url[0])) {
				$popped = array_shift($url);
				if(!count($url)) {
					throw new exception(__METHOD__ .": invalid data after removing unnecessary element (". $popped .")");
				}
			}
			if(preg_match('/^'. $this->blogName .'/', $url[0])) {
				array_shift($url);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid data");
		}
		
		return($url);
	}//end parse_blog_url()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blog() {
		$data = $this->get_recent_blogs(1);
		$vals = array_values($data);
		$retval = $vals[0];
		return($retval);
	}//end get_most_recent_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_recent_blogs($limit=5) {
		if(!is_numeric($limit) || is_null($limit)) {
			$limit = 5;
		}
		
		//TODO: read index of most recent entries for this blog.
		
		return($retval);
	}//end get_recent_blogs()
	//-------------------------------------------------------------------------
}//end blog{}

