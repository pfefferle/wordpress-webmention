# Changelog

All notable changes to this project will be documented in this file.

## [5.6.1] - 2025-12-21

### Changed

- Remove explicit `require_once` for Handler classes, rely on autoloader instead
- Clean up docblock type hints in Handler class

### Added

- Add autoloader tests to verify Handler classes load correctly

## [5.6.0] - 2025-12-18

### Added

- Add option to disable outgoing Webmentions
- Add classic editor fallback for webmention post settings
- Add styling for webmention form elements
- Add content fallback for Bridgy webmentions without text
- Add filter to customize webmention form submit button text
- Add uninstall method to clean up plugin options
- Add hook when avatar successfully sideloaded

### Changed

- Refactor admin settings to use WordPress Settings API
- Remove webmention- prefix from template filenames
- Complete folder rename to lowercase
- Search avatar store before meta
- Only store avatars for webmentions

### Fixed

- Remove duplicate links
- Add null check in is_jsonld_type method
- Fix span tags inside anchor tags confusing WordPress auto-linking
- Fix fatal error when parsing invalid date strings
- Fix array_merge() error when _embedded key is missing
- Fix distorted emoji display when avatars are disabled
- Fix editor plugin error on CPTs without custom-fields support
- Fix undefined array key comment_content warning
- Fix undefined property warning in Item::__call magic method
- Add trailingslashit to upload directory to ensure no issues

For older changelog entries, see [GitHub Releases](https://github.com/pfefferle/wordpress-webmention/releases).
