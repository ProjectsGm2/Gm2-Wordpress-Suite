<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Public {
    private $buffer_started = false;

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
        if (get_option('gm2_show_footer_breadcrumbs', '1') === '1') {
            add_action('wp_footer', [$this, 'output_breadcrumbs']);
        }
        add_shortcode('gm2_breadcrumbs', [$this, 'gm2_breadcrumbs_shortcode']);
        add_action('init', [$this, 'register_breadcrumb_block']);

        add_action('template_redirect', [$this, 'maybe_buffer_output'], 1);
        add_action('shutdown', [$this, 'maybe_flush_buffer'], 0);
        add_action('send_headers', [$this, 'send_cache_headers']);
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

        return $breadcrumbs;
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
     *     canonical:string
     * }
     */
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

        // Output the canonical link tag first if it isn't already hooked.
        if (!has_action('wp_head', [$this, 'output_canonical_url'])) {
            $this->output_canonical_url();
        }

        if (!current_theme_supports('title-tag')) {
            echo '<title>' . esc_html($title) . "</title>\n";
        }
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

    public function output_product_schema() {
        if (get_option('gm2_schema_product', '1') !== '1') {
            return;
        }
        if (!class_exists('WooCommerce') || !function_exists('is_product') || !is_product()) {
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

        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_brand_schema() {
        if (get_option('gm2_schema_brand', '1') !== '1') {
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

    public function output_breadcrumb_schema() {
        if (get_option('gm2_schema_breadcrumbs', '1') !== '1') {
            return;
        }
        $items    = [];
        $position = 1;
        foreach ($this->get_breadcrumb_items() as $crumb) {
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
        echo do_shortcode('[gm2_breadcrumbs]');
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
            echo '<meta name="google-site-verification" content="' . esc_attr($code) . '" />' . "\n";
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

    public function send_cache_headers() {
        do_action('gm2_set_cache_headers');
    }
}
