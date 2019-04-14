# joostvanveen/litespeedcache
A Litespeed cache library for PHP.

## Installation
Require the package using composer:
```
composer require joostvanveen/litespeedcache
```
## Usage
### Caching the current URL
```php
use Joostvanveen\Litespeedcache\Cache;

...

// Cache the current URL as public with a cache lifetime of 120 minutes
$cache = new Cache;
$cache->cache('public', 120);

// You can also use new Cache directly, like so:
(new Cache)->cache('public', 120);
```

### Exclusing URIs from cache

Can contains wildcards.
```php
// A URL will not be cached if it matches any of the URIs set as excluded.
// In http://example.com/foo?bar=baz, the URI is '/foo'
// In the following example, the URI '/checkout/step/1' would not be cached. 
$excludedUris = [
    'checkout*',
    'admin*',
];
(new Cache)->setExcludedUrls($excludedUris)->cache('public', 120);
```

### Excluding query string from cache 

Can contains wildcards.
```php
// A query string will not be cached if it matches any of the URIs set as excluded.
// In http://example.com/foo?bar=baz, the query string is 'bar=baz'
// In the following example, the URL '/search?query=foo&page=1&direction=desc' would not be cached. 
$excludedQueryStrings = [
    '*direction=*',
];
(new Cache)->setExcludedQueryStrings($excludedQueryStrings)->cache('public', 120);
```
                            
### Flushing the cache
```php
// Purge the entire cache
(new Cache)->purgeCache();
```

### Adding tags the the cache
You can add one or more tags to the current URL that is cached. You can use these tags to flush all caches containing those tags at once.
```php
// By default, addTags() takes an array of tags.
(new Cache)->addTags(['articles', 'english'])->cache('public', 120);

// You can also pass in a string, if you need to define only one tag.
(new Cache)->addTags('articles')->cache('public', 120);
``` 

### Purging selected tags from cache
You can delete all caches containing a certain tag at once.
```php
// By default, purgeTags() takes an array of tags.
(new Cache)->purgeTags(['articles', 'english']);

// You can also pass in a string, if you need to define only one tag.
(new Cache)->purgeTags('english');
``` 

### Bypassing the cache
Sometimes, you need to inspect a URL without cache, e.g. for troubleshooting or previewing. 
For that, you can either add `cache_bypass=1` to the query string, or set a `cache_bypass` cookie with a value of `1`.

E.g. this URL bypasses cache: http://example.com?cache_bypass=1

### Disabling the cache
By default, the cache is enabled. But you can disable it as well.
```php
$cache = new Cache; 

$cache->disable();
$enabled = $cache->enabled(); // Returns false

$cache->enable();
$enabled = $cache->enabled(); // Returns true
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## Changelog
[Changelog](/joostvanveen/litespeedcache/blob/master/CHANGELOG.md)

## License
[MIT](/joostvanveen/litespeedcache/blob/master/LICENSE.md)
