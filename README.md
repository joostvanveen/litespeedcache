![coverage](https://img.shields.io/badge/coverage-99%25-yellowgreen.svg?cacheSeconds=3600)

# joostvanveen/litespeedcache
A framework agnostic Litespeed cache library for PHP. Can be used in any PHP application.

If you are looking for the Laravel implementation, see [https://github.com/joostvanveen/laravel-litespeedcache](https://github.com/joostvanveen/laravel-litespeedcache)

## Installation
Require the package using composer:
```
composer require joostvanveen/litespeedcache
```

Enable the Litespeed in your .htaccess file.
```
<IfModule LiteSpeed>
    # Enable public cache
    CacheEnable public /
    
    # Enable private cache if you need to
    CacheEnable private /
    
    # Check the public cache
    CacheLookup public on
    
    # Ignore normal Cache Control headers
    CacheIgnoreCacheControl On
    
    # Maximum expiration time in seconds
    CacheMaxExpire 604800
</IfModule>
``` 

## Usage

The package does not cache:
- any requests other than GET and HEAD
- any ajax requests
- any CLI requests

The cache also
- can be enabled or disabled
- can contain one or multiple tags
- can contain vary headers
- can contain an array of blacklisted URIs that should bot be cached
- can contain an array of blacklisted query strings thta should not be cached

### Default configuration values
- type = public
- enabled = true (cache is enabled)
- lifetime = 120 (2 hours)
- bypass cookiename = cache_bypass (see [Bypassing the cache](#bypassing-the-cache))
- bypass querystring = cache_bypass (see [Bypassing the cache](#bypassing-the-cache))

### Caching the current URL
```php
use Joostvanveen\Litespeedcache\Cache;

[...]

// Cache the current URL as public with a cache lifetime of 120 minutes
$cache = new Cache;
$cache->cache(); // use default type and lifetime

// You can also use new Cache directly, like so:
(new Cache)->cache(); // use default type and lifetime
(new Cache)->cache('private', 360); // use explicit type and lifetime

// You can also use the setType() and setLifetime() methods
(new Cache)->setEnabled(true)->setType('private')->setLifetime(3600)->cache();

// If the lifetime is set to 0 the page will not be cached
(new Cache)->setEnabled(true)->setLifetime(0)->cache();
```

### Caching a specific URL
This can be used for warming the cache, for instance.

```php
// Warm the cache for these URIs
$uris = [
    '/',
    '/about-us',
    '/contact',
];

$cache = (new \Joostvanveen\Litespeedcache\Cache);
foreach($uris as $uri) {
    $cache->cache('public', 120, $uri);
}
```

### Excluding URIs from cache
A URI is the request path without the query string. in `https://example.com/foo?bar=baz`, the URI is `/foo`.

A URL will not be cached if it matches any of the URIs set as excluded. Excluded URIs can contain wildcards. 

In the following example, the URI '/checkout/step/1' would not be cached.
```php 
$excludedUris = [
    'checkout*',
    'admin*',
];
(new \Joostvanveen\Litespeedcache\Cache)->setExcludedUrls($excludedUris)->cache('public', 120);
```

### Caching ajax requests
By default, ajax requests are not cached. But you can set this the following way:
```php
(new Cache)->setEnableAjaxCache(true)->cache();
```

### Caching HTTP request methods
By default, only GET and HEAD requests are cached. But you can set this the following way:
```php
$requestTypes = ['GET', 'HEAD', 'POST', 'PUT'];
(new Cache)->setCacheableHttpVerbs($requestTypes)->cache();
```

### Excluding query string from cache 
A query string consists of the parameters added to a URL after the question mark. In `https://example.com/foo?bar=baz`, the query string is `bar=baz`

A URL with a query string will not be cached if the query string matches any of the URIs set as excluded. The excluded query strings can contain wildcards.

In the following example, the URL `https://example.com/search?query=foo&page=1&direction=desc` would not be cached. 

```php
$excludedQueryStrings = [
    '*direction=*',
];
(new \Joostvanveen\Litespeedcache\Cache)->setExcludedQueryStrings($excludedQueryStrings)->cache('public', 120);
```

### Adding tags to the cache
You can add one or more tags to the current URL that is cached. You can use these tags to flush all caches containing those tags at once.

By default, `addTags()` takes an array of tags.
```php
(new \Joostvanveen\Litespeedcache\Cache)->addTags(['articles', 'english'])
                                        ->addTags(['page1'])
                                        ->cache('public', 120);
``` 

### Adding ESI to the cache
Sometimes, you don't want to cache **all** content on a page. For instance, if you have a form with a csrf token, 
you do not want to cache the csrf token. It has to be unique for all users. You can achieve this by using Edge 
Side Includes, or ESI blocks. These ESI block punch holes, as it were, in your cached page.

An ESI block is a HTML tag that has special markup and is **not** cached. Instead, on constructing the cached page Litespeed cache
will replace the ESI tag with the contents retrieved from another (uncached or privately cached) URL on your domain. 
Typically, such a URL will return a string or a block of HTML, for instance a csrf token, or the name of a logged in user, 
or an intricate HTML string containing the contents of a shopping cart.

Using ESI is quite simple:
1. Enable ESI in joostvanveen/litespeedcache `(new \Joostvanveen\Litespeedcache\Cache)->setEsiEnabled(true)->cache()`
1. Create a URL on you domain that will return the content for the ESI block, for instance 'https://mydomain.com/token'. 
1. Use an ESI markup block in your page that contains the ESI URL: `<esi:include src="https://mydomain.com/token" />`

**ESI example**<br>
Let's say you have a form with a csrf token, and the URL to retreived the uncached token from is https://mydomain.com/token.

Without ESI, you would display the token in a form like this:
```html
<input type="hidden" name="_token" value="{{ csrf_token() }}">
```

With ESI, you first create a URL that returns `<input type="hidden" name="_token" value="{{ csrf_token() }}">` and then insert an ESI 
block in the page that will be replaced by the contents of that URL.
```html
<esi:include src="https://mydomain.com/get-my-token" />
```

Of course, you can also have the URL return just the token and place the ESI block in the form like this:
```html
<input type="hidden" name="_token" value="<esi:include src="https://mydomain.com/get-my-token" />">
```

A word to the wise: try to use as little ESI blocks as possible. Constructing a cached page with a lot 
of ESI blocks can take so much time and resources that it defeats all the advantages of caching.
A page containing only a few ESI blocks will be a little slower, but will still perform well. 
When using ESI blocks, measure the difference in response time for a cached and uncached page. 

### Adding a vary to the cache

Sometimes, you want the cache to distinguish between different variants for the same URL.

Example: say you have a multi-site ap that runs across different subdomains. Say you serve these two URLS:
- www.domain.com/news?page=2
- subdomain.domain.com/news?page=2

These two URLS have different content, so you need Litespeed to store a cache for each URL. 
By default, Litespeed Cache cannot do this. It only takes the `news?page=1` part to create te cache identifier, 
which would be equal for both URLs.

But when you add the subdomain as a `VARY`, Litespeed Cache **will** add that to the cache identifier, making 
it store a different version for each domain.

You want to be careful not to use too many vary values, or the identifiers will become so customized that no two cache identifiers in your app will ever be the same. This can for instance happen if you add the User Agent to the vary. 

```php
(new \Joostvanveen\Litespeedcache\Cache)->addVary('value=subdomain')->cache('public', 360);                            
```

You can also pass in a string, if you need to define only one tag.
```php
(new \Joostvanveen\Litespeedcache\Cache)->addTags('articles')->cache('public', 120);
```

### Purging a selected URI from cache
To purge a specific URI, simply add the URI to the cache before calling purge()
```php
(new \Joostvanveen\Litespeedcache\Cache)->addUri('/about-us')
                                        ->purge();
```

### Purging selected tags from cache
To purge tags, simply add the tag or tags to the cache before calling purge()
```php
(new \Joostvanveen\Litespeedcache\Cache)->addTags(['articles', 'english'])
                                        ->purge();
```

There is also a special method to purge only tags: purgeTags(). By default, purgeTags() takes an array of tags.
```php
(new \Joostvanveen\Litespeedcache\Cache)->purgeTags(['articles', 'english']);
``` 

You can also pass in a string, if you need to define only one tag.
```php
(new \Joostvanveen\Litespeedcache\Cache)->purgeTags('english');
``` 

                            
### Flushing the entire cache

You can purge all items from the cache at once like so:
```php
(new \Joostvanveen\Litespeedcache\Cache)->purgeAll();
```

### Bypassing the cache
Sometimes, you need to inspect a URL without cache, e.g. for troubleshooting or previewing. 
For that, you can either add `cache_bypass=1` to the query string, or set a `cache_bypass` cookie with a value of `1`.

E.g. this URL bypasses cache: `https://example.com?cache_bypass=1`

### Disabling the cache
By default, the cache is enabled. But you can disable it as well.
```php
$cache = new \Joostvanveen\Litespeedcache\Cache; 

$cache->disable();
$enabled = $cache->enabled(); // Returns false

$cache->enable();
$enabled = $cache->enabled(); // Returns true
```

## Use Litespeed Cache in a Laravel project

In a Laravel project, the package is automatically registered. There is also a Litespeedcache facade at your disposal.

Instead of calling something like:
```php
(new \Joostvanveen\Litespeedcache\Cache)->cache('public', 120);
```

You can simply call
```php
use LitespeedCache;

[...]

LitespeedCache::cache('public', 120);
```

When you use caching in Laravel, you best check against the environment. 
Since the cache sets headers, this can break your tests (phpunit sends output before the headers are set, which would result in `headers already sent` errors.)
```php
if(! \App::environment(‘testing’) {
    Cache::purge();
}
```

## Litespeed documentation

You can find the Litespeed Cache documentation here [Litespeed documentation: https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers](Litespeed documentation: https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers)

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## Changelog
[Changelog](/joostvanveen/litespeedcache/blob/master/CHANGELOG.md)

## License
[MIT](/joostvanveen/litespeedcache/blob/master/LICENSE.md)
