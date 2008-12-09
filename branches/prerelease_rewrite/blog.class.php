<?php

require_once(dirname(__FILE__) .'/abstract/dataLayer.abstract.class.php');

class blog extends dataLayerAbstract {
	
	/** Internal name of blog (looks like a permalink) */
	protected $blogName;
	
	/** Displayable name of blog */
	protected $blogDisplayName;
	
	/** Numeric ID of blog */
	protected $blogId;
	
	/** Location of blog */
	protected $blogLocation;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName, $dbType, array $dbParams) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct($dbType, $dbParams);
		
		
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
	protected function initialize_locals($blogName) {
		
		$data = $this->get_blog_data_by_name($blogName);
		
		$this->blogName			= $data['blog_name'];
		$this->blogDisplayName	= $data['blog_display_name'];
		$this->blogId			= $data['blog_id'];
		$this->blogLocation		= $data['blog_location'];
	}//end initialize_locals()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
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
	
	
	
}// end blog{}
?>
