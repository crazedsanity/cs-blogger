<?php

require_once(dirname(__FILE__) .'/blogIndex.abstract.class.php');

abstract class blogAbstract extends blogIndexAbstract {
	
	#protected $dbObj;
	
	protected $fsObj;
	protected $gfObj;
	protected $dbObj;
	
	protected $user;
	protected $uid;
	protected $blogId;
	protected $blogName;
	
	protected $validBlogs;
	
	protected $defaultLocation = '/blog';
	
	protected $isInitialized = FALSE;
	
	//-------------------------------------------------------------------------
	function __construct($blogName) {
		$this->_initialize_locals();
		if(!is_null($blogName) && strlen($blogName)) {
			$this->blogName = $blogName;
			$this->fsObj->cd($blogName);
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": no blogName set (". $blogName .")");
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_blog_exists(array $criteria) {
		//TODO: check if this blog directory exists...
		return($retval);
	}//end check_blog_exists()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_id_from_name($blogName) {
		
		//TODO: find out what this is used for...?
		
		return($retval);
	}//end get_id_from_name()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function can_access_blog($blogName, $username=NULL) {
		if(!is_null($username) && strlen($username)) {
			//TODO: access a file to determine this.
		}
		else {
			$retval = FALSE;
		}
		
		return($retval);
	}//end can_access_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_age_hype($timestamp, $addBrackets=FALSE) {
		$age = time() - $timestamp;
		switch($age) {
			case ($age <= 1800): {
				$extraText = '<font color="red"><b>Ink\'s still WET!</b></font>';
			} break;
			
			case ($age <= 3600): {
				//modified less than an hour ago!
				$extraText = '<font color="red"><b>Hot off the press!</b></font>';
			} break;
			
			case ($age <= 86400): {
				//modified less than 24 hours ago.
				$extraText = '<font color="red"><b>New!</b></font>';
			} break;
			
			case ($age <= 604800): {
				//modified this week.
				$extraText = '<font color="red">Less than a week old</font>';
			} break;
			
			case ($age <= 2592000): {
				//modified this month.
				$extraText = '<b>Updated this month</b>';
			} break;
			
			case ($age <= 5184000): {
				//modified in the last 2 months
				$extraText = '<b>Updated last month</b>';
			} break;
			
			case ($age <= 7776000): {
				$extraText = '<i>Updated 3 months ago</i>';
			} break;
			
			case ($age <= 10368000): {
				$extraText = '<i>Updated 4 months ago</i>';
			} break;
			
			case ($age <= 12960000): {
				$extraText = '<i>Updated 5 months ago</i>';
			} break;
			
			case ($age <= 15552000): {
				$extraText = '<i>Updated in the last 6 months</i>';
			} break;
			
			default: {
				$extraText  = '<i>pretty old</i>';
			}
		}
		
		if(strlen($extraText) && $addBrackets) {
			$extraText = '['. $extraText .']';
		}
		
		return($extraText);
	}//end get_age_hype()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_permalink_from_filename($filename) {
		if(!is_null($filename) && strlen($filename) >= 10) {
			if(preg_match('/\//', $filename)) {
				$pieces = explode('/', $filename);
				$username = $pieces[0];
				$filename = $pieces[1];
			}
			
			$dataPieces = explode('.', $filename);
			$retval = $this->blogName .'/'. $dataPieces[0];
		}
		else {
			throw new exception(__METHOD__ .": invalid filename given (". $filename .")");
		}
		
		return($retval);
	}//end get_permalink_from_filename()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function fix_db_array_indexes(array $a) {
		$retval = array();
		foreach($a as $index=>$value) {
			$myIndex = $index;
			if(preg_match('/\./', $index)) {
				$pieces = explode('.', $index);
				if(count($pieces) > 2) {
					throw new exception(__METHOD__ .": too many pieces in (". $index .")");
				}
				$myIndex = $pieces[1];
			}
			$retval[$myIndex] = $value;
		}
		return($retval);
	}//end fix_db_array_indexes()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------\
	protected function filename_from_title($title, $suffix=NULL) {
		//now build the real filename.
		$filename = strtolower($title);
		
		$filename = preg_replace('/!/', '', $filename);
		$filename = preg_replace('/&\+/', '-', $filename);
		$filename = preg_replace('/\'/', '', $filename);
		$filename = preg_replace("/[^a-zA-Z0-9_]/", "_", $filename);
		
		if(!strlen($filename)) {
			throw new exception(__METHOD__ .": invalid filename (". $filename .") from title=(". $title .")");
		}
		
		//consolidate multiple underscores... (" . . ." becomes "______", after this becomes just "_")
		$filename = preg_replace('/__*/', '_', $filename);
		
		//now add an extension.
		if(!is_null($suffix)) {
			$filename .= "-". $suffix;
		}
		$filename .= '.blog';
		
		return($filename);
	}//end filename_from_title()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_blogs_for_location($location=NULL) {
		//TODO: determine filesystem equivalent for this...
		
		return($data);
	}//end get_blogs_for_location()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_blog_data(array $criteria) {
		
		//TODO: determine filesystem equivalent for this...
		
		return($retval);
	}//end get_blog_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function set_internal_var($varName, $value=NULL) {
		if(property_exists($this, $varName)) {
			$this->$varName = $value;
		}
		else {
			throw new exception(__METHOD__ .": property (". $varName .") doesn't exist, value given was (". $value .")");
		}
	}//end set_internal_var()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_entry($nameOrId) {
		$retval = NULL;
		
		//TODO: determine filesystem equivalent...
		
		return($retval);
	}//end find_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_my_uid($uidOrUsername=NULL, $setInternal=FALSE) {
		
		//TODO: use filesystem equivalent of this...
		
		return($retval);
	}//end get_my_uid()
	//-------------------------------------------------------------------------
}