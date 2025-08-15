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
        add_action('wp_ajax_gm2_save_cpt_fields', [ $this, 'ajax_save_fields' ]);
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

    private function can_manage() {
        return current_user_can('manage_options') || current_user_can('gm2_manage_cpts');
    }

    public function add_menu() {
        $cap = current_user_can('gm2_manage_cpts') ? 'gm2_manage_cpts' : 'manage_options';

        add_menu_page(
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            $cap,
            'gm2-custom-posts',
            [ $this, 'display_page' ],
            'dashicons-admin-post'
        );

        add_submenu_page(
            'gm2-custom-posts',
            esc_html__( 'Edit Post Type', 'gm2-wordpress-suite' ),
            esc_html__( 'Edit Post Type', 'gm2-wordpress-suite' ),
            $cap,
            'gm2_cpt_fields',
            [ $this, 'display_fields_page' ]
        );

        add_submenu_page(
            'gm2-custom-posts',
            esc_html__( 'Edit Taxonomy', 'gm2-wordpress-suite' ),
            esc_html__( 'Edit Taxonomy', 'gm2-wordpress-suite' ),
            $cap,
            'gm2_tax_args',
            [ $this, 'display_taxonomy_page' ]
        );
    }

    public function display_fields_page($slug = '') {
        if (!$this->can_manage()) {
            wp_die(esc_html__( 'Permission denied', 'gm2-wordpress-suite' ));
        }

        if (!$slug) {
            $slug = sanitize_key($_GET['cpt'] ?? '');
        }

        $config = $this->get_config();
        $post_type = $config['post_types'][$slug] ?? null;
        if (!$post_type) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Invalid post type.', 'gm2-wordpress-suite' ) . '</h1></div>';
            return;
        }
        $fields = $post_type['fields'] ?? [];

        echo '<div class="wrap">';
        echo '<h1>' . sprintf(esc_html__( '%s Fields', 'gm2-wordpress-suite' ), esc_html($post_type['label'] ?? $slug)) . '</h1>';
        echo '<form id="gm2-fields-form">';
        wp_nonce_field('gm2_save_cpt_fields', 'gm2_save_cpt_fields_nonce');
        echo '<input type="hidden" name="pt_slug" value="' . esc_attr($slug) . '" />';
        echo '<table class="widefat fixed" id="gm2-fields-table">';
        echo '<thead><tr>';
        echo '<th></th>';
        echo '<th>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Options', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Conditional Field', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Conditional Value', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($fields as $key => $field) {
            $label = $field['label'] ?? '';
            $type  = $field['type'] ?? 'text';
            $def   = $field['default'] ?? '';
            $cond  = $field['conditional'] ?? [];
            $opt   = '';
            if (!empty($field['options']) && is_array($field['options'])) {
                $pairs = [];
                foreach ($field['options'] as $ov => $ol) {
                    $pairs[] = $ov . ':' . $ol;
                }
                $opt = implode(',', $pairs);
            }
            echo '<tr>';
            echo '<td class="gm2-move-field"><span class="dashicons dashicons-move"></span></td>';
            echo '<td><input type="text" class="gm2-field-label" value="' . esc_attr($label) . '" /></td>';
            echo '<td><input type="text" class="gm2-field-slug" value="' . esc_attr($key) . '" /></td>';
            echo '<td><select class="gm2-field-type">';
            foreach ([ 'text', 'number', 'checkbox', 'select', 'radio' ] as $t) {
                echo '<option value="' . esc_attr($t) . '"' . selected($type, $t, false) . '>' . esc_html(ucfirst($t)) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input type="text" class="gm2-field-default" value="' . esc_attr($def) . '" /></td>';
            echo '<td><input type="text" class="gm2-field-options" value="' . esc_attr($opt) . '" /></td>';
            echo '<td><input type="text" class="gm2-cond-field" value="' . esc_attr($cond['field'] ?? '') . '" /></td>';
            echo '<td><input type="text" class="gm2-cond-value" value="' . esc_attr($cond['value'] ?? '') . '" /></td>';
            echo '<td><button type="button" class="button gm2-remove-field">' . esc_html__( 'Remove', 'gm2-wordpress-suite' ) . '</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><button type="button" id="gm2-add-field" class="button">' . esc_html__( 'Add Field', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Fields', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public function display_taxonomy_page() {
        if (!$this->can_manage()) {
            wp_die(esc_html__( 'Permission denied', 'gm2-wordpress-suite' ));
        }

        $slug = sanitize_key($_GET['tax'] ?? '');
        $config = $this->get_config();
        $taxonomy = $config['taxonomies'][$slug] ?? null;
        if (!$taxonomy) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Invalid taxonomy.', 'gm2-wordpress-suite' ) . '</h1></div>';
            return;
        }

        if (isset($_POST['gm2_save_tax_args']) && check_admin_referer('gm2_save_tax_args')) {
            $label      = sanitize_text_field($_POST['tax_label'] ?? '');
            $post_types = array_filter(array_map('sanitize_key', explode(',', $_POST['tax_post_types'] ?? '')));
            $args       = json_decode(wp_unslash($_POST['tax_args'] ?? ''), true);
            if (!is_array($args)) {
                $args = [];
            }
            $config['taxonomies'][$slug] = [
                'label'      => $label ?: ($taxonomy['label'] ?? ucfirst($slug)),
                'post_types' => $post_types,
                'args'       => $args,
            ];
            update_option('gm2_custom_posts_config', $config);
            $taxonomy = $config['taxonomies'][$slug];
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Taxonomy saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        $args_json = wp_json_encode($taxonomy['args'] ?? [], JSON_PRETTY_PRINT);

        echo '<div class="wrap">';
        echo '<h1>' . sprintf(esc_html__( '%s Taxonomy', 'gm2-wordpress-suite' ), esc_html($taxonomy['label'] ?? $slug)) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_save_tax_args');
        echo '<input type="hidden" name="tax_slug" value="' . esc_attr($slug) . '" />';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_label" class="regular-text" value="' . esc_attr($taxonomy['label'] ?? '') . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Post Types (comma separated)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_post_types" class="regular-text" value="' . esc_attr(implode(',', $taxonomy['post_types'] ?? [])) . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Args (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="tax_args" class="large-text code" rows="5">' . esc_textarea($args_json) . '</textarea></label></p>';
        echo '<p><input type="submit" name="gm2_save_tax_args" class="button button-primary" value="' . esc_attr__( 'Save Taxonomy', 'gm2-wordpress-suite' ) . '" /></p>';
        echo '</form>';
        echo '</div>';
    }

    public function display_page() {
        if (!$this->can_manage()) {
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
                $link = admin_url('admin.php?page=gm2_cpt_fields&cpt=' . $slug);
                echo '<li><a href="' . esc_url($link) . '">' . esc_html($slug . ' - ' . ($pt['label'] ?? $slug)) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No custom post types defined.', 'gm2-wordpress-suite' ) . '</p>';
        }

        echo '<h2>' . esc_html__( 'Existing Taxonomies', 'gm2-wordpress-suite' ) . '</h2>';
        if (!empty($config['taxonomies'])) {
            echo '<ul>';
            foreach ($config['taxonomies'] as $slug => $tax) {
                $link = admin_url('admin.php?page=gm2_tax_args&tax=' . $slug);
                echo '<li><a href="' . esc_url($link) . '">' . esc_html($slug . ' - ' . ($tax['label'] ?? $slug)) . '</a></li>';
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
            if ($value === '' && isset($field['default'])) {
                $value = $field['default'];
            }
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
                $value = sanitize_text_field(wp_unslash($_POST[$key]));
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    public function enqueue_scripts($hook) {
        if (in_array($hook, [ 'post.php', 'post-new.php' ], true)) {
            $file = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-posts.js';
            wp_enqueue_script(
                'gm2-custom-posts',
                GM2_PLUGIN_URL . 'admin/js/gm2-custom-posts.js',
                ['jquery'],
                file_exists($file) ? filemtime($file) : GM2_VERSION,
                true
            );
            return;
        }

        if ($hook === 'gm2-custom-posts_page_gm2_cpt_fields') {
            $admin_js = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-posts-admin.js';
            wp_enqueue_script(
                'gm2-custom-posts-admin',
                GM2_PLUGIN_URL . 'admin/js/gm2-custom-posts-admin.js',
                [ 'jquery', 'jquery-ui-sortable' ],
                file_exists($admin_js) ? filemtime($admin_js) : GM2_VERSION,
                true
            );
            wp_localize_script('gm2-custom-posts-admin', 'gm2CPTFields', [
                'nonce' => wp_create_nonce('gm2_save_cpt_fields'),
                'ajax'  => admin_url('admin-ajax.php'),
            ]);

            $admin_css = GM2_PLUGIN_DIR . 'admin/css/gm2-custom-posts-admin.css';
            wp_enqueue_style(
                'gm2-custom-posts-admin',
                GM2_PLUGIN_URL . 'admin/css/gm2-custom-posts-admin.css',
                [],
                file_exists($admin_css) ? filemtime($admin_css) : GM2_VERSION
            );
        }
    }

    public function ajax_save_fields() {
        if (!$this->can_manage()) {
            wp_send_json_error('permission');
        }
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'gm2_save_cpt_fields')) {
            wp_send_json_error('nonce');
        }

        $slug = sanitize_key($_POST['slug'] ?? '');
        $fields = $_POST['fields'] ?? [];
        if (!$slug || !is_array($fields)) {
            wp_send_json_error('data');
        }

        $config = $this->get_config();
        if (empty($config['post_types'][$slug])) {
            wp_send_json_error('invalid');
        }

        $sanitized = [];
        foreach ($fields as $field) {
            $f_slug = sanitize_key($field['slug'] ?? '');
            if (!$f_slug) {
                continue;
            }
            $type = in_array($field['type'] ?? 'text', [ 'text', 'number', 'checkbox', 'select', 'radio' ], true) ? $field['type'] : 'text';
            $def  = sanitize_text_field($field['default'] ?? '');
            $options = [];
            $opt_str = $field['options'] ?? '';
            if (is_string($opt_str) && $opt_str !== '') {
                foreach (explode(',', $opt_str) as $pair) {
                    $parts = array_map('trim', explode(':', $pair));
                    if (!empty($parts[0])) {
                        $options[$parts[0]] = $parts[1] ?? $parts[0];
                    }
                }
            }
            $cond = [];
            if (!empty($field['conditional']['field']) && isset($field['conditional']['value'])) {
                $cond = [
                    'field' => sanitize_key($field['conditional']['field']),
                    'value' => sanitize_text_field($field['conditional']['value']),
                ];
            }
            $sanitized[$f_slug] = [
                'label'       => sanitize_text_field($field['label'] ?? ''),
                'type'        => $type,
                'default'     => $def,
                'options'     => $options,
                'conditional' => $cond,
            ];
        }

        $config['post_types'][$slug]['fields'] = $sanitized;
        update_option('gm2_custom_posts_config', $config);
        wp_send_json_success();
    }
}
