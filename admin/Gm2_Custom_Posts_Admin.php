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
        add_action('wp_ajax_gm2_save_tax_args', [ $this, 'ajax_save_tax_args' ]);
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

        echo '<div class="wrap">';
        echo '<h1>' . sprintf(esc_html__( '%s Fields', 'gm2-wordpress-suite' ), esc_html($post_type['label'] ?? $slug)) . '</h1>';
        echo '<table class="widefat fixed" id="gm2-fields-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Description', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        echo '<p><button type="button" id="gm2-add-field" class="button">' . esc_html__( 'Add New', 'gm2-wordpress-suite' ) . '</button></p>';

        echo '<h2>' . esc_html__( 'Registration Arguments', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<table class="widefat fixed" id="gm2-args-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Argument', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Value', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        echo '<p><button type="button" id="gm2-add-arg" class="button">' . esc_html__( 'Add New', 'gm2-wordpress-suite' ) . '</button></p>';

        // Hidden forms for fields and args
        echo '<div id="gm2-field-form" style="display:none;">';
        echo '<input type="hidden" id="gm2-field-index" />';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-label" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-slug" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Type', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-field-type"><option value="text">Text</option><option value="number">Number</option><option value="checkbox">Checkbox</option><option value="select">Dropdown</option><option value="radio">Radio</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-default" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Description', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-description" class="regular-text" /></label></p>';
        echo '<p><button type="button" class="button button-primary" id="gm2-field-save">' . esc_html__( 'Save', 'gm2-wordpress-suite' ) . '</button> <button type="button" class="button" id="gm2-field-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

        echo '<div id="gm2-arg-form" style="display:none;">';
        echo '<input type="hidden" id="gm2-arg-index" />';
        echo '<p><label>' . esc_html__( 'Key', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-arg-key" class="regular-text" /></label></p>';
        echo '<div id="gm2-arg-value-wrap"></div>';
        echo '<p><button type="button" class="button button-primary" id="gm2-arg-save">' . esc_html__( 'Save', 'gm2-wordpress-suite' ) . '</button> <button type="button" class="button" id="gm2-arg-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

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

        echo '<div class="wrap">';
        echo '<h1>' . sprintf(esc_html__( '%s Taxonomy', 'gm2-wordpress-suite' ), esc_html($taxonomy['label'] ?? $slug)) . '</h1>';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-tax-label" class="regular-text" value="' . esc_attr($taxonomy['label'] ?? '') . '" /></label></p>';
        echo '<p><label>' . esc_html__( 'Post Types (comma separated)', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-tax-post-types" class="regular-text" value="' . esc_attr(implode(',', $taxonomy['post_types'] ?? [])) . '" /></label></p>';

        echo '<h2>' . esc_html__( 'Registration Arguments', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<table class="widefat fixed" id="gm2-tax-args-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Argument', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Value', 'gm2-wordpress-suite' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        echo '<p><button type="button" id="gm2-add-tax-arg" class="button">' . esc_html__( 'Add New', 'gm2-wordpress-suite' ) . '</button></p>';

        echo '<div id="gm2-tax-arg-form" style="display:none;">';
        echo '<input type="hidden" id="gm2-tax-arg-index" />';
        echo '<p><label>' . esc_html__( 'Key', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-tax-arg-key" class="regular-text" /></label></p>';
        echo '<div id="gm2-tax-arg-value-wrap"></div>';
        echo '<p><button type="button" class="button button-primary" id="gm2-tax-arg-save">' . esc_html__( 'Save', 'gm2-wordpress-suite' ) . '</button> <button type="button" class="button" id="gm2-tax-arg-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

        echo '<p><button type="button" class="button button-primary" id="gm2-tax-save">' . esc_html__( 'Save Taxonomy', 'gm2-wordpress-suite' ) . '</button></p>';
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
            if (!empty($field['description'])) {
                echo '<br /><span class="description">' . esc_html($field['description']) . '</span>';
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
                [ 'jquery' ],
                file_exists($admin_js) ? filemtime($admin_js) : GM2_VERSION,
                true
            );

            $slug   = sanitize_key($_GET['cpt'] ?? '');
            $config = $this->get_config();
            $post   = $config['post_types'][$slug] ?? [];
            $fields = [];
            foreach ($post['fields'] ?? [] as $f_slug => $f) {
                $fields[] = [
                    'label'       => $f['label'] ?? '',
                    'slug'        => $f_slug,
                    'type'        => $f['type'] ?? 'text',
                    'default'     => $f['default'] ?? '',
                    'description' => $f['description'] ?? '',
                ];
            }
            $args = [];
            foreach ($post['args'] ?? [] as $a_key => $a_val) {
                $args[] = [
                    'key'   => $a_key,
                    'value' => $a_val,
                ];
            }
            wp_localize_script('gm2-custom-posts-admin', 'gm2CPTFields', [
                'nonce'  => wp_create_nonce('gm2_save_cpt_fields'),
                'ajax'   => admin_url('admin-ajax.php'),
                'slug'   => $slug,
                'fields' => $fields,
                'args'   => $args,
            ]);

            $admin_css = GM2_PLUGIN_DIR . 'admin/css/gm2-custom-posts-admin.css';
            wp_enqueue_style(
                'gm2-custom-posts-admin',
                GM2_PLUGIN_URL . 'admin/css/gm2-custom-posts-admin.css',
                [],
                file_exists($admin_css) ? filemtime($admin_css) : GM2_VERSION
            );
        }

        if ($hook === 'gm2-custom-posts_page_gm2_tax_args') {
            $tax_js = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-tax-admin.js';
            wp_enqueue_script(
                'gm2-custom-tax-admin',
                GM2_PLUGIN_URL . 'admin/js/gm2-custom-tax-admin.js',
                [ 'jquery' ],
                file_exists($tax_js) ? filemtime($tax_js) : GM2_VERSION,
                true
            );

            $slug   = sanitize_key($_GET['tax'] ?? '');
            $config = $this->get_config();
            $tax    = $config['taxonomies'][$slug] ?? [];
            $args   = [];
            foreach ($tax['args'] ?? [] as $a_key => $a_val) {
                $args[] = [ 'key' => $a_key, 'value' => $a_val ];
            }
            wp_localize_script('gm2-custom-tax-admin', 'gm2TaxArgs', [
                'nonce' => wp_create_nonce('gm2_save_tax_args'),
                'ajax'  => admin_url('admin-ajax.php'),
                'slug'  => $slug,
                'args'  => $args,
            ]);
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

        $slug   = sanitize_key($_POST['slug'] ?? '');
        $fields = $_POST['fields'] ?? [];
        $args   = $_POST['args'] ?? [];
        if (!$slug || !is_array($fields) || !is_array($args)) {
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
            $sanitized[$f_slug] = [
                'label'       => sanitize_text_field($field['label'] ?? ''),
                'type'        => $type,
                'default'     => $def,
                'description' => sanitize_text_field($field['description'] ?? ''),
            ];
        }

        $sanitized_args = [];
        foreach ($args as $arg) {
            $a_key = sanitize_key($arg['key'] ?? '');
            if (!$a_key) {
                continue;
            }
            $value = $arg['value'] ?? '';
            if (in_array($a_key, [ 'public', 'hierarchical' ], true)) {
                $sanitized_args[$a_key] = !empty($value);
            } elseif ($a_key === 'supports') {
                if (is_array($value)) {
                    $sanitized_args[$a_key] = array_filter(array_map('sanitize_key', $value));
                } else {
                    $sanitized_args[$a_key] = array_filter(array_map('sanitize_key', explode(',', (string) $value)));
                }
            } else {
                $sanitized_args[$a_key] = sanitize_text_field($value);
            }
        }

        $config['post_types'][$slug]['fields'] = $sanitized;
        $config['post_types'][$slug]['args']   = $sanitized_args;
        update_option('gm2_custom_posts_config', $config);
        wp_send_json_success([
            'fields' => $sanitized,
            'args'   => $sanitized_args,
        ]);
    }

    public function ajax_save_tax_args() {
        if (!$this->can_manage()) {
            wp_send_json_error('permission');
        }
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'gm2_save_tax_args')) {
            wp_send_json_error('nonce');
        }
        $slug = sanitize_key($_POST['slug'] ?? '');
        $args = $_POST['args'] ?? [];
        $label = sanitize_text_field($_POST['label'] ?? '');
        $post_types = array_filter(array_map('sanitize_key', explode(',', $_POST['post_types'] ?? '')));
        if (!$slug || !is_array($args)) {
            wp_send_json_error('data');
        }
        $config = $this->get_config();
        if (empty($config['taxonomies'][$slug])) {
            wp_send_json_error('invalid');
        }
        $sanitized_args = [];
        foreach ($args as $arg) {
            $a_key = sanitize_key($arg['key'] ?? '');
            if (!$a_key) {
                continue;
            }
            $value = $arg['value'] ?? '';
            if (in_array($a_key, [ 'public', 'hierarchical' ], true)) {
                $sanitized_args[$a_key] = !empty($value);
            } else {
                $sanitized_args[$a_key] = sanitize_text_field($value);
            }
        }
        $config['taxonomies'][$slug]['args']       = $sanitized_args;
        if ($label) {
            $config['taxonomies'][$slug]['label'] = $label;
        }
        $config['taxonomies'][$slug]['post_types'] = $post_types;
        update_option('gm2_custom_posts_config', $config);
        wp_send_json_success([
            'args' => $sanitized_args,
        ]);
    }
}
