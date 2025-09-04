<?php
/**
 * LCP optimizer integration tests.
 */

use Gm2\AESEO_LCP_Optimizer;

class LcpOptimizerIntegrationTest extends WP_UnitTestCase {

    /**
     * Reset optimizer state before each test.
     *
     * @param array|null $settings Optional settings to apply.
     */
    private function reset_optimizer_state(?array $settings = null): void {
        $defaults = [
            'remove_lazy_on_lcp'       => true,
            'add_fetchpriority_high'   => true,
            'force_width_height'       => true,
            'responsive_picture_nextgen' => true,
            'add_preconnect'           => true,
            'add_preload'              => true,
        ];
        $class = AESEO_LCP_Optimizer::class;
        $reflect = new ReflectionClass($class);
        $props = [
            'settings'      => $settings === null ? $defaults : $settings,
            'candidate'     => [],
            'current_image' => [],
            'done'          => false,
            'optimized'     => [],
        ];
        foreach ($props as $name => $value) {
            $prop = $reflect->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }

        update_option('gm2_enable_analytics', '0');
        remove_action('send_headers', [ \Gm2\AE_SEO_JS_Manager::class, 'send_server_timing' ], 999);
        global $wp_filter;
        if (isset($wp_filter['init'])) {
            foreach ($wp_filter['init']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $cb) {
                    if (
                        is_array($cb['function']) &&
                        is_object($cb['function'][0]) &&
                        get_class($cb['function'][0]) === 'Gm2\\Gm2_Analytics'
                    ) {
                        remove_action('init', $cb['function'], $priority);
                    }
                }
            }
        }
    }

    /**
     * Helper to remove optimizer hooks.
     */
    private function remove_optimizer_hooks(): void {
        remove_filter('pre_wp_get_loading_optimization_attributes', [ AESEO_LCP_Optimizer::class, 'capture_image_context' ], 10);
        remove_filter('wp_lazy_loading_enabled', [ AESEO_LCP_Optimizer::class, 'maybe_disable_lazy' ], 10);
        remove_filter('wp_get_attachment_image_attributes', [ AESEO_LCP_Optimizer::class, 'maybe_adjust_attributes' ], 10);
        remove_filter('wp_get_attachment_image', [ AESEO_LCP_Optimizer::class, 'maybe_use_picture' ], 10);
        remove_action('wp_head', [ AESEO_LCP_Optimizer::class, 'maybe_print_links' ], 5);
    }

    /**
     * Verify plugin activation runs without fatal errors on supported versions.
     */
    public function test_plugin_activates_without_errors_on_supported_versions(): void {
        if (PHP_VERSION_ID < 70400 || version_compare(get_bloginfo('version'), '5.8', '<')) {
            $this->markTestSkipped('Environment does not meet minimum versions.');
        }

        $this->assertTrue( function_exists('gm2_activate_plugin') );

        try {
            gm2_activate_plugin();
            $activated = true;
        } catch (Throwable $e) {
            $activated = false;
        }

        $this->assertTrue($activated, 'Plugin activation should not throw errors.');
    }

    /**
     * Ensure LCP candidate detection returns expected structure.
     */
    public function test_lcp_candidate_detection_structure(): void {
        $this->reset_optimizer_state();
        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src = wp_get_attachment_url($attachment_id);
        $post_id = self::factory()->post->create(['post_content' => '']);
        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" width="100" height="100" />');
        $candidate = AESEO_LCP_Optimizer::get_lcp_candidate();

        $this->assertIsArray($candidate);
        $this->assertSame('img', $candidate['source']);
        $this->assertSame($attachment_id, $candidate['attachment_id']);
        $this->assertNotEmpty($candidate['url']);
        $this->assertGreaterThan(0, $candidate['width']);
        $this->assertGreaterThan(0, $candidate['height']);
        $this->assertSame(wp_parse_url($candidate['url'], PHP_URL_HOST), $candidate['origin']);
        $this->assertFalse($candidate['is_background']);
    }

    /**
     * Detection should prefer featured image on singular posts.
     */
    public function test_detects_featured_image_on_singular(): void {
        $this->reset_optimizer_state();
        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $post_id = self::factory()->post->create();
        set_post_thumbnail($post_id, $attachment_id);
        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::maybe_prime_candidate();
        $candidate = AESEO_LCP_Optimizer::get_lcp_candidate();
        $this->assertSame($attachment_id, $candidate['attachment_id']);
    }

    /**
     * Detection should fall back to first content image when no featured image exists.
     */
    public function test_detects_first_content_image(): void {
        $this->reset_optimizer_state();
        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src = wp_get_attachment_url($attachment_id);
        $post_id = self::factory()->post->create([
            'post_content' => '<p><img src="' . esc_url($src) . '" width="100" height="100" /></p>',
        ]);
        $this->go_to(get_permalink($post_id));
        apply_filters('the_content', get_post($post_id)->post_content);
        $candidate = AESEO_LCP_Optimizer::get_lcp_candidate();
        $this->assertSame($attachment_id, $candidate['attachment_id']);
    }

    /**
     * Feature flags should toggle respective behaviors.
     */
    public function test_feature_flags_toggle_behaviors(): void {
        // Flags enabled.
        $this->reset_optimizer_state();
        $attachment1 = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src1 = wp_get_attachment_url($attachment1);
        $post1 = self::factory()->post->create([
            'post_content' => ''
        ]);
        $this->go_to(get_permalink($post1));
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src1) . '" width="100" height="100" />');
        $html1 = wp_get_attachment_image($attachment1, 'full');
        $this->assertStringNotContainsString('loading="lazy"', $html1);
        $this->assertStringContainsString('data-aeseo-lcp="1"', $html1);
        $this->assertStringContainsString('fetchpriority="high"', $html1);

        // Flags disabled.
        $settings = [
            'remove_lazy_on_lcp'       => false,
            'add_fetchpriority_high'   => false,
            'force_width_height'       => false,
            'responsive_picture_nextgen' => false,
            'add_preconnect'           => false,
            'add_preload'              => false,
        ];
        $this->reset_optimizer_state($settings);
        $attachment2 = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src2 = wp_get_attachment_url($attachment2);
        $post2 = self::factory()->post->create([
            'post_content' => ''
        ]);
        $this->go_to(get_permalink($post2));
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src2) . '" width="100" height="100" />');
        $html2 = wp_get_attachment_image($attachment2, 'full');
        $this->assertStringContainsString('loading="lazy"', $html2);
        $this->assertStringNotContainsString('fetchpriority="high"', $html2);
    }

    /**
     * WooCommerce main product image should not be lazy-loaded while thumbnails remain lazy.
     */
    public function test_woocommerce_main_image_not_lazy_and_thumbnails_lazy(): void {
        $this->reset_optimizer_state();

        register_post_type('product', [ 'public' => true ]);

        $main_id  = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $main_src = wp_get_attachment_url($main_id);
        $thumb_id  = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $thumb_src = wp_get_attachment_url($thumb_id);

        $product_id = self::factory()->post->create([
            'post_type' => 'product',
        ]);
        set_post_thumbnail($product_id, $main_id);
        update_post_meta($product_id, '_product_image_gallery', (string) $thumb_id);

        $this->go_to(get_permalink($product_id));
        do_action('woocommerce_before_single_product');

        $candidate = AESEO_LCP_Optimizer::get_lcp_candidate();
        $this->assertNotEmpty($candidate);
        $this->assertSame($main_id, $candidate['attachment_id']);

        $main_html = sprintf('<div><img src="%s" data-attachment-id="%d" loading="lazy" /></div>', esc_url($main_src), $main_id);
        $filtered_main = apply_filters('woocommerce_single_product_image_thumbnail_html', $main_html, $product_id);
        $this->assertStringNotContainsString('loading="lazy"', $filtered_main);
        $this->assertStringContainsString('data-aeseo-lcp="1"', $filtered_main);
        $this->assertStringContainsString('fetchpriority="high"', $filtered_main);
        $this->assertSame(1, substr_count($filtered_main, 'fetchpriority="high"'));

        $thumb_html = sprintf('<div><img src="%s" data-attachment-id="%d" loading="lazy" /></div>', esc_url($thumb_src), $thumb_id);
        $filtered_thumb = apply_filters('woocommerce_single_product_image_thumbnail_html', $thumb_html, $product_id);
        $this->assertStringContainsString('loading="lazy"', $filtered_thumb);
        $this->assertStringNotContainsString('data-aeseo-lcp="1"', $filtered_thumb);
        $this->assertStringNotContainsString('fetchpriority="high"', $filtered_thumb);

        unregister_post_type('product');
    }

    /**
     * Content filter should inject fetchpriority="high" when LCP image is printed directly.
     */
    public function test_content_filter_adds_fetchpriority_to_lcp_image(): void {
        $this->reset_optimizer_state();
        $this->remove_optimizer_hooks();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src = wp_get_attachment_url($attachment_id);
        $post_id = self::factory()->post->create([
            'post_content' => '<p><img src="' . esc_url($src) . '" width="100" height="100" loading="lazy" /></p>',
        ]);

        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::boot();

        $filtered = apply_filters('the_content', get_post($post_id)->post_content);
        $this->assertStringContainsString('fetchpriority="high"', $filtered);
        $this->assertSame(1, substr_count($filtered, 'fetchpriority="high"'));
    }

    /**
     * Hooks should run only on front-end requests.
     */
    public function test_hooks_run_only_on_frontend_requests(): void {
        // Front end.
        $this->remove_optimizer_hooks();
        set_current_screen('front');
        AESEO_LCP_Optimizer::boot();
        $frontend_check = function_exists('wp_frontend_request') ? wp_frontend_request() : !is_admin();
        $this->assertTrue($frontend_check);
        $this->assertNotFalse(has_filter('wp_lazy_loading_enabled', [ AESEO_LCP_Optimizer::class, 'maybe_disable_lazy' ]));

        // Admin area.
        $this->remove_optimizer_hooks();
        set_current_screen('dashboard');
        AESEO_LCP_Optimizer::boot();
        $admin_check = function_exists('wp_frontend_request') ? wp_frontend_request() : !is_admin();
        $this->assertFalse($admin_check);
        $this->assertFalse(has_filter('wp_lazy_loading_enabled', [ AESEO_LCP_Optimizer::class, 'maybe_disable_lazy' ]));
    }
}
