# Crowdfunding for WooCommerce — OmniaTV maintained fork

This repository is a maintained GPL fork of **Crowdfunding for WooCommerce 3.1.14**, created from a verified upstream source baseline.

## Scope

The fork is intentionally conservative and preserves the existing crowdfunding data contract used by OmniaTV:

- existing `alg_*` options;
- existing `_alg_crowdfunding_*` product meta;
- existing retained frontend crowdfunding shortcodes and hooks;
- no database migration from the upstream 3.1.14 baseline.

Frontend campaign creation / Product-by-User functionality has been removed. Campaigns are created and managed only through WordPress/WooCommerce administration.

## Compatibility baseline

The maintained fork supports both WooCommerce checkout architectures used by OmniaTV:

- classic WooCommerce cart and checkout;
- WooCommerce Cart and Checkout Blocks through the Store API.

The open-price contribution is validated server-side, retained in cart item data, applied to cart totals, restored from session state, and persisted into the resulting WooCommerce order. The CI suite also covers the cross-surface path where a shopper enters the amount through the classic product form and then continues through Cart/Checkout Blocks.

The compatibility suite currently targets **WordPress 7.0.3**, **WooCommerce 11.0.1**, **PHP 8.3**, with syntax/security smoke coverage on PHP 8.3 and 8.4.

Coexistence is integration-tested with **Product Open Pricing (Name Your Price) for WooCommerce 1.7.4** using two separate products: one Product Open Pricing product with crowdfunding disabled, and one crowdfunding open-price product with Product Open Pricing disabled. The tests verify isolated frontend fields, isolated cart-item keys and independent cart price/totals handling.

TagDiv Composer / Newspaper compatibility is supported for pages that render native WooCommerce checkout surfaces. The OmniaTV production configuration was audited and uses native WooCommerce classic checkout on the Greek site and native WooCommerce Cart/Checkout Blocks on the English site; TagDiv acts as the page/template rendering layer and does not provide a separate checkout engine in this configuration.

HPOS remains explicitly unsupported. The inherited campaign aggregation code still discovers orders through legacy `shop_order` queries and must be migrated to WooCommerce CRUD order queries before HPOS can be declared compatible.

A mixed cart/order containing both open-pricing products at the same time is a separate scenario and is not yet part of the compatibility baseline.

## Releases

Release tags matching `v*` are packaged automatically as installable WordPress ZIP files. Release packages exclude repository-only CI/tests/documentation artifacts and contain the plugin under the `crowdfunding-for-woocommerce/` root directory.

Pre-release tags such as `v3.1.14.1-rc1` are published as GitHub pre-releases. Stable tags such as `v3.1.14.1` and `v3.1.14.2` are published as normal releases. A SHA-256 checksum is generated alongside each ZIP.

## Security

See [SECURITY.md](SECURITY.md).
