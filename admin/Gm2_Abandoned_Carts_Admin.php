<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_init', [ $this, 'maybe_export' ]);
    }

    public function add_menu() {
        add_submenu_page(
            'gm2',
            __('Abandoned Carts', 'gm2-wordpress-suite'),
            __('Abandoned Carts', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-abandoned-carts',
            [ $this, 'display_page' ]
        );
    }

    public function display_page() {
        echo '<div class="wrap"><h1>' . esc_html__('Abandoned Carts', 'gm2-wordpress-suite') . '</h1>';

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

        $table = new GM2_AC_Table();
        $table->prepare_items();
        echo '<hr />';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        $table->search_box(__('Search', 'gm2-wordpress-suite'), 'gm2-ac');
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
}
