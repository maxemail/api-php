# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Changed
- HTTP Client debug logging is disabled by default to save resources if not
needed. Enable via the constructor's `debugLogging` option.

## [4.0.0] - 2017-08-08
### Added
- Apply GNU LGPLv3 software licence
- Dedicated package-specific Exception classes. These extend the original SPL
Exception classes, so no changes should be required in any implementation. This
now means that all package exceptions implement `Mxm\Exception\Exception`.
- Use [Guzzle](http://guzzlephp.org/) to handle HTTP connection. Exceptions
thrown for networking errors (connection timeout, DNS errors, etc.) will be an
instance of `GuzzleHttp\Exception\RequestException`. This extends
`\RuntimeException` so any existing catch blocks will continue to function
without any necessary changes. Errors returned by Maxemail as a response to an
API call will now throw `Emailcenter\MaxemailApi\Exception\ClientException`
which extends the same `\RuntimeException` used previously.
- `Helper::setLogLevel()` can be used to increase the log level from the default
of *debug*. This might be useful if you want to see the file transfer logs at
*info* and filter out the raw HTTP client logs at *debug*.
- Deprecated API methods will be logged at *warning* level and trigger a PHP
`E_USER_DEPRECATED` error.
### Changed
- Namespaces have been changed from `Mxm\Api` to `Emailcenter\MaxemailApi`.
- Deprecate API config keys for *user* and *pass*, replaced with *username* and
*password* respectively
- Primary class has changed from `Mxm\Api` to `Emailcenter\MaxemailApi\Client`.
See [README](README.md) for usage example including config changes above.
### Removed
- Removed support for PHP 5.x as it is no longer
[actively supported](https://php.net/supported-versions.php) by the PHP project
- Remove API config key for *host*. Most implementations using the default
instance of Maxemail will not need to make any other change. If connecting to a
non-standard instance of Maxemail, a new key *uri* can be provided. 

## [3.1.2] - 2017-01-16
### Changed
- Removed API method name from debug log message (still available in log context)

## [3.1.1] - 2016-12-14
### Changed
- Update documentation URLs
- Increase API user-agent version to 3.1

## [3.1.0] - 2016-03-18
### Added
- Support for CURLFile in PHP ^5.5 - HT @sh41
- Official support for php70, although no code changes are required

## [3.0.0] - 2015-03-23
### Added
- File upload and download helpers
- Make API classes logger-aware for better debugging

### Changed
- API config parameters, see README

## [2.0.0] - 2014-10-16
### Added
- Implement Composer to easily add this package to existing projects

### Changed
- Update project to PSR-4 style, raising minimum PHP requirement to 5.4
  - If you require compatibility with a PHP version prior to 5.4, please use the 1.x release (and seriously consider upgrading as php53 is end-of-life)
