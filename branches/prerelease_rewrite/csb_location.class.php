<?php

require_once(dirname(__FILE__) .'/abstract/csb_dataLayer.abstract.class.php');

class csb_location extends csb_dataLayerAbstract {

	//-------------------------------------------------------------------------
    public function __construct(array $dbParams=null) {
    	parent::__construct($dbParams);
    	
    	$this->gfObj = new cs_globalFunctions();
    	
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function add_location($location) {
		if(is_string($location) && strlen($location) > 3) {
			if(!preg_match('/^\//', $location)) {
				$location = "/". $location;
			}
			$location = $this->gfObj->cleanString($location, "sql_insert");
			$sql = "INSERT INTO csblog_location_table (location) " .
					"VALUES ('". $location ."')";
			$numrows = $this->run_sql($sql);
			
			
			if($numrows == 1) {
				//okay, retrieve the id inserted.
				$retval = $this->db->get_currval('csblog_location_table_location_id_seq');
			}
			else {
				throw new exception(__METHOD__ .": failed to create location (". $location ."), " .
						"numrows=(". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid location (". $location .")");
		}
		
		return($retval);
	}//end add_location()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_location_id($location) {
		if(is_string($location) && strlen($location) > 3) {
			$location = $this->fix_location($location);
			$location = $this->gfObj->cleanString($location, "sql_insert");
			$sql = "SELECT location_id FROM csblog_location_table " .
					"WHERE location='". $location ."'";
			$numrows = $this->run_sql($sql, false);
			
			
			if($numrows == 0) {
				$retval = false;
			}
			elseif($numrows == 1) {
				$retval = $this->db->farray();
				$retval = $retval[0];
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve location (". $location ."), " .
						"invalid numrows (". $numrows .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid location (". $location .")");
		}
		
		return($retval);
	}//end get_location_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function fix_location($location) {
		if(strlen($location)) {
			$retval = preg_replace("/\/$/", "", $location);
			if(!preg_match("/^\//", $retval)) {
				$retval = "/". $retval;
			}
			preg_replace("/(\/){2,}/", "/", $retval);
		}
		else {
			throw new exception(__METHOD__ .": no valid location (". $location .")");
		}
		
		return($retval);
	}//end fix_location()
	//-------------------------------------------------------------------------
	
	
}
?>