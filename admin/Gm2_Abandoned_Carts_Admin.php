<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
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

        $table = new GM2_AC_Table();
        $table->prepare_items();
        echo '<hr />';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        $table->search_box(__('Search', 'gm2-wordpress-suite'), 'gm2-ac');
        $table->display();
        echo '</form></div>';
    }
}
