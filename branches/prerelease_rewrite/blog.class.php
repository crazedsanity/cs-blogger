<?php

require_once(dirname(__FILE__) .'/abstract/dataLayer.abstract.class.php');

class blog extends dataLayerAbstract {
	
	/**  */
	protected $gf;
	
	/**  */
	protected $blogName;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName, $dbType, array $dbParams) {
		
		//TODO: put these in the constructor args, or require CONSTANTS.
		parent::__construct($dbType, $dbParams);
		
		
		if(!isset($blogName) || !strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blog name (". $blogName .")");
		}
		
		$this->blogName = $blogName;
		$this->gf = new cs_globalFunctions();
		$this->gf->debugPrintOpt=1;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_new_blog($blogName) {
		if(!isset($blogName) || !strlen($blogName)) {
			throw new exception(__METHOD__ .": invalid blog name given (". $blogName .")");
		}
		
		$retval = false;
		
		return($retval);
	}//end create_new_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function destroy_blog($blogName) {
		$retval = false;
		
		return($retval);
	}//end destroy_blog()
	//-------------------------------------------------------------------------
	
	
}// end blog{}
?>
