<?php
namespace Plugin\CLS\Reservations;

if (!defined('ABSPATH')) {
    exit;
}

function register(): void {
    if (is_admin() || false === apply_filters('plugin_cls_reservations_enabled', true)) {
        return;
    }
    add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets');
}

function enqueue_assets(): void {
    if (is_admin()) {
        return;
    }
    wp_enqueue_style('cls-reserved', GM2_PLUGIN_URL . 'assets/css/cls-reserved.css', [], GM2_VERSION);
    wp_enqueue_script('cls-reservations', GM2_PLUGIN_URL . 'assets/js/cls-reservations.js', [], GM2_VERSION, true);
    wp_script_add_data('cls-reservations', 'defer', true);
    wp_localize_script('cls-reservations', 'clsReservations', [
        'reservations' => get_reservations(),
        'stickyHeader' => is_sticky_header_enabled(),
        'stickyFooter' => is_sticky_footer_enabled(),
    ]);
}

function get_reservations(): array {
    $reservations = get_option('plugin_cls_reservations', []);
    if (!is_array($reservations)) {
        $reservations = [];
    }
    return array_map('sanitize_text_field', $reservations);
}

function is_sticky_header_enabled(): bool {
    $val = get_option('plugin_cls_sticky_header', '0');
    $val = sanitize_text_field((string) $val);
    return $val === '1';
}

function is_sticky_footer_enabled(): bool {
    $val = get_option('plugin_cls_sticky_footer', '0');
    $val = sanitize_text_field((string) $val);
    return $val === '1';
}
