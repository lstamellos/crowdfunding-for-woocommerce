<?php
/**
 * Integration coverage for the GitHub Releases update provider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

function alg_updater_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$plugin_file = 'crowdfunding-for-woocommerce/crowdfunding-for-woocommerce.php';
$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );

alg_updater_assert( '3.1.14.3' === $plugin_data['Version'], 'plugin version is 3.1.14.3' );
alg_updater_assert(
	'https://github.com/lstamellos/crowdfunding-for-woocommerce' === $plugin_data['UpdateURI'],
	'Update URI points to the maintained repository'
);

add_filter(
	'pre_http_request',
	function( $preempt, $args, $url ) {
		if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check/' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'plugins'      => array(),
						'translations' => array(),
						'no_update'    => array(),
					)
				),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( 'https://api.github.com/repos/lstamellos/crowdfunding-for-woocommerce/releases/latest' !== $url ) {
			return $preempt;
		}

		$payload = array(
			'tag_name' => 'v3.1.14.4',
			'html_url' => 'https://github.com/lstamellos/crowdfunding-for-woocommerce/releases/tag/v3.1.14.4',
			'assets'   => array(
				array(
					'name'                 => 'crowdfunding-for-woocommerce-3.1.14.4.zip.sha256',
					'browser_download_url' => 'https://github.com/lstamellos/crowdfunding-for-woocommerce/releases/download/v3.1.14.4/crowdfunding-for-woocommerce-3.1.14.4.zip.sha256',
				),
				array(
					'name'                 => 'crowdfunding-for-woocommerce-3.1.14.4.zip',
					'browser_download_url' => 'https://github.com/lstamellos/crowdfunding-for-woocommerce/releases/download/v3.1.14.4/crowdfunding-for-woocommerce-3.1.14.4.zip',
				),
			),
		);

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $payload ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

delete_site_transient( 'alg_crowdfunding_github_release' );
delete_site_transient( 'update_plugins' );
wp_update_plugins();

$updates = get_site_transient( 'update_plugins' );
alg_updater_assert( is_object( $updates ), 'WordPress update transient exists' );
alg_updater_assert( isset( $updates->response[ $plugin_file ] ), 'custom provider exposes an available update' );

$offer = $updates->response[ $plugin_file ];
alg_updater_assert( '3.1.14.4' === $offer->new_version, 'latest stable release version is exposed' );
alg_updater_assert(
	'https://github.com/lstamellos/crowdfunding-for-woocommerce/releases/download/v3.1.14.4/crowdfunding-for-woocommerce-3.1.14.4.zip' === $offer->package,
	'installable release asset is used as the package'
);
alg_updater_assert(
	false === strpos( $offer->package, '/archive/' ) && false === strpos( $offer->package, '/zipball/' ),
	'GitHub source archives are not used'
);
alg_updater_assert( ! isset( $offer->autoupdate ), 'provider does not force background auto-updates' );

$other = apply_filters(
	'update_plugins_github.com',
	array( 'sentinel' => true ),
	array( 'UpdateURI' => 'https://github.com/example/other-plugin' ),
	'other-plugin/other-plugin.php',
	array( 'en_US' )
);
alg_updater_assert( isset( $other['sentinel'] ) && true === $other['sentinel'], 'provider does not interfere with other GitHub-hosted plugins' );

delete_site_transient( 'alg_crowdfunding_github_release' );
delete_site_transient( 'update_plugins' );

echo "GitHub updater integration passed.\n";
