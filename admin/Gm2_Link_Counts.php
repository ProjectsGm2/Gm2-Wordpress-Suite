<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Link_Counts {

    /**
     * Post types that should receive link counting features.
     *
     * @var string[]
     */
    private $post_types = [];

    public function run() {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment']);
        $this->post_types = apply_filters('gm2_supported_post_types', array_values($types));

        foreach ($this->post_types as $type) {
            add_filter("manage_{$type}_posts_columns", [ $this, 'add_columns' ]);
            add_action("manage_{$type}_posts_custom_column", [ $this, 'render_column' ], 10, 2);
        }

        add_action('save_post', [ $this, 'save_post_counts' ], 10, 3);
        add_action('wp_dashboard_setup', [ $this, 'add_dashboard_widget' ]);
    }

    public function add_columns($cols) {
        $cols['gm2_internal_links'] = __('Internal Links', 'gm2-wordpress-suite');
        $cols['gm2_external_links'] = __('External Links', 'gm2-wordpress-suite');
        return $cols;
    }

    public function render_column($column, $post_id) {
        if ($column === 'gm2_internal_links' || $column === 'gm2_external_links') {
            $internal = (int) get_post_meta($post_id, '_gm2_internal_links', true);
            $external = (int) get_post_meta($post_id, '_gm2_external_links', true);
            if ($internal === 0 && $external === 0) {
                $counts = $this->count_links(get_post_field('post_content', $post_id));
                $internal = $counts['internal'];
                $external = $counts['external'];
                update_post_meta($post_id, '_gm2_internal_links', $internal);
                update_post_meta($post_id, '_gm2_external_links', $external);
            }
            echo esc_html($column === 'gm2_internal_links' ? $internal : $external);
        }
    }

    public function save_post_counts($post_id, $post, $update) {
        if (!in_array($post->post_type, $this->post_types, true)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $counts = $this->count_links($post->post_content);
        update_post_meta($post_id, '_gm2_internal_links', $counts['internal']);
        update_post_meta($post_id, '_gm2_external_links', $counts['external']);
    }

    private function count_links($content) {
        $internal = 0;
        $external = 0;
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        if (preg_match_all('/<a\\s[^>]*href=(\"|\')(.*?)\1/i', $content, $m)) {
            foreach ($m[2] as $href) {
                $href = trim($href);
                if ($href === '' || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
                    continue;
                }
                $host = parse_url($href, PHP_URL_HOST);
                if ($host === null || $host === '' || $host === $site_host) {
                    $internal++;
                } else {
                    $external++;
                }
            }
        }
        return [ 'internal' => $internal, 'external' => $external ];
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget('gm2_link_overview', __('Link Overview', 'gm2-wordpress-suite'), [ $this, 'dashboard_widget' ]);
    }

    public function dashboard_widget() {
        global $wpdb;
        $types_in = "'" . implode("','", array_map('esc_sql', $this->post_types)) . "'";
        $internal = (int) $wpdb->get_var(
            "SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key = '_gm2_internal_links' AND p.post_type IN ($types_in) AND p.post_status = 'publish'"
        );
        $external = (int) $wpdb->get_var(
            "SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key = '_gm2_external_links' AND p.post_type IN ($types_in) AND p.post_status = 'publish'"
        );
        echo '<p>' . esc_html__('Internal Links:', 'gm2-wordpress-suite') . ' ' . esc_html($internal) . '</p>';
        echo '<p>' . esc_html__('External Links:', 'gm2-wordpress-suite') . ' ' . esc_html($external) . '</p>';
    }
}
