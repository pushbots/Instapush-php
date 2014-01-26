Instapush PHP Wrapper
-----

The [Instapush PHP Wrapper] (http://www.instapush.im/) is
a PHP wrapper for Instapush API.

Instapush allows you to recieve push notifications about any trasnaction you care about in your web app immediatly using events based approach.

This wrapper makes async request hence will minimally affect application speed (if any).

Usage
-----
The minimal you'll need to have is:
```php
require("lib/instapush.php");
$ip = InstaPush::getInstance("APPLICATION_ID", "APPLICATION_SECRET");
$ip->track("signup", array( 
 		"email"=> "test@ss.cc"
));
```
