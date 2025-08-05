<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GM2_AC_Table extends \WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'gm2-abandoned-cart',
            'plural'   => 'gm2-abandoned-carts',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'status'      => __('Status', 'gm2-wordpress-suite'),
            'ip_address'  => __('IP Address', 'gm2-wordpress-suite'),
            'email'       => __('Email', 'gm2-wordpress-suite'),
            'location'    => __('Location', 'gm2-wordpress-suite'),
            'device'      => __('Device', 'gm2-wordpress-suite'),
            'browser'     => __('Browser', 'gm2-wordpress-suite'),
            'products'    => __('SKUs in Cart', 'gm2-wordpress-suite'),
            'cart_value'  => __('Cart Value', 'gm2-wordpress-suite'),
            'entry_url'   => __('Entry URL', 'gm2-wordpress-suite'),
            'exit_url'    => __('Exit URL', 'gm2-wordpress-suite'),
            'browsing_time' => __('Browsing Time', 'gm2-wordpress-suite'),
            'revisit_count' => __('Revisits', 'gm2-wordpress-suite'),
            'abandoned_at'=> __('Abandoned At', 'gm2-wordpress-suite'),
        ];
    }

    public function column_cb($item) {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        return '<input type="checkbox" name="id[]" value="' . esc_attr($id) . '" />';
    }

    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'gm2-wordpress-suite'),
        ];
    }

    public function process_bulk_action() {
        if ($this->current_action() !== 'delete') {
            return;
        }
        $ids = isset($_REQUEST['id']) ? (array) $_REQUEST['id'] : [];
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);
        if (!$ids) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));
    }

    private function ensure_value($value) {
        if ($value === null) {
            return 'N/A';
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        return $value === '' ? 'N/A' : $value;
    }

    public function column_default($item, $column_name) {
        $value = $item[$column_name] ?? '';
        return $this->ensure_value($value);
    }

    private function format_duration($seconds) {
        $seconds = (int) $seconds;
        if ($seconds <= 0) {
            return '0s';
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        $parts = [];
        if ($hours) {
            $parts[] = $hours . 'h';
        }
        if ($minutes) {
            $parts[] = $minutes . 'm';
        }
        if ($secs || empty($parts)) {
            $parts[] = $secs . 's';
        }
        return implode(' ', $parts);
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        global $wpdb;
        $table   = $wpdb->prefix . 'wc_ac_carts';
        $per_page = $this->get_items_per_page("gm2_ac_per_page", 20);
        $paged    = $this->get_pagenum();
        $search   = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

        $where  = 'WHERE recovered_order_id IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (email LIKE %s OR ip_address LIKE %s)';
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $total_sql  = "SELECT COUNT(*) FROM $table $where";
        $total_items = $wpdb->get_var($wpdb->prepare($total_sql, ...$params));

        $offset   = ($paged - 1) * $per_page;
        $data_sql = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params2  = array_merge($params, [ $per_page, $offset ]);
        $rows     = $wpdb->get_results($wpdb->prepare($data_sql, ...$params2));

        $items = [];
        foreach ($rows as $row) {
            $products   = [];
            $cart_value = 0;
            $contents   = json_decode($row->cart_contents, true);
            if (!is_array($contents)) {
                $old = maybe_unserialize($row->cart_contents);
                if (is_array($old)) {
                    $contents = [];
                    foreach ($old as $item) {
                        $qty     = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                        $prod_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                        $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : wc_get_product($prod_id);
                        $name    = $product ? $product->get_name() : 'Product #' . $prod_id;
                        $price   = $product ? (float) $product->get_price() : 0;
                        $contents[] = [
                            'id'    => $prod_id,
                            'name'  => $name,
                            'qty'   => $qty,
                            'price' => $price,
                            'sku'   => $product ? $product->get_sku() : '',
                        ];
                    }
                    $wpdb->update($table, [ 'cart_contents' => wp_json_encode($contents) ], [ 'id' => $row->id ]);
                } else {
                    $contents = [];
                }
            }
            foreach ($contents as $item) {
                $sku = $item['sku'] ?? '';
                if (!$sku && !empty($item['id'])) {
                    $product = wc_get_product((int) $item['id']);
                    if ($product) {
                        $sku = $product->get_sku();
                    }
                }
                if (!$sku) {
                    if (!empty($item['id'])) {
                        $sku = (string) $item['id'];
                    } elseif (!empty($item['name'])) {
                        $sku = $item['name'];
                    }
                }
                $products[] = $sku . ' x' . $item['qty'];
                if (!empty($item['price'])) {
                    $cart_value += (float) $item['price'] * (int) $item['qty'];
                }
            }
            if ($cart_value <= 0 && $row->cart_total) {
                $cart_value = (float) $row->cart_total;
            }

            $status = $row->abandoned_at ? __('Abandoned', 'gm2-wordpress-suite') : __('Active', 'gm2-wordpress-suite');
            $abandoned_at = '';
            if ($row->abandoned_at) {
                $abandoned_at = mysql2date(get_option('date_format').' '.get_option('time_format'), $row->abandoned_at);
            }

            $items[] = [
                'id'          => (int) $row->id,
                'status'      => esc_html($this->ensure_value($status)),
                'ip_address'  => esc_html($this->ensure_value($row->ip_address)),
                'email'       => esc_html($this->ensure_value($row->email)),
                'location'    => esc_html($this->ensure_value($row->location)),
                'device'      => esc_html($this->ensure_value($row->device)),
                'browser'     => esc_html($this->ensure_value($row->browser)),
                'products'    => esc_html($this->ensure_value(implode(', ', $products))),
                'cart_value'  => $this->ensure_value(wc_price($cart_value)),
                'entry_url'   => $this->ensure_value(esc_url($row->entry_url)),
                'exit_url'    => $this->ensure_value(esc_url($row->exit_url)),
                'browsing_time'=> esc_html($this->ensure_value($this->format_duration($row->browsing_time))),
                'revisit_count'=> esc_html($this->ensure_value($row->revisit_count)),
                'abandoned_at'=> esc_html($this->ensure_value($abandoned_at)),
            ];
        }

        $this->items = $items;
        $this->set_pagination_args([
            'total_items' => (int) $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}
