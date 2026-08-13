<?php
/**
 * GitHub Releases update provider for the maintained OmniaTV fork.
 *
 * Uses WordPress' native Update URI mechanism and only offers stable GitHub
 * Releases that contain the expected installable plugin ZIP asset. It also
 * exposes the packaged readme.txt through WordPress' native plugin-info modal.
 *
 * @version 3.1.14.6
 * @since   3.1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Alg_Crowdfunding_GitHub_Updater' ) ) :

class Alg_Crowdfunding_GitHub_Updater {

	const UPDATE_URI     = 'https://github.com/lstamellos/crowdfunding-for-woocommerce';
	const API_URL        = 'https://api.github.com/repos/lstamellos/crowdfunding-for-woocommerce/releases/latest';
	const PLUGIN_FILE    = 'crowdfunding-for-woocommerce/crowdfunding-for-woocommerce.php';
	const PLUGIN_SLUG    = 'crowdfunding-for-woocommerce';
	const PLUGIN_NAME    = 'Crowdfunding for WooCommerce — OmniaTV';
	const CACHE_KEY      = 'alg_crowdfunding_github_release';
	const CACHE_LIFETIME = 21600; // 6 hours.

	/**
	 * Register the custom update and plugin-information providers.
	 */
	public function __construct() {
		add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
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
	 * Populate the native wp-admin plugin-information modal from the packaged
	 * WordPress-style readme.txt instead of querying WordPress.org.
	 *
	 * @param false|object|array $result Existing API result.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object|array
	 */
	public function filter_plugin_information( $result, $action, $args ) {
		if (
			'plugin_information' !== $action ||
			! is_object( $args ) ||
			empty( $args->slug ) ||
			self::PLUGIN_SLUG !== $args->slug
		) {
			return $result;
		}

		$readme = $this->read_local_readme();
		if ( '' === $readme ) {
			return $result;
		}

		$sections = $this->parse_readme_sections( $readme );
		if ( empty( $sections['description'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => self::PLUGIN_NAME,
			'slug'          => self::PLUGIN_SLUG,
			'version'       => defined( 'ALG_WC_CROWDFUNDING_VERSION' ) ? ALG_WC_CROWDFUNDING_VERSION : '',
			'author'        => '<a href="https://omniatv.com/">OmniaTV</a>',
			'author_profile'=> 'https://omniatv.com/',
			'homepage'      => self::UPDATE_URI,
			'requires'      => $this->read_readme_header( $readme, 'Requires at least' ),
			'tested'        => $this->read_readme_header( $readme, 'Tested up to' ),
			'requires_php'  => $this->read_readme_header( $readme, 'Requires PHP' ),
			'sections'      => $sections,
			'external'      => true,
		);
	}

	/** @return string */
	private function read_local_readme() {
		$path = dirname( __DIR__ ) . '/readme.txt';
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_string( $contents ) ? $contents : '';
	}

	/**
	 * Extract Description, Installation and Changelog sections from readme.txt.
	 *
	 * @param string $readme Readme contents.
	 * @return array
	 */
	private function parse_readme_sections( $readme ) {
		$sections = array();
		$matches  = array();

		if ( ! preg_match_all( '/^==\s*(.+?)\s*==\s*$/m', $readme, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $sections;
		}

		$count = count( $matches[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$title = strtolower( trim( $matches[1][ $i ][0] ) );
			if ( ! in_array( $title, array( 'description', 'installation', 'changelog' ), true ) ) {
				continue;
			}

			$start = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
			$end   = ( $i + 1 < $count ) ? $matches[0][ $i + 1 ][1] : strlen( $readme );
			$body  = trim( substr( $readme, $start, $end - $start ) );

			$sections[ $title ] = $this->format_readme_section( $body );
		}

		return $sections;
	}

	/**
	 * Render the small subset of WordPress readme markup used by this fork.
	 *
	 * @param string $text Section body.
	 * @return string
	 */
	private function format_readme_section( $text ) {
		$lines      = preg_split( '/\r\n|\r|\n/', $text );
		$html       = '';
		$paragraph  = array();
		$list_type  = '';

		$flush_paragraph = function() use ( &$html, &$paragraph ) {
			if ( ! empty( $paragraph ) ) {
				$html .= '<p>' . $this->format_inline_readme( implode( ' ', $paragraph ) ) . '</p>';
				$paragraph = array();
			}
		};

		$close_list = function() use ( &$html, &$list_type ) {
			if ( '' !== $list_type ) {
				$html .= '</' . $list_type . '>';
				$list_type = '';
			}
		};

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				$flush_paragraph();
				$close_list();
				continue;
			}

			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $heading ) ) {
				$flush_paragraph();
				$close_list();
				$html .= '<h4>' . esc_html( $heading[1] ) . '</h4>';
				continue;
			}

			if ( preg_match( '/^\*\s+(.+)$/', $line, $item ) ) {
				$flush_paragraph();
				if ( 'ul' !== $list_type ) {
					$close_list();
					$list_type = 'ul';
					$html .= '<ul>';
				}
				$html .= '<li>' . $this->format_inline_readme( $item[1] ) . '</li>';
				continue;
			}

			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $item ) ) {
				$flush_paragraph();
				if ( 'ol' !== $list_type ) {
					$close_list();
					$list_type = 'ol';
					$html .= '<ol>';
				}
				$html .= '<li>' . $this->format_inline_readme( $item[1] ) . '</li>';
				continue;
			}

			$close_list();
			$paragraph[] = $line;
		}

		$flush_paragraph();
		$close_list();

		return wp_kses_post( $html );
	}

	/** @return string */
	private function format_inline_readme( $text ) {
		$text = esc_html( $text );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
		return $text;
	}

	/** @return string */
	private function read_readme_header( $readme, $header ) {
		$pattern = '/^' . preg_quote( $header, '/' ) . ':\s*(.+)$/mi';
		if ( preg_match( $pattern, $readme, $match ) ) {
			return sanitize_text_field( trim( $match[1] ) );
		}
		return '';
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
