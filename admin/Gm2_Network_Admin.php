<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Network_Admin {
    public function run() {
        add_action('network_admin_menu', [ $this, 'add_menu' ]);
    }

    public function add_menu() {
        add_menu_page(
            __('Gm2 Network', 'gm2-wordpress-suite'),
            __('Gm2 Network', 'gm2-wordpress-suite'),
            'manage_network',
            'gm2-network',
            [ $this, 'render_models_page' ],
            'dashicons-admin-generic'
        );

        add_submenu_page(
            'gm2-network',
            __('Blueprints', 'gm2-wordpress-suite'),
            __('Blueprints', 'gm2-wordpress-suite'),
            'manage_network',
            'gm2-blueprints',
            [ $this, 'render_blueprints_page' ]
        );
    }

    public function render_models_page() {
        if (!current_user_can('manage_network')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('Gm2 Network Models', 'gm2-wordpress-suite') . '</h1>';
        echo '<p>' . esc_html__('Designate models as network-wide or allow site-local overrides.', 'gm2-wordpress-suite') . '</p>';
        echo '</div>';
    }

    public function render_blueprints_page() {
        if (!current_user_can('manage_network')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('Gm2 Blueprints', 'gm2-wordpress-suite') . '</h1>';
        echo '<p>' . esc_html__('Push models to sites, preview differences, or clone new sites from blueprints.', 'gm2-wordpress-suite') . '</p>';
        echo '</div>';
    }

    public function calculate_diff($site_id, array $blueprint) {
        switch_to_blog($site_id);
        $current = [];
        foreach ($blueprint as $option => $value) {
            $current[$option] = get_option($option);
        }
        restore_current_blog();
        return array_diff_assoc($blueprint, $current);
    }

    public function push_blueprint(array $site_ids, array $blueprint, array $overrides = []) {
        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            foreach ($blueprint as $option => $value) {
                $val = $overrides[$site_id][$option] ?? $value;
                update_option($option, $val);
            }
            restore_current_blog();
        }
    }

    public function clone_site_from_blueprint(array $blueprint, $new_site_id, array $overrides = []) {
        // TODO: Implement full site cloning, seed content, and terms.
        $this->push_blueprint([$new_site_id], $blueprint, $overrides);
    }
}
