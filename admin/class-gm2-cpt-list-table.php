<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GM2_CPT_List_Table extends \WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'gm2-cpt',
            'plural'   => 'gm2-cpts',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'     => '<input type="checkbox" />',
            'slug'   => __('Slug', 'gm2-wordpress-suite'),
            'label'  => __('Label', 'gm2-wordpress-suite'),
            'count'  => __('Posts', 'gm2-wordpress-suite'),
            'actions'=> __('Actions', 'gm2-wordpress-suite'),
        ];
    }

    public function column_cb($item) {
        $slug = esc_attr($item['slug']);
        return '<input type="checkbox" name="slug[]" value="' . $slug . '" />';
    }

    public function column_default($item, $column_name) {
        return esc_html($item[$column_name] ?? '');
    }

    public function column_actions($item) {
        $slug = esc_attr($item['slug']);
        $edit = '<a href="#" class="gm2-edit-pt" data-slug="' . $slug . '">' . esc_html__( 'Edit', 'gm2-wordpress-suite' ) . '</a>';
        $delete  = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="gm2-delete-pt-form" style="display:inline;">';
        $delete .= '<input type="hidden" name="action" value="gm2_delete_post_type" />';
        $delete .= '<input type="hidden" name="slug" value="' . $slug . '" />';
        $delete .= wp_nonce_field('gm2_delete_post_type_' . $slug, '_wpnonce', true, false);
        $delete .= '<button type="submit" class="button-link delete-link">' . esc_html__( 'Delete', 'gm2-wordpress-suite' ) . '</button>';
        $delete .= '</form>';
        return $edit . ' | ' . $delete;
    }

    protected function bulk_actions($which = '') {
        if (empty($this->_actions)) {
            $this->_actions = $this->get_bulk_actions();
        }
        if (empty($this->_actions)) {
            return;
        }
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="gm2_bulk_action">';
        echo '<option value="-1" selected="selected">' . esc_html__( 'Bulk actions', 'gm2-wordpress-suite' ) . '</option>';
        foreach ($this->_actions as $name => $title) {
            echo '<option value="' . esc_attr($name) . '">' . esc_html($title) . '</option>';
        }
        echo '</select>';
        submit_button(__('Apply', 'gm2-wordpress-suite'), 'action', false, false, [ 'id' => "doaction$which" ]);
        echo '</div>';
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'gm2-wordpress-suite'),
        ];
    }

    public function prepare_items() {
        $config = get_option('gm2_custom_posts_config', []);
        $post_types = $config['post_types'] ?? [];
        $items = [];
        foreach ($post_types as $slug => $pt) {
            $label = $pt['label'] ?? $slug;
            $count = 0;
            if (post_type_exists($slug)) {
                $counts = wp_count_posts($slug);
                $count = isset($counts->publish) ? (int) $counts->publish : 0;
            }
            $items[] = [
                'slug'  => $slug,
                'label' => $label,
                'count' => $count,
            ];
        }
        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items = $items;
    }
}
