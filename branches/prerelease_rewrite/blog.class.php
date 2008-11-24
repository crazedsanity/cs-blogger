<?php

class blog {
	
	/**  */
	public $fs;
	
	/**  */
	protected $gf;
	
	/**  */
	protected $blogName;
	
	//-------------------------------------------------------------------------
	public function __construct($blogName) {
		$this->fs = new cs_fileSystemClass(dirname(__FILE__) .'/../../rw/blogger');
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
		
		$lsData = $this->fs->ls();
		
		if(isset($lsData[$blogName]) && is_array($lsData[$blogName])) {
			throw new exception(__METHOD__ .": blog already exists or file of the same name exists (". $blogName .")");
		}
		else {
			$createDirRes = $this->fs->mkdir($blogName);
			
			$this->gf->debug_print(__METHOD__ .": createDirRes=(". $createDirRes .")");
			if($this->fs->is_writable($blogName)) {
				$retval = true;
			}
			else {
				throw new exception(__METHOD__ .": directory not writable (". $blogName .")!");
			}
		}
		
		return($retval);
	}//end create_new_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function destroy_blog($blogName) {
		$this->fs->cd('/');
		if($this->fs->cd($blogName)) {
			$lsData = $this->fs->ls();
			if(is_array($lsData)) {
				#foreach($lsData as $entry=>$entryData) {
				#}
			}
			else {
				$this->fs->cdup();
				if($this->fs->rmdir($blogName)) {
					$retval = true;
				}
				else {
					throw new exception(__METHOD__ .": failed to delete blog directory (". $blogName .")");
				}
			}
		}
		else {
			$this->fs->rmdir($blogName);
		}
		
		return($retval);
	}//end destroy_blog()
	//-------------------------------------------------------------------------
	
	
}// end blog{}
?>
