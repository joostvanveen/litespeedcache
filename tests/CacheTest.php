<?php

namespace Joostvanveen\Litespeedcache\Tests;

use Joostvanveen\Litespeedcache\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->cache('private', 360, '/test?foo=bar');

        $headers = xdebug_get_headers();
        $this->assertTrue(in_array('X-LiteSpeed-Cache-Control: private, max-age=360', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_purge_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->purgeCache();

        $headers = xdebug_get_headers();
        $this->assertTrue(in_array('X-LiteSpeed-Purge: *', $headers));
        $this->assertEquals(1, count($headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_add_tags_to_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->addTags(['articles', 'pages'])
                            ->cache('private', 360, '/test?foo=bar');

        $headers = xdebug_get_headers();
        $this->assertTrue(in_array('X-LiteSpeed-Tag: articles, pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_add_a_single_tag_to_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->addTags('pages')
                            ->cache('private', 360, '/test?foo=bar');

        $headers = xdebug_get_headers();
        $this->assertTrue(in_array('X-LiteSpeed-Tag: pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_purge_tags()
    {
        $tags = ['articles', 'pages'];
        $cache = (new Cache)->setUnitTestMode()
                            ->addTags($tags)
                            ->purgeTags($tags);

        $headers = xdebug_get_headers();
        $this->assertTrue(in_array('X-LiteSpeed-Purge: tag=articles, tag=pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_an_excluded_uri()
    {
        $excludedUrls = [
            '/test*',
        ];

        $cache = (new Cache)->setUnitTestMode()
                            ->setExcludedUrls($excludedUrls)
                            ->cache('public', 360, '/test?foo=bar');

        $this->assertEmpty(xdebug_get_headers());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_an_excluded_queryString()
    {
        $excludedQueryString = [
            'foo=*',
        ];

        $cache = (new Cache)->setUnitTestMode()
                            ->setExcludedQueryStrings($excludedQueryString)
                            ->cache('public', 360, '/test?foo=bar');

        $this->assertEmpty(xdebug_get_headers());
    }

    /** @test */
    public function it_can_be_enabled_and_disabled()
    {
        $cache = new Cache;
        $this->assertSame(false, $cache->disable()->enabled());
        $this->assertSame(true, $cache->enable()->enabled());

        $cache = (new Cache)->setUnitTestMode()
                            ->disable()
                            ->cache('private', 360, '/test?foo=bar');

        $headers = xdebug_get_headers();
        $this->assertFalse(in_array('X-LiteSpeed-Cache-Control: private, max-age=360', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_set_the_cache_control_header()
    {
        $cache = new Cache;
        $cache->setCacheControlHeader('private', 100);

        $headers = xdebug_get_headers();
        $this->assertEquals('X-LiteSpeed-Cache-Control: private, max-age=100', $headers[0]);
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_set_the_cache_cookie_header()
    {
        $cache = new Cache;
        $cache->setCacheCookieHeader('mycookie');

        $headers = xdebug_get_headers();
        $this->assertEquals('X-LiteSpeed-Vary: cookie=mycookie', $headers[0]);
    }

    /** @test */
    public function it_can_set_an_array_of_urls_not_to_be_cached()
    {
        $excludedUrls = ['test'];
        $cache = new Cache;
        $cache->setExcludedUrls($excludedUrls);

        $this->assertSame($excludedUrls, $cache->getExcludedUrls());
    }

    /** @test */
    public function it_can_test_if_a_uri_is_excluded()
    {
        $excludedUrl = 'test';
        $cache = new Cache;
        $cache->setExcludedUrls([$excludedUrl]);

        $this->assertSame(true, $cache->isInExcludedUrls('test'));
    }

    /** @test */
    public function excluded_uris_can_take_wildcards()
    {
        $excludedUrls = [
            'test*',
            '*/foo/*/bar',
        ];
        $cache = new Cache;
        $cache->setExcludedUrls($excludedUrls);

        $excludedUrl = 'test/foo';
        $this->assertSame(true, $cache->isInExcludedUrls($excludedUrl));

        $excludedUrl = 'test/foo/some/bar';
        $this->assertSame(true, $cache->isInExcludedUrls($excludedUrl));
    }

    /** @test */
    public function it_can_test_if_a_query_string_is_excluded()
    {
        $excludedQueryStrings = 'test=1';
        $cache = new Cache;
        $cache->setExcludedQueryStrings([$excludedQueryStrings]);

        $this->assertSame(true, $cache->isInExcludedQueryStrings('test=1'));
    }

    /** @test */
    public function excluded_query_strings_can_take_wildcards()
    {
        $excludedQueryStrings = [
            'test*',
            '*foo=*',
        ];
        $cache = new Cache;
        $cache->setExcludedQueryStrings($excludedQueryStrings);

        $excludedQueryString = 'test=1';
        $this->assertSame(true, $cache->isInExcludedQueryStrings($excludedQueryString));

        $excludedQueryString = '?test=1&foo=bar&baz=bat';
        $this->assertSame(true, $cache->isInExcludedQueryStrings($excludedQueryString));
    }

    /** @test */
    public function it_can_set_an_array_of_querys_trings_not_to_be_cached()
    {
        $excludedQueryStrings = ['test'];

        $cache = new Cache;
        $cache->setExcludedQueryStrings($excludedQueryStrings);

        $this->assertSame($excludedQueryStrings, $cache->getExcludedQueryStrings());
    }
}
