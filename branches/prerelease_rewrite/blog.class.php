<?php

require_once(dirname(__FILE__) .'/abstract/dataLayer.abstract.class.php');

class blog extends dataLayerAbstract {
	
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
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function destroy_blog($blogName) {
		$retval = false;
		
		return($retval);
	}//end destroy_blog()
	//-------------------------------------------------------------------------
	
	
}// end blog{}
?>
