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
// gm2_ga_measurement_id, gm2_search_console_verification, gm2_twitter_site, gm2_twitter_creator, gm2_gads_developer_token,
// gm2_gads_client_id, gm2_gads_client_secret, gm2_gads_refresh_token, gm2_gads_customer_id,
// gm2_gads_login_customer_id, gm2_gads_language, gm2_gads_geo_target, gm2_google_refresh_token,
// gm2_google_access_token, gm2_google_expires_at, gm2_google_profile, gm2_redirects, gm2_404_logs,
// gm2_sitemap_enabled, gm2_sitemap_frequency, gm2_noindex_variants, gm2_noindex_oos,
// gm2_variation_canonical_parent,
// gm2_schema_product, gm2_schema_brand, gm2_schema_breadcrumbs, gm2_schema_article,
// gm2_schema_review, gm2_show_footer_breadcrumbs, gm2_auto_fill_alt, gm2_enable_compression,
// gm2_compression_api_key, gm2_compression_api_url, gm2_minify_html, gm2_minify_css,
// gm2_minify_js, gm2_pretty_versioned_urls, gm2_chatgpt_api_key, gm2_chatgpt_model, gm2_chatgpt_temperature,
// gm2_chatgpt_max_tokens, gm2_chatgpt_endpoint, gm2_gemma_api_key, gm2_gemma_endpoint,
// gm2_gemma_model_path, gm2_gemma_binary_path,
// gm2_llama_api_key, gm2_llama_endpoint, gm2_llama_model_path, gm2_llama_binary_path, gm2_pagespeed_api_key, gm2_pagespeed_scores,
// gm2_bulk_ai_page_size, gm2_bulk_ai_status, gm2_bulk_ai_post_type, gm2_bulk_ai_term, ae_css_settings.
$option_names = array(
    'gm2_suite_settings',
    'gm2_suite_version',
    'gm2_autoload_migration_version',
    'gm2_content_rules',
    'gm2_guideline_rules',
    'gm2_ga_measurement_id',
    'gm2_search_console_verification',
    'gm2_twitter_site',
    'gm2_twitter_creator',
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
    'gm2_sitemap_path',
    'gm2_sitemap_max_urls',
    'gm2_noindex_variants',
    'gm2_noindex_oos',
    'gm2_variation_canonical_parent',
    'gm2_schema_product',
    'gm2_schema_brand',
    'gm2_schema_breadcrumbs',
    'gm2_schema_article',
    'gm2_schema_review',
    'gm2_show_footer_breadcrumbs',
    'gm2_auto_fill_alt',
    'gm2_clean_image_filenames',
    'gm2_enable_compression',
    'gm2_compression_api_key',
    'gm2_compression_api_url',
    'gm2_minify_html',
    'gm2_minify_css',
    'gm2_minify_js',
    'gm2_pretty_versioned_urls',
    'gm2_chatgpt_api_key',
    'gm2_chatgpt_model',
    'gm2_chatgpt_temperature',
    'gm2_chatgpt_max_tokens',
    'gm2_chatgpt_endpoint',
    'gm2_gemma_api_key',
    'gm2_gemma_endpoint',
    'gm2_gemma_model_path',
    'gm2_gemma_binary_path',
    'gm2_llama_api_key',
    'gm2_llama_endpoint',
    'gm2_llama_model_path',
    'gm2_llama_binary_path',
    'gm2_min_internal_links',
    'gm2_min_external_links',
    'gm2_enable_tariff',
    'gm2_enable_seo',
    'gm2_enable_quantity_discounts',
    'gm2_enable_google_oauth',
    'gm2_enable_chatgpt',
    'gm2_enable_chatgpt_logging',
    'gm2_ac_enable_logging',
    'gm2_enable_custom_posts',
    'gm2_pagespeed_api_key',
    'gm2_pagespeed_scores',
    'gm2_perf_query_cache_enabled',
    'gm2_perf_query_cache_use_transients',
    'gm2_bulk_ai_page_size',
    'gm2_bulk_ai_status',
    'gm2_bulk_ai_post_type',
    'gm2_bulk_ai_term',
    'gm2_bulk_ai_missing_title',
    'gm2_bulk_ai_missing_description',
    'gm2_tax_min_length',
    'gm2_clean_slugs',
    'gm2_slug_stopwords',
    'gm2_tax_desc_prompt',
    'gm2_context_business_model',
    'gm2_context_industry_category',
    'gm2_context_target_audience',
    'gm2_context_unique_selling_points',
    'gm2_context_revenue_streams',
    'gm2_context_primary_goal',
    'gm2_context_brand_voice',
    'gm2_context_competitors',
    'gm2_context_core_offerings',
    'gm2_context_geographic_focus',
    'gm2_context_keyword_data',
    'gm2_context_competitor_landscape',
    'gm2_context_success_metrics',
    'gm2_context_buyer_personas',
    'gm2_context_project_description',
    'gm2_context_custom_prompts',
    'gm2_context_ai_prompt',
    'gm2_project_description',
    'gm2_sc_query_limit',
    'gm2_analytics_days',
    'ae_perf_webworker',
    'ae_perf_longtasks',
    'ae_perf_nothrash',
    'ae_perf_passive',
    'ae_perf_passive_patch',
    'ae_perf_domaudit',
    'ae_perf_worker',
    'ae_perf_long_tasks',
    'ae_perf_no_thrash',
    'ae_perf_passive_listeners',
    'ae_perf_dom_audit',
    'ae_css_settings',
);
if ( is_multisite() ) {
    // Remove options from all sites in the network.
    $site_ids = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        foreach ( $option_names as $option ) {
            delete_option( $option );
        }
        restore_current_blog();
    }

    // Remove network-level options.
    foreach ( $option_names as $option ) {
        delete_site_option( $option );
    }
} else {
    foreach ( $option_names as $option ) {
        delete_option( $option );
    }
}

// Remove per-user Bulk AI settings stored as user meta.
$user_meta_keys = array(
    'gm2_bulk_ai_page_size',
    'gm2_bulk_ai_status',
    'gm2_bulk_ai_post_type',
    'gm2_bulk_ai_term',
);
$user_ids = get_users( array( 'fields' => 'ID', 'blog_id' => 0 ) );
foreach ( $user_ids as $uid ) {
    foreach ( $user_meta_keys as $key ) {
        delete_user_meta( $uid, $key );
    }
}

// Remove dynamic SEO guideline options for supported post types and taxonomies.

// Example table cleanup.
global $wpdb;
$table_name = $wpdb->prefix . 'gm2_suite_data';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

if (defined('GM2_CHATGPT_LOG_FILE') && file_exists(GM2_CHATGPT_LOG_FILE)) {
    @unlink(GM2_CHATGPT_LOG_FILE);
}

