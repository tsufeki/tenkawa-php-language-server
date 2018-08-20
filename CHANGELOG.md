Changelog
=========

UNRELEASED
----------

### Added

* Progress notifications as a custom protocol extension.
* Hierarchical document symbols.

### Removed

* Drop PHP 7.0 support.

### Changed

* Upgrade to PHPStan 0.10, with greatly improved standard library type
  information. Also allowing us to add support for anonymous classes.
* Standard library index is now prebuilt, not scanned at startup.
* Schedule tasks by priorities to improve latency on interactive requests.

0.2.0 - 2018-07-01
------------------

### Added

* "Find references" for global and member symbols.

### Deprecated

* PHP 7.0 support, as some of our dependencies will soon be >= 7.1 only.

### Changed

* Completion should be a bit smarter.
* Diagnostic squiggles don't span multiple lines.

### Fixed

* Completion immediately after `$`.
* Don't show hovers for `true`, `false` and `null`.
* Reindex files when closed in client, to mitigate missing changes.

0.1.0 - 2018-04-18
------------------

First release.
