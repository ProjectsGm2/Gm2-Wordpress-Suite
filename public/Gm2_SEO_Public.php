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
    /**
     * Cached sanitized request URI for the current request.
     *
     * @var string|null
     */
    private $sanitized_request_uri = null;

    public static function default_product_template() {
        return [
            '@context' => 'https://schema.org/',
            '@type'    => 'Product',
            'name'     => '{{title}}',
            'image'    => '{{image}}',
            'description' => '{{description}}',
            'sku'      => '{{sku}}',
            'offers'   => [
                '@type'         => 'Offer',
                'priceCurrency' => '{{price_currency}}',
                'price'         => '{{price}}',
                'availability'  => '{{availability}}',
                'url'           => '{{permalink}}',
            ],
            'brand' => [
                '@type' => 'Brand',
                'name'  => '{{brand}}',
            ],
        ];
    }

    public static function default_article_template() {
        return [
            '@context' => 'https://schema.org/',
            '@type'    => 'Article',
            'headline' => '{{title}}',
            'author'   => [
                '@type' => 'Person',
                'name'  => '',
            ],
            'datePublished' => '',
            'image'    => '{{image}}',
            'url'      => '{{permalink}}',
        ];
    }

    public static function default_brand_template() {
        return [
            '@context' => 'https://schema.org/',
            '@type'    => 'Brand',
            'name'     => '{{title}}',
            'description' => '{{description}}',
        ];
    }

    public static function default_breadcrumb_template() {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => '',
                    'item'     => '',
                ],
            ],
        ];
    }

    public static function default_review_template() {
        return [
            '@context'     => 'https://schema.org/',
            '@type'        => 'Review',
            'itemReviewed' => [
                '@type' => 'Product',
                'name'  => '{{title}}',
            ],
            'reviewRating' => [
                '@type'       => 'Rating',
                'ratingValue' => '{{rating}}',
                'bestRating'  => '5',
            ],
        ];
    }

    public static function default_taxonomy_template() {
        return [
            '@context'        => 'https://schema.org/',
            '@type'           => 'ItemList',
            'name'            => '{{title}}',
            'numberOfItems'   => 0,
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'url'      => '{{permalink}}',
                    'name'     => '{{title}}',
                ],
            ],
        ];
    }

    /**
     * Return list of available placeholder tokens for JSON-LD templates.
     *
     * @return array<string,string> Map of token => description.
     */
    public static function get_placeholders() {
        $placeholders = [
            '{{title}}'          => __('Post or term title', 'gm2-wordpress-suite'),
            '{{permalink}}'      => __('Permalink URL', 'gm2-wordpress-suite'),
            '{{url}}'            => __('Alias of {{permalink}}', 'gm2-wordpress-suite'),
            '{{description}}'    => __('SEO description or excerpt', 'gm2-wordpress-suite'),
            '{{image}}'          => __('Featured image URL', 'gm2-wordpress-suite'),
            '{{price}}'          => __('Product price', 'gm2-wordpress-suite'),
            '{{price_currency}}' => __('Currency code', 'gm2-wordpress-suite'),
            '{{availability}}'   => __('Stock availability URL', 'gm2-wordpress-suite'),
            '{{sku}}'            => __('Product SKU', 'gm2-wordpress-suite'),
            '{{brand}}'          => __('Brand name', 'gm2-wordpress-suite'),
            '{{rating}}'         => __('Review rating value', 'gm2-wordpress-suite'),
            '{taxonomy:taxonomy_slug}' => __('Names of terms assigned to the specified taxonomy (e.g. {taxonomy:product_cat})', 'gm2-wordpress-suite'),
            '{field:field_key}'       => __('Value from the matching GM2 field or custom field key', 'gm2-wordpress-suite'),
            '{location_city}'         => __('Location field value such as city, state or country (uses GM2 location fields/options)', 'gm2-wordpress-suite'),
        ];

        /**
         * Filter the placeholder descriptions displayed in the schema settings UI.
         *
         * @param array<string,string> $placeholders Map of token => description.
         */
        return apply_filters('gm2_schema_placeholders', $placeholders);
    }

    private function replace_placeholders($data, $context) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->replace_placeholders($v, $context);
            }
            return $data;
        }
        if (!is_string($data)) {
            return $data;
        }

        $replacements = [];
        foreach ($context as $token => $value) {
            if (!is_string($token) || substr($token, 0, 2) !== '{{' || substr($token, -2) !== '}}') {
                continue;
            }
            $replacements[$token] = $this->prepare_placeholder_value($value);
        }

        $result = strtr($data, $replacements);

        if (strpos($result, '{') === false) {
            return $result;
        }

        return preg_replace_callback(
            '/\{([a-z0-9_]+(?::[a-z0-9_\-]+)?)\}/i',
            function ($matches) use ($context) {
                return $this->resolve_dynamic_token($matches[1], $context);
            },
            $result
        );
    }

    private function prepare_placeholder_value($value) {
        if (is_array($value)) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        return '';
    }

    private function resolve_dynamic_token(string $token, array $context): string {
        $type = $token;
        $key  = '';
        if (strpos($token, ':') !== false) {
            [$type, $key] = explode(':', $token, 2);
        }
        $type = strtolower($type);

        switch ($type) {
            case 'taxonomy':
                $value = $this->resolve_taxonomy_token($key, $context);
                break;
            case 'field':
                $value = $this->resolve_field_token($key, $context);
                break;
            default:
                if (str_starts_with($type, 'location_')) {
                    $value = $this->resolve_location_token(substr($type, strlen('location_')), $context);
                } else {
                    $value = '';
                }
        }

        /**
         * Filter the resolved value for dynamic schema tokens.
         *
         * @param mixed  $value  Resolved value. Can be a scalar or array.
         * @param string $token  Token string without braces.
         * @param array  $context Replacement context data.
         */
        $value = apply_filters('gm2_schema_dynamic_token_value', $value, $token, $context);

        return $this->format_dynamic_value($value);
    }

    private function resolve_taxonomy_token(string $taxonomy, array $context) {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '') {
            return '';
        }

        $object_id = isset($context['gm2_context_object_id']) ? (int) $context['gm2_context_object_id'] : 0;
        $context_type = $context['gm2_context_type'] ?? '';

        if ($context_type === 'term') {
            $term = $context['gm2_context_term'] ?? null;
            if (!$term && $object_id) {
                $current_tax = $context['gm2_context_taxonomy'] ?? '';
                $term        = get_term($object_id, $current_tax ?: $taxonomy);
            }
            if ($term && !is_wp_error($term)) {
                if (($context['gm2_context_taxonomy'] ?? '') === $taxonomy) {
                    return $term->name;
                }
                $related = get_term($term->term_id, $taxonomy);
                if ($related && !is_wp_error($related)) {
                    return $related->name;
                }
            }
            return '';
        }

        if ($context_type === 'post' && $object_id) {
            $terms = wp_get_post_terms($object_id, $taxonomy, ['fields' => 'names']);
            if (!is_wp_error($terms) && !empty($terms)) {
                return $terms;
            }
        }

        return '';
    }

    private function resolve_field_token(string $field_key, array $context) {
        $field_key = trim($field_key);
        if ($field_key === '') {
            return '';
        }

        $object_id    = isset($context['gm2_context_object_id']) ? (int) $context['gm2_context_object_id'] : 0;
        $context_type = $context['gm2_context_type'] ?? 'post';
        if (!in_array($context_type, ['post', 'term', 'user', 'comment', 'option', 'site'], true)) {
            $context_type = 'post';
        }

        $value = \gm2_field($field_key, '', $object_id > 0 ? $object_id : null, $context_type);
        if ($value === '' || $value === null) {
            return '';
        }

        return $value;
    }

    private function resolve_location_token(string $segment, array $context) {
        $segment = trim($segment);
        if ($segment === '') {
            return '';
        }

        $field_key = 'location_' . $segment;
        $value     = $this->resolve_field_token($field_key, $context);
        if ($value !== '' && $value !== null) {
            return $value;
        }

        $option_value = get_option('gm2_' . $field_key, '');
        if ($option_value === '' || $option_value === null) {
            $option_value = get_option($field_key, '');
        }

        return $option_value;
    }

    private function format_dynamic_value($value): string {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $part = $this->format_dynamic_value($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
            return $parts ? implode(', ', $parts) : '';
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $value = (string) $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        } else {
            return '';
        }

        return sanitize_text_field($value);
    }

    private function get_post_context($post_id) {
        $post_type = get_post_type($post_id);
        $context = [
            '{{title}}'       => get_the_title($post_id),
            '{{url}}'         => get_permalink($post_id),
            '{{permalink}}'   => get_permalink($post_id),
            '{{description}}' => wp_strip_all_tags(get_post_meta($post_id, '_gm2_description', true) ?: get_the_excerpt($post_id)),
        ];
        $image = get_the_post_thumbnail_url($post_id, 'full');
        if ($image) {
            $context['{{image}}'] = $image;
        }
        $brand = get_post_meta($post_id, '_gm2_schema_brand', true);
        if (!$brand) {
            $brand = gm2_infer_brand_name($post_id);
        }
        if ($brand) {
            $context['{{brand}}'] = $brand;
        }
        $rating = get_post_meta($post_id, '_gm2_schema_rating', true);
        if ($rating) {
            $context['{{rating}}'] = $rating;
        }
        if (class_exists('WooCommerce')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $context['{{price}}']         = $product->get_price();
                $context['{{price_currency}}'] = get_woocommerce_currency();
                $context['{{availability}}']  = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
                $context['{{sku}}']           = $product->get_sku();
            }
        }
        $context['gm2_context_object_id'] = (int) $post_id;
        $context['gm2_context_type']      = 'post';
        $context['gm2_context_post_type'] = $post_type ?: '';
        $post_obj = get_post($post_id);
        if ($post_obj instanceof \WP_Post) {
            $context['gm2_context_post'] = $post_obj;
        }

        /**
         * Filter placeholder tokens for post-based schema output.
         *
         * @param array $context  Token map.
         * @param int    $post_id Post ID.
         */
        $context = apply_filters('gm2_schema_post_tokens', $context, $post_id);
        $context = apply_filters('gm2_schema_tokens', $context, [
            'type'      => 'post',
            'id'        => (int) $post_id,
            'post_type' => $post_type ?: '',
        ]);

        return $context;
    }

    private function get_term_context($term) {
        if (is_numeric($term)) {
            $term = get_term($term);
        }
        if (!$term || is_wp_error($term)) {
            return [];
        }
        $context = [
            '{{title}}'       => $term->name,
            '{{name}}'        => $term->name,
            '{{url}}'         => get_term_link($term),
            '{{permalink}}'   => get_term_link($term),
            '{{description}}' => wp_strip_all_tags($term->description),
        ];
        $brand = get_term_meta($term->term_id, '_gm2_schema_brand', true);
        if ($brand) {
            $context['{{brand}}'] = $brand;
        }
        $rating = get_term_meta($term->term_id, '_gm2_schema_rating', true);
        if ($rating) {
            $context['{{rating}}'] = $rating;
        }
        $context['gm2_context_object_id'] = (int) $term->term_id;
        $context['gm2_context_type']      = 'term';
        $context['gm2_context_taxonomy']  = $term->taxonomy;
        $context['gm2_context_term']      = $term;

        /**
         * Filter placeholder tokens for term-based schema output.
         *
         * @param array   $context Token map.
         * @param \WP_Term $term   Term object.
         */
        $context = apply_filters('gm2_schema_term_tokens', $context, $term);
        $context = apply_filters('gm2_schema_tokens', $context, [
            'type'     => 'term',
            'id'       => (int) $term->term_id,
            'taxonomy' => $term->taxonomy,
        ]);

        return $context;
    }

    public function run() {
        add_action('template_redirect', [$this, 'maybe_apply_redirects'], 0);
        add_action('template_redirect', [$this, 'log_404_url'], 99);
        add_action('wp_head', [$this, 'output_canonical_url'], 5);
        add_action('wp_head', [$this, 'output_hreflang_links'], 5);
        add_action('wp_head', [$this, 'output_meta_tags']);
        add_action('wp_head', [$this, 'output_search_console_meta']);
        add_action('wp_head', [$this, 'output_ga_tracking_code']);
        add_action('wp_head', [$this, 'output_custom_schema'], 20);
        add_action('wp_head', [$this, 'output_product_schema'], 20);
        add_action('wp_head', [$this, 'output_brand_schema'], 20);
        add_action('wp_head', [$this, 'output_organization_schema'], 20);
        add_action('wp_head', [$this, 'output_website_schema'], 20);
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

    public function maybe_apply_redirects() {
        if (is_admin()) {
            return;
        }

        $redirects = get_option('gm2_redirects', []);
        if (empty($redirects)) {
            return;
        }

        $request_uri = $this->get_sanitized_request_uri();
        $current_path = parse_url($request_uri, PHP_URL_PATH);
        $current = untrailingslashit(is_string($current_path) ? $current_path : '');
        foreach ($redirects as $r) {
            $source = untrailingslashit(parse_url($r['source'], PHP_URL_PATH));
            if ($source === $current) {
                $target = esc_url_raw($r['target']);
                $is_internal = !parse_url($target, PHP_URL_HOST) || strpos($target, home_url('/')) === 0;
                if ($is_internal) {
                    $type = (int) $r['type'];
                    $type = in_array($type, [301, 302], true) ? $type : 302;
                    wp_safe_redirect($target, $type);
                    exit;
                }
            }
        }
    }

    public function log_404_url() {
        if (is_404()) {
            $logs  = get_option('gm2_404_logs', []);
            $request_uri = $this->get_sanitized_request_uri();
            $path_value = parse_url($request_uri, PHP_URL_PATH);
            $path  = untrailingslashit(is_string($path_value) ? $path_value : '');
            if (!in_array($path, $logs, true)) {
                $logs[] = $path;
                if (count($logs) > 100) {
                    array_shift($logs);
                }
                update_option('gm2_404_logs', $logs);
            }
        }
    }

    /**
     * Retrieve the sanitized request URI for the current request.
     *
     * @return string
     */
    private function get_sanitized_request_uri() {
        if ($this->sanitized_request_uri === null) {
            $raw_request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
            $this->sanitized_request_uri = sanitize_text_field($raw_request_uri);
        }

        return $this->sanitized_request_uri;
    }

    /**
     * Resolve the display label for a post breadcrumb, using overrides when available.
     *
     * @param int|\WP_Post $post Post ID or object.
     * @return string
     */
    private function get_post_breadcrumb_name($post) {
        if ($post instanceof \WP_Post) {
            $post_obj = $post;
        } else {
            $post_obj = get_post($post);
        }

        if (!$post_obj instanceof \WP_Post) {
            return '';
        }

        $override = get_post_meta($post_obj->ID, '_gm2_breadcrumb_title', true);
        if (is_string($override)) {
            $override = sanitize_text_field($override);
            if ($override !== '') {
                return $override;
            }
        }

        return get_the_title($post_obj);
    }

    /**
     * Resolve the display label for a term breadcrumb, using overrides when available.
     *
     * @param int|\WP_Term $term     Term ID or object.
     * @param string        $taxonomy Optional taxonomy when a term ID is provided.
     * @return string
     */
    private function get_term_breadcrumb_name($term, string $taxonomy = '') {
        if ($term instanceof \WP_Term) {
            $term_obj = $term;
        } else {
            $term_id = absint($term);
            if ($term_id <= 0) {
                return '';
            }
            if ($taxonomy !== '') {
                $term_obj = get_term($term_id, $taxonomy);
            } else {
                $term_obj = get_term($term_id);
            }
        }

        if (!$term_obj || is_wp_error($term_obj)) {
            return '';
        }

        $override = get_term_meta($term_obj->term_id, '_gm2_breadcrumb_title', true);
        if (is_string($override)) {
            $override = sanitize_text_field($override);
            if ($override !== '') {
                return $override;
            }
        }

        return $term_obj->name;
    }

    /**
     * Generate an array of breadcrumb items for the current request.
     *
     * Each item in the array must contain `name` and `url` keys. The final
     * array can be modified via the `gm2_breadcrumb_items` filter to allow
     * external code to customize or replace the breadcrumb trail.
     *
     * @return array<int, array{name:string, url:string}> Breadcrumb items.
     */
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
            $breadcrumbs = apply_filters('gm2_breadcrumb_items', $breadcrumbs);
            $this->breadcrumbs_cache = $breadcrumbs;

            return $this->breadcrumbs_cache;
        }

        if (is_singular()) {
            $post      = get_queried_object();
            $ancestors = get_post_ancestors($post);
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor) {
                $breadcrumbs[] = [
                    'name' => $this->get_post_breadcrumb_name($ancestor),
                    'url'  => get_permalink($ancestor),
                ];
            }
            $breadcrumbs[] = [
                'name' => $this->get_post_breadcrumb_name($post),
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
                                'name' => $this->get_term_breadcrumb_name($ancestor),
                                'url'  => $ancestor_link,
                            ];
                        }
                    }
                }
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    $breadcrumbs[] = [
                        'name' => $this->get_term_breadcrumb_name($term),
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

        $breadcrumbs = apply_filters('gm2_breadcrumb_items', $breadcrumbs);

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

            if (!$title || !$description) {
                $pt              = get_post_type($post_id);
                $title_templates = get_option('gm2_title_templates', []);
                $desc_templates  = get_option('gm2_description_templates', []);
                $replacements    = [
                    '{site_name}'    => get_bloginfo('name'),
                    '{post_title}'   => get_the_title($post_id),
                    '{post_excerpt}' => wp_strip_all_tags(get_the_excerpt($post_id)),
                ];

                $title_template = $title_templates[$pt] ?? '';
                /**
                 * Filters the meta title template used for fallback generation for a post type.
                 *
                 * The stored template for the post type is provided as the default value.
                 *
                 * @param string $title_template Template string retrieved from plugin settings.
                 * @param int    $post_id        The current post ID.
                 */
                $title_template = apply_filters("gm2_meta_title_template_{$pt}", $title_template, $post_id);
                if (null === $title_template) {
                    $title_template = $title_templates[$pt] ?? '';
                }

                $description_template = $desc_templates[$pt] ?? '';
                /**
                 * Filters the meta description template used for fallback generation for a post type.
                 *
                 * The stored template for the post type is provided as the default value.
                 *
                 * @param string $description_template Template string retrieved from plugin settings.
                 * @param int    $post_id              The current post ID.
                 */
                $description_template = apply_filters("gm2_meta_description_template_{$pt}", $description_template, $post_id);
                if (null === $description_template) {
                    $description_template = $desc_templates[$pt] ?? '';
                }

                if (!$title && !empty($title_template)) {
                    $title = strtr($title_template, $replacements);
                }
                if (!$description && !empty($description_template)) {
                    $description = strtr($description_template, $replacements);
                }
            }

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
        if (apply_filters('gm2_seo_disable_output', false)) {
            return;
        }
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
        if (get_option('gm2_meta_keywords_enabled', '0') === '1') {
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
        }
        $canonical   = $data['canonical'];
        $og_image_id = $data['og_image'];
        if (!$og_image_id && is_singular()) {
            $og_image_id = get_post_thumbnail_id();
        }
        $og_image_url = $og_image_id ? wp_get_attachment_url($og_image_id) : '';
        $og_image_url = apply_filters('gm2_og_image_url', $og_image_url, $og_image_id, $data);
        $card        = $og_image_url ? 'summary_large_image' : 'summary';
        $twitter_site    = trim(get_option('gm2_twitter_site', ''));
        $twitter_creator = trim(get_option('gm2_twitter_creator', ''));

        // Output the canonical link tag first if it isn't already hooked.
        if (!has_action('wp_head', [$this, 'output_canonical_url']) && !apply_filters('gm2_seo_disable_canonical', false)) {
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

        $og_html = '';
        $og_html .= '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        $og_html .= '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        $og_html .= '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        $og_html .= '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";
        $site_name = get_bloginfo('name');
        $locale    = str_replace('_', '-', get_locale());
        $og_html  .= '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        $og_html  .= '<meta property="og:locale" content="' . esc_attr($locale) . '" />' . "\n";
        if ($og_image_url) {
            $og_html .= '<meta property="og:image" content="' . esc_url($og_image_url) . '" />' . "\n";
        }

        $tw_html  = '';
        if ($og_image_url) {
            $tw_html .= '<meta name="twitter:image" content="' . esc_url($og_image_url) . '" />' . "\n";
        }
        $tw_html .= '<meta name="twitter:card" content="' . esc_attr($card) . '" />' . "\n";
        $tw_html .= '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        $tw_html .= '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        if ($twitter_site) {
            $tw_html .= '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '" />' . "\n";
        }
        if ($twitter_creator) {
            $tw_html .= '<meta name="twitter:creator" content="' . esc_attr($twitter_creator) . '" />' . "\n";
        }

        $head_buffer = '';
        if (ob_get_level() > 0) {
            $buffer_contents = ob_get_contents();
            if (is_string($buffer_contents)) {
                $head_buffer = $buffer_contents;
            }
        }

        $has_existing_social_meta = did_action('wpseo_head') > 0;
        if (!$has_existing_social_meta && $head_buffer !== '') {
            $has_existing_social_meta = preg_match('/<meta\s+property=["\']og:/i', $head_buffer) === 1
                || preg_match('/<meta\s+name=["\']twitter:/i', $head_buffer) === 1;
        }

        $has_existing_social_meta = apply_filters(
            'gm2_seo_has_existing_social_meta',
            $has_existing_social_meta,
            $head_buffer,
            $data
        );

        $should_output_social_meta = !$has_existing_social_meta;
        $should_output_social_meta = apply_filters(
            'gm2_seo_should_output_social_meta',
            $should_output_social_meta,
            $data,
            $head_buffer,
            $has_existing_social_meta
        );

        $social_html = '';
        if ($should_output_social_meta) {
            $disable_og = apply_filters('gm2_seo_disable_og_tags', false);
            if (!$disable_og) {
                $social_html .= apply_filters(
                    'gm2_seo_og_meta_html',
                    $og_html,
                    $data,
                    $head_buffer,
                    $has_existing_social_meta,
                    $should_output_social_meta
                );
            }

            $disable_twitter = apply_filters('gm2_seo_disable_twitter_tags', false);
            if (!$disable_twitter) {
                $social_html .= apply_filters(
                    'gm2_seo_twitter_meta_html',
                    $tw_html,
                    $data,
                    $head_buffer,
                    $has_existing_social_meta,
                    $should_output_social_meta
                );
            }
        }

        $social_html = apply_filters(
            'gm2_seo_social_meta_html',
            $social_html,
            $data,
            $head_buffer,
            $has_existing_social_meta,
            $should_output_social_meta,
            $og_html,
            $tw_html
        );

        $html .= $social_html;

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
        if (!$brand) {
            $brand = gm2_infer_brand_name($post_id);
        }
        $rating      = $overrides['schema_rating'] ?? get_post_meta($post_id, '_gm2_schema_rating', true);
        $context     = $this->get_post_context($post_id);
        $custom_tpls = get_option('gm2_custom_schema', []);

        if ($schema_type && !in_array($schema_type, ['product', 'article'], true) && isset($custom_tpls[$schema_type]['json'])) {
            $data = json_decode($custom_tpls[$schema_type]['json'], true);
            if (is_array($data)) {
                $schemas[] = $this->replace_placeholders($data, $context);
            }
        } elseif ($schema_type === 'product' || (!$schema_type && class_exists('WooCommerce') && get_post_type($post_id) === 'product')) {
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
                $schemas[] = $this->replace_placeholders($data, $context);
            }
        } elseif (!$schema_type && $post->post_type === 'page') {
            $data = [
                '@context' => 'https://schema.org/',
                '@type'    => 'WebPage',
                'name'     => get_the_title($post_id),
                'url'      => get_permalink($post_id),
            ];
            $schemas[] = $this->replace_placeholders($data, $context);
        } elseif ($schema_type === 'article' || (!$schema_type && $post->post_type === 'post')) {
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
            $schemas[] = $this->replace_placeholders($data, $context);
        }

        if ($brand) {
            $schemas[] = $this->replace_placeholders([
                '@context' => 'https://schema.org/',
                '@type'    => 'Brand',
                'name'     => $brand,
            ], $context);
        }

        if ($rating) {
            $schemas[] = $this->replace_placeholders([
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
            ], $context);
        } elseif (class_exists('WooCommerce') && get_post_type($post_id) === 'product') {
            $product = wc_get_product($post_id);
            if ($product) {
                $pr = $product->get_average_rating();
                if ($pr) {
                    $schemas[] = $this->replace_placeholders([
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
                    ], $context);
                }
            }
        }

        return $schemas;
    }

    public function generate_term_schema_data($term_id, $taxonomy, $overrides = []) {
        $schemas = [];
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return $schemas;
        }

        $schema_type = $overrides['schema_type'] ?? get_term_meta($term_id, '_gm2_schema_type', true);
        $brand       = $overrides['schema_brand'] ?? get_term_meta($term_id, '_gm2_schema_brand', true);
        $rating      = $overrides['schema_rating'] ?? get_term_meta($term_id, '_gm2_schema_rating', true);
        $context     = $this->get_term_context($term);
        $custom_tpls = get_option('gm2_custom_schema', []);

        if ($schema_type && !in_array($schema_type, ['product', 'article'], true) && isset($custom_tpls[$schema_type]['json'])) {
            $data = json_decode($custom_tpls[$schema_type]['json'], true);
            if (is_array($data)) {
                $schemas[] = $this->replace_placeholders($data, $context);
            }
        } elseif ($schema_type === 'product') {
            $data = [
                '@context' => 'https://schema.org/',
                '@type'    => 'Product',
                'name'     => $term->name,
                'description' => wp_strip_all_tags($term->description),
            ];
            if ($brand) {
                $data['brand'] = [
                    '@type' => 'Brand',
                    'name'  => $brand,
                ];
            }
            $schemas[] = $this->replace_placeholders($data, $context);
        } elseif ($schema_type === 'article') {
            $schemas[] = $this->replace_placeholders([
                '@context' => 'https://schema.org/',
                '@type'    => 'Article',
                'headline' => $term->name,
            ], $context);
        }

        if ($brand) {
            $schemas[] = $this->replace_placeholders([
                '@context' => 'https://schema.org/',
                '@type'    => 'Brand',
                'name'     => $brand,
            ], $context);
        }

        if ($rating) {
            $schemas[] = $this->replace_placeholders([
                '@context'      => 'https://schema.org/',
                '@type'         => 'Review',
                'itemReviewed'  => [
                    '@type' => 'Product',
                    'name'  => $term->name,
                ],
                'reviewRating'  => [
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => '5',
                ],
            ], $context);
        }

        return $schemas;
    }

    public function output_custom_schema() {
        if (!is_singular()) {
            return;
        }
        $post_id    = get_the_ID();
        $schema_type = get_post_meta($post_id, '_gm2_schema_type', true);
        if (!$schema_type || in_array($schema_type, ['product', 'article'], true)) {
            return;
        }
        $templates = get_option('gm2_custom_schema', []);
        if (!is_array($templates) || !isset($templates[$schema_type]['json'])) {
            return;
        }
        $data = json_decode($templates[$schema_type]['json'], true);
        if (!is_array($data)) {
            return;
        }
        $context = $this->get_post_context($post_id);
        $data    = $this->replace_placeholders($data, $context);
        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
        $this->primary_schema_output = true;
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

        $template = get_option('gm2_schema_template_product', wp_json_encode(self::default_product_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_product_template();
        }

        $data = $this->replace_placeholders($data, $this->get_post_context(get_the_ID()));

        if (empty($data['name'])) {
            $data['name'] = get_the_title();
        }
        $image = wp_get_attachment_url($product->get_image_id());
        if (empty($data['image']) && $image) {
            $data['image'] = $image;
        }
        if (empty($data['description'])) {
            $data['description'] = wp_strip_all_tags($product->get_description());
        }
        if (empty($data['sku'])) {
            $data['sku'] = $product->get_sku();
        }
        if ($product instanceof \WC_Product_Variable && $product->get_children()) {
            $offers = [
                '@type'         => 'AggregateOffer',
                'priceCurrency' => get_woocommerce_currency(),
                'lowPrice'      => $product->get_variation_price('min', false),
                'highPrice'     => $product->get_variation_price('max', false),
                'offerCount'    => count($product->get_children()),
                'offers'        => [],
            ];
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) {
                    continue;
                }
                $offers['offers'][] = [
                    '@type'        => 'Offer',
                    'price'        => $variation->get_price(),
                    'availability' => $variation->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url'          => get_permalink($variation->get_id()),
                ];
            }
            $data['offers'] = $offers;
        } else {
            $data['offers']                 = $data['offers'] ?? [];
            $data['offers']['@type']        = $data['offers']['@type'] ?? 'Offer';
            $data['offers']['priceCurrency'] = $data['offers']['priceCurrency'] ?? get_woocommerce_currency();
            $data['offers']['price']        = $data['offers']['price'] ?? $product->get_price();
            $data['offers']['availability'] = $data['offers']['availability'] ?? ($product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock');
            $data['offers']['url']          = $data['offers']['url'] ?? get_permalink();
        }

        $brand_meta = get_post_meta(get_the_ID(), '_gm2_schema_brand', true);
        if (isset($data['brand']) && isset($data['brand']['name']) && $data['brand']['name'] === '') {
            unset($data['brand']);
        }
        if ($brand_meta) {
            $data['brand'] = $data['brand'] ?? ['@type' => 'Brand'];
            $data['brand']['name'] = $brand_meta;
        }
        $avg_rating   = $product->get_average_rating();
        $review_count = $product->get_review_count();
        if ($avg_rating && $review_count) {
            $data['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $avg_rating,
                'reviewCount' => $review_count,
                'bestRating'  => '5',
            ];
        }
        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_article_schema() {
        if (get_option('gm2_schema_article', '1') !== '1') {
            return;
        }

        if (!is_singular('post')) {
            return;
        }
        $schema_type = get_post_meta(get_the_ID(), '_gm2_schema_type', true);
        if ($schema_type && $schema_type !== 'article') {
            return;
        }

        $image = get_the_post_thumbnail_url(get_the_ID(), 'full');

        $template = get_option('gm2_schema_template_article', wp_json_encode(self::default_article_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_article_template();
        }

        $data = $this->replace_placeholders($data, $this->get_post_context(get_the_ID()));

        if (empty($data['headline'])) {
            $data['headline'] = get_the_title();
        }
        $data['author'] = $data['author'] ?? ['@type' => 'Person'];
        if (empty($data['author']['name'])) {
            $data['author']['name'] = get_the_author();
        }
        if (empty($data['datePublished'])) {
            $data['datePublished'] = get_the_date('c');
        }

        if (empty($data['image']) && $image) {
            $data['image'] = $image;
        } elseif (empty($data['image'])) {
            unset($data['image']);
        }
        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_brand_schema() {
        if (get_option('gm2_schema_brand', '1') !== '1') {
            return;
        }

        $template = get_option('gm2_schema_template_brand', wp_json_encode(self::default_brand_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_brand_template();
        }

        $brand = is_singular() ? get_post_meta(get_the_ID(), '_gm2_schema_brand', true) : '';
        if (is_singular() && !$brand) {
            $brand = gm2_infer_brand_name(get_the_ID());
        }
        if ($brand) {
            $data['name'] = $brand;
            unset($data['description']);
            $data = $this->replace_placeholders($data, $this->get_post_context(get_the_ID()));
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

        $data['name']        = $term->name;
        $data['description'] = wp_strip_all_tags(term_description($term->term_id, $term->taxonomy));

        $data = $this->replace_placeholders($data, $this->get_term_context($term));
        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_organization_schema() {
        $name = trim(get_option('gm2_org_name', ''));
        $logo = trim(get_option('gm2_org_logo', ''));
        if ($name === '' || $logo === '') {
            return;
        }
        $schema = [
            '@context' => 'https://schema.org/',
            '@type'    => 'Organization',
            'name'     => $name,
            'url'      => home_url('/'),
            'logo'     => esc_url($logo),
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
    }

    public function output_website_schema() {
        $search = trim(get_option('gm2_site_search_url', ''));
        if ($search === '' || strpos($search, '{search_term_string}') === false) {
            return;
        }
        $schema = [
            '@context' => 'https://schema.org/',
            '@type'    => 'WebSite',
            'url'      => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => esc_url($search),
                'query-input' => 'required name=search_term_string',
            ],
        ];
        $name = trim(get_option('gm2_org_name', ''));
        if ($name === '') {
            $name = get_bloginfo('name');
        }
        if ($name) {
            $schema['name'] = $name;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
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

        $schema = $this->replace_placeholders($schema, $this->get_post_context(get_the_ID()));
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
        $this->primary_schema_output = true;
    }

    public function output_breadcrumb_schema() {
        if (get_option('gm2_schema_breadcrumbs', '1') !== '1') {
            return;
        }
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $schemas = $this->generate_term_schema_data($term->term_id, $term->taxonomy);
                foreach ($schemas as $schema) {
                    echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
                }
            }
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

        $template = get_option('gm2_schema_template_breadcrumb', wp_json_encode(self::default_breadcrumb_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_breadcrumb_template();
        }

        $data['itemListElement'] = $items;

        $context = is_singular() ? $this->get_post_context(get_the_ID()) : (isset($term) ? $this->get_term_context($term) : []);
        $data    = $this->replace_placeholders($data, $context);
        echo '<script type="application/ld+json">' . wp_json_encode($data) . "</script>\n";
    }

    public function output_review_schema() {
        if (get_option('gm2_schema_review', '1') !== '1') {
            return;
        }

        $template = get_option('gm2_schema_template_review', wp_json_encode(self::default_review_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_review_template();
        }

        $rating = get_post_meta(get_the_ID(), '_gm2_schema_rating', true);
        if ($rating) {
            $data['itemReviewed']           = $data['itemReviewed'] ?? ['@type' => 'Product'];
            $data['itemReviewed']['name']   = get_the_title();
            $data['reviewRating']           = $data['reviewRating'] ?? ['@type' => 'Rating', 'bestRating' => '5'];
            $data['reviewRating']['ratingValue'] = $rating;
            $data = $this->replace_placeholders($data, $this->get_post_context(get_the_ID()));
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

        $data['itemReviewed']         = $data['itemReviewed'] ?? ['@type' => 'Product'];
        $data['itemReviewed']['name'] = get_the_title();
        $data['reviewRating']         = $data['reviewRating'] ?? ['@type' => 'Rating', 'bestRating' => '5'];
        $data['reviewRating']['ratingValue'] = $rating;

        $data = $this->replace_placeholders($data, $this->get_post_context(get_the_ID()));
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

        $term = get_queried_object();
        if ($term && isset($term->term_id)) {
            $schemas = $this->generate_term_schema_data($term->term_id, $term->taxonomy);
            foreach ($schemas as $schema) {
                echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
            }
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

        $template = get_option('gm2_schema_template_taxonomy', wp_json_encode(self::default_taxonomy_template()));
        $data     = json_decode($template, true);
        if (!is_array($data)) {
            $data = self::default_taxonomy_template();
        }

        $data['name']          = single_term_title('', false);
        $data['numberOfItems'] = count($items);
        $data['itemListElement'] = $items;

        $data = $this->replace_placeholders($data, $this->get_term_context($term));
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
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $schemas = $this->generate_term_schema_data($term->term_id, $term->taxonomy);
                foreach ($schemas as $schema) {
                    $html .= '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
                }
            }
        }
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

    /**
     * Output <link rel="alternate" hreflang="..."> tags for localized URLs.
     *
     * Attempts to gather translations from common plugins like WPML or
     * Polylang. Developers can filter the final mapping via
     * `gm2_hreflang_urls`.
     */
    public function output_hreflang_links() {
        $urls    = [];
        $post_id = get_queried_object_id();

        // WPML integration.
        if (function_exists('icl_object_id')) {
            $languages = apply_filters('wpml_active_languages', null, 'skip_missing=0');
            if (is_array($languages)) {
                $permalink = $post_id ? get_permalink($post_id) : home_url('/');
                foreach ($languages as $lang => $details) {
                    $url = apply_filters('wpml_permalink', $permalink, $lang);
                    if ($url) {
                        $urls[$lang] = $url;
                    }
                }
            }
        } elseif (function_exists('pll_languages_list')) {
            // Polylang integration.
            $languages = pll_languages_list();
            if ($languages) {
                if ($post_id && function_exists('pll_get_post')) {
                    foreach ($languages as $lang) {
                        $tr_id = pll_get_post($post_id, $lang);
                        if ($tr_id) {
                            $urls[$lang] = get_permalink($tr_id);
                        }
                    }
                } elseif (function_exists('pll_home_url')) {
                    foreach ($languages as $lang) {
                        $urls[$lang] = pll_home_url($lang);
                    }
                }
            }
        }

        $urls = apply_filters('gm2_hreflang_urls', $urls, $post_id);
        if (empty($urls)) {
            return;
        }

        $html = '';
        foreach ($urls as $lang => $url) {
            if (!$lang || !$url) {
                continue;
            }
            $html .= sprintf('<link rel="alternate" hreflang="%s" href="%s" />\n', esc_attr($lang), esc_url($url));
        }

        echo apply_filters('gm2_hreflang_links_html', $html, $urls, $post_id);
    }

    public function output_canonical_url() {
        if (apply_filters('gm2_seo_disable_canonical', false)) {
            return;
        }
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
        if (get_option('gm2_sitemap_enabled', '1') === '1') {
            $path = get_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
            $relative = '/' . ltrim(str_replace(ABSPATH, '', $path), '/');
            $sitemap_url = home_url($relative);
            $output = 'Sitemap: ' . $sitemap_url . "\n" . ltrim($output);
        }

        $custom = get_option('gm2_robots_txt', '');
        if ($custom !== '') {
            if ($output !== '' && substr($output, -1) !== "\n") {
                $output .= "\n";
            }
            $output .= $custom;
        }
        return $output;
    }

    public function send_cache_headers() {
        do_action('gm2_set_cache_headers');
    }
}
