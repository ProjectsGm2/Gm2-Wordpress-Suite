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

    public function ajax_process() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        check_ajax_referer('gm2_ac_process', 'nonce');
        Gm2_Abandoned_Carts::cron_mark_abandoned();
        wp_send_json_success();
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
