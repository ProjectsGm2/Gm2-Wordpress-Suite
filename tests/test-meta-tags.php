<?php
use Gm2\Gm2_SEO_Public;
class MetaTagsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        if (!function_exists('is_product')) {
            function is_product() {
                return true;
            }
        }
        if (!class_exists('WC_Product_Stub')) {
            eval('class WC_Product_Stub {
                private $type;
                private $parent_id;
                public function __construct($type = "simple", $parent_id = 0) { $this->type = $type; $this->parent_id = $parent_id; }
                public function get_image_id() { return 0; }
                public function get_description() { return "Sample description"; }
                public function get_sku() { return "SKU"; }
                public function get_price() { return "10"; }
                public function is_in_stock() { return true; }
                public function get_average_rating() { return 4; }
                public function is_type($t) { return $this->type === $t; }
                public function get_parent_id() { return $this->parent_id; }
            }');
        }
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) {
                $type = get_post_meta($id, 'wc_type', true) ?: 'simple';
                $parent = intval(get_post_meta($id, 'parent_id', true));
                return new WC_Product_Stub($type, $parent);
            }
        }
        if (!function_exists('get_woocommerce_currency')) {
            function get_woocommerce_currency() { return 'USD'; }
        }
    }
    public function test_output_meta_tags_for_post_without_title_support() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Custom Title');
        update_post_meta($post_id, '_gm2_description', 'Custom Description');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        remove_theme_support('title-tag');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('content="Custom Description"', $output);
        add_theme_support('title-tag');
    }

    public function test_output_meta_tags_for_post_with_title_support() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Custom Title');
        update_post_meta($post_id, '_gm2_description', 'Custom Description');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_theme_support('title-tag');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringNotContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('content="Custom Description"', $output);
    }

    public function test_output_meta_tags_for_product_post() {
        register_post_type('product');
        $post_id = self::factory()->post->create([
            'post_type'    => 'product',
            'post_title'   => 'Product Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Product Title');
        update_post_meta($post_id, '_gm2_description', 'Product Description');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta property="og:title" content="Product Title"', $output);
        $this->assertStringContainsString('<meta name="twitter:title" content="Product Title"', $output);
        $this->assertStringContainsString('content="Product Description"', $output);
    }

    public function test_output_meta_tags_for_brand_term() {
        register_taxonomy('brand', 'post');
        $term_id = self::factory()->term->create([
            'taxonomy' => 'brand',
            'name'     => 'Brand One',
        ]);
        update_term_meta($term_id, '_gm2_title', 'Brand Title');
        update_term_meta($term_id, '_gm2_description', 'Brand Description');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_term_link($term_id, 'brand'));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta property="og:title" content="Brand Title"', $output);
        $this->assertStringContainsString('<meta name="twitter:title" content="Brand Title"', $output);
        $this->assertStringContainsString('content="Brand Description"', $output);
    }

    public function test_noindex_nofollow_outputs_correct_robots_meta() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_noindex', '1');
        update_post_meta($post_id, '_gm2_nofollow', '1');
        update_post_meta($post_id, '_gm2_canonical', 'https://example.com/canonical');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow"', $output);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/canonical" />', $output);
    }

    public function test_max_preview_directives_appended_to_robots() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Robots',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_max_snippet', '50');
        update_post_meta($post_id, '_gm2_max_image_preview', 'large');
        update_post_meta($post_id, '_gm2_max_video_preview', '10');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('max-snippet:50', $output);
        $this->assertStringContainsString('max-image-preview:large', $output);
        $this->assertStringContainsString('max-video-preview:10', $output);
    }

    public function test_keywords_meta_tag_outputs_when_present() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'KW Post',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_focus_keywords', 'alpha, beta');
        update_post_meta($post_id, '_gm2_long_tail_keywords', 'gamma');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta name="keywords" content="alpha, beta, gamma"', $output);
    }

    public function test_keywords_meta_tag_omitted_when_empty() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'No KW',
            'post_content' => 'Content',
        ]);

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('name="keywords"', $output);
    }

    public function test_custom_og_image_in_meta_tags() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Image Post',
            'post_content' => 'Content',
        ]);

        $filename = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($filename, $post_id);
        update_post_meta($post_id, '_gm2_og_image', $attachment_id);

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $url = wp_get_attachment_url($attachment_id);
        $this->assertStringContainsString('property="og:image" content="' . esc_url($url) . '"', $output);
        $this->assertStringContainsString('name="twitter:image" content="' . esc_url($url) . '"', $output);
    }

    public function test_meta_tags_for_custom_post_type() {
        register_post_type('book');
        $post_id = self::factory()->post->create([
            'post_type'    => 'book',
            'post_title'   => 'Book',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Book Title');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('content="Book Title"', $output);
    }

    public function test_variation_canonical_points_to_parent() {
        register_post_type('product');

        $parent_id = self::factory()->post->create([
            'post_type'  => 'product',
            'post_title' => 'Parent',
        ]);

        $variation_id = self::factory()->post->create([
            'post_type'  => 'product',
            'post_title' => 'Variation',
        ]);
        update_post_meta($variation_id, 'wc_type', 'variation');
        update_post_meta($variation_id, 'parent_id', $parent_id);

        update_option('gm2_variation_canonical_parent', '1');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($variation_id));
        setup_postdata(get_post($variation_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<link rel="canonical" href="' . esc_url(get_permalink($parent_id)) . '" />', $output);
    }

    public function test_manual_variation_canonical_not_overridden() {
        register_post_type('product');

        $parent_id = self::factory()->post->create([
            'post_type'  => 'product',
            'post_title' => 'Parent',
        ]);

        $variation_id = self::factory()->post->create([
            'post_type'  => 'product',
            'post_title' => 'Variation',
        ]);
        update_post_meta($variation_id, 'wc_type', 'variation');
        update_post_meta($variation_id, 'parent_id', $parent_id);
        update_post_meta($variation_id, '_gm2_canonical', 'https://example.com/custom');

        update_option('gm2_variation_canonical_parent', '1');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($variation_id));
        setup_postdata(get_post($variation_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/custom" />', $output);
    }

    public function test_title_description_template_fallback() {
        register_post_type('book');
        update_option('gm2_title_templates', ['book' => 'Book: {post_title} | {site_name}']);
        update_option('gm2_description_templates', ['book' => 'About {post_title}: {post_excerpt}']);
        $post_id = self::factory()->post->create([
            'post_type'    => 'book',
            'post_title'   => 'My Book',
            'post_content' => 'Great story',
            'post_excerpt' => 'Great story',
        ]);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        remove_theme_support('title-tag');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        add_theme_support('title-tag');
        $blog = get_bloginfo('name');
        $this->assertStringContainsString('<title>Book: My Book | ' . $blog . '</title>', $output);
        $this->assertStringContainsString('content="About My Book: Great story"', $output);
    }

    public function test_social_meta_skipped_when_wpseo_head_has_run() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Yoast Conflict',
            'post_content' => 'Content',
        ]);

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        $previous_count = isset($GLOBALS['wp_actions']['wpseo_head']) ? $GLOBALS['wp_actions']['wpseo_head'] : null;
        do_action('wpseo_head');

        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        if ($previous_count !== null) {
            $GLOBALS['wp_actions']['wpseo_head'] = $previous_count;
        } else {
            unset($GLOBALS['wp_actions']['wpseo_head']);
        }

        $this->assertStringNotContainsString('property="og:title"', $output);
        $this->assertStringNotContainsString('name="twitter:title"', $output);
    }

    public function test_social_meta_skipped_when_existing_tags_detected_in_buffer() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Existing Meta',
            'post_content' => 'Content',
        ]);

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        ob_start();
        echo '<meta property="og:title" content="Existing OG" />' . "\n";
        echo '<meta name="twitter:title" content="Existing Twitter" />' . "\n";
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('Existing OG', $output);
        $this->assertStringContainsString('Existing Twitter', $output);
        $this->assertStringNotContainsString('property="og:type"', $output);
        $this->assertStringNotContainsString('name="twitter:card"', $output);
    }

    public function test_social_meta_replacement_filter_can_restore_markup() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Filter Social',
            'post_content' => 'Content',
        ]);

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        $filter = function ($social_html, $data, $head_buffer, $has_existing, $should_output, $og_html, $tw_html) {
            if ($has_existing && !$should_output) {
                return '<!-- replaced -->' . $og_html . $tw_html;
            }
            return $social_html;
        };
        add_filter('gm2_seo_social_meta_html', $filter, 10, 7);

        ob_start();
        echo '<meta property="og:title" content="Existing OG" />' . "\n";
        $seo->output_meta_tags();
        $output = ob_get_clean();

        remove_filter('gm2_seo_social_meta_html', $filter, 10);

        $this->assertStringContainsString('<!-- replaced -->', $output);
        $this->assertStringContainsString('property="og:type"', $output);
        $this->assertStringContainsString('name="twitter:card"', $output);
    }

    public function test_filters_disable_meta_output() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Filtered',
            'post_content' => 'Content',
        ]);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_filter('gm2_seo_disable_canonical', '__return_true');
        add_filter('gm2_seo_disable_og_tags', '__return_true');
        add_filter('gm2_seo_disable_twitter_tags', '__return_true');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringNotContainsString('rel="canonical"', $output);
        $this->assertStringNotContainsString('og:title', $output);
        $this->assertStringNotContainsString('twitter:title', $output);
    }

    public function test_disable_entire_output() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'No Output',
            'post_content' => 'Content',
        ]);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_filter('gm2_seo_disable_output', '__return_true');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }
}

