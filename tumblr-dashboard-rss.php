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

/** read config ... if available
if ( file_exists('config.ini') ) {
	$config = parse_ini_file('config.ini', true);
	$tumblr_email = $config['tumblr']['email'];
	$tumblr_password = $config['tumblr']['password'];
} */

// default to GMT for dates
date_default_timezone_set('GMT');

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
	// fix quality="best">
	$result = str_replace("quality=\"best\">","quality=\"best\"&gt;",$result);
	
	// fix </embed>
	$result = str_replace("</embed>","&lt;embed/&gt;",$result);

	$xml = simplexml_load_string($result);
	
	$xml = new SimpleXMLElement($result);
	// var_dump($xml);die;
	$posts = array();
	$i = 0;
	foreach ($xml->posts->post as $post) {
		
		$log = $post->{'tumblelog'};
		$log = strtoupper((string)$log['name']).' ('.(string)$log['title'].')';
		$posts[$i]['title'] = $post['slug'].' '.$log.' ['.$post['type'].']'; // wish there was a real title
		$posts[$i]['description'] = $post['type']; // maybe do somehting intelligent with type
		
		switch($post['type']) {
			case 'photo':
				// Pick the first photo in the set.
				$photo_links = $post->{'photo-url'};
				$posts[$i]['data'] = $photo_links[0];
				$posts[$i]['quote'] = $post->{'photo-caption'};
				break;
			case 'regular':
				$posts[$i]['data'] = $post->{'regular-title'};
				break;
			case 'answer':
				$posts[$i]['data'] = $post->{'question'};
				$posts[$i]['quote'] = $post->{'answer'};
				break;
			case 'video':
				$posts[$i]['data'] = $post->{'video-player'};
				$posts[$i]['quote'] = $post->{'video-caption'};
				break;
			case 'quote':
				$posts[$i]['quote'] = $post->{'quote'};
				break;
			case 'audio':
				//$posts[$i]['data'] = $post->{'audio-player'};
				$posts[$i]['quote'] = $post->{'audio-caption'};
				break;
			case 'conversation':
				$posts[$i]['data'] = $post->{'conversation-title'};
				
				$lines = array();
				foreach($post->{'conversation'}->{'line'} as $line){
					$lines[] = "&lt;dt&gt;".$line['label']."&lt;/dt&gt;&lt;dd&gt;".(string)$line."&lt;/dd&gt;";
				}
				
				if( is_array($lines) ){
					$convo = "&lt;dl&gt;".implode("\n",$lines)."&lt;/dl&gt;";
					$posts[$i]['quote'] = $convo;
				}
				break;
			default:
				//var_dump($post->asXML());
				//print "<!-- \n\n\t ".$post['type']."\n\n -->";
				die();
				break;
		}
		
		$posts[$i]['link'] = $post['url-with-slug'];
		$posts[$i]['date'] = date(DATE_RSS, strtotime($post['date']) );
		
		$i++;
	}
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
		
		switch($post['description']){
			case 'photo':
				$description = $dom->createElement( "description", "&lt;img src=\"".$post['data']."\" /&gt;"."&lt;br /&gt;&lt;br /&gt;".$post['quote']."&lt;br /&gt;" );
				break;
			case 'regular':
				$description = $dom->createElement( "description", $post['data']."&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;" );
				break;
			case 'answer':
				$description = $dom->createElement( "description", "&lt;strong&gt;".$post['data']."&lt;/strong&gt;&lt;br /&gt;&lt;blockquote&gt;".$post['quote']."&lt;/blockquote&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;");
				break;
			case 'video':
				$description = $dom->createElement( "description", $post['data']."&lt;br /&gt;".$post['quote']."&lt;br /&gt;&lt;br /&gt;" );
				break;
			case 'audio':
				$description = $dom->createElement( "description", "&lt;br /&gt;".$post['quote']."&lt;br /&gt;&lt;br /&gt;" );
				break;
			case 'quote':
				$description = $dom->createElement( "description", "&lt;blockquote&gt;".$post['quote']."&lt;/blockquote;&gt;&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;");
				break;
			case 'conversation':
				$description = $dom->createElement( "description", "&lt;strong&gt;".$post['data']."&lt;/strong&gt;&lt;br /&gt;".$post['quote']."&lt;br /&gt;&lt;br /&gt;&lt;br /&gt;");
				break;
			default:
				$description = $dom->createElement( "description", $post['description'] );
				break;
		}
		
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
