<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_gm2_add_tariff', [$this, 'ajax_add_tariff']);
        add_action('wp_ajax_nopriv_gm2_add_tariff', [$this, 'ajax_add_tariff']);
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'gm2_page_gm2-tariff') {
            return;
        }
        wp_enqueue_script(
            'gm2-tariff',
            GM2_PLUGIN_URL . 'admin/js/gm2-tariff.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-tariff',
            'gm2Tariff',
            [
                'nonce'    => wp_create_nonce('gm2_add_tariff'),
                'ajax_url' => admin_url('admin-ajax.php'),
            ]
        );
    }

    public function ajax_add_tariff() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        check_ajax_referer('gm2_add_tariff');

        $name = sanitize_text_field($_POST['tariff_name'] ?? '');
        if ($name === '') {
            wp_send_json_error('Tariff name is required');
        }

        $percentage_raw = $_POST['tariff_percentage'] ?? '';

        if (!is_numeric($percentage_raw) || floatval($percentage_raw) < 0) {
            wp_send_json_error('Tariff percentage must be a non-negative number');
        }

        $percentage = floatval($percentage_raw);
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

        // The add tariff form is now part of the Tariff page. The following
        // submenu is kept for editing existing tariffs but hidden from the
        // menu by setting the parent slug to null.
        add_submenu_page(
            null,
            'Edit Tariff',
            'Edit Tariff',
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
                $manager->update_tariff(sanitize_text_field($_POST['tariff_id']), $data);
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
            $tariff  = $manager->get_tariff(sanitize_text_field($_GET['id']));
        }

        $name       = $tariff ? esc_attr($tariff['name']) : '';
        $percentage = $tariff ? esc_attr($tariff['percentage']) : '';
        $status     = $tariff ? $tariff['status'] : 'enabled';
        $id_field   = $tariff ? '<input type="hidden" name="tariff_id" value="' . esc_attr($tariff['id']) . '" />' : '';

        echo '<div class="wrap"><h1>Edit Tariff</h1>';
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
            $id = sanitize_text_field($_GET['id']);
            check_admin_referer('gm2_delete_tariff_' . $id);
            $manager->delete_tariff($id);
            echo '<div class="updated"><p>Tariff deleted.</p></div>';
        }

        $tariffs = $manager->get_tariffs();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Tariffs</h1>';
        echo '<hr class="wp-header-end">';

        echo '<h2>Add Tariff</h2>';
        echo '<div class="notice notice-success hidden" id="gm2-tariff-msg"></div>';
        echo '<form id="gm2-add-tariff-form">';
        wp_nonce_field('gm2_add_tariff', 'gm2_add_tariff_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">Name</label></th><td><input name="tariff_name" type="text" id="tariff_name" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">Percentage</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">Status</th><td><label><input type="checkbox" name="tariff_status" id="tariff_status" checked> Enabled</label></td></tr>';
        echo '</tbody></table>';
        submit_button('Add Tariff');
        echo '</form>';

        echo '<h2>Existing Tariffs</h2>';
        echo '<table class="widefat" id="gm2-tariff-table"><thead><tr><th>Name</th><th>Percentage</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        if ($tariffs) {
            foreach ($tariffs as $tariff) {
                $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $tariff['id']), 'gm2_delete_tariff_' . $tariff['id']);
                $edit_url   = admin_url('admin.php?page=gm2-add-tariff&id=' . $tariff['id']);
                echo '<tr>';
                echo '<td>' . esc_html($tariff['name']) . '</td>';
                echo '<td>' . esc_html($tariff['percentage']) . '%</td>';
                echo '<td>' . esc_html(ucfirst($tariff['status'])) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">View</a> | <a href="' . esc_url( $edit_url ) . '">Edit</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Are you sure?\');">Delete</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">No tariffs found.</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
