# joostvanveen/litespeedcache
A framework agnostic Litespeed cache library for PHP. Can be used in any PHP application.

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
    
    # Enable private cache
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
### Caching the current URL
```php
use Joostvanveen\Litespeedcache\Cache;

[...]

// Cache the current URL as public with a cache lifetime of 120 minutes
$cache = new Cache;
$cache->cache('public', 120);

// You can also use new Cache directly, like so:
(new Cache)->cache('public', 120);
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

### Excluding query string from cache 
A query string consists of the paramters added to a URL after the question mark. In `https://example.com/foo?bar=baz`, the query string is `bar=baz`

A query string will not be cached if it matches any of the URIs set as excluded. The excluded query strings can contain wildcards.

In the following example, the URL `https://example.com/search?query=foo&page=1&direction=desc` would not be cached. 

```php
$excludedQueryStrings = [
    '*direction=*',
];
(new \Joostvanveen\Litespeedcache\Cache)->setExcludedQueryStrings($excludedQueryStrings)->cache('public', 120);
```

### Adding tags to the cache
You can add one or more tags to the current URL that is cached. You can use these tags to flush all caches containing those tags at once.

By default, addTags() takes an array of tags.
```php
(new \Joostvanveen\Litespeedcache\Cache)->addTags(['articles', 'english'])
                                        ->addTags(['page1'])
                                        ->cache('public', 120);
``` 

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

## Usage in Laravel project

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

## Litespeed documentation

You can find the Litespeed Cache documentation here [Litespeed documentation: https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers](Litespeed documentation: https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:developer_guide:response_headers)

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## Changelog
[Changelog](/joostvanveen/litespeedcache/blob/master/CHANGELOG.md)

## License
[MIT](/joostvanveen/litespeedcache/blob/master/LICENSE.md)
