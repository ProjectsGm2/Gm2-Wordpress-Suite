<?php

namespace Gm2 {

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Sitemap {
    private $file_path;

    public function __construct() {
        $this->file_path = ABSPATH . 'sitemap.xml';
    }

    private function get_post_types() {
        $types = ['post', 'page'];
        if (post_type_exists('product')) {
            $types[] = 'product';
        }
        return $types;
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
            return;
        }

        $frequency = get_option('gm2_sitemap_frequency', 'daily');

        $urls = [];
        foreach ($this->get_post_types() as $type) {
            $posts = get_posts([
                'post_type'   => $type,
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);
            foreach ($posts as $post) {
                $urls[] = [
                    'loc'        => get_permalink($post),
                    'lastmod'    => get_the_modified_date('c', $post),
                    'changefreq' => $frequency,
                ];
            }
        }

        foreach ($this->get_taxonomies() as $tax) {
            $terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => true,
            ]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $urls[] = [
                        'loc'        => get_term_link($term),
                        'lastmod'    => date('c'),
                        'changefreq' => $frequency,
                    ];
                }
            }
        }

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url($u['loc']) . "</loc>\n";
            $xml .= "    <lastmod>{$u['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        file_put_contents($this->file_path, $xml);

        $this->ping_search_engines();
    }

    public function ping_search_engines() {
        $sitemap_url = home_url('/sitemap.xml');
        $endpoints    = [
            'https://www.google.com/ping?sitemap=' . rawurlencode($sitemap_url),
            'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemap_url),
        ];
        foreach ($endpoints as $endpoint) {
            wp_remote_get($endpoint);
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
        $s->generate();
    }
}
