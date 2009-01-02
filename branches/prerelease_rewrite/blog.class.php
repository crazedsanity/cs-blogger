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
	public function __construct($blogName) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct();
		
		
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
			$this->blogName			= $data['blog_name'];
			$this->blogDisplayName	= $data['blog_display_name'];
			$this->blogId			= $data['blog_id'];
			$this->blogLocation		= $data['blog_location'];
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
	
	
	
}// end blog{}
?>
