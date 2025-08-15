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
        $saved    = false;
        $models   = (array) get_site_option('gm2_models', []);
        $network  = (array) get_site_option('gm2_network_models', []);

        if (isset($_POST['gm2_models_nonce']) && wp_verify_nonce($_POST['gm2_models_nonce'], 'gm2_save_models')) {
            $network = array_map('sanitize_text_field', $_POST['network_models'] ?? []);
            update_site_option('gm2_network_models', $network);
            $saved = true;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Gm2 Network Models', 'gm2-wordpress-suite') . '</h1>';
        if ($saved) {
            echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
        echo '<p>' . esc_html__('Designate models as network-wide or allow site-local overrides.', 'gm2-wordpress-suite') . '</p>';

        echo '<form method="post">';
        wp_nonce_field('gm2_save_models', 'gm2_models_nonce');
        echo '<table class="widefat"><thead><tr>';
        echo '<th>' . esc_html__('Model', 'gm2-wordpress-suite') . '</th>';
        echo '<th>' . esc_html__('Network Wide', 'gm2-wordpress-suite') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($models as $model) {
            $checked = in_array($model, $network, true) ? 'checked="checked"' : '';
            echo '<tr><td>' . esc_html($model) . '</td>';
            echo '<td><input type="checkbox" name="network_models[]" value="' . esc_attr($model) . '" ' . $checked . '></td></tr>';
        }

        if (empty($models)) {
            echo '<tr><td colspan="2">' . esc_html__('No models found.', 'gm2-wordpress-suite') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<p><input type="submit" class="button-primary" value="' . esc_attr__('Save Changes', 'gm2-wordpress-suite') . '"></p>';
        echo '</form>';
        echo '</div>';
    }

    public function render_blueprints_page() {
        if (!current_user_can('manage_network')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        $action    = $_POST['gm2_action'] ?? '';
        $blueprint = isset($_POST['blueprint']) ? json_decode(wp_unslash($_POST['blueprint']), true) : [];
        $site_ids  = array_map('intval', $_POST['site_ids'] ?? []);
        $message   = '';
        $diffs     = [];

        if ($action && isset($_POST['gm2_blueprints_nonce']) && wp_verify_nonce($_POST['gm2_blueprints_nonce'], 'gm2_blueprints')) {
            if ($action === 'preview' && !empty($site_ids)) {
                foreach ($site_ids as $id) {
                    $diffs[$id] = $this->calculate_diff($id, $blueprint);
                }
            } elseif ($action === 'push' && !empty($site_ids)) {
                $this->push_blueprint($site_ids, $blueprint);
                $message = esc_html__('Blueprint pushed to selected sites.', 'gm2-wordpress-suite');
            }
        }

        echo '<div class="wrap"><h1>' . esc_html__('Gm2 Blueprints', 'gm2-wordpress-suite') . '</h1>';
        if ($message) {
            echo '<div class="updated notice"><p>' . $message . '</p></div>';
        }
        echo '<p>' . esc_html__('Push models to sites, preview differences, or clone new sites from blueprints.', 'gm2-wordpress-suite') . '</p>';

        echo '<form method="post">';
        wp_nonce_field('gm2_blueprints', 'gm2_blueprints_nonce');

        echo '<h2>' . esc_html__('Blueprint', 'gm2-wordpress-suite') . '</h2>';
        echo '<p><textarea name="blueprint" rows="10" cols="60">' . esc_textarea(json_encode($blueprint, JSON_PRETTY_PRINT)) . '</textarea></p>';

        echo '<h2>' . esc_html__('Target Sites', 'gm2-wordpress-suite') . '</h2><ul>';
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            $checked = in_array((int) $site->blog_id, $site_ids, true) ? 'checked="checked"' : '';
            $label   = $site->blogname ?? $site->domain . $site->path;
            echo '<li><label><input type="checkbox" name="site_ids[]" value="' . esc_attr($site->blog_id) . '" ' . $checked . '> ' . esc_html($label) . '</label></li>';
        }
        echo '</ul>';

        echo '<p>';
        echo '<button type="submit" name="gm2_action" value="preview" class="button">' . esc_html__('Preview Differences', 'gm2-wordpress-suite') . '</button> ';
        echo '<button type="submit" name="gm2_action" value="push" class="button-primary">' . esc_html__('Push Blueprint', 'gm2-wordpress-suite') . '</button>';
        echo '</p>';
        echo '</form>';

        if (!empty($diffs)) {
            echo '<h2>' . esc_html__('Differences', 'gm2-wordpress-suite') . '</h2>';
            foreach ($diffs as $id => $diff) {
                echo '<h3>' . esc_html(sprintf(__('Site %d', 'gm2-wordpress-suite'), $id)) . '</h3>';
                if (empty($diff)) {
                    echo '<p>' . esc_html__('No differences', 'gm2-wordpress-suite') . '</p>';
                } else {
                    echo '<pre>' . esc_html(print_r($diff, true)) . '</pre>';
                }
            }
        }

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
        if (!is_numeric($new_site_id)) {
            $args = wp_parse_args($new_site_id, [
                'domain'  => '',
                'path'    => '/',
                'title'   => '',
                'user_id' => get_current_user_id(),
                'meta'    => [],
            ]);

            $created = wpmu_create_blog(
                $args['domain'],
                $args['path'],
                $args['title'],
                (int) $args['user_id'],
                $args['meta'],
                get_current_network_id()
            );

            if (is_wp_error($created)) {
                return $created;
            }

            $new_site_id = (int) $created;
        }

        switch_to_blog($new_site_id);

        if (!term_exists('Uncategorized', 'category')) {
            wp_insert_term('Uncategorized', 'category');
        }

        if (!get_page_by_path('sample-page')) {
            wp_insert_post([
                'post_title'   => __('Sample Page', 'gm2-wordpress-suite'),
                'post_content' => __('This is an example page.', 'gm2-wordpress-suite'),
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'page',
            ]);
        }

        if (!get_posts(['numberposts' => 1])) {
            wp_insert_post([
                'post_title'   => __('Hello world!', 'gm2-wordpress-suite'),
                'post_content' => __('Welcome to your new site!', 'gm2-wordpress-suite'),
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'post',
            ]);
        }

        restore_current_blog();

        $this->push_blueprint([$new_site_id], $blueprint, $overrides);

        return $new_site_id;
    }
}
