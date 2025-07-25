<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_post_gm2_ac_settings', [ $this, 'save_settings' ]);
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

    public function save_settings() {
        check_admin_referer('gm2_ac_settings');
        update_option('gm2_ac_timeout', absint($_POST['gm2_ac_timeout']));
        wp_redirect(admin_url('admin.php?page=gm2-abandoned-carts&updated=1'));
        exit;
    }

    public function display_page() {
        $timeout = get_option('gm2_ac_timeout', 60);
        echo '<div class="wrap"><h1>' . esc_html__('Abandoned Carts', 'gm2-wordpress-suite') . '</h1>';
        if (isset($_GET['updated'])) {
            echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gm2_ac_settings');
        echo '<input type="hidden" name="action" value="gm2_ac_settings">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_ac_timeout">' . esc_html__('Abandonment timeout (minutes)', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input name="gm2_ac_timeout" id="gm2_ac_timeout" type="number" value="' . esc_attr($timeout) . '" class="small-text"></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';

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
