<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {
    private $diagnostics;
    private $quantity_discounts;
    private $oauth_enabled;
    private $chatgpt_enabled;

    public function run() {
        $this->diagnostics = new Gm2_Diagnostics();
        $this->diagnostics->run();
        $this->oauth_enabled   = get_option('gm2_enable_google_oauth', '1') === '1';
        $this->chatgpt_enabled = get_option('gm2_enable_chatgpt', '1') === '1';
        add_action('admin_menu', [$this, 'add_admin_menu'], 9);
        if (get_option('gm2_enable_quantity_discounts', '1') === '1') {
            $this->quantity_discounts = new Gm2_Quantity_Discounts_Admin();
            $this->quantity_discounts->register_hooks();
        }
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_gm2_add_tariff', [$this, 'ajax_add_tariff']);
        add_action('wp_ajax_nopriv_gm2_add_tariff', [$this, 'ajax_add_tariff']);
        if ($this->chatgpt_enabled) {
            add_action('admin_post_gm2_chatgpt_settings', [$this, 'handle_chatgpt_form']);
            add_action('wp_ajax_gm2_chatgpt_prompt', [$this, 'ajax_chatgpt_prompt']);
            add_action('admin_post_gm2_reset_chatgpt_logs', [$this, 'handle_reset_chatgpt_logs']);
        }
        add_action('admin_notices', [$this, 'maybe_show_chatgpt_notice']);
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'gm2_page_gm2-tariff') {
            wp_enqueue_script(
                'gm2-tariff',
                GM2_PLUGIN_URL . 'admin/js/gm2-tariff.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-tariff.js'),
                true
            );
            wp_localize_script(
                'gm2-tariff',
                'gm2Tariff',
                [
                    // Fresh nonce for each page load
                    'nonce'    => wp_create_nonce('gm2_add_tariff'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                ]
            );
        }

        $seo_pages = [
            'gm2_page_gm2-seo',
            'gm2_page_gm2-bulk-ai-review',
            'gm2_page_gm2-bulk-ai-taxonomies',
        ];

        if ($hook === 'gm2_page_gm2-chatgpt') {
            wp_enqueue_style(
                'gm2-chatgpt-style',
                GM2_PLUGIN_URL . 'admin/css/gm2-chatgpt.css',
                [],
                GM2_VERSION
            );
            wp_enqueue_script(
                'gm2-chatgpt',
                GM2_PLUGIN_URL . 'admin/js/gm2-chatgpt.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-chatgpt.js'),
                true
            );
            wp_localize_script(
                'gm2-chatgpt',
                'gm2ChatGPT',
                [
                    'nonce'    => wp_create_nonce('gm2_chatgpt_nonce'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'loading'  => __( 'Loading...', 'gm2-wordpress-suite' ),
                    'error'    => __( 'Error', 'gm2-wordpress-suite' ),
                ]
            );
        }

        if (in_array($hook, $seo_pages, true)) {
            wp_enqueue_style(
                'gm2-seo-style',
                GM2_PLUGIN_URL . 'admin/css/gm2-seo.css',
                [],
                GM2_VERSION
            );
            wp_enqueue_script(
                'gm2-seo',
                GM2_PLUGIN_URL . 'admin/js/gm2-seo.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-seo.js'),
                true
            );
            wp_localize_script(
                'gm2-seo',
                'gm2Seo',
                [
                    'i18n' => [
                        'selectImage' => __( 'Select Image', 'gm2-wordpress-suite' ),
                        'useImage'    => __( 'Use image', 'gm2-wordpress-suite' ),
                    ],
                ]
            );
            wp_enqueue_script(
                'gm2-keyword-research',
                GM2_PLUGIN_URL . 'admin/js/gm2-keyword-research.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-keyword-research.js'),
                true
            );
            wp_enqueue_script(
                'gm2-content-rules',
                GM2_PLUGIN_URL . 'admin/js/gm2-content-rules.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-content-rules.js'),
                true
            );
            wp_enqueue_script(
                'gm2-guideline-rules',
                GM2_PLUGIN_URL . 'admin/js/gm2-guideline-rules.js',
                ['jquery'],
                filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-guideline-rules.js'),
                true
            );
            if ($this->chatgpt_enabled) {
                wp_enqueue_script(
                    'gm2-context-prompt',
                    GM2_PLUGIN_URL . 'admin/js/gm2-context-prompt.js',
                    ['jquery'],
                    filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-context-prompt.js'),
                    true
                );
                wp_localize_script(
                    'gm2-context-prompt',
                    'gm2ChatGPT',
                    [
                        'nonce'    => wp_create_nonce('gm2_chatgpt_nonce'),
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'error'    => __( 'Error', 'gm2-wordpress-suite' ),
                    ]
                );
            }
            $gads_ready = trim(get_option('gm2_gads_developer_token', '')) !== '' &&
                trim(get_option('gm2_gads_customer_id', '')) !== '' &&
                get_option('gm2_google_refresh_token', '') !== '';
            wp_localize_script(
                'gm2-keyword-research',
                'gm2KeywordResearch',
                [
                    'nonce'    => wp_create_nonce('gm2_keyword_ideas'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'enabled'  => $gads_ready,
                    'i18n'     => [
                        'metricsUnavailable' => __( 'Keyword metrics unavailable; showing AI-generated ideas only.', 'gm2-wordpress-suite' ),
                    ],
                ]
            );
            wp_localize_script(
                'gm2-content-rules',
                'gm2ContentRules',
                [
                    'nonce'      => wp_create_nonce('gm2_research_content_rules'),
                    'ajax_url'   => admin_url('admin-ajax.php'),
                    'categories' => 'seo_title, seo_description, focus_keywords, long_tail_keywords, canonical_url, content, general',
                    'loading'    => __( 'Researching...', 'gm2-wordpress-suite' ),
                ]
            );
            wp_localize_script(
                'gm2-guideline-rules',
                'gm2GuidelineRules',
                [
                    'nonce'      => wp_create_nonce('gm2_research_guideline_rules'),
                    'ajax_url'   => admin_url('admin-ajax.php'),
                    'categories' => 'seo_title, seo_description, focus_keywords, long_tail_keywords, canonical_url, content, general',
                    'loading'    => __( 'Researching...', 'gm2-wordpress-suite' ),
                ]
            );
            if ($hook === 'gm2_page_gm2-bulk-ai-review') {
                wp_enqueue_script(
                    'gm2-bulk-ai',
                    GM2_PLUGIN_URL . 'admin/js/gm2-bulk-ai.js',
                    ['jquery'],
                    filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-bulk-ai.js'),
                    true
                );
                wp_localize_script(
                    'gm2-bulk-ai',
                    'gm2BulkAi',
                    [
                        'nonce'       => wp_create_nonce('gm2_ai_research'),
                        'apply_nonce' => wp_create_nonce('gm2_bulk_ai_apply'),
                        'ajax_url'    => admin_url('admin-ajax.php'),
                        'i18n'        => [
                            'processing'   => __( 'Processing %1$s / %2$s', 'gm2-wordpress-suite' ),
                            'complete'     => __( 'Complete', 'gm2-wordpress-suite' ),
                            'stopped'      => __( 'Stopped:', 'gm2-wordpress-suite' ),
                            'invalidJson'  => __( 'Invalid JSON response', 'gm2-wordpress-suite' ),
                            'error'        => __( 'Error', 'gm2-wordpress-suite' ),
                            'saving'       => __( 'Saving...', 'gm2-wordpress-suite' ),
                            'done'         => __( 'Done', 'gm2-wordpress-suite' ),
                            'slug'         => __( 'Slug', 'gm2-wordpress-suite' ),
                            'title'        => __( 'Title', 'gm2-wordpress-suite' ),
                            'apply'        => __( 'Apply', 'gm2-wordpress-suite' ),
                            'refresh'      => __( 'Refresh', 'gm2-wordpress-suite' ),
                            'clear'        => __( 'Clear', 'gm2-wordpress-suite' ),
                            'selectAll'    => __( 'Select all', 'gm2-wordpress-suite' ),
                            'cancel'       => __( 'Cancel', 'gm2-wordpress-suite' ),
                            'undo'         => __( 'Undo', 'gm2-wordpress-suite' ),
                        ],
                    ]
                );
            } elseif ($hook === 'gm2_page_gm2-bulk-ai-taxonomies') {
                wp_enqueue_script(
                    'gm2-bulk-ai-tax',
                    GM2_PLUGIN_URL . 'admin/js/gm2-bulk-ai-tax.js',
                    ['jquery'],
                    filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-bulk-ai-tax.js'),
                    true
                );
                wp_localize_script(
                    'gm2-bulk-ai-tax',
                    'gm2BulkAiTax',
                    [
                        'nonce'       => wp_create_nonce('gm2_ai_research'),
                        'apply_nonce' => wp_create_nonce('gm2_bulk_ai_apply'),
                        'ajax_url'    => admin_url('admin-ajax.php'),
                        'i18n'        => [
                            'apply'   => __( 'Apply', 'gm2-wordpress-suite' ),
                            'error'   => __( 'Error', 'gm2-wordpress-suite' ),
                        ],
                    ]
                );
            }
        }
    }

    public function ajax_add_tariff() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error( __( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_ajax_referer('gm2_add_tariff');

        $name = sanitize_text_field($_POST['tariff_name'] ?? '');
        if ($name === '') {
            wp_send_json_error( __( 'Tariff name is required', 'gm2-wordpress-suite' ) );
        }

        $percentage_raw = $_POST['tariff_percentage'] ?? '';

        if (!is_numeric($percentage_raw)) {
            wp_send_json_error( __( 'Tariff percentage must be a number', 'gm2-wordpress-suite' ) );
        }

        $percentage = floatval($percentage_raw);

        if ($percentage < 0 || $percentage > 100) {
            wp_send_json_error( __( 'Tariff percentage must be between 0 and 100', 'gm2-wordpress-suite' ) );
        }
        $status = ($_POST['tariff_status'] ?? '') === 'enabled' ? 'enabled' : 'disabled';

        $manager = new Gm2_Tariff_Manager();
        $id      = $manager->add_tariff([
            'name'       => $name,
            'percentage' => $percentage,
            'status'     => $status,
        ]);

        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $id),
            'gm2_delete_tariff_' . $id
        );
        $edit_url = admin_url('admin.php?page=gm2-add-tariff&id=' . $id);

        wp_send_json_success([
            'id'         => $id,
            'name'       => $name,
            'percentage' => $percentage,
            'status'     => $status,
            'delete_url' => $delete_url,
            'edit_url'   => $edit_url,
        ]);
    }

    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'Gm2', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2',
            [$this, 'display_dashboard'],
            'dashicons-admin-generic'
        );

        if (get_option('gm2_enable_tariff', '1') === '1') {
            add_submenu_page(
                'gm2',
                esc_html__( 'Tariff', 'gm2-wordpress-suite' ),
                esc_html__( 'Tariff', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-tariff',
                [$this, 'display_tariff_page']
            );

            // The add tariff form is now part of the Tariff page. The following
            // submenu is kept for editing existing tariffs but hidden from the
            // menu by setting the parent slug to null.
            add_submenu_page(
                null,
                esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ),
                esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-add-tariff',
                [$this, 'display_add_tariff_page']
            );
        }

        if ($this->oauth_enabled) {
            add_submenu_page(
                'gm2',
                esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ),
                esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-google-oauth-setup',
                [ $this, 'display_google_oauth_setup_page' ]
            );
        }

        if ($this->chatgpt_enabled) {
            add_submenu_page(
                'gm2',
                esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ),
                esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-chatgpt',
                [ $this, 'display_chatgpt_page' ]
            );
        }
    }

    public function display_dashboard() {
        if (
            isset($_POST['gm2_feature_toggles_nonce']) &&
            wp_verify_nonce($_POST['gm2_feature_toggles_nonce'], 'gm2_feature_toggles')
        ) {
            update_option('gm2_enable_tariff', empty($_POST['gm2_enable_tariff']) ? '0' : '1');
            update_option('gm2_enable_seo', empty($_POST['gm2_enable_seo']) ? '0' : '1');
            update_option('gm2_enable_quantity_discounts', empty($_POST['gm2_enable_quantity_discounts']) ? '0' : '1');
            update_option('gm2_enable_google_oauth', empty($_POST['gm2_enable_google_oauth']) ? '0' : '1');
            update_option('gm2_enable_chatgpt', empty($_POST['gm2_enable_chatgpt']) ? '0' : '1');
            update_option('gm2_enable_abandoned_carts', empty($_POST['gm2_enable_abandoned_carts']) ? '0' : '1');

            $enabled = !empty($_POST['gm2_enable_abandoned_carts']);
            if ($enabled) {
                global $wpdb;
                $carts_table = $wpdb->prefix . 'wc_ac_carts';
                $queue_table = $wpdb->prefix . 'wc_ac_email_queue';
                $carts_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $carts_table));
                $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
                if (!$carts_exists || !$queue_exists) {
                    $ac = new Gm2_Abandoned_Carts();
                    $ac->install();
                }
            }

            echo '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        $tariff = get_option('gm2_enable_tariff', '1') === '1';
        $seo    = get_option('gm2_enable_seo', '1') === '1';
        $qd     = get_option('gm2_enable_quantity_discounts', '1') === '1';
        $oauth  = get_option('gm2_enable_google_oauth', '1') === '1';
        $chatgpt = get_option('gm2_enable_chatgpt', '1') === '1';
        $abandoned = get_option('gm2_enable_abandoned_carts', '0') === '1';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Gm2 Suite', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_feature_toggles', 'gm2_feature_toggles_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">' . esc_html__( 'Tariff', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_tariff"' . checked($tariff, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'SEO', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_seo"' . checked($seo, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Quantity Discounts', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_quantity_discounts"' . checked($qd, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_google_oauth"' . checked($oauth, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_chatgpt"' . checked($chatgpt, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Abandoned Carts', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_enable_abandoned_carts"' . checked($abandoned, true, false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    private function handle_form_submission() {
        if (!empty($_POST['gm2_tariff_nonce']) && wp_verify_nonce($_POST['gm2_tariff_nonce'], 'gm2_save_tariff')) {
            $manager = new Gm2_Tariff_Manager();
            $data    = [
                'name'       => sanitize_text_field($_POST['tariff_name']),
                'percentage' => floatval($_POST['tariff_percentage']),
                'status'     => isset($_POST['tariff_status']) ? 'enabled' : 'disabled',
            ];

            if (!empty($_POST['tariff_id'])) {
                $manager->update_tariff(sanitize_text_field($_POST['tariff_id']), $data);
            } else {
                $manager->add_tariff($data);
            }
            echo '<div class="updated"><p>' . esc_html__('Tariff saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
    }

    public function display_add_tariff_page() {
        $this->handle_form_submission();

        $tariff = false;
        if (!empty($_GET['id'])) {
            $manager = new Gm2_Tariff_Manager();
            $tariff  = $manager->get_tariff(sanitize_text_field($_GET['id']));
        }

        $name       = $tariff ? esc_attr($tariff['name']) : '';
        $percentage = $tariff ? esc_attr($tariff['percentage']) : '';
        $status     = $tariff ? $tariff['status'] : 'enabled';
        $id_field   = $tariff ? '<input type="hidden" name="tariff_id" value="' . esc_attr($tariff['id']) . '" />' : '';

        echo '<div class="wrap"><h1>' . esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_save_tariff', 'gm2_tariff_nonce');
        echo $id_field;
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_name" type="text" id="tariff_name" value="' . $name . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" value="' . $percentage . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="tariff_status"' . checked($status, 'enabled', false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button( esc_html__( 'Save Tariff', 'gm2-wordpress-suite' ) );
        echo '</form></div>';
    }

    public function display_tariff_page() {
        $manager = new Gm2_Tariff_Manager();

        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
            $id = sanitize_text_field($_GET['id']);
            check_admin_referer('gm2_delete_tariff_' . $id);
            $manager->delete_tariff($id);
            echo '<div class="updated"><p>' . esc_html__('Tariff deleted.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $tariffs = $manager->get_tariffs();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Tariffs', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<h2>' . esc_html__( 'Add Tariff', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<div class="notice notice-success hidden" id="gm2-tariff-msg"></div>';
        echo '<form id="gm2-add-tariff-form">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_name" type="text" id="tariff_name" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="tariff_status" id="tariff_status" checked> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button( esc_html__( 'Add Tariff', 'gm2-wordpress-suite' ) );
        echo '</form>';

        echo '<h2>' . esc_html__( 'Existing Tariffs', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<table class="widefat" id="gm2-tariff-table"><thead><tr><th>' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
        if ($tariffs) {
            foreach ($tariffs as $tariff) {
                $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $tariff['id']), 'gm2_delete_tariff_' . $tariff['id']);
                $edit_url   = admin_url('admin.php?page=gm2-add-tariff&id=' . $tariff['id']);
                echo '<tr>';
                echo '<td>' . esc_html($tariff['name']) . '</td>';
                echo '<td>' . esc_html($tariff['percentage']) . '%</td>';
                echo '<td>' . esc_html(ucfirst($tariff['status'])) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'View', 'gm2-wordpress-suite' ) . '</a> | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gm2-wordpress-suite' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure?', 'gm2-wordpress-suite' ) ) . '\');">' . esc_html__( 'Delete', 'gm2-wordpress-suite' ) . '</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">' . esc_html__( 'No tariffs found.', 'gm2-wordpress-suite' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function display_google_oauth_setup_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $notice = '';
        if ( isset( $_POST['gm2_gads_oauth_setup_nonce'] ) && wp_verify_nonce( $_POST['gm2_gads_oauth_setup_nonce'], 'gm2_gads_oauth_setup_save' ) ) {
            $client_id     = isset( $_POST['gm2_gads_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_gads_client_id'] ) ) : '';
            $client_secret = isset( $_POST['gm2_gads_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_gads_client_secret'] ) ) : '';
            $project_id    = isset( $_POST['gm2_gcloud_project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_gcloud_project_id'] ) ) : '';
            $service_json  = isset( $_POST['gm2_service_account_json'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_service_account_json'] ) ) : '';

            update_option( 'gm2_gads_client_id', $client_id );
            update_option( 'gm2_gads_client_secret', $client_secret );
            update_option( 'gm2_gcloud_project_id', $project_id );
            update_option( 'gm2_service_account_json', $service_json );

            $notice = '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        $client_id     = get_option( 'gm2_gads_client_id', '' );
        $client_secret = get_option( 'gm2_gads_client_secret', '' );
        $project_id    = get_option( 'gm2_gcloud_project_id', '' );
        $service_json  = get_option( 'gm2_service_account_json', '' );
        $redirect      = admin_url( 'admin.php?page=gm2-google-connect' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ) . '</h1>';
        echo $notice;
        echo '<p>' . esc_html__( 'Follow these steps to create OAuth credentials on the Google Cloud console:', 'gm2-wordpress-suite' ) . '</p>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Open the Google Cloud console and create a new project.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Enable the Google Ads API and other required APIs.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Create OAuth client ID credentials for a Web application.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . sprintf( esc_html__( 'Set the authorized redirect URI to %s.', 'gm2-wordpress-suite' ), esc_url( $redirect ) ) . '</li>';
        echo '<li>' . esc_html__( 'Copy the client ID and client secret into the fields below.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Find your Project ID on the Google Cloud dashboard. In IAM & Admin → Service Accounts create a new service account, add a key, and download the JSON file.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Enter the Project ID and the path to the downloaded JSON key in the fields below.', 'gm2-wordpress-suite' ) . '</li>';
        echo '</ol>';

        echo '<form method="post">';
        wp_nonce_field( 'gm2_gads_oauth_setup_save', 'gm2_gads_oauth_setup_nonce' );
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_gads_client_id">' . esc_html__( 'Client ID', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_gads_client_id" type="text" id="gm2_gads_client_id" value="' . esc_attr( $client_id ) . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_gads_client_secret">' . esc_html__( 'Client Secret', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_gads_client_secret" type="text" id="gm2_gads_client_secret" value="' . esc_attr( $client_secret ) . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_gcloud_project_id">' . esc_html__( 'Project ID', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_gcloud_project_id" type="text" id="gm2_gcloud_project_id" value="' . esc_attr( $project_id ) . '" class="regular-text"></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_service_account_json">' . esc_html__( 'Service Account JSON Path', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_service_account_json" type="text" id="gm2_service_account_json" value="' . esc_attr( $service_json ) . '" class="regular-text"></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function display_chatgpt_page() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $key   = get_option('gm2_chatgpt_api_key', '');
        $model = get_option('gm2_chatgpt_model', 'gpt-3.5-turbo');
        $temperature = get_option('gm2_chatgpt_temperature', '1.0');
        $max_tokens  = get_option('gm2_chatgpt_max_tokens', '');
        $endpoint    = get_option('gm2_chatgpt_endpoint', 'https://api.openai.com/v1/chat/completions');
        $logging     = get_option('gm2_enable_chatgpt_logging', '0');
        $notice = '';
        if (!empty($_GET['updated'])) {
            $notice = '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        } elseif (!empty($_GET['logs_reset'])) {
            $notice = '<div class="updated notice"><p>' . esc_html__('Logs reset.', 'gm2-wordpress-suite') . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ) . '</h1>';
        echo $notice;
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_chatgpt_settings');
        echo '<input type="hidden" name="action" value="gm2_chatgpt_settings" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_api_key">' . esc_html__( 'API Key', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="password" id="gm2_chatgpt_api_key" name="gm2_chatgpt_api_key" value="' . esc_attr($key) . '" class="regular-text" />';
        echo ' <button type="button" class="button" id="gm2-chatgpt-toggle">' . esc_html__( 'Show', 'gm2-wordpress-suite' ) . '</button></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_model">' . esc_html__( 'Model', 'gm2-wordpress-suite' ) . '</label></th>';
        $options = '';
        foreach (Gm2_ChatGPT::get_available_models() as $m) {
            $selected = selected($model, $m, false);
            $options .= '<option value="' . esc_attr($m) . '"' . $selected . '>' . esc_html($m) . '</option>';
        }
        echo '<td><select id="gm2_chatgpt_model" name="gm2_chatgpt_model">' . $options . '</select></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_temperature">' . esc_html__( 'Temperature', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="number" step="0.1" id="gm2_chatgpt_temperature" name="gm2_chatgpt_temperature" value="' . esc_attr($temperature) . '" class="small-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_max_tokens">' . esc_html__( 'Max Tokens', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="number" id="gm2_chatgpt_max_tokens" name="gm2_chatgpt_max_tokens" value="' . esc_attr($max_tokens) . '" class="small-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_endpoint">' . esc_html__( 'API Endpoint', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="text" id="gm2_chatgpt_endpoint" name="gm2_chatgpt_endpoint" value="' . esc_attr($endpoint) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_enable_chatgpt_logging">' . esc_html__( 'Enable Logging', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="checkbox" id="gm2_enable_chatgpt_logging" name="gm2_enable_chatgpt_logging" value="1"' . checked('1', $logging, false) . ' /></td></tr>';
        echo '</tbody></table>';
        submit_button();
        $show = esc_js( __( 'Show', 'gm2-wordpress-suite' ) );
        $hide = esc_js( __( 'Hide', 'gm2-wordpress-suite' ) );
        echo "<script>document.addEventListener('DOMContentLoaded',function(){var i=document.getElementById('gm2_chatgpt_api_key');var b=document.getElementById('gm2-chatgpt-toggle');if(i&&b){var s='{$show}';var h='{$hide}';b.addEventListener('click',function(){if(i.type==='password'){i.type='text';b.textContent=h;}else{i.type='password';b.textContent=s;}});}});</script>";
        echo '</form>';

        echo '<h2>' . esc_html__( 'Test Prompt', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<form id="gm2-chatgpt-form">';
        echo '<p><textarea id="gm2_chatgpt_prompt" rows="3" class="large-text"></textarea></p>';
        echo '<p><button class="button">' . esc_html__( 'Send', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</form>';
        echo '<pre id="gm2-chatgpt-output"></pre>';
        if (get_option('gm2_enable_chatgpt_logging', '0') === '1') {
            echo '<h2>' . esc_html__( 'ChatGPT Logs', 'gm2-wordpress-suite' ) . '</h2>';
            $entries = $this->parse_chatgpt_logs();
            if (empty($entries)) {
                echo '<p>' . esc_html__( 'No logs found.', 'gm2-wordpress-suite' ) . '</p>';
            } else {
                echo '<div class="gm2-chatgpt-logs">';
                echo '<table class="widefat fixed gm2-chatgpt-table"><thead><tr><th>' . esc_html__( 'Prompt', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Response', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
                foreach ($entries as $e) {
                    echo '<tr class="gm2-log-entry">';
                    echo '<td>';
                    echo '<div class="gm2-log-toggle">' . esc_html__( 'Prompt sent', 'gm2-wordpress-suite' ) . '</div>';
                    echo '<pre class="gm2-log-content">' . esc_html($e['prompt']) . '</pre>';
                    echo '</td>';
                    echo '<td>';
                    echo '<div class="gm2-log-toggle">' . esc_html__( 'Response received', 'gm2-wordpress-suite' ) . '</div>';
                    echo '<pre class="gm2-log-content">' . esc_html($e['response']) . '</pre>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }
            if (file_exists(GM2_CHATGPT_LOG_FILE)) {
                echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                wp_nonce_field('gm2_reset_chatgpt_logs');
                echo '<input type="hidden" name="action" value="gm2_reset_chatgpt_logs" />';
                submit_button( esc_html__( 'Reset Logs', 'gm2-wordpress-suite' ), 'delete' );
                echo '</form>';
            }
        }
        echo '</div>';
    }

    private function parse_chatgpt_logs() {
        $pairs = [];
        if (!defined('GM2_CHATGPT_LOG_FILE') || !file_exists(GM2_CHATGPT_LOG_FILE)) {
            return $pairs;
        }
        $lines = file(GM2_CHATGPT_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $prompt_prefix = 'ChatGPT prompt: ';
        $resp_prefix   = 'ChatGPT response: ';
        $prompt = null;
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (is_array($data) && isset($data['prompt']) && isset($data['response'])) {
                $pairs[] = [ 'prompt' => $data['prompt'], 'response' => $data['response'] ];
                continue;
            }

            // Fallback for legacy two-line log format
            if (strpos($line, $prompt_prefix) === 0) {
                $prompt = substr($line, strlen($prompt_prefix));
            } elseif (strpos($line, $resp_prefix) === 0 && $prompt !== null) {
                $resp = substr($line, strlen($resp_prefix));
                $pairs[] = [ 'prompt' => $prompt, 'response' => $resp ];
                $prompt = null;
            }
        }
        return $pairs;
    }

    public function handle_chatgpt_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_chatgpt_settings');
        $key = isset($_POST['gm2_chatgpt_api_key']) ? sanitize_text_field($_POST['gm2_chatgpt_api_key']) : '';
        $model = isset($_POST['gm2_chatgpt_model']) ? sanitize_text_field($_POST['gm2_chatgpt_model']) : '';
        $temperature = isset($_POST['gm2_chatgpt_temperature']) ? floatval($_POST['gm2_chatgpt_temperature']) : 1.0;
        $max_tokens  = isset($_POST['gm2_chatgpt_max_tokens']) ? intval($_POST['gm2_chatgpt_max_tokens']) : 0;
        $endpoint    = isset($_POST['gm2_chatgpt_endpoint']) ? esc_url_raw($_POST['gm2_chatgpt_endpoint']) : '';
        $logging     = isset($_POST['gm2_enable_chatgpt_logging']) ? '1' : '0';

        update_option('gm2_chatgpt_api_key', $key);
        update_option('gm2_chatgpt_model', $model);
        update_option('gm2_chatgpt_temperature', $temperature);
        update_option('gm2_chatgpt_max_tokens', $max_tokens);
        update_option('gm2_chatgpt_endpoint', $endpoint);
        update_option('gm2_enable_chatgpt_logging', $logging);

        wp_redirect(admin_url('admin.php?page=gm2-chatgpt&updated=1'));
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function handle_reset_chatgpt_logs() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_reset_chatgpt_logs');

        if (defined('GM2_CHATGPT_LOG_FILE') && file_exists(GM2_CHATGPT_LOG_FILE)) {
            file_put_contents(GM2_CHATGPT_LOG_FILE, '');
        }

        wp_redirect(admin_url('admin.php?page=gm2-chatgpt&logs_reset=1'));
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function ajax_chatgpt_prompt() {
        check_ajax_referer('gm2_chatgpt_nonce');

        if (get_option('gm2_enable_chatgpt', '1') !== '1') {
            wp_send_json_error( __( 'ChatGPT is disabled', 'gm2-wordpress-suite' ) );
        }

        if (trim(get_option('gm2_chatgpt_api_key', '')) === '') {
            wp_send_json_error( __( 'ChatGPT API key not set', 'gm2-wordpress-suite' ) );
        }

        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $chat   = new Gm2_ChatGPT();
        $resp   = $chat->query($prompt);

        if (is_wp_error($resp)) {
            wp_send_json_error($resp->get_error_message());
        }

        wp_send_json_success($resp);
    }

    public function maybe_show_chatgpt_notice() {
        $key = trim(get_option('gm2_chatgpt_api_key', ''));
        if ($this->chatgpt_enabled && $key !== '') {
            return;
        }
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        $seo_pages = [
            'gm2_page_gm2-seo',
            'gm2_page_gm2-bulk-ai-review',
            'gm2_page_gm2-bulk-ai-taxonomies',
        ];
        if ($screen && in_array($screen->id, $seo_pages, true)) {
            $url = admin_url('admin.php?page=gm2-chatgpt');
            $link = '<a href="' . esc_url($url) . '">Gm2 &rarr; ChatGPT</a>';
            echo '<div class="notice notice-warning"><p>' .
                sprintf(esc_html__('Configure ChatGPT under %s.', 'gm2-wordpress-suite'), $link) .
                '</p></div>';
        }
    }
}
