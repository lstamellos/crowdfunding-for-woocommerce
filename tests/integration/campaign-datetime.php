<?php
/**
 * Regression coverage for crowdfunding product start/end datetime shortcodes.
 *
 * The frontend must render the saved campaign-local wall-clock time,
 * independently of the server timezone and without inserting false midnight.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

function alg_campaign_time_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
}

$previous_timezone = get_option( 'timezone_string' );
$previous_date_format = get_option( 'date_format' );
$previous_time_format = get_option( 'time_format' );
update_option( 'timezone_string', 'Europe/Athens' );

$product_id = wp_insert_post(
    array(
        'post_type'   => 'product',
        'post_status' => 'draft',
        'post_title'  => 'Temporary crowdfunding date/time regression fixture',
    ),
    true
);

alg_campaign_time_assert( ! is_wp_error( $product_id ) && $product_id > 0, 'temporary product created' );

try {
    update_post_meta( $product_id, '_alg_crowdfunding_deadline', '2026/12/31' );
    update_post_meta( $product_id, '_alg_crowdfunding_startdate', '2026/11/01' );

    $deadline_shortcode = sprintf(
        '[product_crowdfunding_deadline_datetime product_id="%d" date_format="d/m/Y" time_format="H:i"]',
        $product_id
    );
    $start_shortcode = sprintf(
        '[product_crowdfunding_startdatetime product_id="%d" date_format="d/m/Y" time_format="H:i"]',
        $product_id
    );

    $cases = array(
        '18:30'     => '31/12/2026 18:30',
        '06:30 PM'  => '31/12/2026 18:30',
        '6:30 pm'   => '31/12/2026 18:30',
        '05:45 AM'  => '31/12/2026 05:45',
        '00:00'     => '31/12/2026 00:00',
        '12:00 AM'  => '31/12/2026 00:00',
        '12:00 PM'  => '31/12/2026 12:00',
        ''          => '31/12/2026',
        'not-a-time'=> '31/12/2026',
    );

    foreach ( $cases as $stored_time => $expected ) {
        update_post_meta( $product_id, '_alg_crowdfunding_deadline_time', $stored_time );
        $actual = do_shortcode( $deadline_shortcode );
        alg_campaign_time_assert(
            $expected === $actual,
            "deadline time '" . $stored_time . "' renders as '" . $expected . "' (received '" . $actual . "')"
        );
        alg_campaign_time_assert(
            $stored_time === get_post_meta( $product_id, '_alg_crowdfunding_deadline_time', true ),
            'display does not change saved deadline metadata'
        );
    }

    // Reproduce production: WordPress date_format already contains H:i,
    // while the product-info template calls the date-only shortcode.
    update_option( 'date_format', 'd/m/Y H:i' );
    update_option( 'time_format', 'H:i' );

    update_post_meta( $product_id, '_alg_crowdfunding_deadline_time', '11:59 PM' );
    $default_datetime = sprintf(
        '[product_crowdfunding_deadline_datetime product_id="%d"]',
        $product_id
    );
    $default_date_only = sprintf(
        '[product_crowdfunding_deadline product_id="%d"]',
        $product_id
    );

    alg_campaign_time_assert(
        '31/12/2026 23:59' === do_shortcode( $default_datetime ),
        'default datetime does not duplicate site date-format clock or invent 00:00'
    );
    alg_campaign_time_assert(
        '31/12/2026 23:59' === do_shortcode( $default_date_only ),
        'existing production date shortcode uses the saved hour when site date format includes a clock'
    );

    update_post_meta( $product_id, '_alg_crowdfunding_deadline_time', '' );
    alg_campaign_time_assert(
        '31/12/2026' === do_shortcode( $default_date_only ),
        'empty time does not invent midnight even when site date format contains H:i'
    );
    update_post_meta( $product_id, '_alg_crowdfunding_deadline_time', 'invalid-time-xyz' );
    alg_campaign_time_assert(
        '31/12/2026' === do_shortcode( $default_datetime ),
        'invalid time does not invent midnight even when site date format contains H:i'
    );

    update_post_meta( $product_id, '_alg_crowdfunding_starttime', '09:15 PM' );
    alg_campaign_time_assert(
        '01/11/2026 21:15' === do_shortcode( $start_shortcode ),
        'start datetime uses the same campaign-local formatter'
    );

    update_post_meta( $product_id, '_alg_crowdfunding_deadline', '' );
    alg_campaign_time_assert(
        '' === do_shortcode( $deadline_shortcode ),
        'missing deadline date produces no fabricated date or time'
    );
} finally {
    wp_delete_post( $product_id, true );
    update_option( 'timezone_string', $previous_timezone );
    update_option( 'date_format', $previous_date_format );
    update_option( 'time_format', $previous_time_format );
}

echo "Crowdfunding campaign datetime integration passed.\n";
