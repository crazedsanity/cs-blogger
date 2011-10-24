<?php
/*
 * Created on Jan 13, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */


require_once(dirname(__FILE__) .'/testOfCSBlogger.php');

$test = &new testOfCSBlogger();
$test->run(new HtmlReporter());

?>
