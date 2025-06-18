<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Gm2',
            'Gm2',
            'manage_options',
            'gm2',
            [$this, 'display_dashboard'],
            'dashicons-admin-generic'
        );

        add_submenu_page(
            'gm2',
            'Tariff',
            'Tariff',
            'manage_options',
            'gm2-tariff',
            [$this, 'display_tariff_page']
        );

        add_submenu_page(
            'gm2',
            'Add Tariff',
            'Add Tariff',
            'manage_options',
            'gm2-add-tariff',
            [$this, 'display_add_tariff_page']
        );
    }

    public function display_dashboard() {
        echo '<div class="wrap"><h1>Gm2 Suite</h1><p>Welcome to the admin interface!</p></div>';
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
                $manager->update_tariff(intval($_POST['tariff_id']), $data);
            } else {
                $manager->add_tariff($data);
            }
            echo '<div class="updated"><p>Tariff saved.</p></div>';
        }
    }

    public function display_add_tariff_page() {
        $this->handle_form_submission();

        $tariff = false;
        if (!empty($_GET['id'])) {
            $manager = new Gm2_Tariff_Manager();
            $tariff  = $manager->get_tariff(intval($_GET['id']));
        }

        $name       = $tariff ? esc_attr($tariff['name']) : '';
        $percentage = $tariff ? esc_attr($tariff['percentage']) : '';
        $status     = $tariff ? $tariff['status'] : 'enabled';
        $id_field   = $tariff ? '<input type="hidden" name="tariff_id" value="' . intval($tariff['id']) . '" />' : '';

        echo '<div class="wrap"><h1>Add Tariff</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_save_tariff', 'gm2_tariff_nonce');
        echo $id_field;
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">Name</label></th><td><input name="tariff_name" type="text" id="tariff_name" value="' . $name . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">Percentage</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" value="' . $percentage . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">Status</th><td><label><input type="checkbox" name="tariff_status"' . checked($status, 'enabled', false) . '> Enabled</label></td></tr>';
        echo '</tbody></table>';
        submit_button('Save Tariff');
        echo '</form></div>';
    }

    public function display_tariff_page() {
        $manager = new Gm2_Tariff_Manager();

        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
            check_admin_referer('gm2_delete_tariff_' . intval($_GET['id']));
            $manager->delete_tariff(intval($_GET['id']));
            echo '<div class="updated"><p>Tariff deleted.</p></div>';
        }

        $tariffs = $manager->get_tariffs();

        echo '<div class="wrap"><h1>Tariffs</h1>';
        echo '<table class="widefat"><thead><tr><th>Name</th><th>Percentage</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        if ($tariffs) {
            foreach ($tariffs as $tariff) {
                $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $tariff['id']), 'gm2_delete_tariff_' . $tariff['id']);
                $edit_url   = admin_url('admin.php?page=gm2-add-tariff&id=' . $tariff['id']);
                echo '<tr>';
                echo '<td>' . esc_html($tariff['name']) . '</td>';
                echo '<td>' . esc_html($tariff['percentage']) . '%</td>';
                echo '<td>' . esc_html(ucfirst($tariff['status'])) . '</td>';
                echo '<td><a href="' . $edit_url . '">View</a> | <a href="' . $edit_url . '">Edit</a> | <a href="' . $delete_url . '" onclick="return confirm(\'Are you sure?\');">Delete</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">No tariffs found.</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
