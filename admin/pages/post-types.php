<?php
        if ($this->is_locked()) {
            $this->display_locked_page(esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ));
            return;
        }
        if (!$this->can_manage()) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $config = $this->get_config();
        if (!empty($_GET['gm2_pt_saved'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Post type saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }
        if (!empty($_GET['gm2_tax_saved'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Taxonomy saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }
        if (isset($_GET['gm2_pt_deleted'])) {
            if ($_GET['gm2_pt_deleted'] === '1') {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Post type deleted.', 'gm2-wordpress-suite' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete post type.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
        }
        if (!empty($_GET['gm2_pt_converted'])) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Posts converted before deletion.', 'gm2-wordpress-suite' ) . '</p></div>';
        }
        if (isset($_GET['gm2_tax_deleted'])) {
            if ($_GET['gm2_tax_deleted'] === '1') {
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Taxonomy deleted.', 'gm2-wordpress-suite' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete taxonomy.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
        }
        if (!empty($_GET['gm2_tax_converted'])) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Terms converted before deletion.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        if (!empty($_GET['gm2_pt_too_many'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Too many post types selected. Delete in smaller batches or increase max_input_vars in php.ini.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

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
            $supports = [];
            if (!empty($_POST['pt_supports']) && is_array($_POST['pt_supports'])) {
                $supports = array_filter(array_map('sanitize_key', (array) $_POST['pt_supports']));
            }
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
            $cap_type_raw = sanitize_text_field($_POST['pt_capability_type'] ?? '');
            if ($cap_type_raw !== '') {
                $parts = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $cap_type_raw))));
                $cap_type = count($parts) > 1 ? array_slice($parts, 0, 2) : ($parts[0] ?? '');
                if ($cap_type !== '') {
                    $args_input[] = [ 'key' => 'capability_type', 'value' => $cap_type ];
                }
            }
            $caps = json_decode(wp_unslash($_POST['pt_capabilities'] ?? ''), true);
            if (is_array($caps)) {
                $args_input[] = [ 'key' => 'capabilities', 'value' => $caps ];
            }
            $template = json_decode(wp_unslash($_POST['pt_template'] ?? ''), true);
            if (is_array($template)) {
                $args_input[] = [ 'key' => 'template', 'value' => $template ];
            }
            $template_lock = sanitize_key($_POST['pt_template_lock'] ?? '');
            if (in_array($template_lock, [ 'all', 'insert' ], true)) {
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
        require_once __DIR__ . '/class-gm2-cpt-list-table.php';
        $table = new GM2_CPT_List_Table();
        $table->prepare_items();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="gm2_delete_post_type" />';
        $table->display();
        echo '</form>';

        echo '<h2>' . esc_html__( 'Existing Taxonomies', 'gm2-wordpress-suite' ) . '</h2>';
        if (!empty($config['taxonomies'])) {
            echo '<ul>';
            foreach ($config['taxonomies'] as $slug => $tax) {
                $link = admin_url('admin.php?page=gm2_tax_args&tax=' . $slug);
                echo '<li><a href="' . esc_url($link) . '">' . esc_html($slug . ' - ' . ($tax['label'] ?? $slug)) . '</a> <a href="#" class="gm2-edit-tax" data-slug="' . esc_attr($slug) . '">' . esc_html__( 'Edit', 'gm2-wordpress-suite' ) . '</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="gm2-delete-tax-form" style="display:inline;">';
                echo '<input type="hidden" name="action" value="gm2_delete_taxonomy" />';
                echo '<input type="hidden" name="slug" value="' . esc_attr($slug) . '" />';
                wp_nonce_field('gm2_delete_taxonomy_' . $slug);
                echo '<button type="submit" class="button-link delete-link">' . esc_html__( 'Delete', 'gm2-wordpress-suite' ) . '</button>';
                echo '</form></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No custom taxonomies defined.', 'gm2-wordpress-suite' ) . '</p>';
        }

        echo '<hr />';

        $preset_files = apply_filters('gm2/presets/list', []);
        echo '<h2>' . esc_html__( 'Add / Edit Post Type', 'gm2-wordpress-suite' );
        if ($preset_files) {
            echo ' <select id="gm2-preset-select"><option value="">' . esc_html__( 'Select Preset', 'gm2-wordpress-suite' ) . '</option>';
            foreach ($preset_files as $slug => $meta) {
                if (is_array($meta)) {
                    $label = $meta['label'] ?? ucwords(str_replace(['-', '_'], ' ', (string) $slug));
                } else {
                    $label = (string) $meta;
                }
                if ($label === '') {
                    $label = ucwords(str_replace(['-', '_'], ' ', (string) $slug));
                }
                echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
            }
            echo '</select> <button type="button" class="button" id="gm2-import-preset">' . esc_html__( 'Import Preset', 'gm2-wordpress-suite' ) . '</button>';
        }
        echo '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="gm2-post-type-form">';
        wp_nonce_field('gm2_edit_post_type');
        echo '<input type="hidden" name="action" value="gm2_edit_post_type" />';
        echo '<input type="hidden" name="pt_original" id="gm2-pt-original" />';
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
        echo '<fieldset><legend>' . esc_html__( 'Supports', 'gm2-wordpress-suite' ) . '</legend>';
        foreach ([ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ] as $sup) {
            echo '<label><input type="checkbox" name="pt_supports[]" value="' . esc_attr($sup) . '" /> ' . esc_html( ucwords(str_replace('-', ' ', $sup)) ) . '</label><br />';
        }
        echo '</fieldset>';
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
        echo '<select name="pt_template_lock" class="regular-text">';
        echo '<option value="">' . esc_html__( 'None', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="insert">' . esc_html__( 'Insert', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="all">' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label></p>';
        echo '</fieldset>';
        echo '<p><label>' . esc_html__( 'Fields (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="pt_fields" class="large-text code" rows="5" placeholder="{\n  \"field_key\": {\n    \"label\": \"Field Label\",\n    \"type\": \"text\"\n  }\n}"></textarea></label></p>';
        echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Post Type', 'gm2-wordpress-suite' ) . '" /></p>';
        echo '</form>';

        echo '<h2>' . esc_html__( 'Add / Edit Taxonomy', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="gm2-tax-form">';
        wp_nonce_field('gm2_edit_taxonomy');
        echo '<input type="hidden" name="action" value="gm2_edit_taxonomy" />';
        echo '<input type="hidden" name="tax_original" id="gm2-tax-original" />';
        echo '<p><label>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_slug" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Label', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_label" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Post Types (comma separated)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<input type="text" name="tax_post_types" class="regular-text" /></label></p>';
        echo '<p><label>' . esc_html__( 'Args (JSON)', 'gm2-wordpress-suite' ) . '<br />';
        echo '<textarea name="tax_args" class="large-text code" rows="5"></textarea></label></p>';
        echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Taxonomy', 'gm2-wordpress-suite' ) . '" /></p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Thumbnail Regeneration', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<p><button type="button" class="button" id="gm2-start-thumb-regeneration">' . esc_html__( 'Regenerate Thumbnails', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<div id="gm2-thumb-progress" style="display:none"><progress value="0" max="100"></progress> <span class="percent">0%</span></div>';

        echo '</div>';
