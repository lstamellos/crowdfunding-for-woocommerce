<?php
/*
Plugin Name: Crowdfunding for WooCommerce — OmniaTV
Plugin URI: https://github.com/lstamellos/crowdfunding-for-woocommerce
Description: Maintained OmniaTV fork for administrator-managed WooCommerce crowdfunding campaigns.
Version: 3.1.14.5
Author: OmniaTV
Author URI: https://omniatv.com/
Update URI: https://github.com/lstamellos/crowdfunding-for-woocommerce
Text Domain: crowdfunding-for-woocommerce
Domain Path: /langs
WC tested up to: 11.0
Copyright: © 2018-2025 WP Wham. All rights reserved.
Copyright: © 2026 OmniaTV modifications.
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'ALG_WC_CROWDFUNDING_VERSION' ) ) {
	define( 'ALG_WC_CROWDFUNDING_VERSION', '3.1.14.5' );
}

// Keep the update provider available even if WooCommerce is temporarily inactive.
require_once __DIR__ . '/includes/class-wc-crowdfunding-github-updater.php';

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) return;

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, false );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

if ( ! class_exists( 'Alg_Woocommerce_Crowdfunding' ) ) :

/**
 * Main Alg_Woocommerce_Crowdfunding Class
 *
 * @class   Alg_Woocommerce_Crowdfunding
 * @version 3.1.14.5
 */
final class Alg_Woocommerce_Crowdfunding {
	
	public $core     = null;
	public $settings = null;
	
	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 2.3.0
	 */
	public $version = ALG_WC_CROWDFUNDING_VERSION;

	/**
	 * @var Alg_Woocommerce_Crowdfunding The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_Woocommerce_Crowdfunding Instance
	 *
	 * Ensures only one instance of Alg_Woocommerce_Crowdfunding is loaded or can be loaded.
	 *
	 * @static
	 * @return Alg_Woocommerce_Crowdfunding - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_Woocommerce_Crowdfunding Constructor.
	 *
	 * @version 3.1.14
	 * @access  public
	 */
	function __construct() {

		// Set up localisation
		add_action( 'init', array( $this, 'load_localization' ) );

		// Include required files
		$this->includes();

		// Settings & Scripts
		if ( is_admin() ) {
			// Backend
			$this->admin();
		} else {
			// Frontend
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}
			
	/**
	 * @since   3.1.14
	 */
	public function load_localization() {
		load_plugin_textdomain( 'crowdfunding-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
	}

	/**
	 * enqueue_scripts.
	 *
	 * @version 2.9.1
	 * @since   1.2.0
	 */
	function enqueue_scripts() {
		if (
			'yes' === get_option( 'alg_woocommerce_crowdfunding_enabled', 'yes' ) &&
			'yes' === get_option( 'alg_woocommerce_crowdfunding_variable_add_to_cart_radio_enabled', 'no' )
		) {
			wp_enqueue_script( 'alg-variations',   $this->plugin_url() . '/includes/js/alg-variations-frontend.js', array( 'jquery' ), $this->version );
		}

		wp_enqueue_script( 'alg-progress-bar-src', $this->plugin_url() . '/includes/js/progressbar.min.js',         array( 'jquery' ), $this->version );
		wp_enqueue_script( 'alg-progress-bar',     $this->plugin_url() . '/includes/js/alg-progressbar.js',         array( 'jquery' ), $this->version );
	}

	/**
	 * register_admin_scripts.
	 *
	 * @version 2.3.2
	 * @since   1.1.0
	 */
	function register_admin_scripts() {
		wp_register_script(
			'jquery-ui-timepicker',
			$this->plugin_url() . '/includes/js/jquery.timepicker.min.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * enqueue_admin_scripts.
	 *
	 * @version 2.3.3
	 * @todo    [dev] maybe 'jquery-ui-css' => '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css'
	 */
	function enqueue_admin_scripts() {
		wp_enqueue_script( 'jquery-ui-datepicker', false,                                                             array(),           $this->version );
		wp_enqueue_script( 'jquery-ui-timepicker' );
		wp_enqueue_script( 'alg-datepicker',       $this->plugin_url() . '/includes/js/alg-datepicker.js',            array( 'jquery' ), $this->version, true );
		wp_enqueue_style(  'jquery-ui-css',        '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css',     array(),           $this->version );
		wp_enqueue_style(  'alg-timepicker',       $this->plugin_url() . '/includes/css/jquery.timepicker.min.css',   array(),           $this->version );
		wp_enqueue_script( 'jquery-ui-dialog',     false,                                                             array(),           $this->version );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 3.1.10
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$settings = array( '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=alg_crowdfunding' ) ) . '">' .
			esc_html__( 'Settings', 'woocommerce' ) . '</a>' );
		return array_merge( $settings, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 3.0.0
	 */
	function includes() {
		// Product edit meta box etc.
		require_once( 'includes/class-wc-crowdfunding-admin.php' );
		// Core
		$this->core = require_once( 'includes/class-wc-crowdfunding.php' );
	}

	/**
	 * add settings to WC status report
	 *
	 * @version 3.1.6
	 * @since   3.1.6
	 * @author  WP Wham
	 */
	public static function add_settings_to_status_report() {
		#region add_settings_to_status_report
		$settings_general        = Alg_WC_Crowdfunding_Settings_General::get_settings();
		$settings_product_info   = Alg_WC_Crowdfunding_Settings_Product_Info::get_settings();
		$settings_open_pricing   = Alg_WC_Crowdfunding_Settings_Open_Pricing::get_settings();
		$settings = array_merge( $settings_general, $settings_product_info, $settings_open_pricing );
		?>
		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="Crowdfunding Settings"><h2><?php esc_html_e( 'Crowdfunding Settings', 'crowdfunding-for-woocommerce' ); ?></h2></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $settings as $setting ): ?>
				<?php 
				if ( in_array( $setting['type'], array( 'title', 'sectionend' ) ) ) { 
					continue;
				}
				if ( isset( $setting['title'] ) ) {
					$title = $setting['title'];
				} elseif ( isset( $setting['desc'] ) ) {
					$title = $setting['desc'];
				} else {
					$title = $setting['id'];
				}
				$value = get_option( $setting['id'] ); 
				?>
				<tr>
					<td data-export-label="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( $title, 'crowdfunding-for-woocommerce' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value ) : (string) $value ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		#endregion add_settings_to_status_report
	}

	/**
	 * admin.
	 *
	 * @version 3.1.6
	 * @since   2.9.0
	 */
	function admin() {

		// Action links
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );

		// Scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init',            array( $this, 'register_admin_scripts' ) );

		// Settings
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
		require_once( 'includes/settings/class-wc-crowdfunding-settings-section.php' );
		$this->settings = array();
		$this->settings['general']         = require_once( 'includes/settings/class-wc-crowdfunding-settings-general.php' );
		$this->settings['product-info']    = require_once( 'includes/settings/class-wc-crowdfunding-settings-product-info.php' );
		$this->settings['open-pricing']    = require_once( 'includes/settings/class-wc-crowdfunding-settings-open-pricing.php' );
		add_action( 'woocommerce_system_status_report', array( $this, 'add_settings_to_status_report' ) );

		// Version updated
		if ( get_option( 'alg_woocommerce_crowdfunding_version', '' ) !== $this->version ) {
			add_action( 'admin_init', array( $this, 'version_updated' ) );
		}
	}

	/**
	 * version_updated.
	 *
	 * @version 3.0.0
	 * @since   2.7.0
	 */
	function version_updated() {
		// The maintained fork no longer registers the legacy My Account campaign endpoint.
		// Flush rewrite rules once when upgrading so stale endpoint rules are removed.
		flush_rewrite_rules( false );

		update_option( 'alg_woocommerce_crowdfunding_version', $this->version );
	}

	/**
	 * Add Woocommerce settings tab to WooCommerce settings.
	 *
	 * @version 3.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-wc-settings-crowdfunding.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}
}

endif;

if ( ! function_exists( 'alg_wc_crowdfunding' ) ) {
	/**
	 * Returns the main instance of Alg_Woocommerce_Crowdfunding to prevent the need to use globals.
	 *
	 * @return Alg_Woocommerce_Crowdfunding - Main instance
	 */
	function alg_wc_crowdfunding() {
		return Alg_Woocommerce_Crowdfunding::instance();
	}
}

if ( ! function_exists( 'alg_wc_crowdfunding_get_file' ) ) {
	/**
	 * alg_wc_crowdfunding_get_file.
	 *
	 * @version 2.3.1
	 * @since   2.3.1
	 */
	function alg_wc_crowdfunding_get_file() {
		return __FILE__;
	}
}

alg_wc_crowdfunding();