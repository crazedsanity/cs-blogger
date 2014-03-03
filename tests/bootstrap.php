<?php

require_once(dirname(__FILE__) .'/../AutoLoader.class.php');
require_once(dirname(__FILE__) .'/../../cs-webapplibs/debugFunctions.php');

// set a constant for testing...
define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));
define('RWDIR', constant('UNITTEST__LOCKFILE'));
define('LIBDIR', dirname(__FILE__) .'/..');
define('UNITTEST_ACTIVE', 1);


define('DBCREATED_USER', 'TEST');
define('CSBLOG_SETUP_PENDING', true);
define('DEBUGPRINTOPT', 1);
define('DEBUGREMOVEHR', 1);



// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');

error_reporting(E_ALL);
