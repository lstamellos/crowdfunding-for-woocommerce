# Crowdfunding for WooCommerce — OmniaTV

Maintained fork of **Crowdfunding for WooCommerce 3.1.14** for administrator-managed crowdfunding campaigns on OmniaTV.

The immutable upstream import is tagged `upstream-3.1.14`. The fork deliberately preserves the existing `alg_*` options, `_alg_crowdfunding_*` product metadata, and public crowdfunding shortcodes so existing campaigns remain readable without a database migration.

## Fork policy

- Campaigns are created and edited only from the WordPress/WooCommerce administration interface.
- The upstream “Product by User” / My Account campaign-creation subsystem is removed, including its frontend upload path.
- Open pricing remains a public contribution path and is validated server-side before the amount reaches the WooCommerce cart.
- The upstream three-campaign free-version limit and commercial upsells are removed.
- Existing `product_by_user_*` options are left untouched in the database for rollback compatibility, but the fork no longer reads them.

## Compatibility status

The first maintained release intentionally declares **HPOS incompatible** because upstream 3.1.14 discovers orders through `WP_Query( post_type = shop_order )`. HPOS support will only be declared after the order-query layer is migrated to WooCommerce CRUD/query APIs and parity-tested.

Cart and Checkout Blocks are also declared **incompatible** until Store API / Blocks behavior is implemented and tested. Classic WooCommerce cart and checkout remain the current target.

## Development baseline

- Upstream baseline: `upstream-3.1.14`
- First maintained version: `3.1.14.1`
- Target PHP baseline: PHP 8.3; CI also checks PHP 8.4 syntax/security smoke tests.

## License and provenance

Upstream code is licensed under GNU GPL v3.0. Copyright notices for WP Wham / Algoritmika are retained. OmniaTV modifications are distributed under the same license.
