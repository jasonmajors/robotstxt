# Robotstxt Parser
This is a small package to make parsing robots.txt rules easier.
#### Quick example:
```php
// basic usage
$robots  = new Robots\RobotsTxt();
$allowed = $robots->isAllowed("https://www.example.com/some/path"); // true
$allowed = $robots->isAllowed("https://www.another.com/example");   // false
```
## Setup
Install via [composer](https://getcomposer.org/):
```sh
$ composer require jmajors/robotstxt
```
Make sure composer's autoloader is included in your project:
```php
require __DIR__ . '/vendor/autoload.php';
```
That's it.

## Usage
This package is a class made mainly for checking if a crawler is allowed to visit a particular URL. Use the `isAllowed(string $url)` method to check whether or not a crawler is disallowed from crawling a particular path, which returns `true` if the URL's path is not included in the robots.txt Disallowed rules (i.e. you're free to crawl), and `false` if the path is disallowed (no crawling!).
Here's an example:

```php
<?php
use Robots\RobotsTxt;

$robotsTxt = new RobotsTxt();
$allowed = $robotsTxt->isAllowed("https://www.example.com/this/is/fine"); // returns true
```
Additionally, `setUserAgent($userAgent)` will allow you to specify a User Agent in the request header.
```php
$robotsTxt = new RobotsTxt();
$userAgent = 'RobotsTxtBot/1.0; (+https://github.com/jasonmajors/robotstxt)';
// set a user agent
$robotsTxt->setUserAgent($userAgent);
$allowed = $robotsTxt->isAllowed("https://www.example.com/not/sure/if/allowed");

// Alternatively...
$allowed = $robotsTxt->setUserAgent($userAgent)->isAllowed("https://www.someplace.com/a/path");
```
If for some reason there's no robots.txt file at the root of the domain, a `MissingRobotsTxtException` will be thrown.
```php
<?php
// Typical usage
use Robots\RobotsTxt;
use Robots\Exceptions\MissingRobotsTxtException;
...

$robotsTxt = new RobotsTxt();
$userAgent = 'RobotsTxtBot/1.0; (+https://github.com/jasonmajors/robotstxt)';

try {
    $allowed = $robotsTxt->setUserAgent($userAgent)->isAllowed("https://www.example.com/some/path");
} catch (MissingRobotsTxtException $e) {
    $error = $e->getMessage();
    // Handle the error
}
```
Further, `getDisallowed` will return an array of the disallowed paths for `User-Agent: *`:
```php
$robots     = new RobotsTxt();
$disallowed = $robots->getDisallowed("https://www.example.com");
```
## TODO's
 - Write tests
 - Add ability to check disallowed paths based on user agent
 - Check when robots.txt was last updated
 - Return a list of user agents in the file


