<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Model_Export_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_tools_page' ]);
    }

    public function add_tools_page() {
        add_management_page(
            __('Gm2 Models', 'gm2-wordpress-suite'),
            __('Gm2 Models', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-model-export',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }

        $message = '';
        if (!empty($_POST['gm2_export_models']) && check_admin_referer('gm2_export_models')) {
            $format = sanitize_text_field($_POST['format'] ?? 'json');
            $data   = \gm2_model_export($format);
            if (is_wp_error($data)) {
                $message = $data->get_error_message();
            } else {
                header('Content-Type: ' . ('yaml' === $format ? 'application/x-yaml' : 'application/json'));
                header('Content-Disposition: attachment; filename=models.' . ('yaml' === $format ? 'yml' : 'json'));
                echo $data;
                exit;
            }
        } elseif (!empty($_POST['gm2_import_models']) && check_admin_referer('gm2_import_models')) {
            $format = sanitize_text_field($_POST['format'] ?? 'json');
            $raw    = wp_unslash($_POST['model_data'] ?? '');
            $result = \gm2_model_import($raw, $format);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
            } else {
                $message = esc_html__('Models imported.', 'gm2-wordpress-suite');
            }
        } elseif (!empty($_POST['gm2_generate_plugin']) && check_admin_referer('gm2_generate_plugin')) {
            $mu   = !empty($_POST['as_mu']);
            $tmp  = tempnam(sys_get_temp_dir(), 'gm2_model_') . '.zip';
            $data = \gm2_model_export('array');
            $zip  = \gm2_model_generate_plugin($data, $tmp, $mu);
            if (is_wp_error($zip)) {
                $message = $zip->get_error_message();
            } else {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename=' . ($mu ? 'models-mu-plugin.zip' : 'models-plugin.zip'));
                readfile($zip);
                unlink($zip);
                exit;
            }
        }

        echo '<div class="wrap"><h1>' . esc_html__('Gm2 Model Export/Import', 'gm2-wordpress-suite') . '</h1>';
        if ($message) {
            echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<h2>' . esc_html__('Export', 'gm2-wordpress-suite') . '</h2>';
        echo '<form method="post"><p>';
        wp_nonce_field('gm2_export_models');
        echo '<select name="format"><option value="json">JSON</option><option value="yaml">YAML</option></select> ';
        echo '<button type="submit" name="gm2_export_models" class="button">' . esc_html__('Download', 'gm2-wordpress-suite') . '</button>';
        echo '</p></form>';

        echo '<h2>' . esc_html__('Import', 'gm2-wordpress-suite') . '</h2>';
        echo '<form method="post"><p>';
        wp_nonce_field('gm2_import_models');
        echo '<select name="format"><option value="json">JSON</option><option value="yaml">YAML</option></select><br>';
        echo '<textarea name="model_data" rows="10" cols="60"></textarea><br>';
        echo '<button type="submit" name="gm2_import_models" class="button button-primary">' . esc_html__('Import', 'gm2-wordpress-suite') . '</button>';
        echo '</p></form>';

        echo '<h2>' . esc_html__('Generate Plugin', 'gm2-wordpress-suite') . '</h2>';
        echo '<form method="post"><p>';
        wp_nonce_field('gm2_generate_plugin');
        echo '<label><input type="checkbox" name="as_mu" value="1"> ' . esc_html__('As MU-Plugin', 'gm2-wordpress-suite') . '</label> ';
        echo '<button type="submit" name="gm2_generate_plugin" class="button">' . esc_html__('Generate', 'gm2-wordpress-suite') . '</button>';
        echo '</p></form>';
        echo '</div>';
    }
}
