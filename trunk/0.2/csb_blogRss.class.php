<?php


class csb_blogRss extends csb_blogAbstract {
	
	protected $dbParams = null;
	
	protected $defaultNumEntries = 5;
	
	protected $basePath = '/rss/channel';
	
	private $entryCounter=0;
	private $headerBuilt=false;
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $dbParams		(array) connection options for database
	 * 
	 * @return (void)		It's a constructor... no exceptions. ;) 
	 */
	public function __construct(array $dbParams=null) {
		
		if(is_array($dbParams)) {
			$this->dbParams = $dbParams;
		}
		
		$this->xmlCreator = new cs_phpxmlCreator('rss');
		$this->xmlCreator->add_attribute('/rss', array('version'=>"2.0"));
		$this->xmlCreator->create_path('/rss/channel');
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function build_header(array $headerTags) {
		$this->xmlCreator->create_path($this->basePath);
		
		// set some of the standard tags.
		$headerTags['webMaster']	= 'cs-blogger-rss-help@crazedsanity.com';				// who to email with problems.
		$headerTags['ttl']			= (60 * 4);												// minutes before feed should be re-checked.
		$headerTags['generator']	= $this->get_project() .' - '. $this->get_version();	// what generated this...
		$headerTags['docs']			= "http://www.rssboard.org/rss-specification";			//go here to see what an rss file is.
		
		foreach($headerTags as $name=>$value) {
			$this->xmlCreator->add_tag($this->basePath .'/'. $name, $value);
		}
		$this->headerBuilt=true;
	}//end build_header()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Build RSS 2.0 content for a single blog.
	 */
	public function build_single_blog_rss($blogName, $numRecentEntries=null, $setTitlePrefix=false) {
		
		$blog = new csb_blog($blogName, $this->dbParams);
		if(!is_numeric($numRecentEntries)) {
			$numRecentEntries = $this->defaultNumEntries;
		}
		$myData = $blog->get_recent_blogs($numRecentEntries);
		
		
		$siteUrl = "http://". $_SERVER['HTTP_HOST'];
		if(!$this->headerBuilt) {
			//build the RSS (2.0) header.
			$keys = array_keys($myData);
			$keyData = $myData[$keys[0]];
			
			//for info on these tags, check out http://www.rssboard.org/rss-specification
			$blogUrl = $siteUrl .'/blog';
			$headerTags = array(
				'title'				=> $keyData['blog_display_name'],		//
				'link'				=> $blogUrl .'/'. $keyData['blog_name'],		//link to the blog.
				'description'		=> $keyData['blog_display_name'] .' on '. $_SERVER['HTTP_HOST'],		//description of the blog
				//'lastBuildDate'		=> "",		//last date the content changed (date of most recent blog)
				//'managingEditor'	=> "",		// email address of author... TODO: retrieve user's email address as well...
			);
			
			
			$this->build_header($headerTags);
		}
		
		$itemPath = $this->basePath .'/item';
		$this->xmlCreator->create_path($itemPath);
		foreach($myData as $entryData) {
			//title, link, description, pubDate, guid (Global UID)
			
			$myPath = $itemPath .'/'. $this->entryCounter;
			$this->xmlCreator->add_tag($myPath);
			
			$title = $entryData['title'];
			if($setTitlePrefix) {
				$title = $entryData['blog_display_name'] .' - '. $title;
			}
			$this->xmlCreator->add_tag($myPath .'/title', $title);
			$this->xmlCreator->add_tag($myPath .'/link', $siteUrl . $entryData['full_permalink']);
			
			//TODO: truncate the content and append a link to the end... or omit the story contents altogether.
			//$this->xmlCreator->add_tag($myPath .'/description', $entryData['content']);
			
			$this->xmlCreator->add_tag($myPath .'/pubDate', $entryData['post_timestamp']);
			$this->xmlCreator->add_tag($myPath .'/guid', $_SERVER['HTTP_HOST'] .'-'. $entryData['blog_name'] .'-'. $entryData['blog_id'] .'-'. $entryData['entry_id'], array('isPermaLink'=>"false"));
			
			$this->entryCounter++;
		}
		$this->xmlCreator->set_tag_as_multiple($itemPath);//so there are multiple
		
		return($this->xmlCreator->create_xml_string(true));
	}//end build_single_blog_rss()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function build_blog_location_rss($location, $title, $description) {
		$blog = new csb_blogLocation($location, $this->dbParams);
		
		$headerTags = array(
			'title'			=> $title,
			'link'			=> "http://". $_SERVER['HTTP_HOST'] .'/blog',
			'description'	=> $description
		);
		$this->build_header($headerTags);
		
		foreach($blog->validBlogs as $blogId => $blogData) {
			try {
				$name = $blogData['blog_name'];
				$this->build_single_blog_rss($name, 1, true);
			}
			catch(exception $e) {
				//nothing to see here...
			}
		}
		
		return($this->xmlCreator->create_xml_string(true));
	}//end build_blog_location_rss()
	//-------------------------------------------------------------------------
	
	
	
}// end blog{}
?>
