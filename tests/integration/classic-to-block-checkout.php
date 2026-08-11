<?php
/**
 * Cross-surface regression: classic product form -> Store API Cart/Checkout.
 *
 * OmniaTV's crowdfunding amount is entered on the normal WooCommerce product
 * form. A shopper may then land on a Cart or Checkout Block, so the custom
 * amount must survive the hand-off from the classic form handler to Store API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
    exit( 1 );
}

function omni_cf_bridge_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function omni_cf_bridge_assert_float( $expected, $actual, $message, $epsilon = 0.00001 ) {
    omni_cf_bridge_assert(
        abs( (float) $expected - (float) $actual ) < $epsilon,
        $message . " (expected {$expected}, got {$actual})"
    );
}

function omni_cf_bridge_reset_cart() {
    if ( WC()->cart ) {
        WC()->cart->empty_cart( true );
    }
    wc_clear_notices();
    $_POST    = array();
    $_REQUEST = array();
}

function omni_cf_bridge_request( $method, $route, array $params = array() ) {
    $request = new WP_REST_Request( $method, $route );
    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }
    return rest_do_request( $request );
}

function omni_cf_bridge_response_array( $response ) {
    if ( ! $response instanceof WP_REST_Response ) {
        return array();
    }
    return json_decode( wp_json_encode( $response->get_data() ), true );
}

function omni_cf_bridge_response_text( $response ) {
    if ( ! $response instanceof WP_REST_Response ) {
        return 'non-REST response';
    }
    return wp_json_encode( $response->get_data() );
}

omni_cf_bridge_assert( defined( 'WC_VERSION' ) && '11.0.1' === WC_VERSION, 'WooCommerce 11.0.1 must be active.' );
omni_cf_bridge_assert( class_exists( 'Alg_Woocommerce_Crowdfunding' ), 'Crowdfunding fork must be active.' );
omni_cf_bridge_assert( class_exists( 'WC_Form_Handler' ), 'Classic WooCommerce form handler must be available.' );

update_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_original_price', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_qty', 'yes' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );

add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

if ( null === WC()->cart ) {
    wc_load_cart();
}

$product = new WC_Product_Simple();
$product->set_name( 'Classic to Block checkout crowdfunding campaign' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '10' );
$product->set_price( '10' );
$product->set_virtual( true );
$product->set_tax_status( 'none' );
$product_id = $product->save();

omni_cf_bridge_assert( $product_id > 0, 'Fixture product must be created.' );

$order_id = 0;

try {
    update_post_meta( $product_id, '_alg_crowdfunding_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_min_price', '3' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_max_price', '' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_default_price', '10' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_step', '2' );
    clean_post_cache( $product_id );

    omni_cf_bridge_reset_cart();
    $_REQUEST['add-to-cart'] = (string) $product_id;
    $_REQUEST['quantity']    = '1';
    $_POST['add-to-cart']    = (string) $product_id;
    $_POST['quantity']       = '1';
    $_POST['alg_crowdfunding_open_price'] = '12.34';

    WC_Form_Handler::add_to_cart_action( false );

    $cart = WC()->cart->get_cart();
    omni_cf_bridge_assert( 1 === count( $cart ), 'Classic product form must add one crowdfunding cart item.' );
    $cart_item = reset( $cart );
    omni_cf_bridge_assert( isset( $cart_item['alg_crowdfunding_open_price'] ), 'Classic cart item must retain the crowdfunding amount key.' );
    omni_cf_bridge_assert_float( 12.34, $cart_item['alg_crowdfunding_open_price'], 'Classic cart item must retain amount 12.34.' );
    omni_cf_bridge_assert_float( 12.34, $cart_item['data']->get_price(), 'Classic cart item product price must be 12.34.' );

    WC()->cart->calculate_totals();
    omni_cf_bridge_assert_float( 12.34, WC()->cart->get_cart_contents_total(), 'Classic cart total must be 12.34 before Store API hand-off.' );

    rest_get_server();

    $cart_response = omni_cf_bridge_request( 'GET', '/wc/store/v1/cart' );
    omni_cf_bridge_assert(
        $cart_response instanceof WP_REST_Response && 200 === $cart_response->get_status(),
        'Cart Block Store API must accept the cart produced by the classic form. Response: ' . omni_cf_bridge_response_text( $cart_response )
    );
    $cart_data = omni_cf_bridge_response_array( $cart_response );
    omni_cf_bridge_assert( ! empty( $cart_data['items'] ), 'Store API cart must expose the classic crowdfunding item.' );
    omni_cf_bridge_assert(
        isset( $cart_data['totals']['total_items'] ) && '1234' === (string) $cart_data['totals']['total_items'],
        'Cart Block Store API must expose the selected 12.34 contribution in minor units.'
    );

    $address = array(
        'first_name' => 'Integration',
        'last_name'  => 'Tester',
        'company'    => '',
        'address_1'  => '1 Test Street',
        'address_2'  => '',
        'city'       => 'Athens',
        'state'      => '',
        'postcode'   => '10558',
        'country'    => 'GR',
        'email'      => 'classic-to-block@example.test',
        'phone'      => '2100000000',
    );

    $checkout = omni_cf_bridge_request(
        'POST',
        '/wc/store/v1/checkout',
        array(
            'billing_address'  => $address,
            'shipping_address' => $address,
            'payment_method'   => 'bacs',
            'payment_data'     => array(),
        )
    );
    omni_cf_bridge_assert(
        $checkout instanceof WP_REST_Response && $checkout->get_status() >= 200 && $checkout->get_status() < 300,
        'Checkout Block Store API must create an order from the classic crowdfunding cart. Response: ' . omni_cf_bridge_response_text( $checkout )
    );

    $checkout_data = omni_cf_bridge_response_array( $checkout );
    $order_id      = isset( $checkout_data['order_id'] ) ? absint( $checkout_data['order_id'] ) : 0;
    omni_cf_bridge_assert( $order_id > 0, 'Checkout Block response must include an order id.' );

    $order = wc_get_order( $order_id );
    omni_cf_bridge_assert( $order instanceof WC_Order, 'Hybrid checkout order must be loadable through WooCommerce CRUD.' );
    omni_cf_bridge_assert_float( 12.34, $order->get_total(), 'Hybrid checkout order total must preserve 12.34.' );

    $items = $order->get_items();
    omni_cf_bridge_assert( 1 === count( $items ), 'Hybrid checkout order must contain one line item.' );
    $order_item = reset( $items );
    omni_cf_bridge_assert( $product_id === $order_item->get_product_id(), 'Hybrid checkout line item must reference the crowdfunding product.' );
    omni_cf_bridge_assert_float( 12.34, $order_item->get_total(), 'Hybrid checkout line total must preserve the selected contribution.' );

    echo "classic-to-block-checkout-integration: ok\n";
} finally {
    remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
    omni_cf_bridge_reset_cart();
    if ( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->delete( true );
        }
    }
    wp_delete_post( $product_id, true );
}
