# Changelog

## [0.1.1](https://github.com/anu-rock/woobert/compare/v0.1.0...v0.1.1) (2026-07-09)

### Features

* show a plain-English confirmation for write actions
* require confirmation on product, stock, and coupon updates

### Bug Fixes

* remount the flow modal per query to avoid a stale-result flash
* hide the write-confirmation message for direct-execute tools

### Refactors

* share one seeder between blueprint and WP-CLI, fix landing redirect

## 0.1.0 (2026-07-08)

### Features

* initial release: "Ask Woobert" command in the WordPress command palette, resolve/execute inference proxy, confirmation for destructive actions.
