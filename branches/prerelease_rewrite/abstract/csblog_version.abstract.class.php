<?php
/*
 * Created on January 01, 2009 by Dan Falconer
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */

abstract class csblog_versionAbstract {
	
	public $isTest = FALSE;
	
	abstract public function __construct();
	
	
	
	//=========================================================================
	/**
	 * Retrieve our version string from the VERSION file.
	 */
	final public function get_version($asArray=false) {
		$retval = NULL;
		$versionFileLocation = dirname(__FILE__) .'/../VERSION';
		if(file_exists($versionFileLocation)) {
			$myData = file($versionFileLocation);
			
			//set the logical line number that the version string is on, and 
			//	drop by one to get the corresponding array index.
			$lineOfVersion = 3;
			$arrayIndex = $lineOfVersion -1;
			
			$myVersionString = trim($myData[$arrayIndex]);
			
			if(preg_match('/^VERSION: /', $myVersionString)) {
				$fullVersionString = preg_replace('/^VERSION: /', '', $myVersionString);
				$pieces = explode('.', $fullVersionString);
				$retval = array(
					'version_major'			=> $pieces[0],
					'version_minor'			=> $pieces[1],
					'version_maintenance'	=> $pieces[2]
				);
				if(!strlen($retval['version_maintenance'])) {
					$retval['version_maintenance'] = 0;
				}
				
				if(preg_match('/-/', $retval['version_maintenance'])) {
					$bits = explode('-', $retval['version_maintenance']);
					$retval['version_maintenance'] = $bits[0];
					$suffix = $bits[1];
				}
				else {
					$suffix = "";
				}
				
				$fullVersionString = $this->gfObj->string_from_array(array_values($retval), NULL, '.');
				if(strlen($suffix)) {
					$fullVersionString .= '-'. $suffix;
				}
				
				
				if($asArray) {
					$retval['version_suffix'] = $suffix;
					$retval['version_string'] = $fullVersionString;
				}
				else {
					$retval = $fullVersionString;
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve version string");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve version information");
		}
		
		return($retval);
	}//end get_version()
	//=========================================================================
	
	
	
	//=========================================================================
	final public function get_project() {
		$retval = NULL;
		$versionFileLocation = dirname(__FILE__) .'/VERSION';
		if(file_exists($versionFileLocation)) {
			$myData = file($versionFileLocation);
			
			//set the logical line number that the version string is on, and 
			//	drop by one to get the corresponding array index.
			$lineOfProject = 4;
			$arrayIndex = $lineOfProject -1;
			
			$myProject = trim($myData[$arrayIndex]);
			
			if(preg_match('/^PROJECT: /', $myProject)) {
				$retval = preg_replace('/^PROJECT: /', '', $myProject);
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve project string");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve project information");
		}
		
		return($retval);
	}//end get_project()
	//=========================================================================
	
	
}
?>