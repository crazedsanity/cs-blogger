<?php


//TODO: consider moving add_comment() and get_comments() to csb_dataLayerAbstract{}, since they're data-type things.

class csb_blogComment extends csb_blogAbstract {
	
	protected $blogEntryId;
	
	//-------------------------------------------------------------------------
    public function __construct($fullPermalink, array $dbParams=NULL) {
    	
    	parent::__construct($dbParams);
    	
    	$blogData = $this->parse_full_permalink($fullPermalink);
    	
    	$x = $this->get_blog_entry($fullPermalink);
    	$this->blogEntryId = $x['entry_id'];
    	
    	$this->blogLocation = $blogData['location'];
    	$this->blogName = $blogData['blogName'];
    	
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_num_comments() {
		$sql = "SELECT count(*) FROM csblog_comment_table WHERE entry_id=". $this->blogEntryId;
		
		try {
			$numrows = $this->run_sql($sql);
			
			if($numrows = 1) {
				$data = $this->db->farray();
				$retval = $data[0];
			}
			else {
				throw new exception(__METHOD__ .": failed to get number of comments, invalid numrows (". $numrows .")");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve number of comments, DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_num_comments()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_comment($authorUid, $title, $comment, array $ancestry=null, $isAnonymous=false) {
		$sqlArr = array(
			'entry_id'		=> $this>blogEntryId,
			'title'			=> $title,
			'comment'		=> $this->encode_content($comment),
			'author_uid'	=> $authorUid,
			'is_anonymous'	=> $isAnonymous
		);
		
		$cleanArr = array(
			'entry_id'		=> 'numeric',
			'title'			=> 'sql',
			'comment'		=> 'sql',
			'author_uid'	=> 'numeric',
			'is_anonymous'	=> 'bool',
			'ancestry'		=> 'sql'
		);
		
		//handle ancestry, if given.
		$ancestryStr = "";
		if(is_array($ancestry) && count($ancestry) > 0) {
			foreach($ancestry as $id) {
				if(is_numeric($id) && $id > 0) {
					$ancestryStr = $this->gfObj->create_list($ancestryStr, $id, ':');
				}
				else {
					throw new exception(__METHOD__ .": invalid ancestor (". $id .")");
				}
			}
			$sqlArr['ancestry'] = $ancestryStr;
		}
		
		$sql = "INSERT INTO csblog_comment_table ". $this->gfObj->string_from_array($sqlArr, 'insert', null, $cleanArr);
		
		try {
			$this->db->beginTrans();
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = $this->db->get_currval('csblog_comment_table_comment_id_seq');
				$this->db->commitTrans();
			}
			else {
				$this->db->rollbackTrans();
				throw new exception(__METHOD__ .": failed to create record, invalid numrows (". $numrows .")");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to create comment, DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end add_comment()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_comments(array $criteria, $orderBy=null, $limit=NULL, $offset=NULL) {
		if(!is_array($criteria) || !count($criteria)) {
			throw new exception(__METHOD__ .": invalid criteria");
		}
		
		//TODO: should be specifically limited to blogs that are accessible to current user.
		$sql = "SELECT bc.*, be.permalink, bl.location, b.blog_display_name, be.post_timestamp::date as date_short, " .
				"b.blog_name, b.blog_id FROM csblog_comment_table AS bc " .
				"INNER JOIN csblog_entry_table AS be ON (be.entry_id=bc.entry_id) " .
				"INNER JOIN csblog_blog_table AS b ON (be.blog_id=b.blog_id) " .
				"INNER JOIN csblog_location_table AS bl ON (b.location_id=bl.location_id) " .
				"WHERE ";
		
		//add stuff to the SQL...
		foreach($criteria as $field=>$value) {
			if(!preg_match('/^[a-z]{1,}\./', $field)) {
				unset($criteria[$field]);
				$field = "bc.". $field;
				$criteria[$field] = $value;
			}
		}
		$sql .= $this->gfObj->string_from_array($criteria, 'select', NULL, 'sql');
		
		if(strlen($orderBy)) {
			$sql .= " ORDER BY ". $orderBy;
		}
		else {
			$sql .= " ORDER BY bc.comment_id";
		}
		
		if(is_numeric($limit) && $limit > 0) {
			$sql .= " LIMIT ". $limit;
		}
		if(is_numeric($offset) && $limit > 0) {
			$sql .= " OFFSET ". $offset;
		}
		
		$numrows = $this->run_sql($sql);
		
		$retval = $this->db->farray_fieldnames('comment_id', true, false);
		foreach($retval as $entryId=>$data) {
			$retval[$entryId]['content'] = $this->decode_content($data['content']);
			#$retval[$entryId]['full_permalink'] = $this->get_full_permalink($data['permalink']);
		}
		
		return($retval);
	}//end get_comments()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_comment_by_id($id) {
		if(is_numeric($id) && $id > 0) {
			$data = $this->get_comments(array('comment_id'=>$id));
			
			if(count($data) == 1 && isset($data[$id])) {
				$retval = $data[$id];
				$retval['comment'] = $this->decode_content($retval['comment']);
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve proper data for comment ID (". $id .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid comment ID (". $id .")");
		}
		
		return($retval);
	}//end get_comment_by_id()
	//-------------------------------------------------------------------------
	
	
}
?>
