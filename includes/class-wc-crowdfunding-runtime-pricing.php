<?php
/**
 * Runtime open-price bridge for WooCommerce and express checkout flows.
 *
 * Keeps the user-entered crowdfunding amount request-scoped. The amount is
 * never persisted to the product's _price/_regular_price metadata.
 *
 * @version 3.1.14.5
 * @since   3.1.14.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Alg_Crowdfunding_Runtime_Pricing' ) ) :

class Alg_Crowdfunding_Runtime_Pricing {

	const EXPRESS_FIELD = 'wc_crowdfunding_open_price';
	const LEGACY_FIELD  = 'alg_crowdfunding_open_price';

	private $request_amount = false;
	private $request_amount_loaded = false;

	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'bridge_express_request_field' ), 1 );
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_runtime_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_runtime_price' ), PHP_INT_MAX, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_bridge' ), PHP_INT_MAX );
	}

	/**
	 * Copy the wc_* express field to the legacy field consumed by the existing
	 * validation/cart-item path.
	 */
	public function bridge_express_request_field() {
		if ( isset( $_POST[ self::LEGACY_FIELD ] ) || ! isset( $_POST[ self::EXPRESS_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( is_array( $_POST[ self::EXPRESS_FIELD ] ) || is_object( $_POST[ self::EXPRESS_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$_POST[ self::LEGACY_FIELD ] = $_POST[ self::EXPRESS_FIELD ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->request_amount_loaded = false;
		$this->request_amount        = false;
	}

	/**
	 * Use the submitted crowdfunding amount as a request-scoped WC_Product price.
	 * No persistent product price metadata is changed.
	 *
	 * @param string|float $price   Current WooCommerce price.
	 * @param WC_Product   $product Product object.
	 * @return string|float
	 */
	public function filter_runtime_price( $price, $product ) {
		if ( ! $product instanceof WC_Product || ! $this->is_open_price_product( $product ) ) {
			return $price;
		}

		$amount = $this->get_request_amount();
		if ( null === $amount ) {
			$amount = $this->get_product_page_default_amount( $product );
		}

		if ( ! is_string( $amount ) || '' === $amount || ! $this->amount_is_allowed( $amount, $product ) ) {
			return $price;
		}

		return $amount;
	}

	/** Enqueue the browser-side synchronization bridge on open-price products. */
	public function enqueue_frontend_bridge() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $this->is_open_price_product( $product ) ) {
			return;
		}

		wp_enqueue_script(
			'alg-crowdfunding-runtime-pricing',
			alg_wc_crowdfunding()->plugin_url() . '/includes/js/alg-runtime-open-pricing.js',
			array( 'jquery' ),
			ALG_WC_CROWDFUNDING_VERSION,
			true
		);
	}

	private function is_open_price_product( $product ) {
		$product_id = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $product );
		return (
			'yes' === get_post_meta( $product_id, '_alg_crowdfunding_enabled', true ) &&
			'yes' === get_post_meta( $product_id, '_alg_crowdfunding_product_open_price_enabled', true )
		);
	}

	/** @return string|null Null when no request amount exists. */
	private function get_request_amount() {
		if ( $this->request_amount_loaded ) {
			return $this->request_amount;
		}

		$this->request_amount_loaded = true;
		$this->request_amount        = null;
		$field                       = null;

		if ( isset( $_POST[ self::EXPRESS_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$field = self::EXPRESS_FIELD;
		} elseif ( isset( $_POST[ self::LEGACY_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$field = self::LEGACY_FIELD;
		}

		if ( null === $field || is_array( $_POST[ $field ] ) || is_object( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $this->request_amount;
		}

		$normalized = $this->normalize_amount( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( false !== $normalized ) {
			$this->request_amount = $normalized;
		}

		return $this->request_amount;
	}

	/**
	 * Use the configured default only for the currently rendered product page so
	 * wallet buttons have the same initial amount as the visible contribution field.
	 *
	 * @return string|null
	 */
	private function get_product_page_default_amount( $product ) {
		if ( wp_doing_ajax() || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}

		$queried_id = absint( get_queried_object_id() );
		$product_id = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $product );
		if ( ! $queried_id || $queried_id !== $product_id ) {
			return null;
		}

		$default = $this->normalize_amount(
			get_post_meta( $product_id, '_alg_crowdfunding_product_open_price_default_price', true ),
			true
		);

		return ( false === $default || '' === $default ) ? null : $default;
	}

	/** @return string|false */
	private function normalize_amount( $value, $allow_empty = false ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return false;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $allow_empty ? '' : false;
		}

		if ( ! preg_match( '/^\d+(?:[.,]\d+)?$/D', $value ) ) {
			return false;
		}

		$value = wc_format_decimal( str_replace( ',', '.', $value ), wc_get_price_decimals() );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return false;
		}

		$numeric = (float) $value;
		return ( is_finite( $numeric ) && $numeric >= 0 ) ? $value : false;
	}

	private function amount_is_allowed( $amount, $product ) {
		$product_id = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $product );
		$min_price  = $this->normalize_amount( get_post_meta( $product_id, '_alg_crowdfunding_product_open_price_min_price', true ), true );
		$max_price  = $this->normalize_amount( get_post_meta( $product_id, '_alg_crowdfunding_product_open_price_max_price', true ), true );

		if ( false !== $min_price && '' !== $min_price && (float) $amount < (float) $min_price ) {
			return false;
		}
		if ( false !== $max_price && '' !== $max_price && (float) $max_price > 0 && (float) $amount > (float) $max_price ) {
			return false;
		}

		return true;
	}
}

endif;

return new Alg_Crowdfunding_Runtime_Pricing();
