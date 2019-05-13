<?php

namespace Joostvanveen\Litespeedcache;

/**
 * Class Cache
 * TODO implement cookie vary header.
 * TODO implement private caching.
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
     * See README.
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
     * The cache type (public|private|shared|no-vary|esi|no-cache|no-store)
     *
     * @var string
     */
    protected $type = 'public';

    /**
     * Whether esi blocks are enabled
     *
     * @var bool
     */
    protected $esiEnabled = false;

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
     * Should ajax requests be cacheable?
     *
     * @var bool
     */
    protected $enable_ajax_cache = false;

    /**
     * Which requests types should be cached?
     *
     * @var array
     */
    protected $cacheable_http_verbs = ['GET', 'HEAD'];

    /**
     * Tags to attach to the current cache.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * Vary values to attach to the current cache.
     *
     * @var array
     */
    protected $vary = [];

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
    public function cache($type = '', $lifeTime = null, $fullUrl = ''): Cache
    {
        if ($this->enabled == false) {
            return $this;
        }

        if ($lifeTime !== null) {
            $this->setLifetime($lifeTime);
        }

        $this->setUrlAndQueryString($fullUrl);

        if ($this->shouldCache()) {
            if (! empty($type)) {
                $this->setType($type);
            }
            $this->setCacheControlHeader($this->getType(), $lifeTime);
            $this->setVaryHeader();
            $this->setTagsHeader();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function purge(): Cache
    {
        if ($this->isCliRequest()) {
            return $this;
        }

        $this->clearCachingHeaders();

        // Set purge headers
        $purgeString = '';
        if ($this->uri) {
            $purgeString .= '/' . ltrim($this->uri, '/') . ' ';
        }
        if (($tagString = $this->getTagsString($this->tags))) {
            $purgeString .= $tagString;
        }

        header(self::PURGE_HEADER . ': ' . $purgeString);

        return $this;
    }

    /**
     * @return $this
     */
    public function purgeAll(): Cache
    {
        if ($this->isCliRequest()) {
            return $this;
        }

        $this->clearCachingHeaders();

        // Set purge headers
        header(self::PURGE_HEADER . ': *');

        return $this;
    }

    /**
     * @return $this
     * @deprecated Will be removed in 1.0.0. Use purgeAll() instead
     */
    public function purgeCache(): Cache
    {
        return $this->purgeAll();
    }

    /**
     * @param $uri
     *
     * @return Cache
     */
    public function addUri($uri): Cache
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @param $tags
     *
     * @return Cache
     */
    public function addTags($tags): Cache
    {
        $this->tags = array_merge($this->tags, (array) $tags);

        return $this;
    }

    /**
     * @param $varyValues
     *
     * @return Cache
     */
    public function addVary($varyValues): Cache
    {
        $this->vary = array_merge($this->vary, (array) $varyValues);

        return $this;
    }

    /**
     * @return $this
     */
    public function purgeTags($tags): Cache
    {
        if ($this->isCliRequest()) {
            return $this;
        }

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
        $lifeTime = $lifetime ? $lifetime : $this->lifetime;

        // Set cache type
        $header = self::CONTROL_HEADER . ': ' . $type;

        // Set esi
        if ($this->getEsiEnabled()) {
            $header .= ', esi=on';
        }

        // Set lifetime
        $header .= ', max-age=' . $lifeTime;

        header($header);

        return $this;
    }

    /**
     * @return bool
     */
    public function getEsiEnabled(): bool
    {
        return $this->esiEnabled;
    }

    /**
     * @param bool $esiEnabled
     *
     * @return Cache
     */
    public function setEsiEnabled(bool $esiEnabled): Cache
    {
        $this->esiEnabled = $esiEnabled;

        return $this;
    }

    public function setVaryHeader()
    {
        if (! empty($this->vary)) {
            header(self::VARY_HEADER . ': ' . implode(', ', $this->vary));
        }
    }

    /**
     * @return $this
     */
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
        if (! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $this->getEnableAjaxCache() === false) {
            return false;
        }

        // Do not cache any other requests than GET or HEAD requests
        if (! empty($_SERVER['REQUEST_METHOD']) && ! preg_grep('/' . $_SERVER['REQUEST_METHOD'] . '/i', $this->getCacheableHttpVerbs())) {
            return false;
        }

        // Do not cache requests if cache is diabled
        if ($this->getEnabled() == false) {
            return false;
        }

        // Do not cache requests that have the cache bypass cookie set
        if ($this->getLifetime() == 0) {
            return false;
        }

        // Do not cache requests that have the cache bypass cookie set
        if (! empty($_COOKIE[ $this->bypassCookieName ]) && $_COOKIE[ $this->bypassCookieName ] == 1 && $this->unitTestMode == false) {
            return false;
        }

        // Do not cache CLI requests
        if ($this->isCliRequest()) {
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
     * @return bool
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): Cache
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return int
     */
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * Cache lifetime in minutes.
     *
     * @param int $lifetime
     */
    public function setLifetime(int $lifetime): Cache
    {
        $this->lifetime = $lifetime;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): Cache
    {
        $type = $this->guardValidType($type);
        $this->type = $type;

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
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $fullUrl = empty($fullUrl) ? $requestUri : $fullUrl;
        $urlData = parse_url($fullUrl);

        $this->uri = isset($urlData['path']) ? $urlData['path'] : '';
        $this->queryString = isset($urlData['query']) ? $urlData['query'] : '';
    }

    /**
     * @return bool
     */
    public function getEnableAjaxCache(): bool
    {
        return $this->enable_ajax_cache;
    }

    /**
     * @param bool $enable_ajax_cache
     *
     * @return Cache
     */
    public function setEnableAjaxCache(bool $enable_ajax_cache): Cache
    {
        $this->enable_ajax_cache = $enable_ajax_cache;

        return $this;
    }

    /**
     * @return array
     */
    public function getCacheableHttpVerbs(): array
    {
        return $this->cacheable_http_verbs;
    }

    /**
     * @param array $cacheable_http_verbs
     *
     * @return Cache
     */
    public function setCacheableHttpVerbs(array $cacheable_http_verbs): Cache
    {
        $this->cacheable_http_verbs = $cacheable_http_verbs;

        return $this;
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

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return array
     */
    public function getVary(): array
    {
        return $this->vary;
    }

    /**
     *
     */
    protected function clearCachingHeaders(): void
    {
        header_remove(self::CONTROL_HEADER);
        header_remove(self::VARY_HEADER);
        header_remove(self::TAGS_HEADER);
    }

    /**
     * @param $tags
     *
     * @return string
     */
    protected function getTagsString($tags): string
    {
        if (empty($tags)) {
            return '';
        }

        $tagString = '';
        foreach ((array) $tags as $tag) {
            $tagString .= 'tag=' . $tag . ', ';
        }
        $tagString = rtrim($tagString, ', ');

        return $tagString;
    }

    /**
     * @return bool
     */
    protected function isCliRequest(): bool
    {
        return (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') && $this->unitTestMode == false;
    }

    /**
     * @param $type
     *
     * @return mixed
     * @throws LitespeedcacheException
     */
    protected function guardValidType($type)
    {
        $validTypes = [
            'public',
            'private',
        ];

        if (! in_array($type, $validTypes)) {
            throw new LitespeedcacheException($type . ' is not a valid cache type');
        }

        return $type;
    }
}
