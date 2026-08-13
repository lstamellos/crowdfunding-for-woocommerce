=== Crowdfunding for WooCommerce — OmniaTV ===
Contributors: lstamellos, wpwham
Tags: woocommerce, crowdfunding
Requires at least: 6.8
Requires PHP: 8.3
Tested up to: 7.0
Stable tag: 3.1.14.7
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Maintained OmniaTV fork of Crowdfunding for WooCommerce for administrator-managed campaigns.

== Description ==

This fork preserves the existing Crowdfunding for WooCommerce data model and public campaign shortcodes while removing the unsupported frontend campaign-creation subsystem.

Campaigns are created and edited only through WordPress/WooCommerce administration.

Supported campaign functionality includes:

* Goal amount, backer and item targets.
* Start and end dates/times.
* End-on-time and end-on-goal behavior.
* Configurable WooCommerce order statuses included in campaign totals.
* Open pricing / Name Your Price contributions.
* Campaign information and progress-bar shortcodes.
* Campaign aggregate reconciliation and reporting.

The upstream Product by User / My Account campaign creation, editing, deletion and frontend upload feature is intentionally removed from this fork.

Existing `alg_*` options and `_alg_crowdfunding_*` product metadata are preserved. No database migration is required from upstream 3.1.14.

= Compatibility =

HPOS remains deliberately declared incompatible because the upstream order-discovery layer still queries `shop_order` posts directly. HPOS support will only be enabled after the order-query layer is migrated to WooCommerce CRUD APIs and parity-tested.

Classic WooCommerce cart/checkout and Cart/Checkout Blocks are supported. The Blocks path uses WooCommerce Store API integration so the crowdfunding open-price value is validated, retained in cart data, applied to cart totals and persisted to the order.

Cross-surface behavior is also covered: a contribution entered through the classic WooCommerce product form can continue through a Cart or Checkout Block without losing the selected amount.

Product-page express checkout integrations can consume the selected crowdfunding amount through the request-scoped WooCommerce product price. The maintained fork also exposes a synchronized `wc_crowdfunding_open_price` field for WooCommerce Stripe Express Checkout while retaining the legacy `alg_crowdfunding_open_price` field for backward compatibility. The selected amount is never persisted to the product's `_price` metadata.

TagDiv Composer / Newspaper layouts are supported when they render the native WooCommerce classic checkout surface or native WooCommerce Cart/Checkout Blocks. The OmniaTV production configuration was audited to confirm that TagDiv acts as the page/template rendering layer and does not replace WooCommerce checkout with a separate checkout engine.

The crowdfunding open-price path is integration-tested against WordPress 7.0.3 and WooCommerce 11.0.1, including coexistence with Product Open Pricing (Name Your Price) for WooCommerce 1.7.4 on a separate product.

= Updates =

The maintained fork integrates with the native WordPress plugin updater through its `Update URI`.

Update metadata is read from the latest published stable GitHub Release for `lstamellos/crowdfunding-for-woocommerce`. Drafts and prereleases are not offered. Only the installable release asset named `crowdfunding-for-woocommerce-VERSION.zip` from this repository is accepted; GitHub source-code archives are not used as update packages.

The latest release metadata is cached for six hours. The updater does not force background installation: the normal WordPress per-plugin “Enable auto-updates” setting controls whether an available update is installed automatically. Manual updates from WordPress administration and WP-CLI remain supported.

The native wp-admin plugin information dialog is populated from this packaged `readme.txt`, including Description, Installation and Changelog, so the installed plugin's details stay aligned with the shipped release.

= Public Shortcodes =

Backers and money:

* `[product_crowdfunding_total_sum]`
* `[product_crowdfunding_total_backers]`
* `[product_crowdfunding_total_items]`
* `[product_crowdfunding_list_backers]`
* `[product_crowdfunding_goal]`
* `[product_crowdfunding_goal_remaining]`
* `[product_crowdfunding_goal_remaining_progress_bar]`
* `[product_crowdfunding_goal_backers]`
* `[product_crowdfunding_goal_backers_remaining]`
* `[product_crowdfunding_goal_backers_remaining_progress_bar]`
* `[product_crowdfunding_goal_items]`
* `[product_crowdfunding_goal_items_remaining]`
* `[product_crowdfunding_goal_items_remaining_progress_bar]`

Time:

* `[product_crowdfunding_startdate]`
* `[product_crowdfunding_starttime]`
* `[product_crowdfunding_startdatetime]`
* `[product_crowdfunding_deadline]`
* `[product_crowdfunding_deadline_time]`
* `[product_crowdfunding_deadline_datetime]`
* `[product_crowdfunding_time_remaining]`
* `[product_crowdfunding_time_remaining_progress_bar]`

Other:

* `[crowdfunding_totals]`
* `[product_crowdfunding_add_to_cart_form]`
* `[crowdfunding_translate]`

== Installation ==

1. Install the plugin in `/wp-content/plugins/crowdfunding-for-woocommerce/`.
2. Activate it through WordPress administration.
3. Configure it under WooCommerce > Settings > Crowdfunding.
4. Create or edit crowdfunding products from WooCommerce administration only.
5. Optionally enable native WordPress auto-updates for this plugin from the Plugins screen.

== Changelog ==

= 3.1.14.7 - 2026-08-13 =
* FIX: Detect contribution changes made with native number-input spinner arrows and refresh Stripe Express Checkout / Google Pay / Apple Pay totals from the new amount.
* FIX: Cover keyboard and wheel stepping of the numeric contribution field with the same value-driven wallet refresh path.
* UX: Suppress duplicate Stripe wallet refreshes when multiple browser events report the same contribution value.
* TEST: Add JavaScript syntax validation for the runtime open-pricing bridge to CI.

= 3.1.14.6 - 2026-08-13 =
* FIX: Refresh Stripe Express Checkout / Google Pay / Apple Pay product totals from the currently entered crowdfunding amount instead of retaining the configured default.
* FIX: Map Stripe's `wc_crowdfunding_open_price` field into the canonical crowdfunding cart-item data in WooCommerce Store API add-to-cart requests.
* COMPAT: Preserve the selected crowdfunding amount through direct Stripe Express Checkout cart and order flows.
* ADMIN: Populate the native wp-admin plugin information dialog from the packaged `readme.txt` (Description, Installation and Changelog).
* TEST: Add dedicated Stripe Express Checkout / Store API regression coverage.

= 3.1.14.5 - 2026-08-13 =
* FIX: Restore native numeric increment/decrement controls for the crowdfunding contribution field.
* COMPAT: Use the submitted crowdfunding contribution as a request-scoped `WC_Product` price without persisting product price metadata.
* COMPAT: Bridge the selected contribution to WooCommerce Stripe Express Checkout through a synchronized `wc_`-prefixed field so Apple Pay and Google Pay product-page flows receive the selected amount.
* UX: Refresh product-page express checkout totals when the contribution amount changes.
* COMPAT: Retain the existing classic cart and Store API cart-item persistence paths as backward-compatible fallbacks.

= 3.1.14.4 - 2026-08-13 =
* UX: Display whole crowdfunding contribution amounts without redundant decimals (for example `5` instead of `5.00`).
* UX: Display fractional contribution amounts with exactly two decimals and a comma separator (for example `5.5` or `5,5` as `5,50`).
* COMPAT: Accept both comma and dot decimal separators on classic product-form and Store API open-price submissions while preserving canonical WooCommerce dot-decimal values internally.
* TEST: Cover localized frontend rendering and comma-decimal cart/order persistence.

= 3.1.14.3 - 2026-08-12 =
* FEATURE: Add a native WordPress `Update URI` provider backed by published stable GitHub Releases.
* UPDATE: Accept only the version-matched installable release ZIP asset from `lstamellos/crowdfunding-for-woocommerce`; ignore GitHub source-code archives.
* UPDATE: Cache GitHub release metadata for six hours and fail safely if release metadata or the expected package asset is unavailable.
* UPDATE: Preserve the native WordPress per-plugin auto-update policy instead of forcing background installation.
* TEST: Add deterministic integration coverage with mocked WordPress.org and GitHub release responses, including isolation from other GitHub-hosted plugins.

= 3.1.14.2 - 2026-08-12 =
* COMPAT: Add WooCommerce Store API handling for crowdfunding open-price cart data.
* COMPAT: Declare Cart and Checkout Blocks compatible after integration testing.
* COMPAT: Preserve selected crowdfunding contribution amounts from Store API add-to-cart through Cart/Checkout Blocks and into WooCommerce orders.
* COMPAT: Preserve selected amounts across the classic product-form to Block checkout bridge.
* COMPAT: Support TagDiv Composer / Newspaper checkout pages when they render native WooCommerce classic checkout or Cart/Checkout Blocks.
* TEST: Validate classic checkout order persistence, Store API Cart/Checkout Block order persistence, and classic-to-Block checkout against WordPress 7.0.3 and WooCommerce 11.0.1.
* TEST: Revalidate coexistence with Product Open Pricing (Name Your Price) for WooCommerce 1.7.4.
* COMPAT: HPOS remains explicitly unsupported pending WooCommerce CRUD order-query migration and parity testing.

= 3.1.14.1 - 2026-08-08 =
* SECURITY: Restrict progress-bar shortcodes to a fixed, escaped attribute schema and remove the attribute-injection path reported as CVE-2025-5767.
* SECURITY: Add nonce and capability checks to administrator campaign update/reset actions.
* SECURITY: Normalize and validate open-price contributions server-side before storing them in WooCommerce cart data.
* SECURITY: Apply open prices with `WC_Product::set_price()` instead of runtime dynamic properties.
* SECURITY: Harden shortcode wrappers, language output, backer templates and customer-derived values.
* CHANGE: Remove the Product by User / My Account frontend campaign-creation subsystem and frontend file upload path.
* CHANGE: Remove the upstream free-version campaign limit and commercial upsells.
* COMPAT: Declare HPOS incompatible until the order-query layer is migrated and tested.
* COMPAT: Declare Cart and Checkout Blocks incompatible until Store API / Blocks support is implemented and tested.
* COMPAT: Preserve existing `alg_*` options and `_alg_crowdfunding_*` product metadata without migration.
* TEST: Validate coexistence with Product Open Pricing (Name Your Price) for WooCommerce 1.7.4 on separate products.

= 3.1.14 - 2025-06-01 =
* Upstream WP Wham release used as the immutable fork baseline (`upstream-3.1.14`).
