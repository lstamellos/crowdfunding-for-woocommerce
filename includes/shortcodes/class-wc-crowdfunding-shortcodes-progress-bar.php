<?php
/**
 * Crowdfunding for WooCommerce - Progress Bar Shortcodes
 *
 * @version 3.0.0
 * @since   2.3.6
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Crowdfunding_Shortcodes_Progress_Bar' ) ) :

class Alg_WC_Crowdfunding_Shortcodes_Progress_Bar extends Alg_WC_Crowdfunding_Shortcodes {

	/**
	 * Constructor.
	 *
	 * @version 2.3.6
	 * @since   2.3.6
	 */
	function __construct() {
		add_shortcode( 'product_crowdfunding_goal_remaining_progress_bar',         array( $this, 'alg_product_crowdfunding_goal_remaining_progress_bar' ) );
		add_shortcode( 'product_crowdfunding_goal_backers_remaining_progress_bar', array( $this, 'alg_product_crowdfunding_goal_backers_remaining_progress_bar' ) );
		add_shortcode( 'product_crowdfunding_goal_items_remaining_progress_bar',   array( $this, 'alg_product_crowdfunding_goal_items_remaining_progress_bar' ) );
		add_shortcode( 'product_crowdfunding_time_remaining_progress_bar',         array( $this, 'alg_product_crowdfunding_time_remaining_progress_bar' ) );
		// Deprecated
		add_shortcode( 'product_crowdfunding_goal_progress_bar',                   array( $this, 'alg_product_crowdfunding_goal_progress_bar' ) );
		add_shortcode( 'product_crowdfunding_time_progress_bar',                   array( $this, 'alg_product_crowdfunding_time_progress_bar' ) );
	}

	/**
	 * get_progress_bar.
	 *
	 * @version 2.7.0
	 * @since   2.3.0
	 */
	function get_progress_bar( $atts, $value, $max_value ) {
		$atts      = is_array( $atts ) ? $atts : array();
		$value     = max( 0, (float) $value );
		$max_value = max( 0, (float) $max_value );
		$type      = isset( $atts['type'] ) ? sanitize_key( $atts['type'] ) : 'standard';

		if ( in_array( $type, array( 'line', 'circle' ), true ) ) {
			$atts['type'] = $type;
			return $this->get_js_progress_bar( $atts, $value, $max_value );
		}

		return sprintf(
			'<progress value="%s" max="%s"></progress>',
			esc_attr( $value ),
			esc_attr( $max_value )
		);
	}

	/**
	 * Sanitize a CSS dimension accepted by the progress-bar renderer.
	 */
	private function sanitize_css_dimension( $value, $default ) {
		$value = trim( (string) $value );
		if ( 'auto' === strtolower( $value ) || preg_match( '/^-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|%|em|rem|vh|vw|vmin|vmax|pt)?$/i', $value ) ) {
			return $value;
		}
		return $default;
	}

	/**
	 * Sanitize a CSS color value accepted by ProgressBar.js.
	 */
	private function sanitize_color_value( $value, $default ) {
		$value = trim( (string) $value );
		if (
			preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ||
			preg_match( '/^[a-z]+$/i', $value ) ||
			preg_match( '/^(?:rgb|rgba|hsl|hsla)\([0-9.,%\s-]+\)$/i', $value )
		) {
			return $value;
		}
		return $default;
	}

	/**
	 * get_js_progress_bar.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 * @todo    [dev] `width` (maybe `100%`)
	 */
	function get_js_progress_bar( $atts, $value, $max_value ) {
		$type = isset( $atts['type'] ) && in_array( $atts['type'], array( 'line', 'circle' ), true ) ? $atts['type'] : 'line';

		$color      = $this->sanitize_color_value( $atts['color'] ?? '#2bde73', '#2bde73' );
		$text_color = $this->sanitize_color_value( $atts['text_color'] ?? '#999', '#999' );

		$text_position = isset( $atts['text_position'] ) ? sanitize_key( $atts['text_position'] ) : 'right';
		if ( ! in_array( $text_position, array( 'left', 'right', 'variable' ), true ) ) {
			$text_position = 'right';
		}

		$text_position_variable_max_left = isset( $atts['text_position_variable_max_left'] )
			? min( 100, max( 0, (float) $atts['text_position_variable_max_left'] ) )
			: 75;
		$text_top = $this->sanitize_css_dimension( $atts['text_top'] ?? '30px', '30px' );

		$width  = $this->sanitize_css_dimension( $atts['width'] ?? '200px', '200px' );
		$height = $this->sanitize_css_dimension( $atts['height'] ?? ( 'line' === $type ? '8px' : '200px' ), ( 'line' === $type ? '8px' : '200px' ) );
		$style  = isset( $atts['style'] ) ? (string) $atts['style'] : '';
		$style  = safecss_filter_attr( 'width:' . $width . ';height:' . $height . ';position:relative;' . $style );

		$progress_value = ( 0.0 !== (float) $max_value ) ? (float) $value / (float) $max_value : 0;

		return sprintf(
			'<div class="alg-progress-bar" type="%1$s" color="%2$s" text_color="%3$s" text_position="%4$s" text_position_variable_max_left="%5$s" text_top="%6$s" style="%7$s" value="%8$s"></div>',
			esc_attr( $type ),
			esc_attr( $color ),
			esc_attr( $text_color ),
			esc_attr( $text_position ),
			esc_attr( $text_position_variable_max_left ),
			esc_attr( $text_top ),
			esc_attr( $style ),
			esc_attr( $progress_value )
		);
	}

	/**
	 * alg_product_crowdfunding_time_remaining_progress_bar.
	 *
	 * @version 2.3.2
	 * @since   2.2.1
	 */
	function alg_product_crowdfunding_time_remaining_progress_bar( $atts ) {
		$product_id = $this->get_shortcode_product_id( $atts );
		if ( ! $product_id ) {
			return '';
		}

		$deadline_datetime  = trim( get_post_meta( $product_id, '_' . 'alg_crowdfunding_deadline', true )  . ' ' . get_post_meta( $product_id, '_' . 'alg_crowdfunding_deadline_time', true ), ' ' );
		$startdate_datetime = trim( get_post_meta( $product_id, '_' . 'alg_crowdfunding_startdate', true ) . ' ' . get_post_meta( $product_id, '_' . 'alg_crowdfunding_starttime', true ), ' ' );

		$seconds_remaining = strtotime( $deadline_datetime ) - ( (int) current_time( 'timestamp' ) );
		$seconds_total     = strtotime( $deadline_datetime ) - strtotime( $startdate_datetime );

		return $this->output_shortcode( $this->get_progress_bar( $atts, $seconds_remaining, $seconds_total ), $atts );
	}

	/**
	 * alg_product_crowdfunding_time_progress_bar.
	 *
	 * @version     2.2.1
	 * @since       1.2.0
	 * @deprecated
	 */
	function alg_product_crowdfunding_time_progress_bar( $atts ) {
		return $this->alg_product_crowdfunding_time_remaining_progress_bar( $atts );
	}

	/**
	 * alg_product_crowdfunding_goal_items_remaining_progress_bar.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function alg_product_crowdfunding_goal_items_remaining_progress_bar( $atts ) {
		$product_id = $this->get_shortcode_product_id( $atts );
		if ( ! $product_id ) {
			return '';
		}
		$current_value = alg_wc_crdfnd_get_product_orders_data( 'total_items', $atts );
		$max_value     = get_post_meta( $product_id, '_' . 'alg_crowdfunding_goal_items', true );
		return $this->output_shortcode( $this->get_progress_bar( $atts, $current_value, $max_value ), $atts );
	}

	/**
	 * alg_product_crowdfunding_goal_backers_remaining_progress_bar.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function alg_product_crowdfunding_goal_backers_remaining_progress_bar( $atts ) {
		$product_id = $this->get_shortcode_product_id( $atts );
		if ( ! $product_id ) {
			return '';
		}
		$current_value = alg_wc_crdfnd_get_product_orders_data( 'total_orders', $atts );
		$max_value     = get_post_meta( $product_id, '_' . 'alg_crowdfunding_goal_backers', true );
		return $this->output_shortcode( $this->get_progress_bar( $atts, $current_value, $max_value ), $atts );
	}

	/**
	 * alg_product_crowdfunding_goal_remaining_progress_bar.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function alg_product_crowdfunding_goal_remaining_progress_bar( $atts ) {
		$product_id = $this->get_shortcode_product_id( $atts );
		if ( ! $product_id ) {
			return '';
		}
		$current_value = alg_wc_crdfnd_get_product_orders_data( 'orders_sum', $atts );
		$max_value     = get_post_meta( $product_id, '_' . 'alg_crowdfunding_goal_sum', true );
		return $this->output_shortcode( $this->get_progress_bar( $atts, $current_value, $max_value ), $atts );
	}

	/**
	 * alg_product_crowdfunding_goal_progress_bar.
	 *
	 * @version     2.2.0
	 * @since       1.2.0
	 * @deprecated
	 */
	function alg_product_crowdfunding_goal_progress_bar( $atts ) {
		return $this->alg_product_crowdfunding_goal_remaining_progress_bar( $atts );
	}

}

endif;

return new Alg_WC_Crowdfunding_Shortcodes_Progress_Bar();
