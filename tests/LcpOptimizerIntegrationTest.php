<?php
namespace Gm2 {

    // Counter for tracking getimagesize calls within the optimizer.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    global $aeseo_getimagesize_calls;
    $aeseo_getimagesize_calls = 0;

    /**
     * Proxy for PHP's getimagesize to count invocations from the optimizer.
     *
     * @param string $filename Image file path.
     * @param array  $imageinfo Optional image information.
     * @return array|false
     */
    function getimagesize($filename, &$imageinfo = null) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
        global $aeseo_getimagesize_calls;
        $aeseo_getimagesize_calls++;
        return \getimagesize($filename, $imageinfo);
    }
}

namespace {

use Gm2\AESEO_LCP_Optimizer;
use Gm2\Gm2_SEO_Admin;

/**
 * LCP optimizer integration tests.
 */
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
            'settings'        => $settings === null ? $defaults : $settings,
            'candidate'       => [],
            'current_image'   => [],
            'done'            => false,
            'optimized'       => [],
            'preconnect_added' => false,
            'preload_printed'  => false,
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
        remove_action('wp_head', [ AESEO_LCP_Optimizer::class, 'maybe_print_preload' ], 5);
        remove_filter('wp_resource_hints', [ AESEO_LCP_Optimizer::class, 'maybe_add_preconnect' ], 10);
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
        $this->setExpectedIncorrectUsage('wp_get_loading_optimization_attributes');

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
     * Content filter should add missing dimensions for the LCP img inside a picture tag.
     */
    public function test_content_filter_adds_dimensions_inside_picture(): void {
        $this->reset_optimizer_state();
        $this->remove_optimizer_hooks();
        $this->setExpectedIncorrectUsage('wp_get_loading_optimization_attributes');

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src           = wp_get_attachment_url($attachment_id);

        // Prime candidate so the real image URL is recognized.
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" />');

        $post_id = self::factory()->post->create([
            'post_content' => '<picture><source srcset="' . esc_url($src) . ' 1x" /><img src="' . esc_url($src) . '" srcset="' . esc_url($src) . ' 1x" loading="lazy" /></picture>',
        ]);

        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::boot();

        $filtered = apply_filters('the_content', get_post($post_id)->post_content);
        $this->assertStringContainsString('fetchpriority="high"', $filtered);
        $this->assertMatchesRegularExpression('/<img[^>]*width="\d+"[^>]*>/', $filtered);
        $this->assertMatchesRegularExpression('/<img[^>]*height="\d+"[^>]*>/', $filtered);
    }

    /**
     * Optimizer should populate missing dimensions and update metadata.
     */
    public function test_optimizer_populates_dimensions_and_updates_metadata(): void {
        $this->reset_optimizer_state();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

        $meta = wp_get_attachment_metadata($attachment_id);
        unset($meta['width'], $meta['height']);
        wp_update_attachment_metadata($attachment_id, $meta);
        clean_post_cache($attachment_id);
        wp_cache_delete($attachment_id, 'post_meta');

        $src = wp_get_attachment_url($attachment_id);
        $post_id = self::factory()->post->create(['post_content' => '']);
        $this->go_to(get_permalink($post_id));

        global $aeseo_getimagesize_calls;
        $aeseo_getimagesize_calls = 0;
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" />');

        $html = wp_get_attachment_image($attachment_id, 'full');

        $file = get_attached_file($attachment_id);
        $size = getimagesize($file);

        $this->assertStringContainsString('width="' . $size[0] . '"', $html);
        $this->assertStringContainsString('height="' . $size[1] . '"', $html);
        $this->assertSame(1, $aeseo_getimagesize_calls);

        $updated = wp_get_attachment_metadata($attachment_id);
        $this->assertSame($size[0], (int) $updated['width']);
        $this->assertSame($size[1], (int) $updated['height']);

        $aeseo_getimagesize_calls = 0;
        $ref = new \ReflectionClass(AESEO_LCP_Optimizer::class);
        $method = $ref->getMethod('get_attachment_dimensions');
        $method->setAccessible(true);
        $dims = $method->invoke(null, $attachment_id);
        $this->assertSame($size[0], $dims['width']);
        $this->assertSame($size[1], $dims['height']);
        $this->assertSame(0, $aeseo_getimagesize_calls);
    }

    /**
     * maybe_use_picture should wrap the LCP image in a picture tag with next-gen sources.
     */
    public function test_maybe_use_picture_generates_nextgen_sources(): void {
        $this->reset_optimizer_state();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src           = wp_get_attachment_url($attachment_id);
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" width="100" height="100" />');

        add_filter(
            'wp_image_editor_supports',
            static function ($supports, $args) {
                if (isset($args['mime_type']) && in_array($args['mime_type'], [ 'image/avif', 'image/webp' ], true)) {
                    return true;
                }
                return $supports;
            },
            10,
            2
        );

        $meta    = wp_get_attachment_metadata($attachment_id);
        $uploads = wp_get_upload_dir();
        $base    = trailingslashit($uploads['basedir']) . trailingslashit(dirname($meta['file']));
        $file    = pathinfo($meta['file'], PATHINFO_FILENAME);
        touch($base . $file . '.avif');
        touch($base . $file . '.webp');

        $img_html = wp_get_attachment_image($attachment_id, 'full');
        $result   = AESEO_LCP_Optimizer::maybe_use_picture($img_html, $attachment_id, 'full', false, []);

        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString('<source type="image/avif"', $result);
        $this->assertStringContainsString('<source type="image/webp"', $result);
    }

    /**
     * maybe_use_picture should skip processing when source has a webp extension without a type attribute.
     */
    public function test_maybe_use_picture_skips_when_source_has_webp_extension_without_type(): void {
        $this->reset_optimizer_state();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src           = wp_get_attachment_url($attachment_id);
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" width="100" height="100" />');

        $webp_src    = preg_replace('/\.jpg$/', '.webp', $src);
        $picture_html = '<picture><source srcset="' . esc_url($webp_src) . '"><img src="' . esc_url($src) . '" width="100" height="100" /></picture>';

        $result = AESEO_LCP_Optimizer::maybe_use_picture($picture_html, $attachment_id, 'full', false, []);

        $this->assertSame($picture_html, $result);
    }

    /**
     * Optimizer should populate dimensions for LCP image inside a picture tag.
     */
    public function test_optimizer_adds_dimensions_inside_picture_and_updates_metadata(): void {
        $this->reset_optimizer_state();
        $this->remove_optimizer_hooks();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $meta = wp_get_attachment_metadata($attachment_id);
        unset($meta['width'], $meta['height']);
        wp_update_attachment_metadata($attachment_id, $meta);
        clean_post_cache($attachment_id);
        wp_cache_delete($attachment_id, 'post_meta');

        $src = wp_get_attachment_url($attachment_id);
        $this->setExpectedIncorrectUsage('wp_get_loading_optimization_attributes');

        global $aeseo_getimagesize_calls;
        $aeseo_getimagesize_calls = 0;
        AESEO_LCP_Optimizer::detect_from_content('<img src="' . esc_url($src) . '" />');

        $post_id = self::factory()->post->create([
            'post_content' => '<picture><source srcset="' . esc_url($src) . ' 1x" /><img src="' . esc_url($src) . '" srcset="' . esc_url($src) . ' 1x" loading="lazy" /></picture>',
        ]);

        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::boot();

        $filtered = apply_filters('the_content', get_post($post_id)->post_content);

        $file = get_attached_file($attachment_id);
        $size = getimagesize($file);

        $this->assertMatchesRegularExpression('/<img[^>]*width="' . $size[0] . '"/', $filtered);
        $this->assertMatchesRegularExpression('/<img[^>]*height="' . $size[1] . '"/', $filtered);
        $this->assertSame(1, $aeseo_getimagesize_calls);

        $updated = wp_get_attachment_metadata($attachment_id);
        $this->assertSame($size[0], (int) $updated['width']);
        $this->assertSame($size[1], (int) $updated['height']);

        $aeseo_getimagesize_calls = 0;
        $ref = new \ReflectionClass(AESEO_LCP_Optimizer::class);
        $method = $ref->getMethod('get_attachment_dimensions');
        $method->setAccessible(true);
        $dims = $method->invoke(null, $attachment_id);
        $this->assertSame($size[0], $dims['width']);
        $this->assertSame($size[1], $dims['height']);
        $this->assertSame(0, $aeseo_getimagesize_calls);
    }

    /**
     * Override should take precedence over cached or featured images and be sanitized.
     */
    public function test_override_wins_over_cached_candidate(): void {
        $this->reset_optimizer_state();
        $attachment1 = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $attachment2 = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/codeispoetry.png');
        $post_id = self::factory()->post->create();
        set_post_thumbnail($post_id, $attachment1);
        $this->go_to(get_permalink($post_id));
        AESEO_LCP_Optimizer::maybe_prime_candidate();
        $candidate1 = AESEO_LCP_Optimizer::get_lcp_candidate();
        $this->assertSame($attachment1, $candidate1['attachment_id']);
        $admin = new Gm2_SEO_Admin();
        $_POST = [
            'gm2_seo_nonce' => wp_create_nonce('gm2_save_seo_meta'),
            'aeseo_lcp_meta_nonce' => wp_create_nonce('aeseo_lcp_meta'),
            'aeseo_lcp_override' => ' ' . $attachment2 . ' junk',
        ];
        $admin->save_post_meta($post_id, get_post($post_id));
        $_POST = [];
        $this->reset_optimizer_state();
        $this->go_to(get_permalink($post_id));
        $candidate2 = AESEO_LCP_Optimizer::get_lcp_candidate();
        $this->assertSame($attachment2, $candidate2['attachment_id']);
    }

    /**
     * save_post_meta should sanitize override values.
     */
    public function test_save_post_meta_sanitizes_override(): void {
        $admin = new Gm2_SEO_Admin();
        $post_id = self::factory()->post->create();
        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

        $_POST = [
            'gm2_seo_nonce' => wp_create_nonce('gm2_save_seo_meta'),
            'aeseo_lcp_meta_nonce' => wp_create_nonce('aeseo_lcp_meta'),
            'aeseo_lcp_override' => 'https://example.com/img.jpg<script>',
        ];
        $admin->save_post_meta($post_id, get_post($post_id));
        $this->assertSame('https://example.com/img.jpg', get_post_meta($post_id, '_aeseo_lcp_override', true));

        $_POST = [
            'gm2_seo_nonce' => wp_create_nonce('gm2_save_seo_meta'),
            'aeseo_lcp_meta_nonce' => wp_create_nonce('aeseo_lcp_meta'),
            'aeseo_lcp_override' => ' ' . $attachment_id . 'junk',
        ];
        $admin->save_post_meta($post_id, get_post($post_id));
        $this->assertSame((string) $attachment_id, get_post_meta($post_id, '_aeseo_lcp_override', true));

        $_POST = [];
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

}
