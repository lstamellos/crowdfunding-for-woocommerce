<?php
/**
 * Stripe Express Checkout open-price integration regression.
 *
 * Covers the two server-side contracts used by the product-page wallet flow:
 * the wc_* selected-product field must affect WC_Product runtime pricing, and
 * the same wc_* field must survive Store API add-to-cart as the canonical
 * crowdfunding cart-item amount.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
	exit( 1 );
}

function omni_cf_stripe_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function omni_cf_stripe_assert_float( $expected, $actual, $message, $epsilon = 0.00001 ) {
	omni_cf_stripe_assert(
		abs( (float) $expected - (float) $actual ) < $epsilon,
		$message . " (expected {$expected}, got {$actual})"
	);
}

function omni_cf_stripe_reset_cart() {
	if ( WC()->cart ) {
		WC()->cart->empty_cart( true );
	}
	wc_clear_notices();
	$_POST    = array();
	$_REQUEST = array();
}

function omni_cf_stripe_request( $method, $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

omni_cf_stripe_assert( defined( 'WC_VERSION' ), 'WooCommerce must be active.' );
omni_cf_stripe_assert( class_exists( 'Alg_Crowdfunding_Runtime_Pricing' ), 'Runtime pricing bridge must be loaded.' );

update_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' );
add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

if ( null === WC()->cart ) {
	wc_load_cart();
}

$product = new WC_Product_Simple();
$product->set_name( 'Stripe express crowdfunding campaign' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '10' );
$product->set_price( '10' );
$product->set_virtual( true );
$product->set_tax_status( 'none' );
$product_id = $product->save();

omni_cf_stripe_assert( $product_id > 0, 'Fixture product must be created.' );

try {
	update_post_meta( $product_id, '_alg_crowdfunding_enabled', 'yes' );
	update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_enabled', 'yes' );
	update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_min_price', '3' );
	update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_max_price', '' );
	update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_default_price', '10' );
	clean_post_cache( $product_id );

	// Stripe's selected-product legacy AJAX request uses the wc_* field injected
	// by the frontend bridge. Runtime WC_Product pricing must consume it directly.
	$_POST['wc_crowdfunding_open_price'] = '17.25';
	$runtime_product = wc_get_product( $product_id );
	omni_cf_stripe_assert_float(
		17.25,
		$runtime_product->get_price(),
		'Stripe selected-product wc_* amount must become the request-scoped product price.'
	);
	$_POST = array();

	// Stripe simple-product express checkout uses Store API for add-to-cart.
	// The wc_* alias must be normalized to the legacy cart-item key so the
	// existing validation, cart pricing and order persistence path is reused.
	omni_cf_stripe_reset_cart();
	rest_get_server();
	$add = omni_cf_stripe_request(
		'POST',
		'/wc/store/v1/cart/add-item',
		array(
			'id'                         => $product_id,
			'quantity'                   => 1,
			'wc_crowdfunding_open_price' => '13.57',
		)
	);

	omni_cf_stripe_assert(
		$add instanceof WP_REST_Response && $add->get_status() >= 200 && $add->get_status() < 300,
		'Stripe wc_* Store API contribution must be accepted.'
	);

	$cart = WC()->cart->get_cart();
	omni_cf_stripe_assert( 1 === count( $cart ), 'Stripe Store API request must produce one cart item.' );
	$cart_item = reset( $cart );
	omni_cf_stripe_assert(
		isset( $cart_item['alg_crowdfunding_open_price'] ),
		'Stripe wc_* amount must map to the canonical crowdfunding cart-item key.'
	);
	omni_cf_stripe_assert_float( 13.57, $cart_item['alg_crowdfunding_open_price'], 'Canonical cart-item amount must equal the Stripe contribution.' );
	omni_cf_stripe_assert_float( 13.57, $cart_item['data']->get_price(), 'Cart product price must equal the Stripe contribution.' );

	WC()->cart->calculate_totals();
	omni_cf_stripe_assert_float( 13.57, WC()->cart->get_cart_contents_total(), 'Cart total must equal the Stripe contribution.' );

	echo "stripe-express-open-pricing-integration: ok\n";
} finally {
	remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
	omni_cf_stripe_reset_cart();
	wp_delete_post( $product_id, true );
}
