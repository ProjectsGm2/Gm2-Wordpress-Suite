<?php

namespace Gm2 {

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Sitemap {
    private $file_path;

    public function __construct($file_path = '') {
        $default = ABSPATH . 'sitemap.xml';
        $opt     = get_option('gm2_sitemap_path', $default);
        $this->file_path = $file_path !== '' ? $file_path : $opt;
    }

    private function get_post_types() {
        $args  = [
            'public'             => true,
            'show_ui'            => true,
            'exclude_from_search' => false,
        ];
        $types = get_post_types($args, 'names');
        unset($types['attachment']);
        return apply_filters('gm2_supported_post_types', array_values($types));
    }

    private function get_taxonomies() {
        $taxonomies = ['category'];
        if (taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }
        if (taxonomy_exists('brand')) {
            $taxonomies[] = 'brand';
        }
        if (taxonomy_exists('product_brand')) {
            $taxonomies[] = 'product_brand';
        }
        return $taxonomies;
    }

    public function generate() {
        if (get_option('gm2_sitemap_enabled', '1') !== '1') {
            return true;
        }

        $frequency = get_option('gm2_sitemap_frequency', 'daily');
        $max_urls  = intval(get_option('gm2_sitemap_max_urls', 1000));
        if ($max_urls <= 0) {
            $max_urls = 1000;
        }

        $urls = [];
        foreach ($this->get_post_types() as $type) {
            $per_page = apply_filters('gm2_sitemap_posts_per_page', 100);
            $paged    = 1;
            do {
                $query = new \WP_Query([
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                    'fields'         => 'ids',
                ]);
                foreach ($query->posts as $post_id) {
                    $image    = get_the_post_thumbnail_url($post_id, 'full');
                    $priority = apply_filters('gm2_sitemap_priority', '', $type, $post_id);

                    $urls[] = [
                        'loc'        => get_permalink($post_id),
                        'lastmod'    => get_post_modified_time('c', true, $post_id),
                        'changefreq' => $frequency,
                        'image'      => $image,
                        'priority'   => $priority,
                    ];
                }
                $paged++;
            } while ($paged <= $query->max_num_pages);
            wp_reset_postdata();
        }

        foreach ($this->get_taxonomies() as $tax) {
            $terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => true,
            ]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $priority = apply_filters('gm2_sitemap_priority', '', $tax, $term->term_id);

                    $urls[] = [
                        'loc'        => get_term_link($term),
                        'lastmod'    => date('c'),
                        'changefreq' => $frequency,
                        'priority'   => $priority,
                    ];
                }
            }
        }

        $chunks = array_chunk($urls, $max_urls);

        $dir       = trailingslashit(dirname($this->file_path));
        $base_name = basename($this->file_path, '.xml');

        $index_entries = [];
        $i = 1;
        foreach ($chunks as $set) {
            $part_path = $dir . $base_name . '-' . $i . '.xml';

            $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";
            foreach ($set as $u) {
                $xml .= "  <url>\n";
                $xml .= "    <loc>" . esc_url($u['loc']) . "</loc>\n";
                $xml .= "    <lastmod>{$u['lastmod']}</lastmod>\n";
                $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
                if (!empty($u['image'])) {
                    $xml .= "    <image:image><image:loc>" . esc_url($u['image']) . "</image:loc></image:image>\n";
                }
                if (!empty($u['priority'])) {
                    $xml .= "    <priority>" . esc_html($u['priority']) . "</priority>\n";
                }
                $xml .= "  </url>\n";
            }
            $xml .= "</urlset>\n";

            $index_entries[] = [
                'loc'     => home_url('/' . ltrim(str_replace(ABSPATH, '', $part_path), '/')),
                'lastmod' => date('c'),
                'path'    => $part_path,
                'xml'     => $xml,
            ];

            $i++;
        }


        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            return new \WP_Error('fs_init_failed', __('Unable to initialize filesystem', 'gm2-wordpress-suite'));
        }

        // Remove old parts
        $old_parts = glob($dir . $base_name . '-*.xml');
        if (is_array($old_parts)) {
            foreach ($old_parts as $old) {
                $wp_filesystem->delete($old);
            }
        }

        foreach ($index_entries as $entry) {
            $wp_filesystem->put_contents($entry['path'], $entry['xml'], FS_CHMOD_FILE);
        }

        $index_xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $index_xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($index_entries as $entry) {
            $index_xml .= "  <sitemap>\n";
            $index_xml .= "    <loc>" . esc_url($entry['loc']) . "</loc>\n";
            $index_xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $index_xml .= "  </sitemap>\n";
        }
        $index_xml .= "</sitemapindex>\n";

        $written = $wp_filesystem->put_contents($this->file_path, $index_xml, FS_CHMOD_FILE);
        if (!$written) {
            return new \WP_Error('write_failed', sprintf(__('Could not write sitemap to %s', 'gm2-wordpress-suite'), $this->file_path));
        }

        $this->ping_search_engines();
        return true;
    }

    public function ping_search_engines() {
        $sitemap_url = home_url('/' . ltrim(str_replace(ABSPATH, '', $this->file_path), '/'));
        $endpoints    = [
            'https://www.google.com/ping?sitemap=' . rawurlencode($sitemap_url),
            'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemap_url),
        ];
        foreach ($endpoints as $endpoint) {
            wp_safe_remote_get($endpoint, ['timeout' => 5]);
        }
    }

    public function output() {
        if (!file_exists($this->file_path)) {
            $this->generate();
        }
        if (!file_exists($this->file_path)) {
            status_header(404);
            exit;
        }
        header('Content-Type: application/xml; charset=UTF-8');
        readfile($this->file_path);
        exit;
    }
}
}

namespace {
    function gm2_generate_sitemap() {
        $s = new \Gm2\Gm2_Sitemap();
        return $s->generate();
    }
}
