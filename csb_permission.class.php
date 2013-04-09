<?php



//TODO: this should extend csb_blogAbstract{}

class csb_permission extends csb_blogAbstract {

	//-------------------------------------------------------------------------
    public function __construct(cs_phpDB $db) {
    	parent::__construct($db);
    	
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
					"(:blogId, :toUser)";
			$retval = $this->db->run_insert($sql, array('blogId'=>$blogId, 'toUser'=>$toUser), 'csblog_permission_table_permission_id_seq');
		}
		else {
			throw new exception(__METHOD__ .": invalid uid (". $toUser .") or blogId (". $blogId .")");
		}
		
		return($retval);
	}//end add_permission()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function can_access_blog($blogId, $uid) {
		if(strlen($blogId) && (is_numeric($uid) || strlen($uid))) {
			
			try {
				if(!is_numeric($uid)) {
					$uid = $this->get_uid($uid);
				}
				if(!is_numeric($blogId)) {
					$blogData = $this->get_blog_data_by_name($blogId);
					$blogId = $blogData['blog_id'];
				}
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
				$sql = "SELECT * FROM csblog_permission_table WHERE ".
						"blog_id=:blogId AND uid=:uid";
				
				$numrows = $this->db->run_query($sql, array('blogId'=>$blogId, 'uid'=>$uid));
				
				if($numrows == 1) {
					$retval = true;
				}
				elseif($numrows == 0) {
					$retval = false;
				}
				elseif($numrows > 1 || $numrows < 0) {
					throw new exception(__METHOD__ .": invalid data returned, numrows=(". $numrows .")");
				}
				else {
					throw new exception(__METHOD__ .": unknown result, numrows=(". $numrows .")");
				}
			}
		}
		else {
			//they gave invalid data; default to no access.
			$retval = false;
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
			$sql = "DELETE FROM csblog_permission_table WHERE blog_id=:blogId AND uid=:fromUser";
			
			try {
				$this->db->beginTrans();
				$numrows = $this->run_query($sql, array('blogId'=>$blogId, 'fromUser'=>$fromUser));
				
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
