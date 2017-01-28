<?php
namespace Robots;

use Robots\Exceptions\MissingRobotsTxtException;

class RobotsTxt
{
	/**
	 * [$url description]
	 * @var string
	 */
	protected $url;

	/**
	 * [$robotsRules description]
	 * @var array
	 */
	protected $robotsRules;

	/**
	 * Instantiate the class
	 * @param string $url 
	 */
	public function __construct($url)
	{
		$this->url = $url;
		$this->robotsRules = $this->getRobotsRules($url);
	}

	public function lastModified($url)
	{
		//
	}

	/**
	 * Gets the paths specifically allowed by the robots.txt file
	 * 
	 * @return array 
	 */
	public function getAllowed()
	{
		//@todo check if user agent exists
		// get the allowed paths for a user agent
		return $this->robotsRules['userAgent']['*']['allowed'];
	}

	/**
	 * Gets the paths specifically disallowed by the robots.txt file
	 * 
	 * @return array 
	 */
	public function getDisallowed()
	{
		//@todo check if user agent exists
		// get the disallowed paths for a user agent
		return $this->robotsRules['userAgent']['*']['disallowed'];
	}

	/**
	 * Checks if a URL is allowed to br crawled according to robots.txt
	 *
	 * @param  string  $url 		
	 * @return boolean
	 */
	public function isAllowed($url)
	{
		$allowed = true;
		$path = parse_url(strtolower($url), PHP_URL_PATH);
		// Remove leading slashes and add trailing slash
		// We want the path to look like "this/is/a/path/" as our regex pattern will be looking for a trailing slash
		$path = ltrim($path, "/");
		$path = (substr($path, -1) == "/") ? $path : $path . '/';
		// Check if path is allowed
		foreach ($this->getDisallowed() as $disallowed) {
			if ($disallowed == "/") {
				$allowed = false;
				break;
			}
			if (preg_match($this->convertToRegex($disallowed), $path)) {
				$allowed = false;
				break;
			}
		}

		return $allowed;
	}

	/**
	 * Convert a URL path into a regex pattern
	 * @param  string $path A parsed URL path
	 * @return string $parts A regenx pattern
	 */
	public function convertToRegex($path)
	{
		$parts = explode("/", $path);
		array_walk($parts, function(&$value, &$k) {
			// Replace LONE *s with a wildcard to match anything with a trailing slash
			if ($value === '*') {
				$value = "[\w\-\/]+(\/)";
			} else {
				$value = preg_quote($value, "/") . "(\/)";
			}
			// Replace *s WITHIN a path string
			$value = str_replace("\*", "[\w\-]*", $value);
			// '&'' marks the ending URL which we don't need for our regex solution
			$value = str_replace("\\$", "", $value);
		});
		// Join the elements back into a string and escape the group sectioning
		$parts = join('', $parts);
		// Indicate that if we disallow something like "*/account", we'll also disallow */account/somethingelse".
		// Remove the appending regex to allow the above condition (e.g allow the somethingelse path case)
		$parts = '/^' . $parts . "(.*)/"; 

		return $parts;
	}

	/**
	 * Returns the user agents with specified rules
	 * This will not return a user agent that has no rules set after it
	 * 
	 * @return array
	 */
	public function getUserAgents()
	{
		return $robotsRules['userAgent'];
	}

	/**
	 * Removes new line characters and assures we remove any leading slashes
	 * @param  string $path 
	 * @return string       
	 */
	private function normalizePathString($path)
	{
		$path = str_replace(PHP_EOL, '', $path);
		$path = trim($path, "/");

		return $path;
	}

	/**
	 * Parses the robots.txt file into an array
	 * @param  string $url 
	 * @throws MissingRobotsTxtException If no robots.txt file found
	 * @return array      
	 */
	protected function getRobotsRules($url)
 	{
 		ini_set('user_agent', 'Mozilla/5.0 (Linux; U; Android 4.0.3; ko-kr; LG-L160L Build/IML74K) AppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30');
 		// @todo need a more robust search for the actual txt file...
 		$robotsUrl = $url . '/robots.txt'; 

 		// Parse the file into an array of urls we'll ignore
 		$robotsRules = [];
 		$userAgent   = '';
 		$handle = @fopen($robotsUrl, "r");
		if ($handle) {
		    while (($line = strtolower(fgets($handle))) != false) {
		        // process the line read.
		    	if (strpos($line, 'user-agent: ') !== false) {
		    		// Get the user agent
		    		$userAgent = trim(explode('user-agent: ', $line)[1]);
		    		// Remove any new line characters
		    		$userAgent = str_replace(PHP_EOL, '', $userAgent);
		    	} elseif (strpos($line, 'allow: ') === 0) {
		    		$allowedUrl = trim(explode('allow: ', $line)[1]);
		    		$allowedUrl = $this->normalizePathString($allowedUrl);
		    		$robotsRules['userAgent'][$userAgent]['allowed'][] = $allowedUrl;
		    	} elseif (strpos($line, 'disallow: ') === 0) {
		    		$disallowUrl = trim(explode('disallow: ', $line)[1]);
		    		$disallowUrl = $this->normalizePathString($disallowUrl);
		    		$robotsRules['userAgent'][$userAgent]['disallowed'][] = $disallowUrl;
		    	} else {
		    		continue;
		    	}
		    }
		    fclose($handle);
		} else {
		    throw new MissingRobotsTxtException("Unable to retrieve robots.txt file for $url");
		} 

 		return $robotsRules;
 	}
}