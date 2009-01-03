<?php

/**
 * Convert blogs created from the original version of CS-Blogger (no version 
 * number, where it used filenames as the entry text).
 */

require_once(dirname(__FILE__) .'/../../siteConfig.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_phpDB.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_globalFunctions.php');
require_once(dirname(__FILE__) .'/../../cs-content/cs_fileSystemClass.php');



$oldDbParams = array(
	'dbname'	=> CS_BLOGDBNAME,
	'rwDir'		=> CS_SQLITEDBDIR
	
);
$newDbParams = array(
	'host'		=> "localhost",
	'port'		=> 5432,
	'dbname'	=> "cs",
	'user'		=> "postgres",
	'password'	=> ""
);


$obj = new converter($newDbParams, $oldDbParams);

class converter {
	
	/** Connection to the old SQLite database */
	private $oldDb;
	
	/** Connection to the new PostgreSQL database */
	private $newDb;
	
	/** Object to interact with the filesystem. */
	private $fsObj;
	
	/** cs_globalFunctions{} object. */
	private $gfObj;
	
	
	//-------------------------------------------------------------------------
	public function __construct($newDbParms, $oldDbParms) {
		$this->oldDb = new cs_phpDB('sqlite');
		$this->oldDb->connect($oldDbParms);
		
		$this->newDb = new cs_phpDB('pgsql');
		$this->newDb->connect($newDbParms);
		
		$this->fsObj = new cs_fileSystemClass($oldDbParms['rwDir']);
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
	}//end __construct()
	//-------------------------------------------------------------------------
	
}//end converter{}
?>