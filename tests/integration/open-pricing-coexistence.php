<?php
/**
 * Integration regression for coexistence with WP Wham Product Open Pricing
 * (Name Your Price) for WooCommerce 1.7.4.
 *
 * This intentionally uses two separate simple products:
 * - one Product Open Pricing product with crowdfunding disabled;
 * - one crowdfunding open-price product with Product Open Pricing disabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
    exit( 1 );
}

function omni_cf_coexist_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function omni_cf_coexist_assert_float( $expected, $actual, $message, $epsilon = 0.00001 ) {
    omni_cf_coexist_assert(
        abs( (float) $expected - (float) $actual ) < $epsilon,
        $message . " (expected {$expected}, got {$actual})"
    );
}

function omni_cf_coexist_reset_cart() {
    if ( WC()->cart ) {
        WC()->cart->empty_cart( true );
    }
    wc_clear_notices();
    $_POST    = array();
    $_REQUEST = array();
}

function omni_cf_coexist_notices_text() {
    $notices = wc_get_notices();
    $parts   = array();

    foreach ( $notices as $type => $items ) {
        foreach ( $items as $item ) {
            $message = isset( $item['notice'] ) ? wp_strip_all_tags( $item['notice'] ) : '';
            $parts[] = $type . ': ' . $message;
        }
    }

    return implode( ' | ', $parts );
}

function omni_cf_coexist_classic_add_to_cart( $product_id, array $posted_fields ) {
    omni_cf_coexist_reset_cart();

    $_REQUEST['add-to-cart'] = (string) $product_id;
    $_REQUEST['quantity']    = '1';
    $_POST['add-to-cart']    = (string) $product_id;
    $_POST['quantity']       = '1';

    foreach ( $posted_fields as $key => $value ) {
        $_POST[ $key ] = $value;
    }

    WC_Form_Handler::add_to_cart_action( false );
    WC()->cart->calculate_totals();

    return WC()->cart->get_cart();
}

function omni_cf_coexist_render_before_add_to_cart( $product ) {
    $GLOBALS['post']    = get_post( $product->get_id() );
    $GLOBALS['product'] = $product;
    setup_postdata( $GLOBALS['post'] );

    ob_start();
    do_action( 'woocommerce_before_add_to_cart_button' );
    $html = ob_get_clean();

    wp_reset_postdata();
    return $html;
}

function omni_cf_coexist_extract_input_name( $html, $needle ) {
    if ( ! preg_match_all( '/<input\b[^>]*>/i', $html, $matches ) ) {
        return '';
    }

    foreach ( $matches[0] as $input ) {
        if ( false === strpos( $input, $needle ) ) {
            continue;
        }
        if ( preg_match( '/\bname=["\']([^"\']+)["\']/i', $input, $name_match ) ) {
            return html_entity_decode( $name_match[1], ENT_QUOTES, 'UTF-8' );
        }
    }

    return '';
}

function omni_cf_coexist_find_product_open_pricing_version() {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    foreach ( get_plugins() as $basename => $data ) {
        if ( false !== strpos( $basename, 'product-open-pricing-name-your-price-for-woocommerce/' ) ) {
            return isset( $data['Version'] ) ? (string) $data['Version'] : '';
        }
    }

    return '';
}

function omni_cf_coexist_enable_wpwham_open_pricing( $product_id ) {
    update_option( 'alg_wc_product_open_pricing_enabled', 'yes' );

    update_post_meta( $product_id, '_alg_wc_product_open_pricing_enabled', 'yes' );
    update_post_meta( $product_id, '_alg_wc_product_open_pricing_default_price', '5' );
    update_post_meta( $product_id, '_alg_wc_product_open_pricing_min_price', '2' );
    update_post_meta( $product_id, '_alg_wc_product_open_pricing_max_price', '' );

    clean_post_cache( $product_id );
}

omni_cf_coexist_assert( defined( 'WC_VERSION' ), 'WooCommerce must be active.' );
omni_cf_coexist_assert( '11.0.0' === WC_VERSION, 'Integration environment must use WooCommerce 11.0.0.' );
omni_cf_coexist_assert( class_exists( 'Alg_Woocommerce_Crowdfunding' ), 'Crowdfunding fork must be active.' );
omni_cf_coexist_assert( class_exists( 'WC_Form_Handler' ), 'WooCommerce classic form handler must be available.' );
omni_cf_coexist_assert(
    '1.7.4' === omni_cf_coexist_find_product_open_pricing_version(),
    'Product Open Pricing 1.7.4 must be installed.'
);

update_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_original_price', 'yes' );
update_option( 'alg_crowdfunding_open_price_hide_qty', 'yes' );

if ( null === WC()->cart ) {
    wc_load_cart();
}

$wpwham_product = new WC_Product_Simple();
$wpwham_product->set_name( 'Integration free contribution' );
$wpwham_product->set_status( 'publish' );
$wpwham_product->set_catalog_visibility( 'visible' );
$wpwham_product->set_regular_price( '5' );
$wpwham_product->set_price( '5' );
$wpwham_product_id = $wpwham_product->save();

$crowdfunding_product = new WC_Product_Simple();
$crowdfunding_product->set_name( 'Integration crowdfunding contribution' );
$crowdfunding_product->set_status( 'publish' );
$crowdfunding_product->set_catalog_visibility( 'visible' );
$crowdfunding_product->set_regular_price( '10' );
$crowdfunding_product->set_price( '10' );
$crowdfunding_product_id = $crowdfunding_product->save();

omni_cf_coexist_assert( $wpwham_product_id > 0 && $crowdfunding_product_id > 0, 'Fixture products must be created.' );

try {
    // Product A: Product Open Pricing only. Crowdfunding is deliberately disabled.
    omni_cf_coexist_enable_wpwham_open_pricing( $wpwham_product_id );
    update_post_meta( $wpwham_product_id, '_alg_crowdfunding_enabled', 'no' );
    update_post_meta( $wpwham_product_id, '_alg_crowdfunding_product_open_price_enabled', 'no' );

    // Product B: Crowdfunding open pricing only. WP Wham Product Open Pricing is deliberately disabled.
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_enabled', 'yes' );
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_product_open_price_enabled', 'yes' );
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_product_open_price_min_price', '3' );
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_product_open_price_max_price', '' );
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_product_open_price_default_price', '10' );
    update_post_meta( $crowdfunding_product_id, '_alg_crowdfunding_product_open_price_step', '2' );
    update_post_meta( $crowdfunding_product_id, '_alg_wc_product_open_pricing_enabled', 'no' );

    clean_post_cache( $wpwham_product_id );
    clean_post_cache( $crowdfunding_product_id );

    $wpwham_product       = wc_get_product( $wpwham_product_id );
    $crowdfunding_product = wc_get_product( $crowdfunding_product_id );

    // Frontend namespaces must remain isolated.
    $wpwham_html = omni_cf_coexist_render_before_add_to_cart( $wpwham_product );
    omni_cf_coexist_assert(
        false !== strpos( $wpwham_html, 'alg_open_price' ),
        'Product Open Pricing product must render its alg_open_price field.'
    );
    omni_cf_coexist_assert(
        false === strpos( $wpwham_html, 'alg_crowdfunding_open_price' ),
        'Crowdfunding amount field must not render on the Product Open Pricing product.'
    );

    $wpwham_input_name = omni_cf_coexist_extract_input_name( $wpwham_html, 'alg_open_price' );
    omni_cf_coexist_assert(
        '' !== $wpwham_input_name,
        'Product Open Pricing rendered input must expose a POST field name.'
    );

    $crowdfunding_html = omni_cf_coexist_render_before_add_to_cart( $crowdfunding_product );
    omni_cf_coexist_assert(
        false !== strpos( $crowdfunding_html, 'alg_crowdfunding_open_price' ),
        'Crowdfunding product must render its crowdfunding amount field.'
    );
    omni_cf_coexist_assert(
        false === strpos( $crowdfunding_html, 'name="alg_open_price"' ),
        'Product Open Pricing amount field must not render on the crowdfunding-only product.'
    );

    // Product A: submit the exact field contract rendered by WP Wham 1.7.4.
    $wpwham_cart = omni_cf_coexist_classic_add_to_cart(
        $wpwham_product_id,
        array( $wpwham_input_name => '7.50' )
    );
    omni_cf_coexist_assert(
        1 === count( $wpwham_cart ),
        'Product Open Pricing product must add to cart; input=' . $wpwham_input_name . '; notices=' . omni_cf_coexist_notices_text()
    );
    $wpwham_item = reset( $wpwham_cart );
    omni_cf_coexist_assert( isset( $wpwham_item['alg_open_price'] ), 'WP Wham cart item must retain alg_open_price.' );
    omni_cf_coexist_assert( ! isset( $wpwham_item['alg_crowdfunding_open_price'] ), 'Crowdfunding cart key must not leak into WP Wham product.' );
    omni_cf_coexist_assert_float( 7.50, $wpwham_item['data']->get_price(), 'WP Wham selected amount must control its cart price.' );
    omni_cf_coexist_assert_float( 7.50, WC()->cart->get_cart_contents_total(), 'WP Wham cart total must equal selected amount.' );

    // Product B: crowdfunding fork must own the selected price and WP Wham must stay out.
    $crowdfunding_cart = omni_cf_coexist_classic_add_to_cart(
        $crowdfunding_product_id,
        array( 'alg_crowdfunding_open_price' => '12.34' )
    );
    omni_cf_coexist_assert( 1 === count( $crowdfunding_cart ), 'Crowdfunding product must add to cart.' );
    $crowdfunding_item = reset( $crowdfunding_cart );
    omni_cf_coexist_assert( isset( $crowdfunding_item['alg_crowdfunding_open_price'] ), 'Crowdfunding cart item must retain its amount key.' );
    omni_cf_coexist_assert( ! isset( $crowdfunding_item['alg_open_price'] ), 'WP Wham cart key must not leak into crowdfunding product.' );
    omni_cf_coexist_assert_float( 12.34, $crowdfunding_item['data']->get_price(), 'Crowdfunding selected amount must control its cart price.' );
    omni_cf_coexist_assert_float( 12.34, WC()->cart->get_cart_contents_total(), 'Crowdfunding cart total must equal selected amount.' );

    echo "open-pricing-coexistence-1.7.4: ok\n";
} finally {
    omni_cf_coexist_reset_cart();
    wp_delete_post( $wpwham_product_id, true );
    wp_delete_post( $crowdfunding_product_id, true );
}
