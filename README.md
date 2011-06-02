Tumblr Dashboard RSS
====================

Author:
	PJ Kix <pj@pjkix.com>

Version:
	1.1.0

Copyright:
	(cc) 2010 pjkix some rights reserved

License:
	http://creativecommons.org/licenses/by-nc-nd/3.0/

See Also:
	http://www.tumblr.com/docs/en/api

Overview
--------

This script uses the tumblr API to read the dashboard xml and then output an RSS feed.

Usage
-----

Upload the PHP script to a public location on a web server running PHP 5.
Either a) edit the php script directly to set authentication
or b) copy the config.ini.sample as config.ini and edit the settings.

Todo
----
* make it more secure
* multi-user friendly
* content compression

History
-------

v1.1.0 (2011-05-17)
* support for post types
* support enclosure / media rss
* support request caching
* support output caching


v1.0.4 (2010-02-01)
* initial release
* support for basic rss feed
* support for http cache control header
* support conditional get