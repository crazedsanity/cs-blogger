<?php

require_once(dirname(__FILE__) .'/abstract/dataLayer.abstract.class.php');

class blog extends dataLayerAbstract {
	
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
	public function __construct($blogName, array $dbParams=null) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct($dbParams);
		
		
		if(!isset($blogName) || !strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blog name (". $blogName .")");
		}
		
		$this->blogName = $blogName;
		if(!defined('CSBLOG_SETUP_PENDING')) {
			//proceed normally...
			$this->initialize_locals($blogName);
		}
		
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
	 * contents for the proper blog (index or single entry).
	 */
	public function display_blog(array $url) {
		$url = $this->parse_blog_url($url);
		
		switch(count($url)) {
			case 1: {
				//should be a specific entry.
				$retval = $this->get_blog_entry($url[0]);
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
	
	
	
}// end blog{}
?>
