<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_JS_Lazy')) {
    return;
}

/**
 * Enqueue lazy-loading helper script.
 */
class AE_SEO_JS_Lazy {
    /**
     * Bootstrap the loader.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [ __CLASS__, 'enqueue' ]);
    }

    /**
     * Register and enqueue script with configuration.
     */
    public static function enqueue(): void {
        ae_seo_register_asset('ae-lazy', 'ae-lazy.js');
        $modules = [
            'recaptcha' => get_option('ae_lazy_recaptcha', '0') === '1',
            'gtag'      => get_option('ae_lazy_gtag', '0') === '1',
            'gtm'       => get_option('ae_lazy_gtm', '0') === '1',
            'fbq'       => get_option('ae_lazy_fbq', '0') === '1',
        ];
        $ids = [
            'recaptcha' => get_option('ae_recaptcha_site_key', ''),
            'gtag'      => get_option('ae_gtag_id', ''),
            'gtm'       => get_option('ae_gtm_id', ''),
            'fbq'       => get_option('ae_fbq_id', ''),
        ];
        $consent = [
            'key'   => 'aeConsent',
            'value' => 'allow_analytics',
        ];
        wp_localize_script('ae-lazy', 'aeLazy', [
            'ids'     => $ids,
            'consent' => $consent,
            'modules' => $modules,
        ]);
        wp_enqueue_script('ae-lazy');
    }
}

AE_SEO_JS_Lazy::init();
