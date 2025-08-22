<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_init', [ $this, 'maybe_export' ]);
        add_action('admin_post_gm2_ac_reset', [ $this, 'handle_reset' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_ac_get_carts', [ $this, 'ajax_get_carts' ]);
        add_action('wp_ajax_gm2_ac_process', [ $this, 'ajax_process' ]);
        add_action('wp_ajax_gm2_ac_refresh_summary', [ $this, 'ajax_refresh_summary' ]);
        add_action('admin_notices', [ $this, 'maybe_show_failures_notice' ]);
    }

    public function add_menu() {
        add_submenu_page(
            'gm2-cart',
            __('Abandoned Carts', 'gm2-wordpress-suite'),
            __('Abandoned Carts', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-abandoned-carts',
            [ $this, 'display_page' ]
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'gm2-cart_page_gm2-abandoned-carts') {
            return;
        }
        wp_enqueue_script(
            'gm2-ac-activity-log',
            GM2_PLUGIN_URL . 'admin/js/gm2-ac-activity-log.js',
            [ 'jquery' ],
            file_exists(GM2_PLUGIN_DIR . 'admin/js/gm2-ac-activity-log.js') ? filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-ac-activity-log.js') : GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-ac-activity-log',
            'gm2AcActivityLog',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_ac_get_activity'),
                'empty'    => __( 'No activity found.', 'gm2-wordpress-suite' ),
                'error'    => __( 'Unable to load activity.', 'gm2-wordpress-suite' ),
                'load_more'=> __( 'Load more', 'gm2-wordpress-suite' ),
                'per_page' => 50,
            ]
        );
        wp_enqueue_style(
            'gm2-ac-activity-log',
            GM2_PLUGIN_URL . 'admin/css/gm2-ac-activity-log.css',
            [],
            file_exists(GM2_PLUGIN_DIR . 'admin/css/gm2-ac-activity-log.css') ? filemtime(GM2_PLUGIN_DIR . 'admin/css/gm2-ac-activity-log.css') : GM2_VERSION
        );

        wp_enqueue_script(
            'gm2-ac-live-updates',
            GM2_PLUGIN_URL . 'admin/js/gm2-ac-live-updates.js',
            [ 'jquery' ],
            file_exists(GM2_PLUGIN_DIR . 'admin/js/gm2-ac-live-updates.js') ? filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-ac-live-updates.js') : GM2_VERSION,
            true
        );
        $paged = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1;
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        wp_localize_script(
            'gm2-ac-live-updates',
            'gm2AcLive',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_ac_get_carts'),
                'process_nonce' => wp_create_nonce('gm2_ac_process'),
                'summary_nonce' => wp_create_nonce('gm2_ac_refresh_summary'),
                'paged'    => $paged,
                's'        => $search,
            ]
        );
    }

    public function display_page() {
        add_screen_option('per_page', [
            'label'   => __('Items per page', 'gm2-wordpress-suite'),
            'default' => 20,
            'option'  => 'gm2_ac_per_page',
        ]);
        echo '<div class="wrap"><h1>' . esc_html__('Abandoned Carts', 'gm2-wordpress-suite') . '</h1>';

        $logging = get_option('gm2_ac_enable_logging', '0');
        if (isset($_POST['gm2_ac_logging_nonce']) && wp_verify_nonce($_POST['gm2_ac_logging_nonce'], 'gm2_ac_logging_save')) {
            $logging = isset($_POST['gm2_ac_enable_logging']) ? '1' : '0';
            update_option('gm2_ac_enable_logging', $logging);
            echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }

        echo '<h2>' . esc_html__('Logging', 'gm2-wordpress-suite') . '</h2>';
        echo '<form method="post" style="margin-bottom:20px;">';
        wp_nonce_field('gm2_ac_logging_save', 'gm2_ac_logging_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_ac_enable_logging">' . esc_html__('Enable Verbose Logging', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="checkbox" id="gm2_ac_enable_logging" name="gm2_ac_enable_logging" value="1"' . checked('1', $logging, false) . ' /></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';

        if ($logging === '1') {
            echo '<h2>' . esc_html__('Recent Logs', 'gm2-wordpress-suite') . '</h2>';
            if (function_exists('wc_get_log_file_path')) {
                $file = wc_get_log_file_path('gm2_abandoned_carts');
                if (file_exists($file)) {
                    $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);
                    echo '<pre class="gm2-ac-logs" style="background:#fff;border:1px solid #ccc;padding:10px;max-height:300px;overflow:auto;">' . esc_html(implode("\n", $lines)) . '</pre>';
                } else {
                    echo '<p>' . esc_html__('No logs found.', 'gm2-wordpress-suite') . '</p>';
                }
            }
        }

        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }

        if (wp_next_scheduled('gm2_ac_mark_abandoned_cron')) {
            echo '<p>' . esc_html(sprintf(__('Carts inactive for more than %d minutes appear as "Pending Abandonment" until WP Cron finalizes their status.', 'gm2-wordpress-suite'), $minutes)) . '</p>';
        } else {
            echo '<p>' . esc_html__('Carts are marked as abandoned in real time.', 'gm2-wordpress-suite') . '</p>';
        }

        if (!empty($_GET['logs_reset'])) {
            echo '<div class="updated notice"><p>' . esc_html__('Logs reset.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $summary = $this->get_summary_data();
        echo '<div id="gm2-ac-summary" class="gm2-ac-summary" role="status" aria-live="polite">';
        echo '<p><strong>' . esc_html__('Total Carts', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-total"><span class="screen-reader-text">' . esc_html__('Total Carts', 'gm2-wordpress-suite') . ':</span><span class="count">' . intval($summary['total']) . '</span></span></p>';
        echo '<p><strong>' . esc_html__('Pending', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-pending"><span class="screen-reader-text">' . esc_html__('Pending', 'gm2-wordpress-suite') . ':</span><span class="count">' . intval($summary['pending']) . '</span></span></p>';
        echo '<p><strong>' . esc_html__('Abandoned', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-abandoned"><span class="screen-reader-text">' . esc_html__('Abandoned', 'gm2-wordpress-suite') . ':</span><span class="count">' . intval($summary['abandoned']) . '</span></span></p>';
        echo '<p><strong>' . esc_html__('Recovered', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-recovered"><span class="screen-reader-text">' . esc_html__('Recovered', 'gm2-wordpress-suite') . ':</span><span class="count">' . intval($summary['recovered']) . '</span></span></p>';
        echo '<p><strong>' . esc_html__('Potential Revenue', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-potential"><span class="screen-reader-text">' . esc_html__('Potential Revenue', 'gm2-wordpress-suite') . ':</span><span class="count">' . esc_html(wc_price($summary['potential_revenue'])) . '</span></span></p>';
        echo '<p><strong>' . esc_html__('Recovered Revenue', 'gm2-wordpress-suite') . ':</strong> <span id="gm2-ac-recovered-revenue"><span class="screen-reader-text">' . esc_html__('Recovered Revenue', 'gm2-wordpress-suite') . ':</span><span class="count">' . esc_html(wc_price($summary['recovered_revenue'])) . '</span></span></p>';
        echo '</div>';
        echo '<button type="button" class="button" id="gm2-ac-refresh-summary">' . esc_html__( 'Refresh Summary', 'gm2-wordpress-suite' ) . '</button>';

        $args = [
            'page'   => 'gm2-abandoned-carts',
            'action' => 'export',
        ];
        if (!empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_REQUEST['s']));
        }
        if (!empty($_REQUEST['paged'])) {
            $args['paged'] = absint($_REQUEST['paged']);
        }
        $export_url = wp_nonce_url(add_query_arg($args, admin_url('admin.php')), 'gm2-ac-export');
        echo '<a href="' . esc_url($export_url) . '" class="button button-secondary">' . esc_html__('Export CSV', 'gm2-wordpress-suite') . '</a>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:10px;">';
        wp_nonce_field('gm2_ac_reset');
        echo '<input type="hidden" name="action" value="gm2_ac_reset" />';
        submit_button( esc_html__( 'Reset Logs', 'gm2-wordpress-suite' ), 'delete', '', false );
        echo '</form>';

        echo '<button type="button" class="button" id="gm2-ac-process" style="margin-left:10px;">' . esc_html__( 'Process Pending Carts', 'gm2-wordpress-suite' ) . '</button>';

        $table = new GM2_AC_Table();
        $table->process_bulk_action();
        $table->prepare_items();
        echo '<hr />';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        $table->search_box(__('Search', 'gm2-wordpress-suite'), 'gm2-ac');
        echo '</form>';
        echo '<form method="post">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        if (!empty($_REQUEST['s'])) {
            echo '<input type="hidden" name="s" value="' . esc_attr($_REQUEST['s']) . '" />';
        }
        if (!empty($_REQUEST['paged'])) {
            echo '<input type="hidden" name="paged" value="' . absint($_REQUEST['paged']) . '" />';
        }
        $table->display();
        echo '</form></div>';
    }

    public function maybe_export() {
        if (!isset($_GET['page'], $_GET['action']) || $_GET['page'] !== 'gm2-abandoned-carts' || $_GET['action'] !== 'export') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export this data.', 'gm2-wordpress-suite'));
        }

        check_admin_referer('gm2-ac-export');

        $table = new GM2_AC_Table();
        $table->prepare_items();

        $columns = array_keys($table->get_columns());

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abandoned-carts.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_values($table->get_columns()));
        foreach ($table->items as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = isset($item[$col]) ? wp_strip_all_tags($item[$col]) : '';
            }
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function ajax_get_carts() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        check_ajax_referer('gm2_ac_get_carts', 'nonce');

        $table = new GM2_AC_Table();
        if (!empty($_POST['paged'])) {
            $_REQUEST['paged'] = absint($_POST['paged']);
        }
        if (!empty($_POST['s'])) {
            $_REQUEST['s'] = sanitize_text_field(wp_unslash($_POST['s']));
        }
        $table->prepare_items();
        ob_start();
        $table->display_rows_or_placeholder();
        $rows = ob_get_clean();

        wp_send_json_success([ 'rows' => $rows ]);
    }

    private function get_summary_data() {
        $summary = get_transient('gm2_ac_summary');
        if ($summary !== false) {
            return $summary;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row("SELECT COUNT(*) AS total, SUM(abandoned_at IS NULL AND recovered_order_id IS NULL) AS pending, SUM(abandoned_at IS NOT NULL) AS abandoned, SUM(cart_total) AS potential_revenue FROM $table", ARRAY_A);
        $rec_table = $wpdb->prefix . 'wc_ac_recovered';
        $row2 = $wpdb->get_row("SELECT COUNT(*) AS recovered, SUM(cart_total) AS recovered_revenue FROM $rec_table", ARRAY_A);
        $summary = [
            'total' => (int) $row['total'] + (int) $row2['recovered'],
            'pending' => (int) $row['pending'],
            'abandoned' => (int) $row['abandoned'],
            'recovered' => (int) $row2['recovered'],
            'potential_revenue' => (float) $row['potential_revenue'],
            'recovered_revenue' => (float) $row2['recovered_revenue'],
        ];
        set_transient('gm2_ac_summary', $summary, 5 * MINUTE_IN_SECONDS);
        return $summary;
    }

    public function refresh_summary() {
        delete_transient('gm2_ac_summary');
        return $this->get_summary_data();
    }

    public function ajax_refresh_summary() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        check_ajax_referer('gm2_ac_refresh_summary', 'nonce');
        $data = $this->refresh_summary();
        $data['potential_revenue'] = wc_price($data['potential_revenue']);
        $data['recovered_revenue'] = wc_price($data['recovered_revenue']);
        wp_send_json_success($data);
    }

    public static function refresh_summary_cron() {
        $admin = new self();
        $admin->refresh_summary();
    }

    public function ajax_process() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        check_ajax_referer('gm2_ac_process', 'nonce');
        Gm2_Abandoned_Carts::cron_mark_abandoned();
        $this->refresh_summary();
        wp_send_json_success();
    }

    public function maybe_show_failures_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $counts = get_option('gm2_ac_failure_count', []);
        $threshold = (int) apply_filters('gm2_ac_failure_threshold', 5);
        $nonce = isset($counts['nonce']) ? (int) $counts['nonce'] : 0;
        $token = isset($counts['token']) ? (int) $counts['token'] : 0;
        if ($nonce >= $threshold || $token >= $threshold) {
            $message = sprintf(
                __('Abandoned cart issues detected: %d nonce failures, %d token failures.', 'gm2-wordpress-suite'),
                $nonce,
                $token
            );
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    }

    public function handle_reset() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_ac_reset');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wc_ac_carts");

        wp_redirect(admin_url('admin.php?page=gm2-abandoned-carts&logs_reset=1'));
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }
}

add_action('gm2_ac_mark_abandoned_cron', [Gm2_Abandoned_Carts_Admin::class, 'refresh_summary_cron']);
