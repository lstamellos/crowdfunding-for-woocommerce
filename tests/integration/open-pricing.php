<?php
/**
 * WooCommerce integration regression for the administrator-managed
 * crowdfunding open-price path used by OmniaTV.
 *
 * Run through WP-CLI after WordPress, WooCommerce and this plugin are active:
 *   wp eval-file tests/integration/open-pricing.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
    exit( 1 );
}

function omni_cf_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function omni_cf_assert_float( $expected, $actual, $message, $epsilon = 0.00001 ) {
    omni_cf_assert( abs( (float) $expected - (float) $actual ) < $epsilon, $message . " (expected {$expected}, got {$actual})" );
}

function omni_cf_reset_cart() {
    if ( WC()->cart ) {
        WC()->cart->empty_cart( true );
    }
    wc_clear_notices();
    $_POST = array();
}

omni_cf_assert( defined( 'WC_VERSION' ), 'WooCommerce must be active.' );
omni_cf_assert( '11.0.0' === WC_VERSION, 'Integration environment must use WooCommerce 11.0.0.' );
omni_cf_assert( class_exists( 'Alg_Woocommerce_Crowdfunding' ), 'Crowdfunding plugin must be active.' );
omni_cf_assert( class_exists( 'Alg_Crowdfunding_Product_Open_Pricing' ), 'Crowdfunding open-pricing class must be loaded.' );

update_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_original_price', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_qty', 'yes' );

if ( null === WC()->cart ) {
    wc_load_cart();
}

$product = new WC_Product_Simple();
$product->set_name( 'Integration crowdfunding campaign' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '10' );
$product->set_price( '10' );
$product_id = $product->save();

omni_cf_assert( $product_id > 0, 'Fixture product must be created.' );

try {
    update_post_meta( $product_id, '_alg_crowdfunding_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_min_price', '3' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_max_price', '' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_default_price', '10' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_step', '2' );

    clean_post_cache( $product_id );
    $product = wc_get_product( $product_id );

    omni_cf_assert( true === apply_filters( 'woocommerce_is_sold_individually', false, $product ), 'Open-price campaign must remain sold individually.' );
    omni_cf_assert( false === $product->supports( 'ajax_add_to_cart' ), 'Open-price campaign must disable AJAX add-to-cart.' );
    omni_cf_assert( true === $product->is_purchasable(), 'Published open-price campaign must be purchasable.' );

    // Render the real product-page hook and verify production-equivalent input constraints.
    $GLOBALS['post']    = get_post( $product_id );
    $GLOBALS['product'] = $product;
    setup_postdata( $GLOBALS['post'] );

    ob_start();
    do_action( 'woocommerce_before_add_to_cart_button' );
    $open_price_html = ob_get_clean();
    wp_reset_postdata();

    omni_cf_assert(
        1 === preg_match( '/<input[^>]*name="alg_crowdfunding_open_price"[^>]*>/i', $open_price_html, $input_match ),
        'Product page must render the crowdfunding open-price input.'
    );
    $input_html = $input_match[0];
    omni_cf_assert( 1 === preg_match( '/\bmin="3(?:\.0+)?"/i', $input_html ), 'Rendered input must enforce minimum 3.' );
    omni_cf_assert( 1 === preg_match( '/\bvalue="10(?:\.0+)?"/i', $input_html ), 'Rendered input must use default 10.' );
    omni_cf_assert( 0 === preg_match( '/\bmax=/i', $input_html ), 'Rendered input must not invent a maximum.' );
    omni_cf_assert( false !== strpos( $input_html, 'step="0.01"' ), 'Rendered input must allow cent precision.' );

    // Missing amount must be rejected by the real WC_Cart add-to-cart pipeline.
    omni_cf_reset_cart();
    $missing_key = WC()->cart->add_to_cart( $product_id, 1 );
    omni_cf_assert( false === $missing_key, 'Missing open price must be rejected.' );
    omni_cf_assert( wc_notice_count( 'error' ) > 0, 'Missing open price must create a WooCommerce error notice.' );

    // Amount below configured minimum must be rejected.
    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = '2.99';
    $below_min_key = WC()->cart->add_to_cart( $product_id, 1 );
    omni_cf_assert( false === $below_min_key, 'Price below 3 must be rejected.' );
    omni_cf_assert( wc_notice_count( 'error' ) > 0, 'Below-minimum price must create a WooCommerce error notice.' );

    // Malformed and negative values must be rejected.
    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = array( '10' );
    omni_cf_assert( false === WC()->cart->add_to_cart( $product_id, 1 ), 'Array-shaped open price must be rejected.' );

    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = '-1';
    omni_cf_assert( false === WC()->cart->add_to_cart( $product_id, 1 ), 'Negative open price must be rejected.' );

    // Exact minimum is valid.
    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = '3';
    $minimum_key = WC()->cart->add_to_cart( $product_id, 1 );
    omni_cf_assert( is_string( $minimum_key ) && '' !== $minimum_key, 'Configured minimum 3 must be accepted.' );
    $minimum_item = WC()->cart->get_cart_item( $minimum_key );
    omni_cf_assert_float( 3.0, $minimum_item['data']->get_price(), 'Minimum selected price must be applied to cart product.' );

    // A normal decimal contribution must flow through cart item data and totals.
    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = '12.34';
    $cart_item_key = WC()->cart->add_to_cart( $product_id, 1 );
    omni_cf_assert( is_string( $cart_item_key ) && '' !== $cart_item_key, 'Valid decimal open price must add the product to cart.' );

    $cart_item = WC()->cart->get_cart_item( $cart_item_key );
    omni_cf_assert( isset( $cart_item['alg_crowdfunding_open_price'] ), 'Cart item must retain canonical crowdfunding open-price data.' );
    omni_cf_assert_float( 12.34, $cart_item['alg_crowdfunding_open_price'], 'Cart item data must retain selected amount.' );
    omni_cf_assert_float( 12.34, $cart_item['data']->get_price(), 'WC_Product price must equal selected open price.' );

    WC()->cart->calculate_totals();
    omni_cf_assert_float( 12.34, WC()->cart->get_cart_contents_total(), 'Cart contents total must equal selected contribution.' );

    // No configured maximum means ordinary higher values remain valid.
    omni_cf_reset_cart();
    $_POST['alg_crowdfunding_open_price'] = '100';
    $higher_key = WC()->cart->add_to_cart( $product_id, 1 );
    omni_cf_assert( is_string( $higher_key ) && '' !== $higher_key, 'No-max campaign must accept a higher valid amount.' );
    omni_cf_assert_float( 100.0, WC()->cart->get_cart_item( $higher_key )['data']->get_price(), 'Higher selected price must be applied.' );

    // Session restoration must reapply the canonical amount through WC_Product::set_price().
    $restored_product = wc_get_product( $product_id );
    $restored_item = apply_filters(
        'woocommerce_get_cart_item_from_session',
        array( 'data' => $restored_product ),
        array( 'alg_crowdfunding_open_price' => '12.34' ),
        'integration-session-key'
    );
    omni_cf_assert( isset( $restored_item['alg_crowdfunding_open_price'] ), 'Session restore must retain crowdfunding open-price data.' );
    omni_cf_assert_float( 12.34, $restored_item['data']->get_price(), 'Session restore must reapply selected price.' );

    echo "woocommerce-open-pricing-integration: ok\n";
} finally {
    omni_cf_reset_cart();
    wp_delete_post( $product_id, true );
}
