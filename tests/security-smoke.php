<?php
/**
 * Minimal dependency-free security regression checks.
 *
 * These are not WordPress/WooCommerce integration tests. They exercise the
 * high-risk rendering and open-pricing paths with small API stubs so CI can
 * catch regressions without bootstrapping a full store.
 */

define( 'ABSPATH', __DIR__ . '/' );

$notices = array();
$post_meta = array(
    42 => array(
        '_alg_crowdfunding_enabled'                         => 'yes',
        '_alg_crowdfunding_product_open_price_enabled'      => 'yes',
        '_alg_crowdfunding_product_open_price_min_price'    => '3',
        '_alg_crowdfunding_product_open_price_max_price'    => '',
        '_alg_crowdfunding_product_open_price_default_price'=> '10',
        '_alg_crowdfunding_product_open_price_step'         => '2',
    ),
);

function add_shortcode( $tag, $callback ) {}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function get_option( $key, $default = false ) { return $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function safecss_filter_attr( $value ) {
    $value = preg_replace( '/expression\s*\([^)]*\)/i', '', (string) $value );
    $value = preg_replace( '/url\s*\(\s*["\']?javascript:[^)]*\)/i', '', $value );
    return $value;
}
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_html__( $value, $domain = null ) { return esc_html( $value ); }
function __( $value, $domain = null ) { return $value; }
function wp_kses_post( $value ) { return preg_replace( '#<(script|iframe)[^>]*>.*?</\1>#is', '', (string) $value ); }
function wp_unslash( $value ) { return $value; }
function wc_get_price_decimals() { return 2; }
function wc_format_decimal( $value, $dp = false ) {
    $value = str_replace( ',', '.', trim( (string) $value ) );
    if ( '' === $value || ! is_numeric( $value ) ) {
        return '';
    }
    $number = (float) $value;
    if ( ! is_finite( $number ) ) {
        return '';
    }
    return false === $dp ? (string) $number : number_format( $number, (int) $dp, '.', '' );
}
function get_post_meta( $post_id, $key, $single = true ) {
    global $post_meta;
    return $post_meta[ $post_id ][ $key ] ?? '';
}
function alg_wc_crdfnd_get_product_id_or_variation_parent_id( $product ) { return $product->get_id(); }
function wc_add_notice( $message, $type = 'success' ) { global $notices; $notices[] = array( $type, $message ); }
function current_user_can( $capability, ...$args ) { return true; }
function get_permalink( $id ) { return 'https://example.test/product/' . $id; }
function get_woocommerce_currency_symbol() { return '€'; }

class WC_Product {
    private $id;
    private $price = null;
    public function __construct( $id ) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function exists() { return true; }
    public function set_price( $price ) { $this->price = $price; }
    public function get_price_for_test() { return $this->price; }
}

$products = array( 42 => new WC_Product( 42 ) );
function wc_get_product( $id = null ) { global $products; return $products[ $id ?: 42 ] ?? null; }
function alg_wc_crdfnd_get_product_post_status( $product ) { return 'publish'; }

function assert_true( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

require_once __DIR__ . '/../includes/shortcodes/class-wc-crowdfunding-shortcodes.php';

$base_shortcodes = new Alg_WC_Crowdfunding_Shortcodes();
$percent_result = $base_shortcodes->output_shortcode( 50, array( 'type' => 'percent', 'total_value' => 200, 'round_precision' => '0 onclick=alert(1)' ) );
assert_true( 25.0 == $percent_result && is_numeric( $percent_result ), 'Percent shortcode precision must be coerced to a bounded integer.' );

$progress = require __DIR__ . '/../includes/shortcodes/class-wc-crowdfunding-shortcodes-progress-bar.php';

$malicious = $progress->get_progress_bar(
    array(
        'type'       => 'line',
        'width'      => '200px" onmouseover="alert(1)',
        'color'      => '#fff" onclick="alert(1)',
        'onclick'    => 'alert(1)',
        'style'      => 'background:url(javascript:alert(1))',
    ),
    5,
    10
);
assert_true( false === strpos( $malicious, ' onclick=' ), 'Unknown shortcode attributes must not become HTML attributes.' );
assert_true( false === strpos( $malicious, ' onmouseover=' ), 'Attribute values must not break out into new HTML attributes.' );
assert_true( false === strpos( $malicious, 'javascript:' ), 'Unsafe CSS must not survive style sanitization.' );
assert_true( false !== strpos( $malicious, 'value="0.5"' ), 'Progress value should be rendered as a normalized ratio.' );

$open_pricing = require __DIR__ . '/../includes/class-wc-crowdfunding-open-pricing.php';

$_POST = array( 'alg_crowdfunding_open_price' => '10.25' );
$notices = array();
assert_true( true === $open_pricing->validate_open_price_on_add_to_cart( true, 42 ), 'Valid open price should pass validation.' );
$cart_data = $open_pricing->add_open_price_to_cart_item_data( array(), 42, 0 );
assert_true( '10.25' === $cart_data['alg_crowdfunding_open_price'], 'Cart data must store the canonical open price.' );
$cart_item = $open_pricing->add_open_price_to_cart_item( array( 'data' => wc_get_product( 42 ) ) + $cart_data, 'key' );
assert_true( 10.25 === $cart_item['data']->get_price_for_test(), 'Canonical open price must be applied through WC_Product::set_price().' );

$_POST = array( 'alg_crowdfunding_open_price' => '2.99' );
$notices = array();
assert_true( false === $open_pricing->validate_open_price_on_add_to_cart( true, 42 ), 'Price below configured minimum must fail.' );
assert_true( ! empty( $notices ), 'Rejected price must produce a WooCommerce notice.' );

$_POST = array( 'alg_crowdfunding_open_price' => '<script>alert(1)</script>' );
$notices = array();
assert_true( false === $open_pricing->validate_open_price_on_add_to_cart( true, 42 ), 'Non-numeric open price must fail.' );

$_POST = array( 'alg_crowdfunding_open_price' => array( '10' ) );
$notices = array();
assert_true( false === $open_pricing->validate_open_price_on_add_to_cart( true, 42 ), 'Array-shaped open price input must fail.' );

$_POST = array();
$notices = array();
assert_true( false === $open_pricing->validate_open_price_on_add_to_cart( true, 42 ), 'Missing open price must fail for an open-price campaign.' );

$repo_root = realpath( __DIR__ . '/..' );
$main_source = file_get_contents( $repo_root . '/crowdfunding-for-woocommerce.php' );
assert_true( false !== strpos( $main_source, "declare_compatibility( 'custom_order_tables', __FILE__, false )" ), 'HPOS must stay explicitly incompatible until integration-tested.' );
assert_true( false !== strpos( $main_source, "declare_compatibility( 'cart_checkout_blocks', __FILE__, true )" ), 'Cart/Checkout Blocks compatibility must stay explicitly declared after Store API integration coverage.' );

$open_pricing_source = file_get_contents( $repo_root . '/includes/class-wc-crowdfunding-open-pricing.php' );
assert_true( false !== strpos( $open_pricing_source, 'woocommerce_store_api_add_to_cart_data' ), 'Store API amount capture hook must remain registered.' );
assert_true( false !== strpos( $open_pricing_source, 'woocommerce_store_api_validate_add_to_cart' ), 'Native Store API validation hook must remain registered.' );

$removed_files = array(
    'includes/class-wc-crowdfunding-my-account.php',
    'includes/functions/wc-crowdfunding-functions-user-campaign-fields.php',
    'includes/settings/class-wc-crowdfunding-settings-product-by-user.php',
    'includes/shortcodes/class-wc-crowdfunding-shortcodes-products-add-form.php',
);
foreach ( $removed_files as $removed_file ) {
    assert_true( ! file_exists( $repo_root . '/' . $removed_file ), 'Removed Product-by-User file must not be restored: ' . $removed_file );
}

$php_source = '';
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $repo_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if (
        'php' === strtolower( $file->getExtension() ) &&
        false === strpos( $file->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR ) &&
        false === strpos( $file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR )
    ) {
        $php_source .= file_get_contents( $file->getPathname() );
    }
}
assert_true( false === strpos( $php_source, 'product_crowdfunding_add_new_campaign' ), 'Frontend campaign-creation shortcode must remain removed.' );
assert_true( false === strpos( $php_source, 'move_uploaded_file' ), 'Legacy frontend direct-upload path must remain removed.' );

echo "security-smoke: ok\n";
