# Changelog

## 1.1.0 2019-04-22
### Changed
- Added $cache->setEnabled() + $cache->getEnabled() + unit tests.

## 1.0.0 2019-04-21
### Changed
- Added $ache->setLifetime() and $cache->setType() + feature tests.
- Removed Laravel-specific code. That will get its own package.

## 0.6.2 2019-04-17
### Changed
- Do not purge() or purgeAll() on CLI request. This breaks unit tests of application that use this package.

## 0.6.1 2019-04-16
### Changed
- Fixed bug when run through tests in CLI 

## 0.6.0 2019-04-16
### Added
- Added purgeAll()
- Added purge() method. Also takes tags and a single URI into account.

### Changed
- deprecated purgeCache() method

### Removed
- Removed undocumented, obsolete method addTag()

## 0.5.0 2019-04-16
### Added
- Added VARY headers.

## 0.4.1 2019-04-15
### Added
- Added the ability to add one tag or an array of tags at a time.
- Added tags for packagist.

## 0.4.0 2019-04-15
### Added
- Added Laravel ServiceProvider and Facade.

## 0.3.0 2019-04-12
### Changed
- Methods Cache::setExcludedUrls() and Cache::setExcludedQueryStrings() now also accept strings.
- Remove tags header when purging cache.
- Better documentation

## 0.2.0 2019-04-12
### Added
- Bypass query string
- Code coverage 100%
- Documentation.

## 0.1.0 2019-04-12
### Added
- Basic caching library and tests.
