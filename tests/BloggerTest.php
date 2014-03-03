<?php
/*
 * Created on Jan 13, 2009
 */

//=============================================================================
class testOfCSBlogger extends testDbAbstract {
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		
		//define some constants.
		{
			$this->gfObj = new cs_globalFunctions;
			$this->gfObj->debugPrintOpt=1;
			parent::__construct();
		}
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Method that is called before every test* method.
	 */
	function setUp() {
		
		
		$this->reset_db(dirname(__FILE__) .'/../../cs-webapplibs/setup/schema.pgsql.sql');
		
		$this->blog = new csb_blog($this->dbObj, 'postgres');
		
		$this->assertTrue((bool)$this->blog->run_setup());
		
		parent::setUp();
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Called after every test* method.
	 */
	function tearDown() {
//		parent::tearDown();
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_create_blog() {
		
		$testUser = 'simpletest';
		$testPass = 'test';
		
		$testUserExists = $this->blog->get_uid($testUser);
		$this->assertTrue(($testUserExists == false), "User (" . $testUser . ") already exists (" . $testUserExists . ")");
		$newUid = $this->blog->create_user($testUser, $testPass);
		$this->assertTrue(is_numeric($newUid), "Value of newUid (" . $newUid . ") isn't a number");
//		$this->assertTrue(($newUid > 100), "New UID (" . $newUid . ") isn't greater than 100 as expected");
		
		//Can't figure out why "expectException()" doesn't work, so do it the old-fashioned way.
		try {
			$this->assertFalse($this->blog->create_user($testUser, $testPass), "ERROR: created DUPLICATE USER");
		} catch (exception $e) {
			$this->assertTrue(strlen($e->getMessage()) > 0, $e->getMessage());
		}
		
		$newBlogId = $this->blog->create_blog("test", $newUid, "blog/test");
		$this->assertTrue((bool)$newBlogId, "Failed to create new blog (". $newBlogId .")");
		
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
			$this->assertEquals($result,$expectedResult,
				"Failed on test '". $name ."'... result=(". $result ."), expected=(". $expectedResult .")"
			);
		}
	}//end test_permalink()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_entry() {
		$blogLocation = "/blog/test";
		$myBlogId = $this->blog->create_blog("testing", 'test', $blogLocation);
		$this->assertTrue(is_numeric($myBlogId), "Failed to create temporary blog...");
		
		$newUserId = $this->blog->create_user('test2', 'test');
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
			$this->assertNotEquals(
				$testInputData['initial']['content'],
				$encodedContent,
				"Encoding failed, content the same before encoding as after"
			);
			$this->assertEquals(
				$testInputData['initial']['content'],
				$decodedContent,
				"Contents different after encode + decode..."
			);
			
			//test cleaning.
			$this->assertEquals(
				$encodedContent,
				$this->gfObj->cleanString($encodedContent, 'sql92_insert'),
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
			$this->assertTrue(is_array($createPostRes), "Invalid return, result should be an array");
			
			
			//test permalink stuff (with internal check for duplicate titles).
			$expectedPermalink = $testInputData['expectedPermalink'];
			$expectedFullPermalink = $blogLocation ."/". $createdBlogData['blog_name'] ."/". $expectedPermalink;
			$this->assertEquals(
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
			
			$this->assertEquals(
				$retrievedEntry['permalink'],
				$expectedPermalink,
				"Permalink different than expected.. expected=(". $expectedPermalink ."), actual=(". $retrievedEntry['permalink'] .")"
			);
			$this->assertEquals(
				$retrievedEntry['full_permalink'],
				$expectedFullPermalink,
				"Retrieved full permalink invalid, expected=(". $expectedFullPermalink ."), actual=(". $retrievedEntry['full_permalink'] .")"
			);
			$this->assertEquals(
				$decodedContent,
				$retrievedEntry['content']
			);
			$this->assertEquals(
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
			$this->assertNotEquals($lastGetPermalink, $expectedFullPermalink, "*** FAIL *** ");
			$lastGetPermalink = $expectedFullPermalink;
			$retrievedEntry = $this->blog->get_blog_entry($expectedFullPermalink);
			foreach($testInputData['initial'] as $checkIndex=>$checkValue) {
				$this->assertEquals($checkValue, $retrievedEntry[$checkIndex]);
			}
			
			
			//test updates to the existing entry.
			$updates = $testInputData['update'];
			
			$updateEntryId = $retrievedEntry['entry_id'];
			
			$this->assertEquals($updateEntryId, $expectedId, "Expected id=(". $expectedId ."), got id=(". $updateEntryId .")");
			
			
			
			
			$blogEntry = new csb_blogEntry($this->dbObj, $retrievedEntry['full_permalink']);
			$updateRes = $blogEntry->update_entry($updateEntryId, $updates);
			$this->assertTrue($updateRes, "Failed to update (". $updateRes .")");
			
			//retrieve the updated entry & make sure it's good.
			$blogEntry = $this->blog->get_blog_entry($expectedFullPermalink);
			$this->assertEquals(
				$blogEntry['entry_id'],
				$updateEntryId,
				"Updated entry #". $updateEntryId .", got entry #". $blogEntry['entry_id'] 
					." when using permalink=". $expectedFullPermalink
			);
			$this->assertEquals(
				$blogEntry['content'],
				$updates['content']
			);
			$this->assertEquals(
				$blogEntry['post_timestamp'],
				$updates['post_timestamp'],
				"Failed to update timestamp"
			);
		}
		
		
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
		$locObj = new csb_location($this->dbObj);
		foreach($createBlogData as $blogName=>$info) {
			$theLocation = $locObj->fix_location($info['location']);
			if(isset($locationsArr[$theLocation])) {
				$locationsArr[$theLocation] += 1;
			}
			else {
				$locationsArr[$theLocation] = 1;
			}
			$theUser = $info['userInfo']['username'];
			if(isset($usersArr[$theUser])) {
				$usersArr[] += 1;
			}
			else {
				$usersArr[$theUser] = 1;
			}
			if(isset($entriesPerLocation[$theLocation])) {
				$entriesPerLocation[$theLocation] += count($info['entries']);
			}
			else {
				$entriesPerLocation[$theLocation] = count($info['entries']);
			}
		}
		
		$testLocNum=0;
		foreach($locationsArr as $locName=>$num) {
			$blog = new csb_blogLocation($this->dbObj, $locName);
			
			//get just one entry per blog name.
			$data = $blog->get_most_recent_blogs(1);
			$this->assertEquals(count($data), $num);
			
			//now get a few of the most recent entries from a given location.
			$data = $blog->get_most_recent_blogs(5);
			$checkThis = 0;
			$this->assertEquals(count($data), $locationsArr[$locName]);
			foreach($data as $blogName=>$entries) {
				$keys = array_keys($entries);
				$this->assertFalse(is_numeric($blogName));
				$this->assertTrue(is_numeric($entries[$keys[0]]['blog_id']), "invalid blog_id for entry");
				$checkThis += count($entries);
			}
			$this->assertEquals($entriesPerLocation[$locObj->fix_location($locName)], $checkThis);
			$testLocNum++;
		}
		
	}//end test_blog_list()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	function test_locations() {
		$locObj = new csb_location($this->dbObj);
		
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
			throw new exception(__METHOD__ .": locations already exist... ". $e->getMessage());
		}
		catch(exception $e) {
//			$this->gfObj->debug_print(__METHOD__ .": no pre-existing locations");
		}
		$this->assertEquals(count($existingLocations),0);
		
		$addedLocations = array();
		
		foreach($testThis as $testName=>$data) {
			
			//Test Cleaning...
			$this->assertEquals($locObj->fix_location($data[1]), $data[1], "Unclean test pattern '". $testName ."' (".
					$locObj->fix_location($data[1]) .")");
			$this->assertEquals($locObj->fix_location($data[0]), $data[1], "Cleaning failed for pattern '". $testName ."' (". 
					$locObj->fix_location($data[0]) .")");
			
			//Test adding the locations.
			$newLocId = $locObj->add_location($data[0]);
			if(isset($addedLocations[$data[1]])) {
				$addedLocations[$data[1]]++;
			}
			else {
				$addedLocations[$data[1]] = 1;
			}
			$this->assertTrue(is_numeric($newLocId), "Didn't get valid location_id (". $newLocId .")");
			
			$locArr = $locObj->get_locations();
			$this->assertEquals($locArr[$newLocId], $data[1], "New location (". $locArr[$newLocId] .") does not match expected (". $data[1] .")");
			
			//make sure retrieving the location_id using clean & unclean data works properly.
			$getUncleanLoc	= $locObj->get_location_id($data[0]);
			$getCleanLoc	= $locObj->get_location_id($data[1]);
//			$this->assertEquals($newLocId, $getUncleanLoc);
			$this->assertEquals($getUncleanLoc, $getCleanLoc);
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
				$createRes = $this->blog->create_blog($blogName, $username, $location);
				$user2blog[$username] = $createRes;
				$this->assertTrue(is_numeric($createRes));
			}
			else {
				try {
					$createRes = $this->blog->create_blog($blogName, $username, $location);
					$this->assertTrue(false, "created blog without valid blog name");
				}
				catch(exception $e) {
					$this->assertTrue(true);
				}
			}
		}
		
		//since the third user has no permissions or blogs, let's test them first.
		$testBlog = new csb_blogUser($this->dbObj, 'thirdUser', null);
		
		
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
		
		$this->assertTrue(is_array($userData), "Data retrieved for (". $user .") isn't an array...". $this->gfObj->debug_print($userData,0));
		$this->assertEquals($userData['passwd'], md5($user .'-'. $pass));
		$this->assertEquals($uid, $userData['uid']);
		$this->assertEquals($user, $userData['username']);
		
		
		//test a complex password.
		$user = $user .'-2';
		$pass = __METHOD__ ."!@#$%5^&*()_+-={}[]\\|';:\",./<>?";
		$uid = $this->blog->create_user($user, $pass);
		
		$userData = $this->blog->get_user($user);
		$this->assertTrue(is_array($userData));
		$this->assertEquals($userData['passwd'], md5($user .'-'. $pass));
		$this->assertEquals($uid, $userData['uid']);
		$this->assertEquals($user, $userData['username']);
		
	}//end test_user_authentication()
	//-------------------------------------------------------------------------
	
	
	
	
	
}//end testOfCSBlogger
//=============================================================================

?>
