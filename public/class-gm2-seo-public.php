<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Public {
    public function run() {
        add_action('init', [$this, 'add_sitemap_rewrite']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'maybe_output_sitemap']);
        add_action('wp_head', [$this, 'output_canonical_url'], 5);
        add_action('wp_head', [$this, 'output_meta_tags']);
        add_action('wp_head', [$this, 'output_structured_data'], 20);
        add_action('wp_footer', [$this, 'output_breadcrumbs']);
    }

    public function add_sitemap_rewrite() {
        add_rewrite_rule('sitemap\\.xml$', 'index.php?gm2_sitemap=1', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'gm2_sitemap';
        return $vars;
    }

    public function maybe_output_sitemap() {
        if (get_query_var('gm2_sitemap')) {
            $s = new Gm2_Sitemap();
            $s->output();
        }
    }

    private function get_seo_meta() {
        $title       = '';
        $description = '';
        $noindex     = '';
        $nofollow    = '';
        $canonical   = '';

        if (is_singular()) {
            $post_id    = get_queried_object_id();
            $title       = get_post_meta($post_id, '_gm2_title', true);
            $description = get_post_meta($post_id, '_gm2_description', true);
            $noindex     = get_post_meta($post_id, '_gm2_noindex', true);
            $nofollow    = get_post_meta($post_id, '_gm2_nofollow', true);
            $canonical   = get_post_meta($post_id, '_gm2_canonical', true);

            if (class_exists('WooCommerce') && function_exists('is_product') && is_product()) {
                $product = wc_get_product($post_id);
                if ($product) {
                    if ('1' === get_option('gm2_noindex_variants', '0') && $product->is_type('variation')) {
                        $noindex = '1';
                    }
                    if ('1' === get_option('gm2_noindex_oos', '0') && !$product->is_in_stock()) {
                        $noindex = '1';
                    }
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $title       = get_term_meta($term->term_id, '_gm2_title', true);
                $description = get_term_meta($term->term_id, '_gm2_description', true);
                $noindex     = get_term_meta($term->term_id, '_gm2_noindex', true);
                $nofollow    = get_term_meta($term->term_id, '_gm2_nofollow', true);
                $canonical   = get_term_meta($term->term_id, '_gm2_canonical', true);
            }
        }

        if (!$title) {
            $title = wp_get_document_title();
        }
        if (!$description) {
            $description = get_bloginfo('description');
        }

        if (!$canonical) {
            if (is_singular()) {
                $canonical = get_permalink();
            } elseif (is_category() || is_tag() || is_tax()) {
                $canonical = get_term_link(get_queried_object());
            } else {
                $canonical = home_url();
            }
        }

        return [
            'title'       => $title,
            'description' => $description,
            'noindex'     => $noindex,
            'nofollow'    => $nofollow,
            'canonical'   => $canonical,
        ];
    }

    public function output_meta_tags() {
        $data        = $this->get_seo_meta();
        $title       = $data['title'];
        $description = $data['description'];
        $robots      = [];
        $robots[]    = ($data['noindex'] === '1') ? 'noindex' : 'index';
        $robots[]    = ($data['nofollow'] === '1') ? 'nofollow' : 'follow';
        $canonical   = $data['canonical'];

        echo '<title>' . esc_html($title) . "</title>\n";
        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta name="robots" content="' . esc_attr(implode(',', $robots)) . '" />' . "\n";

        $url  = $canonical;
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
        $data = $this->get_seo_meta();
        $canonical = $data['canonical'];
        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }
}
