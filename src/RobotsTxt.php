<?php
/**
 * @version  1.0.0
 */
namespace Robots;

use Robots\Exceptions\MissingRobotsTxtException;

class RobotsTxt
{
    /**
     * Array that will contain the robots.txt rules keyed by domain
     * @var array
     */
    protected $robotsRules = [];

    /**
     * The user agent we set
     * @var string
     */
    protected $userAgent;

    /**
     * Instantiate the class
     * @param string $url 
     */
    public function __construct() 
    {

    }

    /**
     * Set the user-agent string in the request header
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        ini_set('user_agent', $userAgent);

        return $this;
    }

    /**
     * Gets the paths specifically disallowed by the robots.txt file
     * 
     * @return array 
     */
    public function getDisallowed($url)
    {
        $domain = parse_url($url)['host'];
        $scheme = parse_url($url)['scheme'];
        $base   = $scheme . '://' . $domain;

        if ($this->isBaseRulesSet($base) === false) {
            $this->setBaseRules($base);
        }

        return $this->robotsRules[$base]['userAgent']['*']['disallowed'];
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
        foreach ($this->getDisallowed($url) as $disallowed) {
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
    protected function convertToRegex($path)
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
     * Removes new line characters and assures we remove any leading slashes
     * @param  string $path 
     * @return string       
     */
    protected function normalizePathString($path)
    {
        $path = str_replace(PHP_EOL, '', $path);
        $path = trim($path, "/");

        return $path;
    }


    /**
     * Retrieves the roobots.txt file URL for a given url
     * @param  string $url 
     * @return string www.example.com/robots.txt
     */
    protected function getRobotsUrl($url) 
    {
        $parts  = parse_url($url);
        $domain = $parts['host'] ;
        $scheme = $parts['scheme'];

        return $scheme . '://' . $domain . '/robots.txt';
    }

    /**
     * Check if we already have the rules for a given base
     * @param  string $domain The domain portion of the URL e.g. example.com
     * @return bool
     */
    protected function isBaseRulesSet($base)
    {
        // need to get domain of url then check
        return isset($this->robotsRules[$base]);
    }

    /**
     * Set the robots.txt rules for a domain
     * @param string $domain 
     */
    protected function setBaseRules($base)
    {
        $robotsUrl = $this->getRobotsUrl($base);
        $this->robotsRules[$base] = $this->getRobotsRules($robotsUrl);
    }

    /**
     * Parses the robots.txt file into an array
     * @param  string $robotsUrl 
     * @throws MissingRobotsTxtException If no robots.txt file found
     * @return array      
     */
    protected function getRobotsRules($robotsUrl)
    {
        // Parse the file into an array of urls we'll ignore
        $robotsRules = [];
        $userAgent   = '';
        $handle = @fopen($robotsUrl, "r");
        if ($handle) {
            while (($line = strtolower(fgets($handle))) != false) {
                // Remove hashes in place for comments
                $line = strpos($line, '#') ? strstr($line, '#', true) : $line;
                // process the line read.
                if (strpos($line, 'user-agent: ') !== false) {
                    // Get the user agent
                    $userAgent = trim(explode('user-agent: ', $line)[1]);
                    // Remove any new line characters
                    $userAgent = str_replace(PHP_EOL, '', $userAgent);
                } elseif (strpos($line, 'disallow:') === 0) {
                    $disallowUrl = trim(explode('disallow:', $line)[1]);
                    // Don't strip slashes from the path if the disallowed path is root
                    if ($disallowUrl !== '/') {
                        $disallowUrl = $this->normalizePathString($disallowUrl);
                    }
                    // Add rule to array
                    $robotsRules['userAgent'][$userAgent]['disallowed'][] = $disallowUrl;
                } else {    
                    continue;
                }
            }
            fclose($handle);
        } else {
            throw new MissingRobotsTxtException("Unable to retrieve robots.txt file for URL: {$robotsUrl}");
        } 

        return $robotsRules;
    }
}