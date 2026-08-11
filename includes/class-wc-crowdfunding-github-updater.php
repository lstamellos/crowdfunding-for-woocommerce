<?php
/**
 * GitHub Releases update provider for the maintained OmniaTV fork.
 *
 * Uses WordPress' native Update URI mechanism and only offers stable GitHub
 * Releases that contain the expected installable plugin ZIP asset.
 *
 * @since 3.1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Alg_Crowdfunding_GitHub_Updater' ) ) :

class Alg_Crowdfunding_GitHub_Updater {

	const UPDATE_URI     = 'https://github.com/lstamellos/crowdfunding-for-woocommerce';
	const API_URL        = 'https://api.github.com/repos/lstamellos/crowdfunding-for-woocommerce/releases/latest';
	const PLUGIN_FILE    = 'crowdfunding-for-woocommerce/crowdfunding-for-woocommerce.php';
	const PLUGIN_SLUG    = 'crowdfunding-for-woocommerce';
	const CACHE_KEY      = 'alg_crowdfunding_github_release';
	const CACHE_LIFETIME = 21600; // 6 hours.

	/**
	 * Register the custom update provider for the Update URI hostname.
	 */
	public function __construct() {
		add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 10, 4 );
	}

	/**
	 * Supply WordPress with update metadata from the latest stable GitHub Release.
	 *
	 * @param array|false $update      Existing update response.
	 * @param array       $plugin_data Parsed plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if (
			self::PLUGIN_FILE !== $plugin_file ||
			empty( $plugin_data['UpdateURI'] ) ||
			self::UPDATE_URI !== untrailingslashit( $plugin_data['UpdateURI'] )
		) {
			return $update;
		}

		$release = $this->get_latest_release();
		if ( ! is_array( $release ) ) {
			return $update;
		}

		$version = isset( $release['tag_name'] ) ? ltrim( (string) $release['tag_name'], "vV" ) : '';
		if ( '' === $version || ! preg_match( '/^\d+(?:\.\d+)+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return $update;
		}

		$package = $this->find_installable_asset_url( $release, $version );
		if ( '' === $package ) {
			return $update;
		}

		return array(
			'id'      => self::UPDATE_URI,
			'slug'    => self::PLUGIN_SLUG,
			'version' => $version,
			'url'     => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : self::UPDATE_URI,
			'package' => esc_url_raw( $package ),
		);
	}

	/**
	 * Fetch and cache the latest stable GitHub Release metadata.
	 * GitHub's /releases/latest endpoint excludes drafts and prereleases.
	 *
	 * @return array|false
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'           => 'Crowdfunding-for-WooCommerce-OmniaTV/' . ( defined( 'ALG_WC_CROWDFUNDING_VERSION' ) ? ALG_WC_CROWDFUNDING_VERSION : 'unknown' ),
					'X-GitHub-Api-Version' => '2026-03-10',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) || empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return false;
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_LIFETIME );
		return $release;
	}

	/**
	 * Return the exact installable release asset URL for a version.
	 * Source-code archives are deliberately ignored.
	 *
	 * @param array  $release GitHub release payload.
	 * @param string $version Normalized version.
	 * @return string
	 */
	private function find_installable_asset_url( $release, $version ) {
		$expected_name = 'crowdfunding-for-woocommerce-' . $version . '.zip';

		foreach ( $release['assets'] as $asset ) {
			if (
				is_array( $asset ) &&
				isset( $asset['name'], $asset['browser_download_url'] ) &&
				$expected_name === $asset['name'] &&
				0 === strpos( $asset['browser_download_url'], 'https://github.com/lstamellos/crowdfunding-for-woocommerce/releases/download/' )
			) {
				return $asset['browser_download_url'];
			}
		}

		return '';
	}
}

endif;

return new Alg_Crowdfunding_GitHub_Updater();
