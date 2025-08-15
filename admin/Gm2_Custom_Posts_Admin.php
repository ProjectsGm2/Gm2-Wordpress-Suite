<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Custom_Posts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('add_meta_boxes', [ $this, 'add_meta_boxes' ]);
        add_action('save_post', [ $this, 'save_meta_boxes' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
    }

    private function get_config() {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            $config = [];
        }
        $config = wp_parse_args($config, [
            'post_types' => [],
            'taxonomies' => [],
        ]);
        return $config;
    }

    public function add_menu() {
        add_menu_page(
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-custom-posts',
            [ $this, 'display_page' ],
            'dashicons-admin-post'
        );
    }

    public function display_page() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $config = $this->get_config();

        if (isset($_POST['gm2_add_post_type']) && check_admin_referer('gm2_add_post_type')) {
            $slug   = sanitize_key($_POST['pt_slug'] ?? '');
            $label  = sanitize_text_field($_POST['pt_label'] ?? '');
            $fields = json_decode(wp_unslash($_POST['pt_fields'] ?? ''), true);
            if (!is_array($fields)) {
                $fields = [];
            }
            if ($slug) {
                $config['post_types'][$slug] = [
                    'label'  => $label ?: ucfirst($slug),
                    'fields' => $fields,
                ];
                update_option('gm2_custom_posts_config', $config);
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Post type saved.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
        }

        if (isset($_POST['gm2_add_taxonomy']) && check_admin_referer('gm2_add_taxonomy')) {
            $slug       = sanitize_key($_POST['tax_slug'] ?? '');
            $label      = sanitize_text_field($_POST['tax_label'] ?? '');
            $post_types = array_filter(array_map('sanitize_key', explode(',', $_POST['tax_post_types'] ?? '')));
            $args       = json_decode(wp_unslash($_POST['tax_args'] ?? ''), true);
            if (!is_array($args)) {
                $args = [];
            }
            if ($slug) {
                $config['taxonomies'][$slug] = [
                    'label'      => $label ?: ucfirst($slug),
                    'post_types' => $post_types,
                    'args'       => $args,
                ];
                update_option('gm2_custom_posts_config', $config);
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Taxonomy saved.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ) . '</h1>';

        echo '<h2>' . esc_html__( 'Existing Post Types', 'gm2-wordpress-suite' ) . '</h2>';
        if (!empty($config['post_types'])) {
            echo '<ul>';
            foreach ($config['post_types'] as $slug => $pt) {
                echo '<li>' . esc_html($slug . ' - ' . ($pt['label'] ?? $slug)) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No custom post types defined.', 'gm2-wordpress-suite' ) . '</p>';
        }

        echo '<h2>' . esc_html__( 'Existing Taxonomies', 'gm2-wordpress-suite' ) . '</h2>';
        if (!empty($config['taxonomies'])) {
            echo '<ul>';
            foreach ($config['taxonomies'] as $slug => $tax) {
                echo '<li>' . esc_html($slug . ' - ' . ($tax['label'] ?? $slug)) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No custom taxonomies defined.', 'gm2-wordpress-suite' ) . '</p>';
        }

        echo '<hr />';

        echo '<h2>' . esc_html__( 'Add / Edit Post Type', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('gm2_add_post_type');
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_slug" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_label" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Fields (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="pt_fields" class="large-text code" rows="5" placeholder="{\n  \"field_key\": {\n    \"label\": \"Field Label\",\n    \"type\": \"text\"\n  }\n}"></textarea></label></p>';
        echo '<p><input type="submit" name="gm2_add_post_type" class="button button-primary" value="' . esc_attr__( 'Save Post Type', 'gm2-wordpress-suite' ) . '" /></p>';
        echo '</form>';

        echo '<h2>' . esc_html__( 'Add / Edit Taxonomy', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('gm2_add_taxonomy');
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_slug" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_label" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Post Types (comma separated)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_post_types" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Args (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="tax_args" class="large-text code" rows="5"></textarea></label></p>';
        echo '<p><input type="submit" name="gm2_add_taxonomy" class="button button-primary" value="' . esc_attr__( 'Save Taxonomy', 'gm2-wordpress-suite' ) . '" /></p>';
        echo '</form>';

        echo '</div>';
    }

    public function add_meta_boxes() {
        $config = $this->get_config();
        foreach ($config['post_types'] as $slug => $pt) {
            $fields = $pt['fields'] ?? [];
            if (empty($fields)) {
                continue;
            }
            add_meta_box(
                'gm2_fields_' . $slug,
                esc_html($pt['label'] ?? $slug),
                function($post) use ($fields, $slug) {
                    $this->render_meta_box($post, $fields, $slug);
                },
                $slug,
                'normal',
                'default'
            );
        }
    }

    public function render_meta_box($post, $fields, $slug) {
        wp_nonce_field('gm2_save_custom_fields', 'gm2_custom_fields_nonce');
        foreach ($fields as $key => $field) {
            $type  = $field['type'] ?? 'text';
            $label = $field['label'] ?? $key;
            $value = get_post_meta($post->ID, $key, true);
            $cond  = $field['conditional'] ?? [];
            $options = $field['options'] ?? [];
            echo '<div class="gm2-field"';
            if (!empty($cond['field']) && isset($cond['value'])) {
                echo ' data-conditional-field="' . esc_attr($cond['field']) . '" data-conditional-value="' . esc_attr($cond['value']) . '"';
            }
            echo '>';
            echo '<p><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><br />';
            switch ($type) {
                case 'number':
                    echo '<input type="number" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                    break;
                case 'checkbox':
                    echo '<input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1"' . checked($value, '1', false) . ' />';
                    break;
                case 'select':
                    echo '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">';
                    foreach ($options as $opt_val => $opt_label) {
                        echo '<option value="' . esc_attr($opt_val) . '"' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'radio':
                    foreach ($options as $opt_val => $opt_label) {
                        echo '<label><input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($opt_val) . '"' . checked($value, $opt_val, false) . '/> ' . esc_html($opt_label) . '</label><br />';
                    }
                    break;
                default:
                    echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                    break;
            }
            echo '</p></div>';
        }
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['gm2_custom_fields_nonce']) || !wp_verify_nonce($_POST['gm2_custom_fields_nonce'], 'gm2_save_custom_fields')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $post_type = get_post_type($post_id);
        $config = $this->get_config();
        if (empty($config['post_types'][$post_type]['fields'])) {
            return;
        }
        foreach ($config['post_types'][$post_type]['fields'] as $key => $field) {
            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') {
                $value = isset($_POST[$key]) ? '1' : '0';
                update_post_meta($post_id, $key, $value);
            } elseif (isset($_POST[$key])) {
                $value = sanitize_text_field($_POST[$key]);
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    public function enqueue_scripts($hook) {
        if (!in_array($hook, [ 'post.php', 'post-new.php' ], true)) {
            return;
        }
        $file = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-posts.js';
        wp_enqueue_script(
            'gm2-custom-posts',
            GM2_PLUGIN_URL . 'admin/js/gm2-custom-posts.js',
            ['jquery'],
            file_exists($file) ? filemtime($file) : GM2_VERSION,
            true
        );
    }
}
