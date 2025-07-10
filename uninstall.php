<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

// List of options to remove.
// This covers all settings added by the plugin including
// GA/Ads credentials, SEO rules, performance settings and
// ChatGPT configuration. Taxonomy and post type specific
// guideline options are removed further below.
// Removed options: gm2_suite_settings, gm2_suite_version, gm2_content_rules,
// gm2_ga_measurement_id, gm2_search_console_verification, gm2_gads_developer_token,
// gm2_gads_client_id, gm2_gads_client_secret, gm2_gads_refresh_token, gm2_gads_customer_id,
// gm2_gads_login_customer_id, gm2_gads_language, gm2_gads_geo_target, gm2_google_refresh_token,
// gm2_google_access_token, gm2_google_expires_at, gm2_google_profile, gm2_redirects, gm2_404_logs,
// gm2_sitemap_enabled, gm2_sitemap_frequency, gm2_noindex_variants, gm2_noindex_oos,
// gm2_schema_product, gm2_schema_brand, gm2_schema_breadcrumbs, gm2_schema_article,
// gm2_schema_review, gm2_show_footer_breadcrumbs, gm2_auto_fill_alt, gm2_enable_compression,
// gm2_compression_api_key, gm2_compression_api_url, gm2_minify_html, gm2_minify_css,
// gm2_minify_js, gm2_chatgpt_api_key, gm2_chatgpt_model, gm2_chatgpt_temperature,
// gm2_chatgpt_max_tokens, gm2_chatgpt_endpoint, gm2_seo_guidelines_post_*, gm2_seo_guidelines_tax_*.
$option_names = array(
    'gm2_suite_settings',
    'gm2_suite_version',
    'gm2_content_rules',
    'gm2_ga_measurement_id',
    'gm2_search_console_verification',
    'gm2_gads_developer_token',
    'gm2_gads_client_id',
    'gm2_gads_client_secret',
    'gm2_gads_refresh_token',
    'gm2_gads_customer_id',
    'gm2_gads_login_customer_id',
    'gm2_gads_language',
    'gm2_gads_geo_target',
    'gm2_google_refresh_token',
    'gm2_google_access_token',
    'gm2_google_expires_at',
    'gm2_google_profile',
    'gm2_redirects',
    'gm2_404_logs',
    'gm2_sitemap_enabled',
    'gm2_sitemap_frequency',
    'gm2_noindex_variants',
    'gm2_noindex_oos',
    'gm2_schema_product',
    'gm2_schema_brand',
    'gm2_schema_breadcrumbs',
    'gm2_schema_article',
    'gm2_schema_review',
    'gm2_show_footer_breadcrumbs',
    'gm2_auto_fill_alt',
    'gm2_enable_compression',
    'gm2_compression_api_key',
    'gm2_compression_api_url',
    'gm2_minify_html',
    'gm2_minify_css',
    'gm2_minify_js',
    'gm2_chatgpt_api_key',
    'gm2_chatgpt_model',
    'gm2_chatgpt_temperature',
    'gm2_chatgpt_max_tokens',
    'gm2_chatgpt_endpoint',
);

foreach ( $option_names as $option ) {
    if ( is_multisite() ) {
        delete_site_option( $option );
    }

    delete_option( $option );
}

// Remove dynamic SEO guideline options for supported post types and taxonomies.
$post_types = array( 'post', 'page' );
if ( post_type_exists( 'product' ) ) {
    $post_types[] = 'product';
}
foreach ( $post_types as $pt ) {
    $opt = 'gm2_seo_guidelines_post_' . $pt;
    delete_option( $opt );
    if ( is_multisite() ) {
        delete_site_option( $opt );
    }
}

$taxonomies = array( 'category' );
if ( taxonomy_exists( 'product_cat' ) ) {
    $taxonomies[] = 'product_cat';
}
if ( taxonomy_exists( 'brand' ) ) {
    $taxonomies[] = 'brand';
}
if ( taxonomy_exists( 'product_brand' ) ) {
    $taxonomies[] = 'product_brand';
}
foreach ( $taxonomies as $tax ) {
    $opt = 'gm2_seo_guidelines_tax_' . $tax;
    delete_option( $opt );
    if ( is_multisite() ) {
        delete_site_option( $opt );
    }
}

// Example table cleanup.
global $wpdb;
$table_name = $wpdb->prefix . 'gm2_suite_data';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

