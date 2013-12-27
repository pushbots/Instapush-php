Instapush PHP Wrapper
-----

The [Instapush-php Wrapper] (http://www.instapush.im/) is
a PHP wrapper arround Instapush service.

Instapush allows you to recieve push notifications about any trasnaction you care about in your web app immediatly using events based approach.

This wrapper makes async request hence will minimally affect application speed (if any).

Usage
-----
The minimal you'll need to have is:
```php
$ip = new instaPush();
$ip->App("52977dee128773e93de23cf5", "2ee88fa5bb3ebd8d3a23530715b6ccb8");
$ip->trackers("email", "test@ss.cc");
$ip->Event("signup");
$ip->Push();
```


Report Issues/Bugs
===============
[Bugs](http://instapush.im/bugs)
