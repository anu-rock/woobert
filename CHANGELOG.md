# Changelog

## [0.1.2](https://github.com/antelligent-org/woobert/compare/v0.1.1...v0.1.2) (2026-07-10)


### Bug Fixes

* make sample data consistent and refundable ([60b0a58](https://github.com/antelligent-org/woobert/commit/60b0a587e75479c7b9e6c87d5021dedf09bd43be))


### Documentation

* use the canonical repo owner in the changelog compare link ([9010a9a](https://github.com/antelligent-org/woobert/commit/9010a9aec12099faa292d1c13ce8ecc82122f065))

## [0.1.1](https://github.com/antelligent-org/woobert/compare/v0.1.0...v0.1.1) (2026-07-09)

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
