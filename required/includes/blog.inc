<?php

require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blog.class.php');
require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blogLocation.class.php');
require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blogUser.class.php');
require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blogEntry.class.php');

$page->rip_all_block_rows();

$page->add_template_var('html_title', 'Member Blogs - CrazedSanity.com');
if(count($sectionArr) == 1 || (count($sectionArr) == 2 && $sectionArr[1] == 'index')) {
	//show most recent entries for all blogs.
	$blog = new csb_blogLocation('/blog');
	$myData = $blog->get_most_recent_blogs(5);
	
	$mainRowTmpl = $page->templateRows['blogUserLink'];
	$subRowTmpl = $page->templateRows['blogUserLink_single'];
	foreach($myData as $blogName=>$entries) {
		$mySubRow = "";
		foreach($entries as $id=>$details) {
			$mySubRow .= $page->mini_parser($subRowTmpl, $details, '%%', '%%');
		}
		$repArr = array(
			'blogname'				=> $blogName,
			'formattedBlogname'	=> ucwords($blogName),
			'blogUserLink_single'	=> $mySubRow
		);
		$myRow .= $page->mini_parser($mainRowTmpl, $repArr, '{', '}');
	}
	$page->add_template_var('blogUserLink', $myRow);
}
elseif(count($sectionArr) == 3) {
	// A SINGLE ENTRY
	$page->allow_invalid_urls(TRUE);
	$page->add_template_file('content', 'blog/singleEntry.tmpl');
	
	$username = $sectionArr[1];
	
	$fullPermalink = $page->gfObj->string_from_array($sectionArr, null, '/');
	$blog = new csb_blogEntry($fullPermalink);
	$myData = $blog->display_blog($sectionArr);
	$mainBlogData = $blog->get_blog_data_by_name($username);
	
	foreach($myData as $tmplVar=>$val) {
		if(!preg_match('/^blog_/', $tmplVar)) {
			$tmplVar = 'blog_'. $tmplVar;
		}
		$page->add_template_var($tmplVar, $val);
	}
	$doNotRipRows = array();
	$permObj = new csb_permission();
	if(strlen($_SESSION['auth']['userInfo']['username'])) {
		$checkUid = $permObj->get_uid($_SESSION['auth']['userInfo']['username']);
		if($permObj->can_access_blog($mainBlogData['blog_id'], $checkUid)) {
			$doNotRipRows[] = 'editLink';
		}
	}
	$page->rip_all_block_rows('content', $doNotRipRows);
	$page->add_template_var('html_title', $mainBlogData['display_blog_name'] .': ' .$myData['title'] .' [CrazedSanity.com]');
}
else {
	// LAST (X) ENTRIES FOR A BLOG
	$page->allow_invalid_urls(TRUE);
	$page->add_template_file('_singleEntry', 'blog/singleEntry.tmpl');
	$blogName = $sectionArr[1];
	$blog = new csb_blog($blogName);
	$myData = $blog->get_recent_blogs();
	
	$entryRow = $page->templateVars['_singleEntry'];
	foreach($myData as $entryId=>$entryData) {
		foreach($entryData as $k=>$v) {
			if(!preg_match('/^blog_/', $k)) {
				$entryData['blog_'. $k] = $v;
				unset($entryData[$k]);
			}
		}
		$myParsedRow .= $page->mini_parser($entryRow, $entryData, '{', '}');
		$myParsedRow .= "\n<HR><!-- END OF entry... -->";
	}
	$doNotRipRows = array();
	$permObj = new csb_permission();
	if($permObj->can_access_blog($blogName, $_SESSION['auth']['userInfo']['username'])) {
		$doNotRipRows[] = 'editLink';
	}
	
	$mainBlogData = $blog->get_blog_data_by_name($blogName);
	$page->add_template_var('html_title', $mainBlogData['blog_display_name'] .' -- Last 5 Entries [CrazedSanity.com]');
	
	$page->add_template_var('content', $myParsedRow);
	$page->rip_all_block_rows('content', $doNotRipRows);
}

