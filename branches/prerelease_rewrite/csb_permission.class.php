<?php

require_once(dirname(__FILE__) .'/abstract/csb_dataLayer.abstract.class.php');

class csb_permission extends csb_dataLayerAbstract {

	//-------------------------------------------------------------------------
    public function __construct(array $dbParams=null) {
    	parent::__construct($dbParams);
    	
    	$this->gfObj = new cs_globalFunctions();
    	
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_permission($blogId, $toUser) {
		if(!is_numeric($toUser) && is_string($toUser) && strlen($toUser)) {
			$toUser = $this->get_uid($toUser);
		}
		
		if(is_numeric($toUser) && $toUser > 0 && is_numeric($blogId) && $blogId > 0) {
			$this->db->beginTrans();
			$sql = "INSERT INTO csblog_permission_table (blog_id, uid) VALUES " .
					"(". $blogId .", ". $toUser .")";
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$this->db->commitTrans();
				$retval = $this->db->get_currval('csblog_permission_table_permission_id_seq');
			}
			else {
				$this->db->rollbackTrans();
				throw new exception(__METHOD__ .": invalid numrows (". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid uid (". $toUser .") or blogId (". $blogId .")");
		}
		
		return($retval);
	}//end add_permission()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function can_access_blog($blogId, $uid) {
		if(is_numeric($blogId) && is_numeric($uid)) {
			
			try {
				//if this call doesn't cause an exception, we're good to go (add extra logic anyway)
				$blogData = $this->get_blogs(array('blog_id'=>$blogId, 'uid'=>$uid));
				if(is_array($blogData) && count($blogData) == 1 && $blogData[$blogId]['uid'] == $uid) {
					$retval = true;
				}
				else {
					$retval = false;
				}
			}
			catch(exception $e) {
				//an exception means there was no record; check the permissions table.
				$sql = "SELECT * FROM csblog_permission_table WHERE blog_id=". $blogId .
						" AND uid=". $uid;
				
				$numrows = $this->run_sql($sql,false);
				
				$retval = false;
				if($numrows == 1) {
					$retval = true;
				}
				elseif($numrows > 1 || $numrows < 0) {
					throw new exception(__METHOD__ .": invalid data returned, numrows=(". $numrows .")");
				}
			}
		}
		else {
			cs_debug_backtrace($this->gfObj->debugPrintOpt);
			throw new exception(__METHOD__ .": invalid data for blogId (". $blogId .") or uid (". $uid .")");
		}
		
		return($retval);
		
	}//end can_access_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function remove_permission($blogId, $fromUser) {
		if(!is_numeric($fromUser) && is_string($fromUser) && strlen($fromUser)) {
			$fromUser = $this->get_uid($fromUser);
		}
		
		if(is_numeric($fromUser) && $fromUser > 0 && is_numeric($blogId) && $blogId > 0) {
			$sql = "DELETE FROM csblog_permission_table WHERE blog_id=". $blogId 
				." AND uid=". $fromUser;
			
			try {
				$this->db->beginTrans();
				$numrows = $this->run_sql($sql, false);
				
				if($numrows == 0 || $numrows == 1) {
					$this->db->commitTrans();
					$retval = $numrows;
				}
				else {
					$this->db->rollbackTrans();
					throw new exception(__METHOD__ .": deleted too many records (". $numrows .")");
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": unable to delete permission... DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid uid (". $fromUser .") or blogId (". $blogId .")");
		}
		
		return($retval);
	}//end remove_permission()
	//-------------------------------------------------------------------------
	
	
}
?>