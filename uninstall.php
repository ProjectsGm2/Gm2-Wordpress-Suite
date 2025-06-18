<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

// List of options to remove.
$option_names = array(
    'gm2_suite_settings',
    'gm2_suite_version',
);

foreach ( $option_names as $option ) {
    if ( is_multisite() ) {
        delete_site_option( $option );
    }

    delete_option( $option );
}

// Example table cleanup.
global $wpdb;
$table_name = $wpdb->prefix . 'gm2_suite_data';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

