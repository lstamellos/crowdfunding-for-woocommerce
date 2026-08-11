<?php
/**
 * WooCommerce Store API / Cart and Checkout Blocks integration regression.
 *
 * This test verifies that a crowdfunding open-price contribution survives the
 * Store API cart flow and produces an order with the selected amount. It also
 * verifies the plugin's declared Cart/Checkout Blocks compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
    exit( 1 );
}

function omni_cf_blocks_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function omni_cf_blocks_assert_float( $expected, $actual, $message, $epsilon = 0.00001 ) {
    omni_cf_blocks_assert(
        abs( (float) $expected - (float) $actual ) < $epsilon,
        $message . " (expected {$expected}, got {$actual})"
    );
}

function omni_cf_blocks_reset_cart() {
    if ( WC()->cart ) {
        WC()->cart->empty_cart( true );
    }
    wc_clear_notices();
    $_POST    = array();
    $_REQUEST = array();
}

function omni_cf_blocks_request( $method, $route, array $params = array() ) {
    $request = new WP_REST_Request( $method, $route );
    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }
    return rest_do_request( $request );
}

function omni_cf_blocks_response_text( $response ) {
    if ( ! $response instanceof WP_REST_Response ) {
        return 'non-REST response';
    }
    return wp_json_encode( $response->get_data() );
}

/**
 * Store API response schemas may return nested stdClass values even though the
 * top-level response data is an array. Normalize recursively for assertions.
 */
function omni_cf_blocks_response_array( $response ) {
    if ( ! $response instanceof WP_REST_Response ) {
        return array();
    }

    return json_decode( wp_json_encode( $response->get_data() ), true );
}

omni_cf_blocks_assert( defined( 'WC_VERSION' ), 'WooCommerce must be active.' );
omni_cf_blocks_assert( '11.0.1' === WC_VERSION, 'Integration environment must use WooCommerce 11.0.1.' );
omni_cf_blocks_assert( class_exists( 'Alg_Woocommerce_Crowdfunding' ), 'Crowdfunding fork must be active.' );
omni_cf_blocks_assert( class_exists( 'Alg_Crowdfunding_Product_Open_Pricing' ), 'Crowdfunding open-pricing class must be loaded.' );
omni_cf_blocks_assert( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ), 'WooCommerce FeaturesUtil must be available.' );

update_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_original_price', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_qty', 'yes' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option(
    'woocommerce_bacs_settings',
    array(
        'enabled'      => 'yes',
        'title'        => 'Direct bank transfer',
        'description'  => '',
        'instructions' => '',
    )
);

add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

if ( null === WC()->cart ) {
    wc_load_cart();
}

$product = new WC_Product_Simple();
$product->set_name( 'Blocks integration crowdfunding campaign' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '10' );
$product->set_price( '10' );
$product->set_virtual( true );
$product->set_tax_status( 'none' );
$product_id = $product->save();

omni_cf_blocks_assert( $product_id > 0, 'Fixture product must be created.' );

$order_id = 0;

try {
    update_post_meta( $product_id, '_alg_crowdfunding_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_min_price', '3' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_max_price', '' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_default_price', '10' );
    update_post_meta( $product_id, '_alg_crowdfunding_product_open_price_step', '2' );
    clean_post_cache( $product_id );

    $compatibility = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin(
        'crowdfunding-for-woocommerce/crowdfunding-for-woocommerce.php'
    );
    omni_cf_blocks_assert(
        in_array( 'cart_checkout_blocks', $compatibility['compatible'], true ),
        'Plugin must declare Cart and Checkout Blocks compatibility.'
    );
    omni_cf_blocks_assert(
        ! in_array( 'cart_checkout_blocks', $compatibility['incompatible'], true ),
        'Plugin must not remain declared incompatible with Cart and Checkout Blocks.'
    );

    // Ensure Store API routes are registered before making requests.
    rest_get_server();

    omni_cf_blocks_reset_cart();
    $missing = omni_cf_blocks_request(
        'POST',
        '/wc/store/v1/cart/add-item',
        array(
            'id'       => $product_id,
            'quantity' => 1,
        )
    );
    omni_cf_blocks_assert(
        $missing instanceof WP_REST_Response && $missing->get_status() >= 400,
        'Store API must reject a crowdfunding contribution without an amount. Response: ' . omni_cf_blocks_response_text( $missing )
    );
    omni_cf_blocks_assert( 0 === count( WC()->cart->get_cart() ), 'Missing Store API amount must not add a cart item.' );

    omni_cf_blocks_reset_cart();
    $below_min = omni_cf_blocks_request(
        'POST',
        '/wc/store/v1/cart/add-item',
        array(
            'id'                          => $product_id,
            'quantity'                    => 1,
            'alg_crowdfunding_open_price' => '2.99',
        )
    );
    omni_cf_blocks_assert(
        $below_min instanceof WP_REST_Response && $below_min->get_status() >= 400,
        'Store API must reject a contribution below the configured minimum. Response: ' . omni_cf_blocks_response_text( $below_min )
    );
    omni_cf_blocks_assert( 0 === count( WC()->cart->get_cart() ), 'Below-minimum Store API amount must not add a cart item.' );

    omni_cf_blocks_reset_cart();
    $malformed = omni_cf_blocks_request(
        'POST',
        '/wc/store/v1/cart/add-item',
        array(
            'id'                          => $product_id,
            'quantity'                    => 1,
            'alg_crowdfunding_open_price' => array( '12.34' ),
        )
    );
    omni_cf_blocks_assert(
        $malformed instanceof WP_REST_Response && $malformed->get_status() >= 400,
        'Store API must reject a malformed crowdfunding amount. Response: ' . omni_cf_blocks_response_text( $malformed )
    );
    omni_cf_blocks_assert( 0 === count( WC()->cart->get_cart() ), 'Malformed Store API amount must not add a cart item.' );

    omni_cf_blocks_reset_cart();
    $add = omni_cf_blocks_request(
        'POST',
        '/wc/store/v1/cart/add-item',
        array(
            'id'                          => $product_id,
            'quantity'                    => 1,
            'alg_crowdfunding_open_price' => '12.34',
        )
    );
    omni_cf_blocks_assert(
        $add instanceof WP_REST_Response && $add->get_status() >= 200 && $add->get_status() < 300,
        'Valid Store API crowdfunding contribution must be added. Response: ' . omni_cf_blocks_response_text( $add )
    );

    $cart = WC()->cart->get_cart();
    omni_cf_blocks_assert( 1 === count( $cart ), 'Valid Store API contribution must produce one cart item.' );
    $cart_item = reset( $cart );
    omni_cf_blocks_assert(
        isset( $cart_item['alg_crowdfunding_open_price'] ),
        'Store API cart item must retain the crowdfunding amount key.'
    );
    omni_cf_blocks_assert_float( 12.34, $cart_item['alg_crowdfunding_open_price'], 'Store API cart item must retain the canonical amount.' );
    omni_cf_blocks_assert_float( 12.34, $cart_item['data']->get_price(), 'Store API cart product price must equal the selected amount.' );

    WC()->cart->calculate_totals();
    omni_cf_blocks_assert_float( 12.34, WC()->cart->get_cart_contents_total(), 'Store API cart total must equal the selected amount.' );

    $cart_response = omni_cf_blocks_request( 'GET', '/wc/store/v1/cart' );
    omni_cf_blocks_assert(
        $cart_response instanceof WP_REST_Response && 200 === $cart_response->get_status(),
        'Cart Store API response must succeed.'
    );
    $cart_data = omni_cf_blocks_response_array( $cart_response );
    omni_cf_blocks_assert( ! empty( $cart_data['items'] ), 'Cart Store API response must expose the crowdfunding cart item.' );
    omni_cf_blocks_assert(
        isset( $cart_data['totals']['total_items'] ) && '1234' === (string) $cart_data['totals']['total_items'],
        'Cart Store API totals must expose 12.34 in minor currency units.'
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
        'email'      => 'blocks-integration@example.test',
        'phone'      => '2100000000',
    );

    $checkout = omni_cf_blocks_request(
        'POST',
        '/wc/store/v1/checkout',
        array(
            'billing_address'  => $address,
            'shipping_address' => $address,
            'payment_method'   => 'bacs',
            'payment_data'     => array(),
        )
    );
    omni_cf_blocks_assert(
        $checkout instanceof WP_REST_Response && $checkout->get_status() >= 200 && $checkout->get_status() < 300,
        'Checkout Store API must create an order from the crowdfunding cart. Response: ' . omni_cf_blocks_response_text( $checkout )
    );

    $checkout_data = omni_cf_blocks_response_array( $checkout );
    $order_id      = isset( $checkout_data['order_id'] ) ? absint( $checkout_data['order_id'] ) : 0;
    omni_cf_blocks_assert( $order_id > 0, 'Checkout Store API response must include an order id.' );

    $order = wc_get_order( $order_id );
    omni_cf_blocks_assert( $order instanceof WC_Order, 'Store API checkout order must be loadable through WooCommerce CRUD.' );
    omni_cf_blocks_assert_float( 12.34, $order->get_total(), 'Store API checkout order total must equal the selected contribution.' );

    $items = $order->get_items();
    omni_cf_blocks_assert( 1 === count( $items ), 'Store API checkout order must contain one line item.' );
    $order_item = reset( $items );
    omni_cf_blocks_assert( $product_id === $order_item->get_product_id(), 'Store API checkout order item must reference the crowdfunding product.' );
    omni_cf_blocks_assert_float( 12.34, $order_item->get_total(), 'Store API checkout line total must preserve the selected contribution.' );

    echo "cart-checkout-blocks-integration: ok\n";
} finally {
    remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
    omni_cf_blocks_reset_cart();
    if ( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->delete( true );
        }
    }
    wp_delete_post( $product_id, true );
}
