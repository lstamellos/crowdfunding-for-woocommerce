=== Crowdfunding for WooCommerce — OmniaTV ===
Contributors: lstamellos, wpwham
Tags: woocommerce, crowdfunding
Requires at least: 6.8
Requires PHP: 8.3
Tested up to: 6.8
Stable tag: 3.1.14.1
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

HPOS is deliberately declared incompatible in 3.1.14.1 because the upstream order-discovery layer still queries `shop_order` posts directly. HPOS support will only be enabled after the order-query layer is migrated to WooCommerce CRUD APIs and parity-tested.

Cart and Checkout Blocks are also deliberately declared incompatible until Store API / Blocks behavior is implemented and tested. Classic WooCommerce cart and checkout are the current target.

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

== Changelog ==

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

= 3.1.14 - 2025-06-01 =
* Upstream WP Wham release used as the immutable fork baseline (`upstream-3.1.14`).
