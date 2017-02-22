<?php
/**
 * @version  1.0.0
 */
namespace Robots;

use Robots\Exceptions\MissingRobotsTxtException;
use CachingIterator;
use ArrayIterator;

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
        $disallowed = [];

        $domain = parse_url($url)['host'];
        $scheme = parse_url($url)['scheme'];
        $base   = $scheme . '://' . $domain;

        if ($this->isBaseRulesSet($base) === false) {
            $this->setBaseRules($base);
        }

        if (isset($this->robotsRules[$base]['userAgent']['*']['disallowed'])) {
            $disallowed = $this->robotsRules[$base]['userAgent']['*']['disallowed'];
        } 

        return $disallowed;
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
        $path  = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $path = $path . '?' . $query;
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
     * @link   https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt#url-matching-based-on-path-values
     * @param  string $path A parsed URL path + querystring
     * @return string A regex string
     */
    public function convertToRegex($path)
    {
        $regexStr   = '';
        $collection = new CachingIterator(new ArrayIterator(str_split($path)));
        foreach ($collection as $k => $character) {
            // Disallowed path begins with "/", path must BEGIN with whats to follow
            if ($k === 0 && $character === '/') {
                $regexStr .= '^\\/';
            // Escape the forward flashes for matching
            } elseif ($character === '/') {
                $regexStr .= '\\/';
            // Wildcard character: Any sequency of valid characters should match
            } elseif ($character === '*') {
                $regexStr .= '[a-zA-Z0-9_\-\/]*';
            // End of string
            } elseif ($character === '$') {
                $regexStr .= $character;
            // Trailing * char is ignored
            } elseif ($collection->hasNext() === false && $character === '*') {
                // End of collection
                break;
            } else {
                $regexStr .= preg_quote($character);
            }
        }
        // Delimiters added for preg_match
        return  '/' . $regexStr . '/';
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
            // @todo dont force lower case as URLs are case sensitive!
            while (($line = fgets($handle)) != false) {
                // Remove hashes in place for comments
                $line = strpos($line, '#') ? strstr($line, '#', true) : $line;
                // process the line read.
                if (strpos($line, 'User-agent: ') !== false) {
                    // Get the user agent
                    $userAgent = trim(explode('User-agent: ', $line)[1]);
                    // Remove any new line characters
                    $userAgent = str_replace(PHP_EOL, '', $userAgent);
                } elseif (strpos($line, 'Disallow:') === 0) {
                    $disallowUrl = trim(explode('Disallow:', $line)[1]);
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