<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GM2_AC_Table extends \WP_List_Table {
    private $items_per_page = 20;

    public function get_columns() {
        return [
            'ip_address'  => __('IP Address', 'gm2-wordpress-suite'),
            'email'       => __('Email', 'gm2-wordpress-suite'),
            'location'    => __('Location', 'gm2-wordpress-suite'),
            'device'      => __('Device', 'gm2-wordpress-suite'),
            'products'    => __('Products in Cart', 'gm2-wordpress-suite'),
            'cart_value'  => __('Cart Value', 'gm2-wordpress-suite'),
            'entry_url'   => __('Entry URL', 'gm2-wordpress-suite'),
            'exit_url'    => __('Exit URL', 'gm2-wordpress-suite'),
            'abandoned_at'=> __('Abandoned At', 'gm2-wordpress-suite'),
        ];
    }

    protected function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }

    public function prepare_items() {
        global $wpdb;
        $table   = $wpdb->prefix . 'wc_ac_carts';
        $per_page = $this->items_per_page;
        $paged    = $this->get_pagenum();
        $search   = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

        $where  = 'WHERE abandoned_at IS NOT NULL AND recovered_order_id IS NULL';
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
        $data_sql = "SELECT * FROM $table $where ORDER BY abandoned_at DESC LIMIT %d OFFSET %d";
        $params2  = array_merge($params, [ $per_page, $offset ]);
        $rows     = $wpdb->get_results($wpdb->prepare($data_sql, ...$params2));

        $items = [];
        foreach ($rows as $row) {
            $products   = [];
            $cart_value = 0;
            $contents   = maybe_unserialize($row->cart_contents);
            if (is_array($contents)) {
                foreach ($contents as $item) {
                    $qty  = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    $name = '';
                    $price = 0;
                    if (isset($item['data']) && is_object($item['data'])) {
                        $name  = $item['data']->get_name();
                        $price = (float) $item['data']->get_price();
                    } elseif (isset($item['product_id'])) {
                        $name  = get_the_title($item['product_id']);
                        $prod  = wc_get_product($item['product_id']);
                        if ($prod) {
                            $price = (float) $prod->get_price();
                        }
                    }
                    if ($name !== '') {
                        $products[] = $name . ' x' . $qty;
                        $cart_value += $price * $qty;
                    }
                }
            }
            if ($cart_value <= 0 && $row->cart_total) {
                $cart_value = (float) $row->cart_total;
            }
            $items[] = [
                'ip_address'  => esc_html($row->ip_address),
                'email'       => esc_html($row->email),
                'location'    => esc_html($row->location),
                'device'      => esc_html($row->device),
                'products'    => esc_html(implode(', ', $products)),
                'cart_value'  => wc_price($cart_value),
                'entry_url'   => esc_url($row->entry_url),
                'exit_url'    => esc_url($row->exit_url),
                'abandoned_at'=> esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'), $row->abandoned_at)),
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
