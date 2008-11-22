<?php

abstract class blogIndexAbstract {
	
	/** Cached index of ALL entries (based on author, blog, or date) */
	protected $indexAll = array();
	
	/** Cached index of RECENT entries (based on author, blog, or date) */
	protected $indexRecent = array();
	
	//-------------------------------------------------------------------------
	public function __construct() {
		
		$this->_initialize_locals();
		
		//check if the directory for holding indexes exists...
		$lsData = $this->fsObj->ls();
		if(isset($lsData['indexes']) && is_array($lsData['indexes'])) {
			//TODO: read-in existing indexes into memory...
			$this->read_indexes();
		}
		else {
			//no indexes; build some.
			$this->fsObj->mkdir('indexes');
			$this->rebuild_indexes();
		}
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function _initialize_locals() {
		if($this->isInitialized !== TRUE) {
			$this->fsObj = new cs_fileSystemClass(CS_BLOGRWDIR);
			$this->gfObj = new cs_globalFunctions;
			$this->isInitialized = TRUE;
		}
	}//end _initialize_locals()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function read_indexes() {
		$this->fsObj->cd('indexes');
	}//end read_indexes()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Rebuilds indexes from scratch; it is assumed that if any indexes exist, 
	 * the data in them is invalid and is completely overwritten.
	 * 
	 * NOTE: timestamp is based solely on the last modify time of the file, 
	 * which could potentially be different than what the actual blog says.
	 */
	protected function rebuild_indexes() {
		
		
		$this->read_indexes();
	}//end rebuild_indexes()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Pass blog, author, date, and entry so indexes can be updated quickly.
	 */
	protected function update_index() {
	}//end update_index()
	//-------------------------------------------------------------------------

}
?>