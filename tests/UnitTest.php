<?php

namespace Joostvanveen\Litespeedcache\Tests;

use Joostvanveen\Litespeedcache\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{

    /** @test */
    public function it_can_be_enabled_and_disabled()
    {
        $cache = new Cache;
        $this->assertSame(false, $cache->disable()->enabled());
        $this->assertSame(true, $cache->enable()->enabled());

        $cache = (new Cache)->setUnitTestMode()
                            ->disable()
                            ->cache('private', 360, '/test?foo=bar');

        $headers = $this->getHeaders();
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

        $headers = $this->getHeaders();
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

        $headers = $this->getHeaders();
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

    /** @test */
    public function it_can_add_tags ()
    {
        $cache = (new Cache)->addTags(['articles', 'pages']);
        $this->assertEquals(['articles', 'pages'], $cache->getTags());

        $cache = (new Cache)->addTags('articles')
                            ->addTags('pages');
        $this->assertEquals(['articles', 'pages'], $cache->getTags());

        $cache = (new Cache)->addTags(['articles', 'pages'])
                            ->addTags('post-1');
        $this->assertEquals(['articles', 'pages', 'post-1'], $cache->getTags());
    }

    /** @test */
    public function it_can_add_a_single_tag ()
    {
        $cache = (new Cache)->addTag('articles')->addTag('pages');
        $this->assertEquals(['articles', 'pages'], $cache->getTags());
    }


    /** @test */
    public function it_can_add_vary_values ()
    {
        $cache = (new Cache)->addVary(['example.com', 'default-app']);
        $this->assertEquals(['example.com', 'default-app'], $cache->getVary());

        $cache = (new Cache)->addVary('example.com')
                            ->addVary('default-app');
        $this->assertEquals(['example.com', 'default-app'], $cache->getVary());
    }

    protected function getHeaders()
    {
        if (! function_exists('xdebug_get_headers')) {
            throw new \Exception('function xdebug_get_headers() does not exist. Please activate Xdebug');
        }

        return xdebug_get_headers();
    }
}
