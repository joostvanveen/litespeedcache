<?php

namespace Joostvanveen\Litespeedcache\Tests;

use Joostvanveen\Litespeedcache\Cache;
use PHPUnit\Framework\TestCase;

class FeatureTest extends TestCase
{

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->cache('private', 360, '/test?foo=bar');

        $headers = $this->getHeaders();
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

        $headers = $this->getHeaders();
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
                            ->addTags('post-1')
                            ->cache('private', 360, '/test?foo=bar');

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Tag: articles, pages, post-1', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_add_vary_to_cache()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->addVary('value=default')
                            ->cache('private', 360, '/test?foo=bar');

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Vary: value=default', $headers));
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

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Tag: pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_purge_a_uri()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->addUri('/about-us')
                            ->purge();

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Purge: /about-us', $headers));
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
                            ->purge();

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Purge: tag=articles, tag=pages', $headers));
        $this->assertFalse(in_array('X-LiteSpeed-Tag: articles, pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_can_purge_tags_using_its_own_method()
    {
        $tags = ['articles', 'pages'];
        $cache = (new Cache)->setUnitTestMode()
                            ->purgeTags($tags);

        $headers = $this->getHeaders();
        $this->assertTrue(in_array('X-LiteSpeed-Purge: tag=articles, tag=pages', $headers));
        $this->assertFalse(in_array('X-LiteSpeed-Tag: articles, pages', $headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_cli_requests()
    {
        $cache = (new Cache)->cache('private', 360, '/test?foo=bar');

        $this->assertEquals(0, count($this->getHeaders()));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_ajax_requests()
    {
        $_SERVER['X-Requested-With'] = 'XMLHttpRequest';
        $cache = (new Cache)->cache('private', 360, '/test?foo=bar');

        $this->assertEquals(0, count($this->getHeaders()));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_caches_get_and_head_requests()
    {
        $requestTypes = [
            'GET',
            'HEAD',
        ];

        foreach ($requestTypes as $requestType) {
            $_SERVER['REQUEST_METHOD'] = $requestType;
            $cache = (new Cache)->setUnitTestMode()
                                ->cache('private', 360, '/test?foo=bar');
            $this->assertTrue(in_array('X-LiteSpeed-Cache-Control: private, max-age=360', $this->getHeaders()));
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_post_put_or_delete_requests()
    {
        $requestTypes = [
            'POST',
            'PUT',
            'DELETE',
        ];

        foreach ($requestTypes as $requestType) {
            $_SERVER['REQUEST_METHOD'] = $requestType;
            $cache = (new Cache)->cache('private', 360, '/test?foo=bar');
            $this->assertEquals(0, count($this->getHeaders()));
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_if_bypass_cookie_is_set()
    {
        $_COOKIE['cache_bypass'] = 1;
        $cache = (new Cache)->cache('private', 360, '/test');

        $headers = $this->getHeaders();
        $this->assertEquals(0, count($headers));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_does_not_cache_if_bypass_is_in_query_string()
    {
        $cache = (new Cache)->setUnitTestMode()
                            ->cache('private', 360, '/test?cache_bypass=1');

        $headers = $this->getHeaders();
        $this->assertEquals(0, count($headers));
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

        $this->assertEmpty($this->getHeaders());
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

        $this->assertEmpty($this->getHeaders());
    }

    /**
     * @test
     * @runInSeparateProcess
     */
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

    protected function getHeaders()
    {
        if (! function_exists('xdebug_get_headers')) {
            throw new \Exception('function xdebug_get_headers() does not exist. Please activate Xdebug');
        }

        return xdebug_get_headers();
    }
}
