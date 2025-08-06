<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Public {
    private $buffer_started = false;
    /**
     * Cache for breadcrumb items generated during a request.
     *
     * @var array|null
     */
    private $breadcrumbs_cache = null;
    /**
     * Whether a primary schema type (Product or Article) has been output.
     *
     * @var bool
     */
    private $primary_schema_output = false;

    public function run() {
        add_action('init', [$this, 'add_sitemap_rewrite']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'maybe_apply_redirects'], 0);
        add_action('template_redirect', [$this, 'maybe_output_sitemap']);
        add_action('template_redirect', [$this, 'log_404_url'], 99);
        add_action('wp_head', [$this, 'output_canonical_url'], 5);
        add_action('wp_head', [$this, 'output_meta_tags']);
        add_action('wp_head', [$this, 'output_search_console_meta']);
        add_action('wp_head', [$this, 'output_ga_tracking_code']);
        add_action('wp_head', [$this, 'output_product_schema'], 20);
        add_action('wp_head', [$this, 'output_brand_schema'], 20);
        add_action('wp_head', [$this, 'output_breadcrumb_schema'], 20);
        add_action('wp_head', [$this, 'output_review_schema'], 20);
        add_action('wp_head', [$this, 'output_article_schema'], 20);
        add_action('wp_head', [$this, 'output_taxonomy_schema'], 20);
        add_action('wp_head', [$this, 'output_webpage_schema'], 20);
        add_filter('the_content', [$this, 'apply_link_rel']);
        if (get_option('gm2_show_footer_breadcrumbs', '1') === '1') {
            add_action('wp_footer', [$this, 'output_breadcrumbs']);
        }
        add_shortcode('gm2_breadcrumbs', [$this, 'gm2_breadcrumbs_shortcode']);
        add_action('init', [$this, 'register_breadcrumb_block']);

        add_action('template_redirect', [$this, 'maybe_buffer_output'], 1);
        add_action('shutdown', [$this, 'maybe_flush_buffer'], 0);
        add_action('send_headers', [$this, 'send_cache_headers']);
        add_filter('robots_txt', [$this, 'filter_robots_txt'], 10, 2);
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

    public function maybe_apply_redirects() {
        if (is_admin()) {
            return;
        }

        $redirects = get_option('gm2_redirects', []);
        if (empty($redirects)) {
            return;
        }

        $current = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        foreach ($redirects as $r) {
            $source = untrailingslashit(parse_url($r['source'], PHP_URL_PATH));
            if ($source === $current) {
                wp_redirect($r['target'], intval($r['type']));
                exit;
            }
        }
    }

    public function log_404_url() {
        if (is_404()) {
            $logs  = get_option('gm2_404_logs', []);
            $path  = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            if (!in_array($path, $logs, true)) {
                $logs[] = $path;
                if (count($logs) > 100) {
                    array_shift($logs);
                }
                update_option('gm2_404_logs', $logs);
            }
        }
    }

    private function get_breadcrumb_items() {
        if (is_array($this->breadcrumbs_cache)) {
            return $this->breadcrumbs_cache;
        }

        $breadcrumbs   = [];
        $breadcrumbs[] = [
            'name' => get_bloginfo('name'),
            'url'  => home_url('/'),
        ];

        if (is_front_page() || is_home()) {
            return $breadcrumbs;
        }

        if (is_singular()) {
            $post      = get_queried_object();
            $ancestors = get_post_ancestors($post);
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor) {
                $breadcrumbs[] = [
                    'name' => get_the_title($ancestor),
                    'url'  => get_permalink($ancestor),
                ];
            }
            $breadcrumbs[] = [
                'name' => get_the_title($post),
                'url'  => get_permalink($post),
            ];
        } elseif (is_tax() || is_category() || is_tag()) {
            $term      = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $ancestors = array_reverse(get_ancestors($term->term_id, $term->taxonomy));
                foreach ($ancestors as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, $term->taxonomy);
                    if ($ancestor && !is_wp_error($ancestor)) {
                        $ancestor_link = get_term_link($ancestor);
                        if (!is_wp_error($ancestor_link)) {
                            $breadcrumbs[] = [
                                'name' => $ancestor->name,
                                'url'  => $ancestor_link,
                            ];
                        }
                    }
                }
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    $breadcrumbs[] = [
                        'name' => $term->name,
                        'url'  => $term_link,
                    ];
                }
            }
        } else {
            $breadcrumbs[] = [
                'name' => wp_get_document_title(),
                'url'  => home_url(add_query_arg([], $GLOBALS['wp']->request)),
            ];
        }

        $this->breadcrumbs_cache = $breadcrumbs;

        return $this->breadcrumbs_cache;
    }

    /**
     * Retrieve SEO metadata for the current query.
     *
     * For singular screens this pulls post meta which includes custom post
     * types such as `product`. When viewing taxonomy archives term meta is
     * used, covering taxonomies like `product_cat` and `brand`.
     *
     * @return array{
     *     title:string,
     *     description:string,
     *     noindex:string,
     *     nofollow:string,
     *     canonical:string,
     *     max_snippet:string,
     *     max_image_preview:string,
     *     max_video_preview:string,
     *     focus_keywords:string,
     *     long_tail_keywords:string
     * }
     */
    private function get_seo_meta() {
        $title       = '';
        $description = '';
        $noindex     = '';
        $nofollow    = '';
        $canonical         = '';
        $og_image          = '';
        $max_snippet       = '';
        $max_image_preview = '';
        $max_video_preview = '';
        $focus_keywords    = '';
        $long_tail_keywords = '';

        if (is_singular()) {
            $post_id    = get_queried_object_id();
            $title       = get_post_meta($post_id, '_gm2_title', true);
            $description = get_post_meta($post_id, '_gm2_description', true);
            $noindex     = get_post_meta($post_id, '_gm2_noindex', true);
            $nofollow    = get_post_meta($post_id, '_gm2_nofollow', true);
            $canonical         = get_post_meta($post_id, '_gm2_canonical', true);
            $og_image          = get_post_meta($post_id, '_gm2_og_image', true);
            $max_snippet       = get_post_meta($post_id, '_gm2_max_snippet', true);
            $max_image_preview = get_post_meta($post_id, '_gm2_max_image_preview', true);
            $max_video_preview = get_post_meta($post_id, '_gm2_max_video_preview', true);
            $focus_keywords    = get_post_meta($post_id, '_gm2_focus_keywords', true);
            $long_tail_keywords = get_post_meta($post_id, '_gm2_long_tail_keywords', true);

            if (class_exists('WooCommerce') && function_exists('is_product') && is_product()) {
                $product = wc_get_product($post_id);
                if ($product) {
                    if ('1' === get_option('gm2_noindex_variants', '0') && $product->is_type('variation')) {
                        $noindex = '1';
                    }
                    if ('1' === get_option('gm2_noindex_oos', '0') && !$product->is_in_stock()) {
                        $noindex = '1';
                    }
                    if (empty($canonical) && '1' === get_option('gm2_variation_canonical_parent', '0') && $product->is_type('variation')) {
                        $parent_id = $product->get_parent_id();
                        if ($parent_id) {
                            $canonical = get_permalink($parent_id);
                        }
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
                $canonical         = get_term_meta($term->term_id, '_gm2_canonical', true);
                $og_image          = get_term_meta($term->term_id, '_gm2_og_image', true);
                $max_snippet       = get_term_meta($term->term_id, '_gm2_max_snippet', true);
                $max_image_preview = get_term_meta($term->term_id, '_gm2_max_image_preview', true);
                $max_video_preview = get_term_meta($term->term_id, '_gm2_max_video_preview', true);
                $focus_keywords    = get_term_meta($term->term_id, '_gm2_focus_keywords', true);
                $long_tail_keywords = get_term_meta($term->term_id, '_gm2_long_tail_keywords', true);
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
                $term_link = get_term_link(get_queried_object());
                $canonical = !is_wp_error($term_link) ? $term_link : home_url();
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
            'og_image'    => $og_image,
            'max_snippet' => $max_snippet,
            'max_image_preview' => $max_image_preview,
            'max_video_preview' => $max_video_preview,
            'focus_keywords'    => $focus_keywords,
            'long_tail_keywords' => $long_tail_keywords,
        ];
    }

    public function output_meta_tags() {
        $data        = $this->get_seo_meta();
        $title       = $data['title'];
        $description = $data['description'];
        $robots      = [];
        $robots[]    = ($data['noindex'] === '1') ? 'noindex' : 'index';
        $robots[]    = ($data['nofollow'] === '1') ? 'nofollow' : 'follow';
        if ($data['max_snippet'] !== '') {
            $robots[] = 'max-snippet:' . $data['max_snippet'];
        }
        if ($data['max_image_preview'] !== '') {
            $robots[] = 'max-image-preview:' . $data['max_image_preview'];
        }
        if ($data['max_video_preview'] !== '') {
            $robots[] = 'max-video-preview:' . $data['max_video_preview'];
        }
        $keywords = '';
        $fw = trim($data['focus_keywords']);
        $lt = trim($data['long_tail_keywords']);
        if ($fw !== '' || $lt !== '') {
            $parts = [];
            foreach ([$fw, $lt] as $list) {
                if ($list !== '') {
                    foreach (explode(',', $list) as $k) {
                        $k = trim($k);
                        if ($k !== '') {
                            $parts[] = $k;
                        }
                    }
                }
            }
            $keywords = implode(', ', array_unique($parts));
        }
        $canonical   = $data['canonical'];
        $og_image_id = $data['og_image'];
        if (!$og_image_id && is_singular()) {
            $og_image_id = get_post_thumbnail_id();
        }
        $og_image_url = $og_image_id ? wp_get_attachment_url($og_image_id) : '';
        $og_image_url = apply_filters('gm2_og_image_url', $og_image_url, $og_image_id, $data);

        // Output the canonical link tag first if it isn't already hooked.
        if (!has_action('wp_head', [$this, 'output_canonical_url'])) {
            $this->output_canonical_url();
        }

        $html = '';
        if (!current_theme_supports('title-tag')) {
            $html .= '<title>' . esc_html($title) . "</title>\n";
        }
        $html .= '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        $html .= '<meta name="robots" content="' . esc_attr(implode(',', $robots)) . '" />' . "\n";
        if ($keywords !== '') {
            $html .= '<meta name="keywords" content="' . esc_attr($keywords) . '" />' . "\n";
        }

        $url  = $canonical;
        $type = is_singular() ? 'article' : 'website';

        $html .= '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        $html .= '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        $html .= '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        $html .= '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";

        if ($og_image_url) {
            $html .= '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
            $html .= '<meta name="twitter:image" content="' . esc_url($og_image_url) . '" />' . "\n";
        }

        $html .= '<meta name="twitter:card" content="summary" />' . "\n";
        $html .= '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        $html .= '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";

        echo apply_filters('gm2_meta_tags', $html, $data);
    }

    public function generate_schema_data($post_id, $overrides = []) {
        $schemas = [];
        $post = get_post($post_id);
        if (!$post) {
            return $schemas;
        }

        $schema_type = $overrides['schema_type'] ?? get_post_meta($post_id, '_gm2_schema_type', true);
        $brand       = $overrides['schema_brand'] ?? get_post_meta($post_id, '_gm2_schema_brand', true);
        $rating      = $overrides['schema_rating'] ?? get_post_meta($post_id, '_gm2_schema_rating', true);

        if ($schema_type === 'product' || (!$schema_type && class_exists('WooCommerce') && get_post_type($post_id) === 'product')) {
            $product = class_exists('WooCommerce') ? wc_get_product($post_id) : null;
            if ($product) {
                $data = [
                    '@context' => 'https://schema.org/',
                    '@type'    => 'Product',
                    'name'     => get_the_title($post_id),
                    'image'    => wp_get_attachment_url($product->get_image_id()),
                    'description' => wp_strip_all_tags($product->get_description()),
                    'sku'      => $product->get_sku(),
                    'offers'   => [
                        '@type'         => 'Offer',
                        'priceCurrency' => get_woocommerce_currency(),
                        'price'         => $product->get_price(),
                        'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'url'           => get_permalink($post_id),
                    ],
                ];
                if ($brand) {
                    $data['brand'] = [
                        '@type' => 'Brand',
                        'name'  => $brand,
                    ];
                }
                $schemas[] = $data;
            }
        } elseif ($schema_type === 'article' || (!$schema_type && in_array($post->post_type, ['post', 'page'], true))) {
            $image = get_the_post_thumbnail_url($post_id, 'full');
            $data  = [
                '@context' => 'https://schema.org/',
                '@type'    => 'Article',
                'headline' => get_the_title($post_id),
                'author'   => [
                    '@type' => 'Person',
                    'name'  => get_the_author_meta('display_name', $post->post_author),
                ],
                'datePublished' => get_the_date('c', $post_id),
            ];
            if ($image) {
                $data['image'] = $image;
            }
            $schemas[] = $data;
        }

        if ($brand) {
            $schemas[] = [
                '@context' => 'https://schema.org/',
                '@type'    => 'Brand',
                'name'     => $brand,
            ];
        }

        if ($rating) {
            $schemas[] = [
                '@context'      => 'https://schema.org/',
                '@type'         => 'Review',
                'itemReviewed'  => [
                    '@type' => 'Product',
                    'name'  => get_the_title($post_id),
                ],
                'reviewRating'  => [
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => '5',
                ],
            ];
        } elseif (class_exists('WooCommerce') && get_post_type($post_id) === 'product') {
            $product = wc_get_product($post_id);
            if ($product) {
                $pr = $product->get_average_rating();
                if ($pr) {
                    $schemas[] = [
                        '@context'      => 'https://schema.org/',
                        '@type'         => 'Review',
                        'itemReviewed'  => [
                            '@type' => 'Product',
                            'name'  => get_the_title($post_id),
                        ],
                        'reviewRating'  => [
                            '@type'       => 'Rating',
                            'ratingValue' => $pr,
                            'bestRating'  => '5',
                        ],
                    ];
                }
            }
        }

        return $schemas;
    }

    public function output_product_schema() {
        if (get_option('gm2_schema_product', '1') !== '1') {
            return;
        }
        $schema_type = get_post_meta(get_the_ID(), '_gm2_schema_type', true);
        if ($schema_type && $schema_type !== 'product') {
            return;
        }
        if (!$schema_type && (!class_exists('WooCommerce') || !function_exists('is_product') || !is_product())) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product) {
            return;
        }

        $data = [
            '@context' => 'https://schema.org/',
            '@type'    => 'Product',
            'name'     => get_the_title(),
            'image'    => wp_get_attachment_url($product->get_image_id()),
            'description' => wp_strip_all_tags($product->get_description()),
            'sku'      => $product->get_sku(),
            'offers'   => [
                '@type'         => 'Offer',
                'priceCurrency' => get_woocommerce_currency(),
                'price'         => $product->get_price(),
                'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url'           => get_permalink(),
            ],
        ];
        $brand_meta = get_post_meta(get_the_ID(), '_gm2_schema_brand', true);
        if ($brand_meta) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name'  => $brand_meta,
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_article_schema() {
        if (get_option('gm2_schema_article', '1') !== '1') {
            return;
        }

        $schema_type = get_post_meta(get_the_ID(), '_gm2_schema_type', true);
        if ($schema_type && $schema_type !== 'article') {
            return;
        }
        if (!$schema_type && !is_singular('post') && !is_page()) {
            return;
        }

        $image = get_the_post_thumbnail_url(get_the_ID(), 'full');

        $data = [
            '@context' => 'https://schema.org/',
            '@type'    => 'Article',
            'headline' => get_the_title(),
            'author'   => [
                '@type' => 'Person',
                'name'  => get_the_author(),
            ],
            'datePublished' => get_the_date('c'),
        ];

        if ($image) {
            $data['image'] = $image;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_brand_schema() {
        if (get_option('gm2_schema_brand', '1') !== '1') {
            return;
        }

        $brand = is_singular() ? get_post_meta(get_the_ID(), '_gm2_schema_brand', true) : '';
        if ($brand) {
            $data = [
                '@context' => 'https://schema.org/',
                '@type'    => 'Brand',
                'name'     => $brand,
            ];
            echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
            return;
        }

        if (!is_tax(['brand', 'product_brand'])) {
            return;
        }

        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return;
        }

        $data = [
            '@context' => 'https://schema.org/',
            '@type'    => 'Brand',
            'name'     => $term->name,
            'description' => wp_strip_all_tags(term_description($term->term_id, $term->taxonomy)),
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_webpage_schema() {
        if (!is_singular()) {
            return;
        }
        if ($this->primary_schema_output) {
            return;
        }

        $data = $this->get_seo_meta();
        $schema = [
            '@context'    => 'https://schema.org/',
            '@type'       => 'WebPage',
            'name'        => wp_strip_all_tags($data['title']),
            'description' => wp_strip_all_tags($data['description']),
            'url'         => $data['canonical'],
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_breadcrumb_schema() {
        if (get_option('gm2_schema_breadcrumbs', '1') !== '1') {
            return;
        }
        $breadcrumbs = $this->get_breadcrumb_items();
        $items       = [];
        $position    = 1;
        foreach ($breadcrumbs as $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => wp_strip_all_tags($crumb['name']),
                'item'     => $crumb['url'],
            ];
        }

        $data = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_review_schema() {
        if (get_option('gm2_schema_review', '1') !== '1') {
            return;
        }

        $rating = get_post_meta(get_the_ID(), '_gm2_schema_rating', true);
        if ($rating) {
            $data = [
                '@context'      => 'https://schema.org/',
                '@type'         => 'Review',
                'itemReviewed'  => [
                    '@type' => 'Product',
                    'name'  => get_the_title(),
                ],
                'reviewRating'  => [
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => '5',
                ],
            ];
            echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
            return;
        }

        if (!class_exists('WooCommerce') || !function_exists('is_product') || !is_product()) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product) {
            return;
        }

        $rating = $product->get_average_rating();
        if (!$rating) {
            return;
        }

        $data = [
            '@context'      => 'https://schema.org/',
            '@type'         => 'Review',
            'itemReviewed'  => [
                '@type' => 'Product',
                'name'  => get_the_title(),
            ],
            'reviewRating'  => [
                '@type'       => 'Rating',
                'ratingValue' => $rating,
                'bestRating'  => '5',
            ],
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_taxonomy_schema() {
        if (get_option('gm2_schema_taxonomy', '1') !== '1') {
            return;
        }

        if (!is_category() && !is_tag() && !is_tax()) {
            return;
        }

        global $wp_query;
        if (empty($wp_query->posts)) {
            return;
        }

        $items    = [];
        $position = 1;
        foreach ($wp_query->posts as $post) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => get_permalink($post),
                'name'     => wp_strip_all_tags(get_the_title($post)),
            ];
        }

        $data = [
            '@context'        => 'https://schema.org/',
            '@type'           => 'ItemList',
            'name'            => single_term_title('', false),
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function gm2_breadcrumbs_shortcode() {
        $breadcrumbs = $this->get_breadcrumb_items();
        if (empty($breadcrumbs)) {
            return '';
        }

        $html  = '<nav class="gm2-breadcrumbs" aria-label="Breadcrumb"><ol>';
        $total = count($breadcrumbs);
        $i     = 0;
        foreach ($breadcrumbs as $crumb) {
            $i++;
            if ($i === $total) {
                $html .= '<li class="current">' . esc_html($crumb['name']) . '</li>';
            } else {
                $html .= '<li><a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['name']) . '</a></li>';
            }
        }
        $html .= '</ol></nav>';

        $items    = [];
        $position = 1;
        foreach ($breadcrumbs as $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => wp_strip_all_tags($crumb['name']),
                'item'     => $crumb['url'],
            ];
        }
        $data = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
        $html .= '<script type="application/ld+json">' . wp_json_encode($data) . '</script>';

        return $html;
    }

    public function register_breadcrumb_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'gm2-breadcrumb-block',
            GM2_PLUGIN_URL . 'public/js/breadcrumb-block.js',
            ['wp-blocks', 'wp-element'],
            GM2_VERSION,
            true
        );

        register_block_type('gm2/breadcrumbs', [
            'editor_script'   => 'gm2-breadcrumb-block',
            'render_callback' => function () {
                return do_shortcode('[gm2_breadcrumbs]');
            },
        ]);
    }

    public function output_structured_data() {
        echo "<!-- Structured data placeholder -->\n";
    }

    public function output_breadcrumbs() {
        echo $this->gm2_breadcrumbs_shortcode();
    }

    public function output_canonical_url() {
        $data = $this->get_seo_meta();
        $canonical = $data['canonical'];
        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }

    public function output_search_console_meta() {
        $code = get_option('gm2_search_console_verification', '');
        if ($code) {
            $html = '<meta name="google-site-verification" content="' . esc_attr($code) . '" />' . "\n";
            echo apply_filters('gm2_search_console_meta', $html, $code);
        }
    }

    public function output_ga_tracking_code() {
        $id = trim(get_option('gm2_ga_measurement_id', ''));
        if ($id) {
            echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($id) . '"></script>' . "\n";
            echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\', new Date());gtag(\'config\', \'' . esc_js($id) . '\');</script>' . "\n";
        }
    }

    public function maybe_buffer_output() {
        $html = get_option('gm2_minify_html', '0');
        $css  = get_option('gm2_minify_css', '0');
        $js   = get_option('gm2_minify_js', '0');
        if ($html === '1' || $css === '1' || $js === '1') {
            ob_start([$this, 'minify_output']);
            $this->buffer_started = true;
        }
    }

    public function maybe_flush_buffer() {
        if ($this->buffer_started && ob_get_level() > 0) {
            ob_end_flush();
            $this->buffer_started = false;
        }
    }

    public function minify_output($html) {
        if (get_option('gm2_minify_html', '0') === '1') {
            $html = preg_replace('/>\s+</', '><', $html);
            $html = preg_replace('/\s+/', ' ', $html);
        }
        if (get_option('gm2_minify_css', '0') === '1') {
            $html = preg_replace_callback('#<style[^>]*>(.*?)</style>#s', function ($m) {
                $css = preg_replace('!\s+!', ' ', $m[1]);
                $css = preg_replace('!/\*.*?\*/!s', '', $css);
                return '<style>' . trim($css) . '</style>';
            }, $html);
        }
        if (get_option('gm2_minify_js', '0') === '1') {
            $html = preg_replace_callback('#<script[^>]*>(.*?)</script>#s', function ($m) {
                $js = preg_replace('!\s+!', ' ', $m[1]);
                $js = preg_replace('!/\*.*?\*/!s', '', $js);
                return '<script>' . trim($js) . '</script>';
            }, $html);
        }
        return $html;
    }

    public function apply_link_rel($content) {
        if (is_admin()) {
            return $content;
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }
        $json = get_post_meta($post_id, '_gm2_link_rel', true);
        if (!$json) {
            return $content;
        }
        $map = json_decode($json, true);
        if (!is_array($map) || empty($map)) {
            return $content;
        }
        if (!class_exists('\DOMDocument') || !function_exists('libxml_use_internal_errors')) {
            return $content;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (isset($map[$href])) {
                $rel = trim($map[$href]);
                if ($rel === '') {
                    $a->removeAttribute('rel');
                } else {
                    $a->setAttribute('rel', $rel);
                }
            }
        }
        return $doc->saveHTML();
    }

    public function filter_robots_txt($output, $public) {
        $custom = get_option('gm2_robots_txt', '');
        if ($custom !== '') {
            $output .= "\n" . $custom;
        }
        return $output;
    }

    public function send_cache_headers() {
        do_action('gm2_set_cache_headers');
    }
}
