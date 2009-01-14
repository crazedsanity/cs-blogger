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




//=============================================================================
class TestOfBlogger extends UnitTestCase {
	
	
	//-------------------------------------------------------------------------
	function __construct() {
		$this->UnitTestCase();
		require_once(dirname(__FILE__) .'/../csb_blog.class.php');
		require_once(dirname(__FILE__) .'/../csb_blogUser.class.php');
		require_once(dirname(__FILE__) .'/../csb_blogLocation.class.php');
		require_once(dirname(__FILE__) .'/../csb_blogEntry.class.php');
		
		//define some constants.
		{
			//Username we'll use in this class.
			define("TEST_USER", 'simpletest');
			
			//created during run_setup() in the schema file.
			define("DBCREATED_USER", 'test');
			
			//Tell the system that setup is still pending (delay retrieving some data until after setup)
			define('CSBLOG_SETUP_PENDING', true);
			
			define('DEBUGPRINTOPT', 1);
			define('DEBUGREMOVEHR', 1);
		}
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Method that is called before every test* method.
	 */
	function setUp() {
		
		//NOTE::: all of this very much assumes things are running inside a
		//	transaction, started within the schema file.  If not, the next
		//	run will definitely fail straight away.
		
		//setup a new, temporary blog.
		define('CSBLOG__DBTYPE', 'pgsql');
		#define('CSBLOG__RWDIR', dirname(__FILE__) .'/../../rw/testblogger');
		#define('CSBLOG__DBNAME', 'simpletestblog.db');
		
		define('CSBLOG_DB_HOST',		'localhost');
		define('CSBLOG_DB_PORT',		'5432');
		define('CSBLOG_DB_DBNAME',		'csblogger_test');
		define('CSBLOG_DB_USER',		'postgres');
		define('CSbLOG_DB_PASSWORD',	'');
		$this->connParams = array(
			'host'		=> "localhost",
			'port'		=> "5432",
			'dbname'	=> "csblogger_test",
			'user'		=> "postgres",
			'password'	=> ""
		);
		
		
		
		//setup some local objects for use later.
		$this->gf = new cs_globalFunctions;
		$this->gf->debugPrintOpt=1;
		$this->fs = new cs_fileSystemClass(CSBLOG__RWDIR);
		
		$this->assertTrue(function_exists('pg_connect'));
		
		$this->blog = new csb_blog('test', $this->connParams);
		
		$this->blog->db->beginTrans();
		$this->assertTrue($this->blog->run_setup());
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Called after every test* method.
	 */
	function tearDown() {
		$this->blog->db->rollbackTrans();
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_create_blog() {
		
		$testUser = 'simpletest';
		$testPass = 'test';
		
		$testUserExists = $this->blog->get_uid($testUser);
		if($this->assertTrue(($testUserExists==false), "User (". $testUser .") already exists (". $testUserExists .")")) {
			$newUid = $this->blog->create_user($testUser, $testPass);
			$this->assertTrue(is_numeric($newUid), "Value of newUid (". $newUid .") isn't a number");
			$this->assertTrue(($newUid > 100), "New UID (". $newUid .") isn't greater than 100 as expected");
			
			//Can't figure out why "expectException()" doesn't work, so do it the old-fashioned way.
			try {
				$this->assertFalse($this->blog->create_user($testUser, $testPass), "ERROR: created DUPLICATE USER");
			}
			catch(exception $e) {
				$this->assertTrue(strlen($e->getMessage()), $e->getMessage());
			}
			
			$newBlogId = $this->blog->create_blog("test", $newUid, "blog/test");
			$this->assertTrue($newBlogId, "Failed to create new blog (". $newBlogId .")");
		}
		else {
			throw new exception(__METHOD__ .": FATAL: unable to create new user (". $testUser .")");
		}
		
	}//end test_create_blog()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_permalink() {
		
		$expectedResult = "my_big_long_title";
		
		$testArr = array(
			'quotes'		=> "My \'\"Big Long \'\' title",
			'underscores'	=> "My_Big_Long___title",
			'punctuations'	=> "M!y !!Big!!-%^Long@#\$#()_**+=_;:'\"/?.>,<Title",
		);
		
		foreach($testArr as $name => $test) {
			$result = $this->blog->create_permalink_from_title($test);
			$this->assertEqual($result,$expectedResult,
				"Failed on test '". $name ."'... result=(". $result ."), expected=(". $expectedResult .")"
			);
		}
	}//end test_permalink()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_entry() {
		$blogLocation = "/blog/test";
		$myBlogId = $this->blog->create_blog("testing", DBCREATED_USER, $blogLocation);
		$this->assertTrue(is_numeric($myBlogId), "Failed to create temporary blog...");
		
		$newUserId = $this->blog->create_user(TEST_USER, 'test');
		$this->assertTrue(is_numeric($newUserId), "Failed to create test user...");
		
		$createdBlogData = $this->blog->get_blog_data_by_id($myBlogId);
		
		
		$testEntryArr = array(
			1	=> array(
				'initial'	=> array(
					'title'		=> "Test Duplicate entry",
					'content'	=> " ___#1___ This is 'my' test data... Symbols: \\, !@#$%&*()_+-=<>?,./[]`~{}|"
				),
				'update'	=> array(
					'post_timestamp'	=> '2008-12-08 13:18:00',
					'garbage'			=> "This is garbage, should not be in the final data array.",
					'content'			=> " ___#1___ UPDATED entry!!!"
				),
				'expectedPermalink'	=> "test_duplicate_entry"
			),
			2	=> array(
				'initial'	=> array(
					'title'		=> "test Duplicate Entry",
					'content'	=> " ___#2___ Second entry's text..."
				),
				'update'	=> array(
					'post_timestamp'	=> '2008-12-07 13:18:00',
					'garbage'			=> "This is garbage, should not be in the final data array.",
					'content'			=> " ___#2___ UPDATED entry!!! -- asdfkasd;flaksd;fkasd"
				),
				'expectedPermalink'	=> "test_duplicate_entry-1"
			),
			3	=> array(
				'initial'	=> array(
					'title'		=> "TEst_duplIcate Entry",
					'content'	=> " ___#3___ This one should have a permalink like the first two, even though the title looks different."
				),
				'update'	=> array(
					'post_timestamp'	=> '2008-12-09 13:18:00',
					'garbage'			=> "This is garbage, should not be in the final data array.",
					'content'			=> " ___#3___ UPDATED entry!!! stuff is different here, yo.  '' \#@8df---"
				),
				'expectedPermalink'	=> "test_duplicate_entry-2"
			)
		);
		
		
		
		foreach($testEntryArr as $expectedId=>$testInputData) {
			
			//test encoding & decoding the contents.
			$encodedContent = $this->blog->encode_content($testInputData['initial']['content']);
			$decodedContent = $this->blog->decode_content($encodedContent);
			$this->assertNotEqual(
				$testInputData['initial']['content'],
				$encodedContent,
				"Encoding failed, content the same before encoding as after"
			);
			$this->assertEqual(
				$testInputData['initial']['content'],
				$decodedContent,
				"Contents different after encode + decode..."
			);
			
			//test cleaning.
			$this->assertEqual(
				$encodedContent,
				$this->gf->cleanString($encodedContent, 'sql92_insert'),
				"Content cleaning failed: encoded content was changed by cleanString()"
			);
			
			//test creation of new posts.
			$createPostRes = $this->blog->create_entry(
				$myBlogId,
				$newUserId,
				$testInputData['initial']['title'],
				$testInputData['initial']['content'],
				$testInputData['update']
			);
			
			//make sure it has the expected indexes.
			$expectedResIndexes = array("entry_id", "full_permalink");
			foreach($expectedResIndexes as $indexName) {
				$this->assertTrue(
					isset($createPostRes[$indexName]),
					"Results array (from blog::create_entry()) missing expected index (". $indexName .")"
				);
			}
			$this->assertTrue(is_array($createPostRes), "Invalid return (". $createPostRes ."), should be an array");
			
			
			//test permalink stuff (with internal check for duplicate titles).
			$expectedPermalink = $testInputData['expectedPermalink'];
			$expectedFullPermalink = $blogLocation ."/". $createdBlogData['blog_name'] ."/". $expectedPermalink;
			$this->assertEqual(
				$expectedFullPermalink,
				$createPostRes['full_permalink'],
				"Permalink seems malformed, expected=(". $expectedFullPermalink ."), actual=(". $createPostRes['full_permalink'] .")"
			);
			
			
			//retrieve the post & make sure it has everything we expect it to.
			$retrievedEntry = $this->blog->get_blog_entry($createPostRes['full_permalink']);
			$expectedResIndexes = array('title', 'post_timestamp', 'content', 'permalink', 'title', 'full_permalink');
			foreach($expectedResIndexes as $indexName) {
				$this->assertTrue(isset($retrievedEntry[$indexName]), "Data returned lacks index (". $indexName .")");
			}
			
			$this->assertEqual(
				$retrievedEntry['permalink'],
				$expectedPermalink,
				"Permalink different than expected.. expected=(". $expectedPermalink ."), actual=(". $retrievedEntry['permlink'] .")"
			);
			$this->assertEqual(
				$retrievedEntry['full_permalink'],
				$expectedFullPermalink,
				"Retrieved full permalink invalid, expected=(". $expectedFullPermalink ."), actual=(". $retrievedEntry['full_permalink'] .")"
			);
			$this->assertEqual(
				$decodedContent,
				$retrievedEntry['content']
			);
			$this->assertEqual(
				$retrievedEntry['content'],
				$testInputData['initial']['content'],
				"Original content doesn't match retrieved blog content"
			);
		}//end foreach
		
		
		//run updates separately for extra checking.
		$lastGetPermalink = "";
		foreach($testEntryArr as $expectedId=>$testInputData) {
			
			$expectedPermalink = $testInputData['expectedPermalink'];
			$expectedFullPermalink = $blogLocation ."/". $createdBlogData['blog_name'] ."/". $expectedPermalink;
			
			//as an extra check, let's test that the data we retrieve is the data that is actually in the database.
			$this->assertNotEqual($lastGetPermalink, $expectedFullPermalink, "*** FAIL *** ");
			$lastGetPermalink = $expectedFullPermalink;
			$retrievedEntry = $this->blog->get_blog_entry($expectedFullPermalink);
			foreach($testInputData['initial'] as $checkIndex=>$checkValue) {
				$this->assertEqual($checkValue, $retrievedEntry[$checkIndex]);
			}
			
			
			//test updates to the existing entry.
			$updates = $testInputData['update'];
			
			$updateEntryId = $retrievedEntry['entry_id'];
			
			$this->assertEqual($updateEntryId, $expectedId, "Expected id=(". $expectedId ."), got id=(". $updateEntryId .")");
			
			
			
			
			$blogEntry = new csb_blogEntry($retrievedEntry['full_permalink'], $this->connParams);
			$updateRes = $blogEntry->update_entry($updateEntryId, $updates);
			$this->assertTrue($updateRes, "Failed to update (". $updateRes .")");
			
			//retrieve the updated entry & make sure it's good.
			$blogEntry = $this->blog->get_blog_entry($expectedFullPermalink);
			$this->assertEqual(
				$blogEntry['entry_id'],
				$updateEntryId,
				"Updated entry #". $updateEntryId .", got entry #". $blogEntry['entry_id'] 
					." when using permalink=". $expectedFullPermalink
			);
			$this->assertEqual(
				$blogEntry['content'],
				$updates['content']
			);
			$this->assertEqual(
				$blogEntry['update_timestamp'],
				$updates['update_timestamp'],
				"Failed to update timestamp"
			);
			$this->assertNotEqual(
				$blogEntry['author_uid'],
				$updates['author_uid'],
				"Should not be able to update author_uid"
			);
		}
		
		//now let's test that we can retrieve ONLY the most recent entry.
		
		
	}//end test_entry()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_blog_list() {
		//Create some blogs so we have something to work with.
		$createBlogData = array(
			"Slaughter's Blog"	=> array(
				'location'	=> "blog/test",
				'userInfo'	=> array(
					'username'	=> "slaughter",
					'password'	=> "Scr3wOff"
				),
				'entries'	=> array(
					array(
						'title'				=> "My First Post",
						'content'			=> "The first post ever... but timestamp will be later.",
						'post_timestamp'	=> "2008-12-31 10:00:00"
					),
					array(
						'title'				=> "Second Post",
						'content'			=> "The second post, but post timestamp will be earlier.",
						'post_timestamp'	=> "2007-01-01 14:00:00"
					)
				)
			),
			"Help's Blog"		=> array(
				'location'	=> "/blog/test",
				'userInfo'	=> array(
					'username'	=> "help",
					'password'	=> "H3lpMEeEJ@ckass"
				),
				'entries'	=> array(
					array(
						'title'				=> "The New Help Blog",
						'content'			=> "Go here for help with everything.",
						'post_timestamp'	=> "2008-10-10 10:10:10"
					),
					array(
						'title'				=> "Server Problems",
						'content'			=> "The server had problems, but it is now fixed.",
						'post_timestamp'	=> "2008-12-12 12:12:12"
					)
				)
			),
			"Stupid's Blog"		=> array(
				'location'	=> "/blog/crap",
				'userInfo'	=> array(
					'username'	=> "stupid",
					'password'	=> "H3lpMEeEJ@ckass"
				),
				'entries'	=> array(
					array(
						'title'				=> "The New STOOPID Blog",
						'content'			=> "For all things stupid.",
						'post_timestamp'	=> "2008-10-11 15:14:14"
					),
					array(
						'title'				=> "My next really dumb blog.",
						'content'			=> "yeah, it's really stupid.",
						'post_timestamp'	=> "2008-12-14 10:11:12"
					)
				)
			)
		);
		
		//create some blogs.
		foreach($createBlogData as $blogDisplayName=>$blogInfo) {
			//create the user.
			$createdUid = $this->blog->create_user($blogInfo['userInfo']['username'], $blogInfo['userInfo']['password']);
			$this->assertTrue(is_numeric($createdUid));
			
			//create the blog.
			$newBlogId = $this->blog->create_blog($blogDisplayName, $createdUid, $blogInfo['location']);
			$this->assertTrue(is_numeric($newBlogId));
			
			//now create all the entries.
			foreach($blogInfo['entries'] as $entryData) {
				$extraUpdates = array(
					'post_timestamp'	=> $entryData['post_timestamp']
				);
				$newBlogData = $this->blog->create_entry($newBlogId, $createdUid, $entryData['title'], $entryData['content'], $extraUpdates);
				$this->assertTrue(is_array($newBlogData));
			}
		}
		
		
		//do some counting & stuff.
		$locationsArr = array();
		$usersArr = array();
		$entriesPerLocation = array();
		$locObj = new csb_location();
		foreach($createBlogData as $blogName=>$info) {
			$locationsArr[$locObj->fix_location($info['location'])] += 1;
			$usersArr[$info['userInfo']['username']] += 1;
			$entriesPerLocation[$locObj->fix_location($info['location'])] += count($info['entries']);
		}
		
		$testLocNum=0;
		foreach($locationsArr as $locName=>$num) {
			$this->gf->debug_print(__METHOD__ .": loop #". $testLocNum);
			$blog = new csb_blogLocation($locName, $this->connParams);
			
			//get just one entry per blog name.
			$data = $blog->get_most_recent_blogs(1);
			$this->assertEqual(count($data), $num);
			
			//now get a few of the most recent entries from a given location.
			$data = $blog->get_most_recent_blogs(5);
			$checkThis = 0;
			$this->assertEqual(count($data), $locationsArr[$locName]);
			foreach($data as $blogName=>$entries) {
				$keys = array_keys($entries);
				$this->assertFalse(is_numeric($blogName));
				$this->assertTrue(is_numeric($entries[$keys[0]]['blog_id']), "invalid blog_id for entry");
				$checkThis += count($entries);
			}
			$this->assertEqual($entriesPerLocation[$locObj->fix_location($locName)], $checkThis);
			$testLocNum++;
		}
		
	}//end test_blog_list()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_locations() {
		$locObj = new csb_location($this->connParams);
		
		//FORMAT: <name>=>array(test,expected)
		$testThis = array(
			'multipleSlashes'	=> array('////x//y/z///a//b/////////////c/', '/x/y/z/a/b/c'),
			'noBeginningSlash'	=> array('x/y/test/you', '/x/y/test/you'),
			'endingSlash'		=> array('/x/y/', '/x/y'),
			'invalidCharacters'	=> array('/x__354-x@2/3/@/#!%^/&*/()+=@/', '/x__354-x2/3')
		);
		
		$existingLocations = array();
		try {
			$existingLocations = $locObj->get_locations();
			$this->gf->debug_print(__METHOD__ .": locations already exist... ". $this->gf->debug_print($existingLocations,0));
			throw new exception(__METHOD__ .": locations already exist... ". $e->getMessage());
		}
		catch(exception $e) {
			$this->gf->debug_print(__METHOD__ .": no pre-existing locations");
		}
		$this->assertEqual(count($existingLocations),0);
		
		$addedLocations = array();
		
		foreach($testThis as $testName=>$data) {
			
			//Test Cleaning...
			$this->assertEqual($locObj->fix_location($data[1]), $data[1], "Unclean test pattern '". $testName ."' (".
					$locObj->fix_location($data[1]) .")");
			$this->assertEqual($locObj->fix_location($data[0]), $data[1], "Cleaning failed for pattern '". $testName ."' (". 
					$locObj->fix_location($data[0]) .")");
			
			//Test adding the locations.
			$newLocId = $locObj->add_location($data[0]);
			$addedLocations[$data[1]]++;
			$this->assertTrue(is_numeric($newLocId), "Didn't get valid location_id (". $newLocId .")");
			
			$locArr = $locObj->get_locations();
			$this->assertEqual($locArr[$newLocId], $data[1], "New location (". $locArr[$newLocId] .") does not match expected (". $data[1] .")");
			
			//make sure retrieving the location_id using clean & unclean data works properly.
			$getUncleanLoc	= $locObj->get_location_id($data[0]);
			$getCleanLoc	= $locObj->get_location_id($data[1]);
			$this->assertEqual($newLocId, $getUncleanLoc);
			$this->assertEqual($getUncleanLoc, $getCleanLoc);
		}
	}//end test_locations()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_permissions() {
		$testUsers = array(
			'firstUser'		=> "First Blog EVAR  first user",
			'secondUser'	=> "Second Blog ever, second user",
			'thirdUser'		=> null
		);
		$testBlogs = array(
			'My Blog'
		);
		$location = "/test/perms";
		
		$user2uid = array();
		$user2blog = array();
		foreach($testUsers as $username => $blogName) {
			$user2uid[$username] = $this->blog->create_user($username, "crapT4stic");
			$this->assertTrue(is_numeric($user2uid[$username]));
			
			if(!is_null($blogName)) {
				$user2blog[$username] = $this->blog->create_blog($blogName, $username, $location);
			}
			else {
				$this->gf->debug_print(__METHOD__ .": skipping user (". $username ."), no blogName");
			}
		}
		
		//since the third user has no permissions or blogs, let's test them first.
		$testBlog = new csb_blogUser('thirdUser', null, $this->connParams);
		
		
	}//end test_permissions()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_user_authentication() {
		//test basic creation & password verification.
		$user = preg_replace("/:/", "_", __METHOD__ ."-firstUser");
		$pass = __METHOD__ ."-testing@veryERQ32423LoNgPASs";
		$uid = $this->blog->create_user($user, $pass);
		
		$this->assertTrue(is_numeric($uid));
		$userData = $this->blog->get_user($user);
		
		$this->assertTrue(is_array($userData), "Data retrieved for (". $user .") isn't an array...". $this->gf->debug_print($userData,0));
		$this->assertEqual($userData['passwd'], md5($user .'-'. $pass));
		$this->assertEqual($uid, $userData['uid']);
		$this->assertEqual($user, $userData['username']);
		
		
		//test a complex password.
		$user = $user .'-2';
		$pass = __METHOD__ ."!@#$%5^&*()_+-={}[]\\|';:\",./<>?";
		$uid = $this->blog->create_user($user, $pass);
		
		$userData = $this->blog->get_user($user);
		$this->assertTrue(is_array($userData));
		$this->assertEqual($userData['passwd'], md5($user .'-'. $pass));
		$this->assertEqual($uid, $userData['uid']);
		$this->assertEqual($user, $userData['username']);
		
	}//end test_user_authentication()
	//-------------------------------------------------------------------------
	
	
	
	
}//end TestOfBlogger
//=============================================================================

?>
