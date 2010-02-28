<?php
/**
 * Tumblr Dashboard RSS Feed
 * 
 * features: valid rss, cache-control, conditional get, easy config
 * requires: curl, dom, simplexml
 * @package Feeds
 * @author PJ Kix <pj@pjkix.com>
 * @copyright (cc) 2010 pjkix
 * @license http://creativecommons.org/licenses/by-nc-nd/3.0/
 * @see http://www.tumblr.com/docs/en/api
 * @version 1.0.4 $Id:$
 * @todo post types, make it more secure, multi-user friendly, compression
 */

//* debug
ini_set('display_errors', true) ;
error_reporting (E_ALL | E_STRICT) ;
//*/

/** Authorization info */
$tumblr_email    = 'email@example.com';
$tumblr_password = 'password';

/** read config ... if available */
if ( file_exists('config.ini') ) {
	$config = parse_ini_file('config.ini', true);
	$tumblr_email = $config['tumblr']['email'];
	$tumblr_password = $config['tumblr']['password'];
}

// default to GMT for dates
// date_default_timezone_set('GMT');

fetch_tumblr_dashboard_xml($tumblr_email, $tumblr_password);

/** Functions
 ------------------------------------- */

/**
 * Tumbler Dashboard API Read
 *
 * @param string $email tumblr account email address
 * @param string $password tumblr account password
 * @return void
 */
function fetch_tumblr_dashboard_xml($email, $password) {
	// Prepare POST request
	$request_data = http_build_query(
	    array(
	        'email'     => $email,
	        'password'  => $password,
	        'generator' => 'tumblr Dashboard Feed 1.0',
	        'num' => '50'
	    )
	);

	// Send the POST request (with cURL)
	$ch = curl_init('http://www.tumblr.com/api/dashboard');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	// Check for success
	if ($status == 200) {
		// echo "Success! The output is $result.\n";
		$posts = read_xml($result);
		output_rss($posts);
		// cache xml file ... check last mod & serve static
	} else if ($status == 403) {
		echo 'Bad email or password';
	} else if ($status == 503) {
		echo 'Rate Limit Exceded or Service Down';
	} else {
		echo "Error: $result\n";
	}
}

/**
 * parse tumblr dashboard xml
 *
 * @param string $result curl result string
 * @return array $posts array of posts for rss
 */
function read_xml($result)
{
	// $xml = simplexml_load_string($result);
	$xml = new SimpleXMLElement($result);
	// var_dump($xml);die;
	$posts = array();
	$i = 0;
	foreach ($xml->posts->post as $post) {
		// var_dump($post);
		$posts[$i]['title'] = $post['slug']; // wish there was a real title
		$posts[$i]['description'] = $post['type']; // maybe do somehting intelligent with type
		$posts[$i]['link'] = $post['url-with-slug'];
		$posts[$i]['date'] = date(DATE_RSS, strtotime($post['date']) );
		$i++;
	}
	// var_dump($posts);
	return $posts;
}


/**
 * generate rss feed output
 *
 * @param array $items post item array
 * @return void
 */
function output_rss ($posts)
{
	if (!is_array($posts)) die('no posts ...');
	$lastmod = strtotime($posts[0]['date']);
	
	// http headers
	header('Content-type: text/xml'); // set mime ... application/rss+xml
	header('Cache-Control: max-age=300, must-revalidate'); // cache control 5 mins
	header('Last-Modified: ' . gmdate('D, j M Y H:i:s T', $lastmod) ); //D, j M Y H:i:s T
	header('Expires: ' . gmdate('D, j M Y H:i:s T', time() + 300));

	// conditional get ... 
	$ifmod = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] === gmdate('D, j M Y H:i:s T', $lastmod) : false; 
	if ( false !== $ifmod ) {
		header('HTTP/1.0 304 Not Modified'); 
		exit; 
	}

	// build rss using dom
	$dom = new DomDocument();
	$dom->formatOutput = true;
	$dom->encoding = 'utf-8';

	$rss = $dom->createElement('rss');
	$rss->setAttribute('version', '2.0');
	$rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
	$dom->appendChild($rss);

	$channel = $dom->createElement('channel');
	$rss->appendChild($channel);

	// set up feed properties
	$title = $dom->createElement('title', 'My Tumblr Dashboard Feed');
	$channel->appendChild($title);
	$link = $dom->createElement('link', 'http://tumblr.com/dashboard');
	$channel->appendChild($link);
	$description = $dom->createElement('description', 'My tumblr dashboard feed');
	$channel->appendChild($description);
	$language = $dom->createElement('language', 'en-us');
	$channel->appendChild($language);
	$pubDate = $dom->createElement('pubDate', $posts[0]['date'] );
	$channel->appendChild($pubDate);
	$lastBuild = $dom->createElement('lastBuildDate', date(DATE_RSS) );
	$channel->appendChild($lastBuild);
	$docs = $dom->createElement('docs', 'http://blogs.law.harvard.edu/tech/rss' );
	$channel->appendChild($docs);
	$generator = $dom->createElement('generator', 'Tumbler API' );
	$channel->appendChild($generator);
	$managingEditor = $dom->createElement('managingEditor', 'editor@example.com (editor)' );
	$channel->appendChild($managingEditor);
	$webMaster = $dom->createElement('webMaster', 'webmaster@example.com (webmaster)' );
	$channel->appendChild($webMaster);
	$self = $dom->createElement('atom:link');
	$self->setAttribute('href', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
	$self->setAttribute('rel', 'self');
	$self->setAttribute('type', 'application/rss+xml');
	$channel->appendChild($self);

	// add items
	foreach( $posts as $post )
	{
		$item = $dom->createElement( "item" );

		$link = $dom->createElement( 'link', $post['link'] );
		// $link->appendChild( $dom->createTextNode( $item['link'] ) );
		$item->appendChild( $link );
		$title = $dom->createElement( "title" , $post['title'] );
		$item->appendChild( $title );
		$description = $dom->createElement( "description", $post['description'] );
		$item->appendChild( $description );
		$pubDate = $dom->createElement( "pubDate", $post['date'] );
		$item->appendChild( $pubDate );
		$guid = $dom->createElement( "guid", $post['link'] );
		$item->appendChild( $guid );

		$channel->appendChild( $item );
	  }

	  echo $dom->saveXML();

}


?>
