<?php
        if ($this->is_locked()) {
            $this->display_locked_page(esc_html__( 'Edit Taxonomy', 'gm2-wordpress-suite' ));
            return;
        }
        if (!$this->can_manage()) {
            wp_die(esc_html__( 'Permission denied', 'gm2-wordpress-suite' ));
        }

        $slug   = sanitize_key($_GET['tax'] ?? '');
        $config = $this->get_config();

        if (!$slug) {
            $taxonomies = $config['taxonomies'];
            if (!$taxonomies) {
                wp_safe_redirect(admin_url('admin.php?page=gm2-custom-posts'));
                exit;
            }
            echo '<div class="wrap"><h1>' . esc_html__( 'Select a Taxonomy', 'gm2-wordpress-suite' ) . '</h1><ul>';
            foreach ($taxonomies as $tax_slug => $tax) {
                $label = $tax['label'] ?? $tax_slug;
                $url   = admin_url('admin.php?page=gm2_tax_args&tax=' . $tax_slug);
                echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
            }
            echo '</ul></div>';
            return;
        }

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
