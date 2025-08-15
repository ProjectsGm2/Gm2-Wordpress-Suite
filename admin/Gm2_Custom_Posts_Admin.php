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
        echo '<p><label>' . esc_html__( 'Order', 'gm2-wordpress-suite' ) . '<br /><input type="number" id="gm2-field-order" class="small-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Container', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-field-container"><option value="">' . esc_html__( 'None', 'gm2-wordpress-suite' ) . '</option><option value="tab">' . esc_html__( 'Tab', 'gm2-wordpress-suite' ) . '</option><option value="accordion">' . esc_html__( 'Accordion', 'gm2-wordpress-suite' ) . '</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Instructions', 'gm2-wordpress-suite' ) . '<br /><textarea id="gm2-field-instructions" class="large-text" rows="3"></textarea></label></p>';
        echo '<p><label>' . esc_html__( 'Placeholder', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-placeholder" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Admin CSS Classes', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-field-class" class="regular-text" /></label></p>';
        echo '<h3>' . esc_html__( 'Location Rules', 'gm2-wordpress-suite' ) . '</h3>';
        echo '<div id="gm2-field-location" class="gm2-conditions"><div class="gm2-condition-groups"></div><p><button type="button" class="button gm2-add-condition-group">' . esc_html__( 'Add Location Group', 'gm2-wordpress-suite' ) . '</button></p></div>';
        echo '<h3>' . esc_html__( 'Display Conditions', 'gm2-wordpress-suite' ) . '</h3>';
        echo '<div id="gm2-field-conditions" class="gm2-conditions"><div class="gm2-condition-groups"></div><p><button type="button" class="button gm2-add-condition-group">' . esc_html__( 'Add Condition Group', 'gm2-wordpress-suite' ) . '</button></p></div>';
        echo '<p><button type="button" class="button button-primary" id="gm2-field-save">' . esc_html__( 'Save', 'gm2-wordpress-suite' ) . '</button> <button type="button" class="button" id="gm2-field-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

        echo '<div id="gm2-arg-form" style="display:none;">';
        echo '<input type="hidden" id="gm2-arg-index" />';
        echo '<p><label>' . esc_html__( 'Key', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-arg-key" class="regular-text" /></label></p>';
        echo '<div id="gm2-arg-value-wrap"></div>';
        echo '<h3>' . esc_html__( 'Display Conditions', 'gm2-wordpress-suite' ) . '</h3>';
        echo '<div id="gm2-arg-conditions" class="gm2-conditions"><div class="gm2-condition-groups"></div><p><button type="button" class="button gm2-add-condition-group">' . esc_html__( 'Add Condition Group', 'gm2-wordpress-suite' ) . '</button></p></div>';
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

        $hier = !empty($taxonomy['args']['hierarchical']['value'] ?? $taxonomy['args']['hierarchical'] ?? false);
        echo '<p><label><input type="checkbox" id="gm2-tax-hierarchical" value="1"' . checked($hier, true, false) . ' /> ' . esc_html__( 'Hierarchical', 'gm2-wordpress-suite' ) . '</label></p>';

        echo '<fieldset><legend>' . esc_html__( 'Visibility', 'gm2-wordpress-suite' ) . '</legend>';
        foreach ([ 'public', 'show_ui', 'show_in_nav_menus', 'show_admin_column', 'show_tagcloud', 'show_in_quick_edit' ] as $vis) {
            $checked = !empty($taxonomy['args'][$vis]['value'] ?? $taxonomy['args'][$vis] ?? false);
            echo '<label><input type="checkbox" id="gm2-tax-' . esc_attr($vis) . '" value="1"' . checked($checked, true, false) . ' /> ' . esc_html( ucwords(str_replace('_', ' ', $vis)) ) . '</label><br />';
        }
        echo '</fieldset>';

        echo '<fieldset><legend>' . esc_html__( 'REST API', 'gm2-wordpress-suite' ) . '</legend>';
        $rest_checked = !empty($taxonomy['args']['show_in_rest']['value'] ?? $taxonomy['args']['show_in_rest'] ?? false);
        echo '<p><label><input type="checkbox" id="gm2-tax-show-rest" value="1"' . checked($rest_checked, true, false) . ' /> ' . esc_html__( 'Show in REST', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '</fieldset>';

        $rewrite_slug = '';
        if (!empty($taxonomy['args']['rewrite']['value']['slug'])) {
            $rewrite_slug = $taxonomy['args']['rewrite']['value']['slug'];
        } elseif (!empty($taxonomy['args']['rewrite']['slug'])) {
            $rewrite_slug = $taxonomy['args']['rewrite']['slug'];
        }
        echo '<fieldset><legend>' . esc_html__( 'Rewrite', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-tax-rewrite-slug" class="regular-text" value="' . esc_attr($rewrite_slug) . '" /></label></p>';
        echo '</fieldset>';

        $orderby = $taxonomy['args']['orderby']['value'] ?? $taxonomy['args']['orderby'] ?? '';
        $order   = $taxonomy['args']['order']['value'] ?? $taxonomy['args']['order'] ?? '';
        echo '<fieldset><legend>' . esc_html__( 'Ordering', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Order By', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-tax-orderby"><option value="">' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</option><option value="name"' . selected($orderby, 'name', false) . '>Name</option><option value="slug"' . selected($orderby, 'slug', false) . '>Slug</option><option value="term_order"' . selected($orderby, 'term_order', false) . '>Term Order</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Order', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-tax-order"><option value="">' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</option><option value="ASC"' . selected(strtoupper($order), 'ASC', false) . '>ASC</option><option value="DESC"' . selected(strtoupper($order), 'DESC', false) . '>DESC</option></select></label></p>';
        echo '</fieldset>';

        $default_terms = !empty($taxonomy['default_terms']) ? wp_json_encode($taxonomy['default_terms']) : '';
        echo '<p><label>' . esc_html__( 'Default Terms (JSON)', 'gm2-wordpress-suite' ) . '<br /><textarea id="gm2-tax-default-terms" class="large-text code" rows="5">' . esc_textarea($default_terms) . '</textarea></label></p>';

        $term_fields = !empty($taxonomy['term_fields']) ? wp_json_encode($taxonomy['term_fields']) : '';
        echo '<p><label>' . esc_html__( 'Term Meta Fields (JSON)', 'gm2-wordpress-suite' ) . '<br /><textarea id="gm2-tax-term-fields" class="large-text code" rows="5">' . esc_textarea($term_fields) . '</textarea></label></p>';

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
        echo '<h3>' . esc_html__( 'Display Conditions', 'gm2-wordpress-suite' ) . '</h3>';
        echo '<div id="gm2-tax-conditions" class="gm2-conditions"><div class="gm2-condition-groups"></div><p><button type="button" class="button gm2-add-condition-group">' . esc_html__( 'Add Condition Group', 'gm2-wordpress-suite' ) . '</button></p></div>';
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
            $fields = $this->sanitize_fields_array($fields);

            $args_input = [];
            $labels = json_decode(wp_unslash($_POST['pt_labels'] ?? ''), true);
            if (is_array($labels)) {
                $args_input[] = [ 'key' => 'labels', 'value' => $labels ];
            }
            $menu_icon = sanitize_text_field($_POST['pt_menu_icon'] ?? '');
            if ($menu_icon !== '') {
                $args_input[] = [ 'key' => 'menu_icon', 'value' => $menu_icon ];
            }
            $menu_position = isset($_POST['pt_menu_position']) ? sanitize_text_field($_POST['pt_menu_position']) : '';
            if ($menu_position !== '') {
                $args_input[] = [ 'key' => 'menu_position', 'value' => $menu_position ];
            }
            $supports = array_filter(array_map('sanitize_key', explode(',', $_POST['pt_supports'] ?? '')));
            if ($supports) {
                $args_input[] = [ 'key' => 'supports', 'value' => $supports ];
            }
            if (!empty($_POST['pt_hierarchical'])) {
                $args_input[] = [ 'key' => 'hierarchical', 'value' => true ];
            }
            foreach ([ 'public', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_in_admin_bar', 'exclude_from_search', 'has_archive' ] as $vis_key) {
                if (!empty($_POST['pt_' . $vis_key])) {
                    $args_input[] = [ 'key' => $vis_key, 'value' => true ];
                }
            }
            if (!empty($_POST['pt_show_in_rest'])) {
                $args_input[] = [ 'key' => 'show_in_rest', 'value' => true ];
            }
            $rest_base = sanitize_key($_POST['pt_rest_base'] ?? '');
            if ($rest_base !== '') {
                $args_input[] = [ 'key' => 'rest_base', 'value' => $rest_base ];
            }
            $rest_controller = sanitize_text_field($_POST['pt_rest_controller_class'] ?? '');
            if ($rest_controller !== '') {
                $args_input[] = [ 'key' => 'rest_controller_class', 'value' => $rest_controller ];
            }
            $rewrite = [];
            $rewrite_slug = sanitize_title_with_dashes($_POST['pt_rewrite_slug'] ?? '');
            if ($rewrite_slug !== '') {
                $rewrite['slug'] = $rewrite_slug;
            }
            foreach ( [ 'with_front', 'hierarchical', 'feeds', 'pages' ] as $r_key ) {
                if (!empty($_POST['pt_rewrite_' . $r_key])) {
                    $rewrite[$r_key] = true;
                }
            }
            if (!empty($rewrite)) {
                $args_input[] = [ 'key' => 'rewrite', 'value' => $rewrite ];
            }
            if (!empty($_POST['pt_map_meta_cap'])) {
                $args_input[] = [ 'key' => 'map_meta_cap', 'value' => true ];
            }
            $cap_type = sanitize_text_field($_POST['pt_capability_type'] ?? '');
            if ($cap_type !== '') {
                $args_input[] = [ 'key' => 'capability_type', 'value' => $cap_type ];
            }
            $caps = json_decode(wp_unslash($_POST['pt_capabilities'] ?? ''), true);
            if (is_array($caps)) {
                $args_input[] = [ 'key' => 'capabilities', 'value' => $caps ];
            }
            $template = json_decode(wp_unslash($_POST['pt_template'] ?? ''), true);
            if (is_array($template)) {
                $args_input[] = [ 'key' => 'template', 'value' => $template ];
            }
            $template_lock = sanitize_text_field($_POST['pt_template_lock'] ?? '');
            if ($template_lock !== '') {
                $args_input[] = [ 'key' => 'template_lock', 'value' => $template_lock ];
            }

            $args = $this->sanitize_args_array($args_input);

            if ($slug) {
                $config['post_types'][$slug] = [
                    'label'  => $label ?: ucfirst($slug),
                    'fields' => $fields,
                    'args'   => $args,
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
            $args = $this->sanitize_args_array($args);
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
        echo '<p><label>' . esc_html__( 'Labels (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="pt_labels" class="large-text code" rows="5"></textarea></label></p>';
        echo '<p><label>' . esc_html__( 'Menu Icon', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_menu_icon" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Menu Position', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="number" name="pt_menu_position" class="small-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Supports (comma separated)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_supports" class="regular-text" /></label></p>';
        echo '<p><label><input type="checkbox" name="pt_hierarchical" value="1" /> ' . esc_html__( 'Hierarchical', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<fieldset><legend>' . esc_html__( 'Visibility', 'gm2-wordpress-suite' ) . '</legend>';
        foreach ([ 'public', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_in_admin_bar', 'exclude_from_search', 'has_archive' ] as $vis) {
            echo '<label><input type="checkbox" name="pt_' . esc_attr($vis) . '" value="1" /> ' . esc_html( ucwords(str_replace('_', ' ', $vis)) ) . '</label><br />';
        }
        echo '</fieldset>';
        echo '<fieldset><legend>' . esc_html__( 'REST API', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label><input type="checkbox" name="pt_show_in_rest" value="1" /> ' . esc_html__( 'Show in REST', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label>' . esc_html__( 'REST Base', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_rest_base" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'REST Controller Class', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_rest_controller_class" class="regular-text" /></label></p>';
        echo '</fieldset>';
        echo '<fieldset><legend>' . esc_html__( 'Rewrite', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_rewrite_slug" class="regular-text" /></label></p>';
        foreach ([ 'with_front', 'hierarchical', 'feeds', 'pages' ] as $rw ) {
            echo '<label><input type="checkbox" name="pt_rewrite_' . esc_attr($rw) . '" value="1" /> ' . esc_html( ucwords(str_replace('_', ' ', $rw)) ) . '</label><br />';
        }
        echo '</fieldset>';
        echo '<fieldset><legend>' . esc_html__( 'Capabilities', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label><input type="checkbox" name="pt_map_meta_cap" value="1" /> ' . esc_html__( 'Map Meta Cap', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label>' . esc_html__( 'Capability Type', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_capability_type" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Capabilities (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="pt_capabilities" class="large-text code" rows="5"></textarea></label></p>';
        echo '</fieldset>';
        echo '<fieldset><legend>' . esc_html__( 'Templates', 'gm2-wordpress-suite' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Template (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="pt_template" class="large-text code" rows="5"></textarea></label></p>';
        echo '<p><label>' . esc_html__( 'Template Lock', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="pt_template_lock" class="regular-text" /></label></p>';
        echo '</fieldset>';
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

    private function sanitize_fields_array($fields) {
        $out = [];
        if (!is_array($fields)) {
            return $out;
        }
        foreach ($fields as $key => $field) {
            $slug = sanitize_key(is_array($field) && isset($field['slug']) ? $field['slug'] : $key);
            if (!$slug) {
                continue;
            }
            $type  = in_array($field['type'] ?? 'text', [ 'text', 'number', 'checkbox', 'select', 'radio' ], true) ? $field['type'] : 'text';
            $def   = sanitize_text_field($field['default'] ?? '');
            $order = isset($field['order']) ? (int) $field['order'] : 0;
            $container = in_array($field['container'] ?? '', [ 'tab', 'accordion' ], true) ? $field['container'] : '';
            $sanitized = [
                'label'        => sanitize_text_field($field['label'] ?? ''),
                'type'         => $type,
                'default'      => $def,
                'description'  => sanitize_text_field($field['description'] ?? ''),
                'order'        => $order,
                'container'    => $container,
                'instructions' => sanitize_textarea_field($field['instructions'] ?? ''),
                'placeholder'  => sanitize_text_field($field['placeholder'] ?? ''),
                'class'        => sanitize_html_class($field['class'] ?? ''),
                'location'     => $this->sanitize_location($field['location'] ?? []),
                'conditions'   => $this->sanitize_conditions($field['conditions'] ?? []),
            ];
            if (!empty($field['options']) && is_array($field['options'])) {
                $opts = [];
                foreach ($field['options'] as $opt_val => $opt_label) {
                    $opts[sanitize_text_field($opt_val)] = sanitize_text_field($opt_label);
                }
                $sanitized['options'] = $opts;
            }
            $out[$slug] = $sanitized;
        }
        return $out;
    }

    private function sanitize_args_array($args) {
        $sanitized_args = [];
        if (!is_array($args)) {
            return $sanitized_args;
        }
        $bool_keys = [ 'public', 'hierarchical', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_in_admin_bar', 'exclude_from_search', 'has_archive', 'show_in_rest', 'map_meta_cap', 'show_admin_column', 'show_tagcloud', 'show_in_quick_edit' ];
        foreach ($args as $arg) {
            $a_key = sanitize_key($arg['key'] ?? '');
            if (!$a_key) {
                continue;
            }
            $value = $arg['value'] ?? '';
            $conditions = $this->sanitize_conditions($arg['conditions'] ?? []);
            if (in_array($a_key, $bool_keys, true)) {
                $val = !empty($value);
            } elseif ($a_key === 'supports') {
                if (is_array($value)) {
                    $val = array_filter(array_map('sanitize_key', $value));
                } else {
                    $val = array_filter(array_map('sanitize_key', explode(',', (string) $value)));
                }
            } elseif ($a_key === 'labels') {
                if (is_string($value)) {
                    $value = json_decode(wp_unslash($value), true);
                }
                $val = [];
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $val[sanitize_key($k)] = sanitize_text_field($v);
                    }
                }
            } elseif ($a_key === 'rewrite') {
                if (is_string($value)) {
                    $value = json_decode(wp_unslash($value), true);
                }
                $val = [];
                if (is_array($value)) {
                    $val['slug']         = sanitize_title_with_dashes($value['slug'] ?? '');
                    $val['with_front']   = !empty($value['with_front']);
                    $val['hierarchical'] = !empty($value['hierarchical']);
                    $val['feeds']        = !empty($value['feeds']);
                    $val['pages']        = !empty($value['pages']);
                }
            } elseif ($a_key === 'capabilities') {
                if (is_string($value)) {
                    $value = json_decode(wp_unslash($value), true);
                }
                $val = [];
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $val[sanitize_key($k)] = sanitize_text_field($v);
                    }
                }
            } elseif ($a_key === 'template') {
                if (is_string($value)) {
                    $value = json_decode(wp_unslash($value), true);
                }
                $val = is_array($value) ? $value : [];
            } elseif ($a_key === 'menu_position') {
                $val = is_numeric($value) ? (int) $value : 0;
            } elseif ($a_key === 'orderby') {
                $val = sanitize_key($value);
            } elseif ($a_key === 'order') {
                $val = in_array(strtoupper($value), [ 'ASC', 'DESC' ], true) ? strtoupper($value) : 'ASC';
            } else {
                $val = sanitize_text_field($value);
            }
            $sanitized_args[$a_key] = [ 'value' => $val, 'conditions' => $conditions ];
        }
        return $sanitized_args;
    }

    private function sanitize_terms_array($terms) {
        $out = [];
        if (!is_array($terms)) {
            return $out;
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                continue;
            }
            $slug = sanitize_key($term['slug'] ?? '');
            $name = sanitize_text_field($term['name'] ?? '');
            if ($slug === '' && $name === '') {
                continue;
            }
            if ($slug === '') {
                $slug = sanitize_title($name);
            }
            $item = [
                'slug'  => $slug,
                'name'  => $name ?: $slug,
                'order' => isset($term['order']) ? (int) $term['order'] : 0,
                'color' => sanitize_hex_color($term['color'] ?? ''),
                'icon'  => sanitize_text_field($term['icon'] ?? ''),
                'meta'  => [],
            ];
            if (!empty($term['meta']) && is_array($term['meta'])) {
                foreach ($term['meta'] as $k => $v) {
                    $item['meta'][sanitize_key($k)] = sanitize_text_field($v);
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    private function sanitize_location($groups) {
        $out = [];
        if (!is_array($groups)) {
            return $out;
        }
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $g_rel = in_array($group['relation'] ?? 'AND', [ 'AND', 'OR' ], true) ? $group['relation'] : 'AND';
            $rules = [];
            if (!empty($group['rules']) && is_array($group['rules'])) {
                foreach ($group['rules'] as $rule) {
                    if (!is_array($rule)) {
                        continue;
                    }
                    $param = sanitize_key($rule['param'] ?? '');
                    $op    = in_array($rule['operator'] ?? '==', [ '==', '!=' ], true) ? $rule['operator'] : '==';
                    $val   = sanitize_text_field($rule['value'] ?? '');
                    if ($param === '') {
                        continue;
                    }
                    $rules[] = [ 'param' => $param, 'operator' => $op, 'value' => $val ];
                }
            }
            if ($rules) {
                $out[] = [ 'relation' => $g_rel, 'rules' => $rules ];
            }
        }
        return $out;
    }

    private function sanitize_conditions($groups) {
        $out = [];
        if (!is_array($groups)) {
            return $out;
        }
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $g_rel = in_array($group['relation'] ?? 'AND', [ 'AND', 'OR' ], true) ? $group['relation'] : 'AND';
            $conds = [];
            if (!empty($group['conditions']) && is_array($group['conditions'])) {
                foreach ($group['conditions'] as $cond) {
                    if (!is_array($cond)) {
                        continue;
                    }
                    $c_rel = in_array($cond['relation'] ?? 'AND', [ 'AND', 'OR' ], true) ? $cond['relation'] : 'AND';
                    $target = sanitize_key($cond['target'] ?? '');
                    $op = in_array($cond['operator'] ?? '=', [ '=', '!=', '>', '<', 'contains' ], true) ? $cond['operator'] : '=';
                    $val = sanitize_text_field($cond['value'] ?? '');
                    if ($target === '') {
                        continue;
                    }
                    $conds[] = [
                        'relation' => $c_rel,
                        'target'   => $target,
                        'operator' => $op,
                        'value'    => $val,
                    ];
                }
            }
            if ($conds) {
                $out[] = [ 'relation' => $g_rel, 'conditions' => $conds ];
            }
        }
        return $out;
    }

    public function render_meta_box($post, $fields, $slug) {
        wp_nonce_field('gm2_save_custom_fields', 'gm2_custom_fields_nonce');
        uasort($fields, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        foreach ($fields as $key => $field) {
            $type   = $field['type'] ?? 'text';
            $label  = $field['label'] ?? $key;
            $value  = get_post_meta($post->ID, $key, true);
            if ($value === '' && isset($field['default'])) {
                $value = $field['default'];
            }
            $cond    = $field['conditional'] ?? [];
            $conds   = $field['conditions'] ?? [];
            $options = $field['options'] ?? [];
            $visible = gm2_evaluate_conditions($field, $post->ID);
            $classes = 'gm2-field';
            if (!empty($field['class'])) {
                $classes .= ' ' . esc_attr($field['class']);
            }
            if (!empty($field['container'])) {
                $classes .= ' gm2-container-' . esc_attr($field['container']);
            }
            echo '<div class="' . $classes . '"';
            if (!empty($conds)) {
                echo ' data-conditions="' . esc_attr(wp_json_encode($conds)) . '"';
            } elseif (!empty($cond['field']) && isset($cond['value'])) {
                echo ' data-conditional-field="' . esc_attr($cond['field']) . '" data-conditional-value="' . esc_attr($cond['value']) . '"';
            }
            if (!$visible) {
                echo ' style="display:none;"';
            }
            echo '>';
            echo '<p><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><br />';
            switch ($type) {
                case 'number':
                    echo '<input type="number" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '" class="regular-text" />';
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
                    echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '" class="regular-text" />';
                    break;
            }
            if (!empty($field['description'])) {
                echo '<br /><span class="description">' . esc_html($field['description']) . '</span>';
            }
            if (!empty($field['instructions'])) {
                echo '<br /><span class="description">' . esc_html($field['instructions']) . '</span>';
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
            if (!gm2_evaluate_conditions($field, $post_id)) {
                delete_post_meta($post_id, $key);
                continue;
            }
            $type    = $field['type'] ?? 'text';
            $options = $field['options'] ?? [];
            if ($type === 'checkbox') {
                $value = isset($_POST[$key]) ? '1' : '0';
                update_post_meta($post_id, $key, $value);
            } elseif ($type === 'number') {
                if (isset($_POST[$key])) {
                    $value = sanitize_text_field(wp_unslash($_POST[$key]));
                    $value = ($value === '') ? '' : (string) (0 + $value);
                    update_post_meta($post_id, $key, $value);
                } else {
                    delete_post_meta($post_id, $key);
                }
            } elseif (in_array($type, [ 'select', 'radio' ], true)) {
                if (isset($_POST[$key])) {
                    $value = sanitize_text_field(wp_unslash($_POST[$key]));
                    if (empty($options) || array_key_exists($value, $options)) {
                        update_post_meta($post_id, $key, $value);
                    } else {
                        delete_post_meta($post_id, $key);
                    }
                } else {
                    delete_post_meta($post_id, $key);
                }
            } else {
                if (isset($_POST[$key])) {
                    $value = sanitize_text_field(wp_unslash($_POST[$key]));
                    update_post_meta($post_id, $key, $value);
                } else {
                    delete_post_meta($post_id, $key);
                }
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
            $cond_js = GM2_PLUGIN_DIR . 'admin/js/conditions.js';
            wp_enqueue_script(
                'gm2-conditions',
                GM2_PLUGIN_URL . 'admin/js/conditions.js',
                [ 'jquery' ],
                file_exists($cond_js) ? filemtime($cond_js) : GM2_VERSION,
                true
            );
            $admin_js = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-posts-admin.js';
            wp_enqueue_script(
                'gm2-custom-posts-admin',
                GM2_PLUGIN_URL . 'admin/js/gm2-custom-posts-admin.js',
                [ 'jquery', 'gm2-conditions' ],
                file_exists($admin_js) ? filemtime($admin_js) : GM2_VERSION,
                true
            );

            $slug   = sanitize_key($_GET['cpt'] ?? '');
            $config = $this->get_config();
            $post   = $config['post_types'][$slug] ?? [];
            $fields = [];
            foreach ($post['fields'] ?? [] as $f_slug => $f) {
                $fields[] = [
                    'label'        => $f['label'] ?? '',
                    'slug'         => $f_slug,
                    'type'         => $f['type'] ?? 'text',
                    'default'      => $f['default'] ?? '',
                    'description'  => $f['description'] ?? '',
                    'order'        => $f['order'] ?? 0,
                    'container'    => $f['container'] ?? '',
                    'instructions' => $f['instructions'] ?? '',
                    'placeholder'  => $f['placeholder'] ?? '',
                    'class'        => $f['class'] ?? '',
                    'location'     => $f['location'] ?? [],
                    'conditions'   => $f['conditions'] ?? [],
                ];
            }
            $args = [];
            foreach ($post['args'] ?? [] as $a_key => $a_val) {
                $args[] = [
                    'key'   => $a_key,
                    'value' => is_array($a_val) && array_key_exists('value', $a_val) ? $a_val['value'] : $a_val,
                    'conditions' => is_array($a_val) && isset($a_val['conditions']) ? $a_val['conditions'] : [],
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
            $cond_js = GM2_PLUGIN_DIR . 'admin/js/conditions.js';
            wp_enqueue_script(
                'gm2-conditions',
                GM2_PLUGIN_URL . 'admin/js/conditions.js',
                [ 'jquery' ],
                file_exists($cond_js) ? filemtime($cond_js) : GM2_VERSION,
                true
            );
            $tax_js = GM2_PLUGIN_DIR . 'admin/js/gm2-custom-tax-admin.js';
            wp_enqueue_script(
                'gm2-custom-tax-admin',
                GM2_PLUGIN_URL . 'admin/js/gm2-custom-tax-admin.js',
                [ 'jquery', 'gm2-conditions' ],
                file_exists($tax_js) ? filemtime($tax_js) : GM2_VERSION,
                true
            );

            $slug   = sanitize_key($_GET['tax'] ?? '');
            $config = $this->get_config();
            $tax    = $config['taxonomies'][$slug] ?? [];
            $args   = [];
            foreach ($tax['args'] ?? [] as $a_key => $a_val) {
                $args[] = [ 'key' => $a_key, 'value' => is_array($a_val) && array_key_exists('value', $a_val) ? $a_val['value'] : $a_val, 'conditions' => is_array($a_val) && isset($a_val['conditions']) ? $a_val['conditions'] : [] ];
            }
            $fields = [];
            foreach ($tax['post_types'] ?? [] as $pt_slug) {
                foreach ($config['post_types'][$pt_slug]['fields'] ?? [] as $f_slug => $f) {
                    $fields[] = [ 'slug' => $f_slug ];
                }
            }
            wp_localize_script('gm2-custom-tax-admin', 'gm2TaxArgs', [
                'nonce'  => wp_create_nonce('gm2_save_tax_args'),
                'ajax'   => admin_url('admin-ajax.php'),
                'slug'   => $slug,
                'args'   => $args,
                'fields' => $fields,
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

        $sanitized      = $this->sanitize_fields_array($fields);
        $sanitized_args = $this->sanitize_args_array($args);

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
        $hierarchical = !empty($_POST['hierarchical']);
        $visibility_keys = [ 'public', 'show_ui', 'show_in_nav_menus', 'show_admin_column', 'show_tagcloud', 'show_in_quick_edit' ];
        $visibilities = [];
        foreach ($visibility_keys as $vk) {
            $visibilities[$vk] = !empty($_POST[$vk]);
        }
        $show_rest = !empty($_POST['show_in_rest']);
        $rewrite_slug = sanitize_title_with_dashes($_POST['rewrite_slug'] ?? '');
        $default_terms = json_decode(wp_unslash($_POST['default_terms'] ?? ''), true);
        if (!is_array($default_terms)) {
            $default_terms = [];
        }
        $term_fields = json_decode(wp_unslash($_POST['term_fields'] ?? ''), true);
        if (!is_array($term_fields)) {
            $term_fields = [];
        }
        $orderby = sanitize_key($_POST['orderby'] ?? '');
        $order   = sanitize_text_field($_POST['order'] ?? '');
        if (!$slug || !is_array($args)) {
            wp_send_json_error('data');
        }
        $config = $this->get_config();
        if (empty($config['taxonomies'][$slug])) {
            wp_send_json_error('invalid');
        }
        $args_input = $args;
        $args_input[] = [ 'key' => 'hierarchical', 'value' => $hierarchical ];
        foreach ($visibilities as $vk => $val) {
            $args_input[] = [ 'key' => $vk, 'value' => $val ];
        }
        $args_input[] = [ 'key' => 'show_in_rest', 'value' => $show_rest ];
        if ($rewrite_slug !== '') {
            $args_input[] = [ 'key' => 'rewrite', 'value' => [ 'slug' => $rewrite_slug ] ];
        }
        if ($orderby !== '') {
            $args_input[] = [ 'key' => 'orderby', 'value' => $orderby ];
        }
        if ($order !== '') {
            $args_input[] = [ 'key' => 'order', 'value' => $order ];
        }
        $sanitized_args = $this->sanitize_args_array($args_input);
        $config['taxonomies'][$slug]['args']       = $sanitized_args;
        if ($label) {
            $config['taxonomies'][$slug]['label'] = $label;
        }
        $config['taxonomies'][$slug]['post_types']   = $post_types;
        $config['taxonomies'][$slug]['default_terms'] = $this->sanitize_terms_array($default_terms);
        $config['taxonomies'][$slug]['term_fields']   = $this->sanitize_fields_array($term_fields);
        update_option('gm2_custom_posts_config', $config);
        wp_send_json_success([
            'args' => $sanitized_args,
        ]);
    }
}
