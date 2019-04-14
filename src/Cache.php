<?php

namespace Joostvanveen\Litespeedcache;

/**
 * Class Cache
 *
 * @package Joostvanveen\Litespeedcache
 */
class Cache
{

    /**
     * The header used to set Litespeed Cache.
     *
     * @see https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers#x-litespeed-cache-control
     */
    const CONTROL_HEADER = 'X-LiteSpeed-Cache-Control';

    /**
     * Set the cache vary for the current response. This tells the server to cache the object
     * with a specific vary value. This will not affect the varies used by other pages.
     *
     * @see https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers#x-litespeed-vary
     */
    const VARY_HEADER = 'X-LiteSpeed-Vary';

    /**
     * Tell LiteSpeed Web Server to purge cache objects, it can purge by URL, or by Tag.
     *
     * @see https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers#x-litespeed-purge
     */
    const PURGE_HEADER = 'X-LiteSpeed-Purge';

    /**
     * Assign a tag to a cache object. Each object can have multiple tags assigned to it.
     *
     * @see https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers#x-litespeed-tag
     */
    const TAGS_HEADER = 'X-LiteSpeed-Tag';

    /**
     * Enable / disable cache
     *
     * @var bool
     */
    protected $enabled = true;

    /**
     * The cache lifetime in minutes
     *
     * @var int
     */
    protected $lifetime = 120;

    /**
     * If this cookie is present and set to '1' then we wil not cache.
     *
     * @var string
     */
    protected $bypassCookieName = 'cache_bypass';

    /**
     * Urls that should not be cached. Can take * wildcards, like 'products/*.html'.
     *
     * @var array
     */
    protected $excludedUrls = [];

    /**
     * Query strings that should not be cached. Can take * wildcards, like 'products/*.html'.
     *
     * @var array
     */
    protected $excludedQueryStrings = [];

    /**
     * The URI current for the current URL.
     *
     * @var string
     */
    protected $uri = '';

    /**
     * The QUERY STRING current for the current URL.
     *
     * @var string
     */
    protected $queryString = '';

    /**
     * Tags to attach to the current cache.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * @var bool
     */
    protected $unitTestMode = false;

    /**
     * @param string $type
     * @param int $lifeTime
     * @param string $fullUrl
     *
     * @return $this
     */
    public function cache($type = '', $lifeTime = 0, $fullUrl = ''): Cache
    {
        if ($this->enabled == false) {
            return $this;
        }

        $this->setUrlAndQueryString($fullUrl);

        if ($this->shouldCache()) {
            $this->setCacheControlHeader($type, $lifeTime);
            $this->setTagsHeader();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function purgeCache(): Cache
    {
        $this->clearCachingHeaders();

        // Set purge headers
        header(self::PURGE_HEADER . ': *');

        return $this;
    }

    public function addTags($tags): Cache
    {
        $this->tags = (array) $tags;

        return $this;
    }

    /**
     * @return $this
     */
    public function purgeTags($tags): Cache
    {
        $this->clearCachingHeaders();

        $tagString = $this->getTagsString($tags);
        header(self::PURGE_HEADER . ': ' . $tagString);

        return $this;
    }

    /**
     * @param string $type
     * @param int $lifetime
     *
     * @return $this
     */
    public function setCacheControlHeader($type = 'public', $lifetime = 0): Cache
    {
        $lifeTime = $lifetime ? $lifetime : self::LIFETIME;
        header(self::CONTROL_HEADER . ': ' . $type . ', max-age=' . $lifeTime);

        return $this;
    }

    public function setTagsHeader()
    {
        if (! empty($this->tags)) {
            header(self::TAGS_HEADER . ': ' . implode(', ', $this->tags));
        }

        return $this;
    }

    /**
     * @param $cookieName
     *
     * @return $this
     */
    public function setCacheCookieHeader($cookieName): Cache
    {
        header(self::VARY_HEADER . ': cookie=' . $cookieName);

        return $this;
    }


    /**
     * @return bool
     */
    public function shouldCache(): bool
    {
        // Do not cache ajax requests
        if (! empty($_SERVER['X-Requested-With']) && $_SERVER['X-Requested-With'] == 'XMLHttpRequest') {
            return false;
        }

        // Do not cache any other requests than GET or HEAD requests
        $validMethods = ['GET', 'HEAD'];
        if (! empty($_SERVER['REQUEST_METHOD']) && ! preg_grep('/' . $_SERVER['REQUEST_METHOD'] . '/i', $validMethods)) {
            return false;
        }

        // Do not cache requests that have the cache bypass cookie set
        if (! empty($_COOKIE[ $this->bypassCookieName ]) && $_COOKIE[ $this->bypassCookieName ] == 1 && $this->unitTestMode == false) {
            return false;
        }

        // Do not cache CLI requests
        if ((php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') && $this->unitTestMode == false) {
            return false;
        }

        // Do not cache if current url is in blacklist or in admin folder
        if ($this->isInExcludedUrls($this->uri)) {
            return false;
        }

        // Do not cache if current query string is in blacklist
        if ($this->isInExcludedQueryStrings($this->queryString)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isInExcludedUrls($string = ''): bool
    {
        $string = ltrim($string, '/');
        foreach ($this->excludedUrls as $excludedUrl) {
            $excludedUrl = ltrim($excludedUrl, '/');
            if (fnmatch($excludedUrl, $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public function isInExcludedQueryStrings($string = ''): bool
    {
        $bypassQueryString = ['*' . $this->bypassCookieName . '=1*'];
        $this->excludedQueryStrings = array_merge($this->excludedQueryStrings, $bypassQueryString);
        foreach ($this->excludedQueryStrings as $excludedQueryString) {
            if (fnmatch($excludedQueryString, $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return Cache
     */
    public function enable(): Cache
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * @return Cache
     */
    public function disable(): Cache
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * @return array
     */
    public function getExcludedUrls(): array
    {
        return $this->excludedUrls;
    }

    /**
     * @param array|string $excludedUrls
     */
    public function setExcludedUrls($excludedUrls): Cache
    {
        $this->excludedUrls = (array) $excludedUrls;

        return $this;
    }

    /**
     * @return array
     */
    public function getExcludedQueryStrings(): array
    {
        return $this->excludedQueryStrings;
    }

    /**
     * @param array|string $excludedQueryStrings
     *
     * @return Cache
     */
    public function setExcludedQueryStrings($excludedQueryStrings): Cache
    {
        $this->excludedQueryStrings = (array) $excludedQueryStrings;

        return $this;
    }

    /**
     * Set $this->uri and $this->queryString.
     * Uses $_SERVER['REQUEST_URI'] by default.
     *
     * @param string $fullUrl optional
     */
    protected function setUrlAndQueryString($fullUrl = ''): void
    {
        $fullUrl = empty($fullUrl) ? $_SERVER['REQUEST_URI'] : $fullUrl;
        $urlData = parse_url($fullUrl);

        $this->uri = isset($urlData['path']) ? $urlData['path'] : '';
        $this->queryString = isset($urlData['query']) ? $urlData['query'] : '';
    }

    /**
     * @return $this
     */
    public function setUnitTestMode(): Cache
    {
        if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
            $this->unitTestMode = true;
        }

        return $this;
    }

    protected function clearCachingHeaders(): void
    {
        header_remove(self::CONTROL_HEADER);
        header_remove(self::VARY_HEADER);
    }

    /**
     * @param $tags
     *
     * @return string
     */
    protected function getTagsString($tags): string
    {
        $tagString = '';
        foreach ((array) $tags as $tag) {
            $tagString .= 'tag=' . $tag . ', ';
        }
        $tagString = rtrim($tagString, ', ');

        return $tagString;
    }
}
