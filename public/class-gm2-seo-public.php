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

    private function get_seo_meta() {
        $title       = '';
        $description = '';

        if (is_singular()) {
            $post_id    = get_queried_object_id();
            $title       = get_post_meta($post_id, '_gm2_title', true);
            $description = get_post_meta($post_id, '_gm2_description', true);
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $title       = get_term_meta($term->term_id, '_gm2_title', true);
                $description = get_term_meta($term->term_id, '_gm2_description', true);
            }
        }

        if (!$title) {
            $title = wp_get_document_title();
        }
        if (!$description) {
            $description = get_bloginfo('description');
        }

        return [
            'title'       => $title,
            'description' => $description,
        ];
    }

    public function output_meta_tags() {
        $data = $this->get_seo_meta();
        $title = $data['title'];
        $description = $data['description'];

        echo '<title>' . esc_html($title) . "</title>\n";
        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";

        $url  = is_singular() ? get_permalink() : (is_category() || is_tag() || is_tax() ? get_term_link(get_queried_object()) : home_url());
        $type = is_singular() ? 'article' : 'website';

        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";

        echo '<meta name="twitter:card" content="summary" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
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
