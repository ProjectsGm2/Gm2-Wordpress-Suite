<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Public {
    public function run() {
        add_action('wp_head', [$this, 'output_canonical_url'], 5);
        add_action('wp_head', [$this, 'output_meta_tags']);
        add_action('wp_head', [$this, 'output_structured_data'], 20);
        add_action('wp_footer', [$this, 'output_breadcrumbs']);
    }

    public function output_meta_tags() {
        echo "<!-- SEO meta tags placeholder -->\n";
    }

    public function output_structured_data() {
        echo "<!-- Structured data placeholder -->\n";
    }

    public function output_breadcrumbs() {
        echo "<!-- Breadcrumbs placeholder -->\n";
    }

    public function output_canonical_url() {
        if (is_singular()) {
            echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '" />' . "\n";
        }
    }
}
