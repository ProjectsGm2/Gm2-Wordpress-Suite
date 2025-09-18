<?php

declare(strict_types=1);

namespace Gm2\Admin\Performance;

use Gm2\Performance\AutoloadInspector;

use function __;
use function _n;
use function checked;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_option;
use function settings_fields;
use function submit_button;

/**
 * Renders the Performance settings screen with autoload insights.
 */
class SettingsPage
{
    private const WARNING_THRESHOLD  = 500000; // ~488 KB.
    private const CRITICAL_THRESHOLD = 800000; // ~781 KB.

    /**
     * Render the Performance settings page.
     *
     * @param array<string,string>               $flagOptions  Option => label map for front-end flags.
     * @param array<string,array<string,string>> $cacheOptions Option => [label, description, default].
     */
    public static function render(array $flagOptions, array $cacheOptions): void
    {
        $totals = AutoloadInspector::get_totals();
        $status = self::determine_status((int) ($totals['yes']['bytes'] ?? 0));
        $heavy  = AutoloadInspector::get_heavy_options(50000, 10);

        echo '<div class="wrap gm2-performance-settings">';
        echo '<h1>' . esc_html__( 'Performance', 'gm2-wordpress-suite' ) . '</h1>';
        self::render_styles();
        self::render_summary($totals, $status);

        echo '<form method="post" action="options.php" class="gm2-performance-form">';
        settings_fields('gm2_performance');
        self::render_cache_section($cacheOptions);
        echo '<h2>' . esc_html__( 'Front-end helpers', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Toggle optional JavaScript helpers exposed through the AE_PERF_FLAGS global.', 'gm2-wordpress-suite' ) . '</p>';
        echo '<div class="gm2-performance-flags">';
        do_settings_sections('gm2-perf');
        echo '</div>';
        submit_button();
        echo '</form>';

        self::render_heavy_options($heavy);
        echo '</div>';
    }

    private static function render_cache_section(array $options): void
    {
        if (empty($options)) {
            return;
        }

        echo '<h2>' . esc_html__( 'Query cache', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<table class="form-table">';
        foreach ($options as $option => $config) {
            $value       = get_option($option, $config['default'] ?? '0');
            $label       = $config['label'] ?? $option;
            $description = $config['description'] ?? '';

            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th>';
            echo '<td>';
            echo '<label><input type="checkbox" id="' . esc_attr($option) . '" name="' . esc_attr($option) . '" value="1" ' . checked($value, '1', false) . ' /> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label>';
            if ($description !== '') {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    private static function render_summary(array $totals, string $status): void
    {
        $autoloadBytes = (int) ($totals['yes']['bytes'] ?? 0);
        $autoloadCount = (int) ($totals['yes']['count'] ?? 0);
        $nonBytes      = (int) ($totals['no']['bytes'] ?? 0);
        $nonCount      = (int) ($totals['no']['count'] ?? 0);

        echo '<div class="gm2-autoload-grid">';
        echo '<div class="gm2-autoload-card card">';
        echo '<h2>' . esc_html__( 'Autoload footprint', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<p><span class="gm2-status-badge ' . esc_attr(self::status_class($status)) . '">' . esc_html(self::status_label($status)) . '</span> ';
        printf(
            '<strong>%s</strong> %s',
            esc_html(AutoloadInspector::format_bytes($autoloadBytes)),
            esc_html__( 'autoloaded across the current request.', 'gm2-wordpress-suite' )
        );
        echo '</p>';
        printf(
            '<p>%s</p>',
            esc_html(
                sprintf(
                    /* translators: 1: number of autoloaded options. */
                    _n('%d option loads on every request.', '%d options load on every request.', $autoloadCount, 'gm2-wordpress-suite'),
                    $autoloadCount
                )
            )
        );
        printf(
            '<p>%s</p>',
            esc_html(
                sprintf(
                    /* translators: 1: total size, 2: number of options. */
                    __('%1$s stored as non-autoloaded across %2$d options.', 'gm2-wordpress-suite'),
                    AutoloadInspector::format_bytes($nonBytes),
                    $nonCount
                )
            )
        );
        echo '</div>';

        echo '<div class="gm2-autoload-card card">';
        echo '<h2>' . esc_html__( 'Remediation tips', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<p>' . esc_html__( 'Keep autoloaded data under 500 KB to avoid saturating wp_options. Large serialized payloads should opt into autoload = no.', 'gm2-wordpress-suite' ) . '</p>';
        echo '<p>' . esc_html__( 'Use the WP-CLI command “wp gm2 perf autoload” to inspect heavy rows and set autoload to “no” for options that are rarely read on every request.', 'gm2-wordpress-suite' ) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    private static function render_heavy_options(array $options): void
    {
        echo '<h2>' . esc_html__( 'Largest autoloaded options', 'gm2-wordpress-suite' ) . '</h2>';
        if (empty($options)) {
            echo '<p>' . esc_html__( 'No autoloaded options exceed the 50 KB threshold.', 'gm2-wordpress-suite' ) . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Option', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Size', 'gm2-wordpress-suite' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($options as $row) {
            $name  = $row['option_name'] ?? '';
            $bytes = (int) ($row['bytes'] ?? 0);
            echo '<tr>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html(AutoloadInspector::format_bytes($bytes)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    private static function determine_status(int $bytes): string
    {
        if ($bytes >= self::CRITICAL_THRESHOLD) {
            return 'critical';
        }
        if ($bytes >= self::WARNING_THRESHOLD) {
            return 'warning';
        }
        return 'ok';
    }

    private static function status_label(string $status): string
    {
        switch ($status) {
            case 'critical':
                return esc_html__( 'Critical', 'gm2-wordpress-suite' );
            case 'warning':
                return esc_html__( 'Warning', 'gm2-wordpress-suite' );
            default:
                return esc_html__( 'Healthy', 'gm2-wordpress-suite' );
        }
    }

    private static function status_class(string $status): string
    {
        switch ($status) {
            case 'critical':
                return 'gm2-status-badge--critical';
            case 'warning':
                return 'gm2-status-badge--warning';
            default:
                return 'gm2-status-badge--ok';
        }
    }

    private static function render_styles(): void
    {
        echo '<style>'
            . '.gm2-autoload-grid{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:24px;}'
            . '.gm2-autoload-card{flex:1 1 280px;}'
            . '.gm2-status-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;margin-right:8px;}'
            . '.gm2-status-badge--ok{background:#e6f4ea;color:#155724;}'
            . '.gm2-status-badge--warning{background:#fff3cd;color:#856404;}'
            . '.gm2-status-badge--critical{background:#f8d7da;color:#721c24;}'
            . '.gm2-performance-flags .form-table{margin-top:0;}'
            . '.gm2-performance-form .form-table{margin-top:0;}'
            . '</style>';
    }
}
