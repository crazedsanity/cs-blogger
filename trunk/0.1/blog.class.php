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
		
		$sql = "SELECT be.*, b.blog_name, a.username FROM cs_blog_entry_table AS be INNER JOIN " .
				"cs_blog_table AS b ON (b.blog_id=be.blog_id) INNER JOIN cs_authentication_table " .
				"AS a ON (b.uid=a.uid) WHERE be.blog_id=". $this->blogId .
				" ORDER BY post_timestamp DESC LIMIT ". $limit;
		$numrows = $this->run_sql($sql);
		
		if($numrows <= $limit) {
			$data = $this->dbObj->farray_fieldnames('be.blog_entry_id',1);
			
			$retval = array();
			
			//fix indexes so they don't contain the table prefixes.
			foreach($data as $index=>$value) {
				$value = $this->fix_db_array_indexes($value);
				$value['age_hype'] = $this->get_age_hype($value['post_timestamp']);
				$value['display_name'] = ucwords($value['blog_name']);
				$value['date_short'] = strftime('%Y/%m/%d', $value['post_timestamp']);
				$value['permalink'] = $this->get_permalink_from_filename($value['filename']);
				$value['formatted_username'] = ucwords($value['username']);
				$value['formatted_post_timestamp'] = strftime('%A, %B %d, %Y %I:%M %p', $value['post_timestamp']);
				$value['formatted_post_timestamp_time'] = strftime('%I:%M %p', $value['post_timestamp']);
				$value['contents'] = $this->fsObj->read($value['filename']);
				$retval[$index] = $value;
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve data (". $numrows .")");
		}
		
		return($retval);
	}//end get_recent_blogs()
	//-------------------------------------------------------------------------
}//end blog{}

