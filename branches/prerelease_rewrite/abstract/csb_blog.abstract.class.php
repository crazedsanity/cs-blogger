<?php

require_once(dirname(__FILE__) .'/csb_dataLayer.abstract.class.php');

class csb_blogAbstract extends csb_dataLayerAbstract {
	
	/** Internal name of blog (looks like a permalink) */
	protected $blogName;
	
	/** Displayable name of blog */
	protected $blogDisplayName;
	
	/** Numeric ID of blog */
	protected $blogId=false;
	
	/** Location of blog */
	protected $blogLocation;
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $blogName		(str) name of blog (NOT the display name)
	 * @param $dbType		(str) Type of database (pgsql/mysql/sqlite)
	 * @param $dbParams		(array) connection options for database
	 * 
	 * @return exception	throws an exception on error.
	 */
	public function __construct(array $dbParams=null) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct($dbParams);
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Initializes information about the selected blog.
	 * 
	 * @param $blogName		(str) name of blog (NOT the display name)
	 */
	public function initialize_locals($blogName) {
		
		if(!is_numeric($this->blogId)) {
			$data = $this->get_blog_data_by_name($blogName);
			
			$var2index = array(
				'blogDisplayName'	=> 'blog_display_name',
				'blogName'			=> 'blog_name',
				'blogId'			=> 'blog_id',
				'blogLocation'		=> 'location'
			);
			
			foreach($var2index as $var=>$index) {
				if(isset($data[$index]) && strlen($data[$index])) {
					$this->$var = $data[$index];
				}
				else {
					throw new exception(__METHOD__ .": var ". $var ." not set from index ". $index .", no data (". $data[$index] .")");
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": already initialized");
		}
	}//end initialize_locals()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves protected (or private?) internal var values
	 * 
	 * @param $var			(str) name of internal var to retrieve
	 * 
	 * @return exception 	throws an exception if named var doesn't exist.
	 */
	public function get_internal_var($var) {
		if(isset($this->$var)) {
			$retval = $this->$var;
		}
		else {
			throw new exception(__METHOD__ .": invalid var name (". $var .")");
		}
		
		return($retval);
	}//end get_internal_var()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function is_initialized() {
		$retval = false;
		if(is_numeric($this->blogId)) {
			$retval = true;
		}
		return($retval);
	}//end is_initialized()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Takes an array for URL, like what contentSystem{} builds, and return the 
	 * contents for the proper blog.
	 */
	public function display_blog(array $url) {
		$fullPermalink = "/". $this->gfObj->string_from_array($url, null, '/');
		$retval = $this->get_blog_entry($fullPermalink);
		
		return($retval);
	}//end display_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function can_access_blog($blogName, $username) {
		if(strlen($blogName) && strlen($username)) {
			$blogId = $blogName;
			if(!is_numeric($blogName)) {
				$blogData = $this->get_blog_data_by_name($blogName);
				$blogId = $blogData['blog_id'];
			}
			$useUid = $username;
			if(!is_numeric($username)) {
				$useUid = $this->get_uid($username);
			}
			
			if(is_numeric($blogId) && is_numeric($useUid)) {
				
				$sql = "SELECT * FROM csblog_permission_table WHERE blog_id=". $blogId .
						" AND uid=". $useUid;
				
				$numrows = $this->run_sql($sql,false);
				
				$retval = false;
				if($numrows == 1 || $blogData['uid'] == $useUid) {
					$retval = true;
				}
				elseif($numrows > 1 || $numrows < 0) {
					throw new exception(__METHOD__ .": invalid data returned, numrows=(". $numrows .")");
				}
			}
			else {
				throw new exception(__METHOD__ .": invalid data for blogId (". $blogId .") or uid (". $useUid .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": no data for blogName (". $blogName .") or username (". $username .")");
		}
		
		return($retval);
		
	}//end can_access_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Creates a "permalink" just from title (does NOT include blog location):
	 * lowercases, strips special characters, uses "_" in place of spaces and 
	 * special characters (NEVER creates more than one "_" in a row)
	 * 
	 * @param $title		(str) string to create permalink from.
	 * 
	 * @return exception	throws exception on error
	 * @return (string)		permalink
	 */
	public function create_permalink_from_title($title) {
		if(is_string($title) && strlen($title) >= CSBLOG_TITLE_MINLEN) {
			
			$permalink = strtolower($title);
			$permalink = preg_replace('/!/', '', $permalink);
			$permalink = preg_replace('/&\+/', '-', $permalink);
			$permalink = preg_replace('/\'/', '', $permalink);
			$permalink = preg_replace("/[^a-zA-Z0-9_]/", "_", $permalink);
			
			if(!strlen($permalink)) {
				throw new exception(__METHOD__ .": invalid filename (". $permalink .") from title=(". $title .")");
			}
			
			//consolidate multiple underscores... (" . . ." becomes "______", after this becomes just "_")
			$permalink = preg_replace('/__*/', '_', $permalink);
		}
		else {
			throw new exception(__METHOD__ .": invalid title (". $title .")");
		}
		
		return($permalink);
	}//end create_permalink_from_title()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Encodes content using base64
	 * 
	 * @param $content		(str) content to encode
	 * 
	 * @return (string)		encoded content.
	 */
	public function encode_content($content) {
		//make it base64 data, so it is easy to insert.
		$retval = base64_encode($content);
		return($retval);
	}//end encode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Decoded content (reverse of encode_content())
	 * 
	 * @param $content		(str) Encoded content to decode
	 * 
	 * @return (string)		Decoded content.
	 */
	public function decode_content($content) {
		$retval = base64_decode($content);
		return($retval);
	}//end decode_content()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves all data about a blog (the main entry only) by its name.
	 * 
	 * @param $blogName		(str) name of blog (displayable or proper)
	 * 
	 * @return exception	throws exceptions on error
	 * @return (array)		array of data about the blog.
	 */
	public function get_blog_data_by_name($blogName) {
		if(strlen($blogName) > 3) {
			$data = $this->get_blogs(array('b.blog_name'=>$blogName), 'blog_id');
			
			if(count($data) == 1) {
				$keys = array_keys($data);
				$retval = $data[$keys[0]];
			}
			else {
				throw new exception(__METHOD__ .": too many records returned (". count($data) .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog name (". $blogName .")");
		}
		
		return($retval);
	}//end get_blog_data_by_name()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Same as get_blog_data_by_name(), but use blog_id to find it.
	 * 
	 * @param $blogId		(int) blog_id to retrieve info for.
	 * 
	 * @return exception	throws exception on error
	 * @return (array)		array of data about the blog.
	 */
	public function get_blog_data_by_id($blogId) {
		
		if(is_numeric($blogId) && $blogId > 0) {
			$data = $this->get_blogs(array('blog_id'=>$blogId), 'blog_id');
			if(count($data) == 1) {
				$keys = array_keys($data);
				$retval = $data[$keys[0]];
			}
			else {
				throw new exception(__METHOD__ .": invalid number of records returned (". count($data) .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid blog id (". $blogId .")");
		}
		
		return($retval);
	}//end get_blog_data_by_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Determines if there is one or more matching permalinks for the given 
	 * blog; duplicates are given a suffix of "-N" (where "N" is the number of
	 * matching entries; first dup is given "-1", and so on).
	 * 
	 * @param $blogId		(int) blog_id for the permalink 
	 * @param $permaLink	(str) permalink to check
	 * 
	 * @return exception	thrown on error.
	 * @return 
	 */
	public function check_permalink($blogId, $permalink) {
		if(is_string($permalink) && strlen($permalink) >= CSBLOG_TITLE_MINLEN && is_numeric($blogId) && $blogId > 0) {
			#if($permalink == $this->create_permalink_from_title($permalink)) {
			$permalink = $this->create_permalink_from_title($permalink);
			$sql = "SELECT * FROM csblog_entry_table WHERE blog_id=". $blogId 
				." AND permalink='". $permalink ."' OR permalink LIKE '". $permalink ."-%'";
			
			$numrows = $this->run_sql($sql, false);
			
			if($numrows >= 0) {
				if($numrows >= 1) {
					//got a record, give 'em the data back.
					$retval = $permalink ."-". $numrows;
				}
				elseif($numrows == 0) {
					$retval = $permalink;
				}
				else {
					throw new exception(__METHOD__ .": unknown error, numrows=(". $numrows ."), dberror");
				}
			}
			else {
				throw new exception(__METHOD__ .": invalid numrows (". $numrows .") or dberror");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid permalink (". $permalink .") or blog_id (". $blogId .")");
		}
		
		return($retval);
	}//end check_permalink()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_age_hype($timestamp, $addBrackets=FALSE) {
		if(strlen($timestamp >= 9)) {
			if(!is_numeric($timestamp)) {
				$timestamp = strtotime($timestamp);
			}
			
			$age = time() - $timestamp;
			switch($age) {
				case ($age <= 1800): {
					$extraText = '<font color="red"><b>Ink\'s still WET!</b></font>';
				} break;
				
				case ($age <= 3600): {
					//modified less than an hour ago!
					$extraText = '<font color="red"><b>Hot off the press!</b></font>';
				} break;
				
				case ($age <= 86400): {
					//modified less than 24 hours ago.
					$extraText = '<font color="red"><b>New!</b></font>';
				} break;
				
				case ($age <= 604800): {
					//modified this week.
					$extraText = '<font color="red">Less than a week old</font>';
				} break;
				
				case ($age <= 2592000): {
					//modified this month.
					$extraText = '<b>Less than a month old</b>';
				} break;
				
				case ($age <= 5184000): {
					//modified in the last 2 months
					$extraText = '<b>Updated last month</b>';
				} break;
				
				case ($age <= 7776000): {
					$extraText = '<i>Updated 3 months ago</i>';
				} break;
				
				case ($age <= 10368000): {
					$extraText = '<i>Updated 4 months ago</i>';
				} break;
				
				case ($age <= 12960000): {
					$extraText = '<i>Updated 5 months ago</i>';
				} break;
				
				case ($age <= 15552000): {
					$extraText = '<i>Updated in the last 6 months</i>';
				} break;
				
				default: {
					$extraText  = '<i>pretty old</i>';
				}
			}
			
			if(strlen($extraText) && $addBrackets) {
				$extraText = '['. $extraText .']';
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid timestamp (". $timestamp .")");
		}
		
		return($extraText);
	}//end get_age_hype()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_most_recent_blog() {
		$retval = $this->get_recent_blogs(1);
		return($retval);
	}//end get_most_recent_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_recent_blogs($limit=5, $offset=0) {
		if(is_numeric($this->blogId)) {
			if(is_numeric($limit) && $limit > 0) {
				if(is_numeric($offset) && $offset >= 0) {
					$retval = $this->get_blog_entries(array('blog_id'=>$this->blogId), 'post_timestamp DESC', $limit, $offset);
				}
				else {
					throw new exception(__METHOD__ .": invalid offset (". $offset .")");
				}
			}
			else {
				throw new exception(__METHOD__ .": invalid limit (". $limit .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": blogId not set");
		}
		
		return($retval);
	}//end get_recent_blogs()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_full_permalink($permalink) {
		$retval = $this->blogLocation .'/'. $this->blogName .'/'. $permalink;
		return($retval);
	}//end get_full_permalink()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function parse_full_permalink($fullPermalink) {
		
		if(strlen($fullPermalink) && preg_match("/\//", $fullPermalink)) {
			$fullPermalink = preg_replace("/^\//", "", $fullPermalink);
			$parts = explode("/", $fullPermalink);
			
			if(count($parts) >= 3) {
				$permalink = array_pop($parts);
				$blogName = array_pop($parts);
				$location = "/". $this->gfObj->string_from_array($parts, NULL, "/");
				
				$retval = array(
					'location'	=> $location,
					'blogName'	=> $blogName,
					'permalink'	=> $permalink
				);
			}
			else {
				throw new exception(__METHOD__ .": not enough parts (i.e. location, blogName, & permalink) in " .
						"full permalink (". $fullPermalink .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": ");
		}
		
		return($retval);
	}//end parse_full_permalink()
	//-------------------------------------------------------------------------
	
	
	
}// end blog{}
?>