# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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
