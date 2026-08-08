# Crowdfunding for WooCommerce — OmniaTV maintained fork

This repository is a maintained GPL fork of **Crowdfunding for WooCommerce 3.1.14**, created from a verified upstream source baseline.

## Scope

The fork is intentionally conservative and preserves the existing crowdfunding data contract used by OmniaTV:

- existing `alg_*` options;
- existing `_alg_crowdfunding_*` product meta;
- existing retained frontend crowdfunding shortcodes and hooks;
- no database migration in the first maintained release.

Frontend campaign creation / Product-by-User functionality has been removed. Campaigns are created and managed only through WordPress/WooCommerce administration.

## Compatibility baseline

The first maintained release supports the current OmniaTV classic WooCommerce flow. HPOS and Cart/Checkout Blocks are explicitly declared unsupported until dedicated parity tests are completed.

The crowdfunding open-price path is tested against WordPress 7.0.3, WooCommerce 11.0.0 and PHP 8.3, with syntax/security smoke coverage on PHP 8.3 and 8.4.

Coexistence is also integration-tested with **Product Open Pricing (Name Your Price) for WooCommerce 1.7.4** using two separate products: one Product Open Pricing product with crowdfunding disabled, and one crowdfunding open-price product with Product Open Pricing disabled. The tests verify isolated frontend fields, isolated cart-item keys and independent cart price/totals handling.

A mixed cart/order containing both open-pricing products at the same time is a separate scenario and is not yet part of the compatibility baseline.

## Releases

Release tags matching `v*` are packaged automatically as installable WordPress ZIP files. Release packages exclude repository-only CI/tests/documentation artifacts and contain the plugin under the `crowdfunding-for-woocommerce/` root directory.

Pre-release tags such as `v3.1.14.1-rc1` are published as GitHub pre-releases. Stable tags such as `v3.1.14.1` are published as normal releases. A SHA-256 checksum is generated alongside each ZIP.

## Security

See [SECURITY.md](SECURITY.md).
