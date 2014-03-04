<?php


class csb_blogRss extends csb_blogAbstract {
	
	public $db = null;
	private $xml = null;
	
	protected $defaultNumEntries = 5;
	
	protected $basePath = '/rss/channel';
	
	private $entryCounter=0;
	private $headerBuilt=false;
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $db			(cs_phpDB) database object
	 * 
	 * @return (void)		It's a constructor... no exceptions. ;) 
	 */
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->xml = new SimpleXMLElement('<rss version="2.0" encoding="utf-8"></rss>');
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function build_header(array $headerTags) {
		// set some of the standard tags.
		
		
		$headerTags['webMaster']	= 'cs-blogger-rss-help@crazedsanity.com';				// who to email with problems.
		$headerTags['ttl']			= (60 * 4);												// minutes before feed should be re-checked.
		$headerTags['generator']	= $this->get_project() .' - '. $this->get_version();	// what generated this...
		$headerTags['docs']			= "http://www.rssboard.org/rss-specification";			//go here to see what an rss file is.
//		$headerTags['pubDate']		= date(DATE_RSS);										//date it was published...
		
		$this->xml->addChild('channel');
		foreach($headerTags as $name=>$value) {
			$this->xml->channel->addChild($name, $value);
		}
		$this->headerBuilt=true;
	}//end build_header()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Build RSS 2.0 content for a single blog.
	 */
	public function build_single_blog_rss($blogName, $numRecentEntries=null, $setTitlePrefix=false) {
		
		$blog = new csb_blog($this->db, $blogName);
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
		
		foreach($myData as $entryData) {
			//title, link, description, pubDate, guid (Global UID)
			
			$item = $this->xml->channel->addChild('item');
			
			$title = $entryData['title'];
			if($setTitlePrefix) {
				$title = $entryData['blog_display_name'] .' - '. $title;
			}
			$item->addChild('title', $title);
			$item->addChild('link', $siteUrl . $entryData['full_permalink']);
			
			$item->addChild('pubDate', $entryData['post_timestamp']);
			$item->addChild('guid', $_SERVER['HTTP_HOST'] .'-'. $entryData['blog_name'] .'-'. $entryData['blog_id'] .'-'. $entryData['entry_id'], array('isPermaLink'=>"false"));
			
			$this->entryCounter++;
		}
		
		return("$this->xml");
	}//end build_single_blog_rss()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function build_blog_location_rss($location, $title, $description) {
		$blog = new csb_blogLocation($this->db, $location);
		
		$headerTags = array(
			'title'			=> $title,
			'link'			=> "http://". $_SERVER['HTTP_HOST'] .'/blog',
			'description'	=> $description
		);
		$this->build_header($headerTags);
		
		foreach($blog->validBlogs as $blogData) {
			try {
				$name = $blogData['blog_name'];
				$this->build_single_blog_rss($name, 1, true);
			}
			catch(exception $e) {
				//nothing to see here...
			}
		}
		
		return("$this->xml");
	}//end build_blog_location_rss()
	//-------------------------------------------------------------------------
	
	
	
}// end blog{}
