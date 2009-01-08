<?php

require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blog.class.php');
require_once(dirname(__FILE__) .'/../lib/cs-blogger/csb_blogList.class.php');
$page->rip_all_block_rows();

//they're just viewing the /blog page; show list of member's blogs.
$fs = new cs_fileSystemClass(CS_BLOGRWDIR);

$page->add_template_var('html_title', 'Member Blogs - CrazedSanity.com');
if(count($sectionArr) == 1 || (count($sectionArr) == 2 && $sectionArr[1] == 'index')) {
	
	$blog = new csb_blogList('/blog');
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
	$blog = new csb_blog($username);
	$myData = $blog->display_blog($sectionArr);
	
	foreach($myData as $tmplVar=>$val) {
		if(!preg_match('/^blog_/', $tmplVar)) {
			$tmplVar = 'blog_'. $tmplVar;
		}
		$page->add_template_var($tmplVar, $val);
	}
	$doNotRipRows = array();
	if($blog->can_access_blog($myData['blog_name'], $_SESSION['auth']['userInfo']['username'])) {
		$doNotRipRows[] = 'editLink';
	}
	$page->rip_all_block_rows('content', $doNotRipRows);
	$page->add_template_var('html_title', ucfirst($username) .'\'s Blog: ' .$myData['title'] .' [CrazedSanity.com]');
}
else {
	// LAST (X) ENTRIES FOR A BLOG
	$page->allow_invalid_urls(TRUE);
	$page->add_template_file('_singleEntry', 'blog/singleEntry.tmpl');
	$username = $sectionArr[1];
	$blog = new csb_blog($username);
	$myData = $blog->display_blog($sectionArr);
	
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
	$page->add_template_var('content', $myParsedRow);
	$page->add_template_var('html_title', ucfirst($username) .'\'s Blog: ' .$myData['title'] .' [CrazedSanity.com]');
}

