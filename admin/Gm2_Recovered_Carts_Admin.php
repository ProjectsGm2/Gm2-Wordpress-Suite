<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Recovered_Carts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_init', [ $this, 'maybe_export' ]);
        add_action('admin_post_gm2_ac_recovered_reset', [ $this, 'handle_reset' ]);
    }

    public function add_menu() {
        add_submenu_page(
            'gm2',
            __('Recovered Carts', 'gm2-wordpress-suite'),
            __('Recovered Carts', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-recovered-carts',
            [ $this, 'display_page' ]
        );
    }

    public function display_page() {
        add_screen_option('per_page', [
            'label'   => __('Items per page', 'gm2-wordpress-suite'),
            'default' => 20,
            'option'  => 'gm2_ac_per_page',
        ]);
        echo '<div class="wrap"><h1>' . esc_html__('Recovered Carts', 'gm2-wordpress-suite') . '</h1>';

        if (!empty($_GET['logs_reset'])) {
            echo '<div class="updated notice"><p>' . esc_html__('Logs reset.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $args = [
            'page'   => 'gm2-recovered-carts',
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
        wp_nonce_field('gm2_ac_recovered_reset');
        echo '<input type="hidden" name="action" value="gm2_ac_recovered_reset" />';
        submit_button( esc_html__( 'Reset Logs', 'gm2-wordpress-suite' ), 'delete', '', false );
        echo '</form>';

        $table = new GM2_AC_Table([ 'table' => 'wc_ac_recovered', 'recovered' => true ]);
        $table->process_bulk_action();
        $table->prepare_items();
        echo '<hr />';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        $table->search_box(__('Search', 'gm2-wordpress-suite'), 'gm2-ac-recovered');
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
        if (!isset($_GET['page'], $_GET['action']) || $_GET['page'] !== 'gm2-recovered-carts' || $_GET['action'] !== 'export') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export this data.', 'gm2-wordpress-suite'));
        }

        check_admin_referer('gm2-ac-export');

        $table = new GM2_AC_Table([ 'table' => 'wc_ac_recovered', 'recovered' => true ]);
        $table->prepare_items();

        $columns = array_keys($table->get_columns());

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="recovered-carts.csv"');

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

    public function handle_reset() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_ac_recovered_reset');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wc_ac_recovered");

        wp_redirect(admin_url('admin.php?page=gm2-recovered-carts&logs_reset=1'));
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }
}
