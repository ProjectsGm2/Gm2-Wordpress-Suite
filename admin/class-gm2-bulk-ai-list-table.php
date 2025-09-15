<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Gm2_Bulk_Ai_List_Table extends \WP_List_Table {
    private $admin;
    private $page_size;
    private $status;
    private $post_type;
    private $terms;
    private $missing_title;
    private $missing_desc;
    private $seo_status;

    public function __construct($admin, $args) {
        $this->admin         = $admin;
        $this->page_size     = max(1, (int) ($args['page_size'] ?? 10));
        $this->status        = $args['status'] ?? 'publish';
        $this->post_type     = $args['post_type'] ?? 'all';
        $this->terms         = $args['terms'] ?? [];
        $this->seo_status    = $args['seo_status'] ?? 'all';
        $this->missing_title = $args['missing_title'] ?? '0';
        $this->missing_desc  = $args['missing_desc'] ?? '0';

        parent::__construct([
            'plural'   => 'gm2-bulk-ai',
            'singular' => 'gm2-bulk-ai',
            'ajax'     => false,
            'screen'   => 'gm2-bulk-ai',
        ]);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" id="gm2-bulk-select-all" />',
            'title'       => esc_html__( 'Title', 'gm2-wordpress-suite' ),
            'seo_title'   => esc_html__( 'SEO Title', 'gm2-wordpress-suite' ),
            'description'       => esc_html__( 'Description', 'gm2-wordpress-suite' ),
            'focus_keywords'    => esc_html__( 'Focus Keywords', 'gm2-wordpress-suite' ),
            'long_tail_keywords'=> esc_html__( 'Long Tail Keywords', 'gm2-wordpress-suite' ),
            'ai'                => esc_html__( 'AI Suggestions', 'gm2-wordpress-suite' ),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'title' => [ 'title', false ],
        ];
    }

    protected function column_cb($item) {
        return '<input type="checkbox" class="gm2-select" value="' . intval($item->ID) . '" />';
    }

    protected function column_title($item) {
        $edit_link = get_edit_post_link($item->ID);
        $title     = $edit_link ? '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($item->post_title) . '</a>' : esc_html($item->post_title);
        return $title;
    }

    protected function column_seo_title($item) {
        return esc_html( get_post_meta($item->ID, '_gm2_title', true) );
    }

    protected function column_description($item) {
        return esc_html( get_post_meta($item->ID, '_gm2_description', true) );
    }

    protected function column_focus_keywords($item) {
        return esc_html( get_post_meta($item->ID, '_gm2_focus_keywords', true) );
    }

    protected function column_long_tail_keywords($item) {
        return esc_html( get_post_meta($item->ID, '_gm2_long_tail_keywords', true) );
    }

    protected function column_ai($item) {
        $stored   = get_post_meta($item->ID, '_gm2_ai_research', true);
        $has_prev = (
            get_post_meta($item->ID, '_gm2_prev_title', true) !== '' ||
            get_post_meta($item->ID, '_gm2_prev_description', true) !== '' ||
            get_post_meta($item->ID, '_gm2_prev_slug', true) !== '' ||
            get_post_meta($item->ID, '_gm2_prev_post_title', true) !== '' ||
            get_post_meta($item->ID, '_gm2_prev_focus_keywords', true) !== '' ||
            get_post_meta($item->ID, '_gm2_prev_long_tail_keywords', true) !== ''
        );
        $result_html = '';
        if ($stored) {
            $data = json_decode($stored, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $result_html = $this->render_result($data, $item->ID, $has_prev);
            } elseif ($has_prev) {
                $result_html = $this->render_result([], $item->ID, true);
            }
        } elseif ($has_prev) {
            $result_html = $this->render_result([], $item->ID, true);
        }
        return '<div class="gm2-result">' . $result_html . '</div>';
    }

    private function render_result($data, $post_id, $has_prev = false) {
        $html        = '';
        $suggestions = '';

        if (!empty($data['seo_title'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="' . esc_attr($data['seo_title']) . '"> ' . esc_html($data['seo_title']) . '</label></p>';
        }
        if (!empty($data['description'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="' . esc_attr($data['description']) . '"> ' . esc_html($data['description']) . '</label></p>';
        }
        if (!empty($data['focus_keywords'])) {
            $fk = is_array($data['focus_keywords']) ? implode(', ', $data['focus_keywords']) : $data['focus_keywords'];
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="focus_keywords" data-value="' . esc_attr($fk) . '"> ' . esc_html__( 'Focus Keywords', 'gm2-wordpress-suite' ) . ': ' . esc_html($fk) . '</label></p>';
        }
        if (!empty($data['long_tail_keywords'])) {
            $lt = is_array($data['long_tail_keywords']) ? implode(', ', $data['long_tail_keywords']) : $data['long_tail_keywords'];
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="long_tail_keywords" data-value="' . esc_attr($lt) . '"> ' . esc_html__( 'Long Tail Keywords', 'gm2-wordpress-suite' ) . ': ' . esc_html($lt) . '</label></p>';
        }

        if ($suggestions !== '' || $has_prev) {
            if ($suggestions !== '') {
                $html .= '<p><label><input type="checkbox" class="gm2-row-select-all"> ' . esc_html__( 'Select all', 'gm2-wordpress-suite' ) . '</label></p>';
                $html .= $suggestions;
            }
            $html .= '<p><button class="button gm2-apply-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Apply', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Apply', 'gm2-wordpress-suite' ) . '</button> ';
            $html .= '<button class="button gm2-refresh-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Refresh', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Refresh', 'gm2-wordpress-suite' ) . '</button> ';
            $html .= '<button class="button gm2-clear-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Clear', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Clear', 'gm2-wordpress-suite' ) . '</button>';
            if ($has_prev) {
                $html .= ' <button class="button gm2-undo-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Undo', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Undo', 'gm2-wordpress-suite' ) . '</button>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $types = $this->admin->get_supported_post_types();
        if ($this->post_type !== 'all' && in_array($this->post_type, $types, true)) {
            $types = [ $this->post_type ];
        }

        $args = [
            'post_type'      => $types,
            'post_status'    => $this->status,
            'posts_per_page' => $this->page_size,
            'paged'          => $this->get_pagenum(),
        ];

        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($this->terms) {
            $taxonomies = $this->admin->get_supported_taxonomies();
            $tax_query  = [];
            foreach ($this->terms as $tax => $ids) {
                if (!in_array($tax, $taxonomies, true)) {
                    continue;
                }
                $ids = array_filter(array_map('absint', (array) $ids));
                if ($ids) {
                    $tax_query[] = [
                        'taxonomy' => $tax,
                        'field'    => 'term_id',
                        'terms'    => $ids,
                    ];
                }
            }
            if ($tax_query) {
                if (count($tax_query) > 1) {
                    $tax_query = array_merge(['relation' => 'AND'], $tax_query);
                }
                $args['tax_query'] = $tax_query;
            }
        }

        $meta_query = [];
        if ($this->missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($this->missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($this->seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($this->seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        } elseif ($this->seo_status === 'has_ai') {
            $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge([ 'relation' => 'AND' ], $meta_query);
        }

        $sortable_columns = $sortable;
        $default_orderby  = 'title';
        $orderby_key      = $default_orderby;

        if (isset($_REQUEST['orderby'])) {
            $requested_orderby = sanitize_key(wp_unslash($_REQUEST['orderby']));
            if (isset($sortable_columns[$requested_orderby])) {
                $orderby_key = $requested_orderby;
            }
        }

        if (isset($sortable_columns[$orderby_key])) {
            $orderby_value = $sortable_columns[$orderby_key][0] ?? $orderby_key;
            $args['orderby'] = $orderby_value;

            $order = 'asc';
            if (isset($_REQUEST['order'])) {
                $requested_order = sanitize_key(wp_unslash($_REQUEST['order']));
                if (in_array($requested_order, [ 'asc', 'desc' ], true)) {
                    $order = $requested_order;
                }
            }

            $args['order'] = $order;
        }

        $query = new \WP_Query($args);
        $this->items = $query->posts;
        $this->set_pagination_args([
            'total_items' => (int) $query->found_posts,
            'per_page'    => $this->page_size,
            'total_pages' => (int) max(1, $query->max_num_pages),
        ]);
    }

    public function single_row($item) {
        echo '<tr id="gm2-row-' . intval($item->ID) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    public function display() {
        $this->display_tablenav('top');
        $classes = implode(' ', $this->get_table_classes());
        echo '<table id="gm2-bulk-list" class="wp-list-table ' . esc_attr($classes) . '">';

        echo '<thead>';
        $this->print_column_headers();
        echo '</thead>';

        echo '<tbody id="the-list">';
        $this->display_rows_or_placeholder();
        echo '</tbody>';

        echo '<tfoot>';
        $this->print_column_headers(false);
        echo '</tfoot>';

        echo '</table>';
        $this->display_tablenav('bottom');
    }

    public function no_items() {
        esc_html_e( 'No posts found.', 'gm2-wordpress-suite' );
    }
}
