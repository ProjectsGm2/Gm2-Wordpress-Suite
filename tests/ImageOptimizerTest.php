<?php
use Gm2\AE_SEO_Image_Optimizer;
use Gm2\AESEO_LCP_Optimizer;

/**
 * Image optimizer integration tests.
 */
class ImageOptimizerTest extends WP_UnitTestCase {
    public function set_up() : void {
        parent::set_up();
        AESEO_LCP_Optimizer::boot();
        AE_SEO_Image_Optimizer::boot();
    }

    /**
     * Ensure attachment images are wrapped in a picture element.
     */
    public function test_attachment_image_wrapped_in_picture(): void {
        $id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $html = wp_get_attachment_image($id, 'thumbnail');
        $this->assertStringContainsString('<picture>', $html);
        $this->assertMatchesRegularExpression('/image\/(avif|webp)/', $html);
    }

    /**
     * Ensure images inside post content are converted.
     */
    public function test_content_images_wrapped_in_picture(): void {
        $id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $src = wp_get_attachment_url($id);
        $content = sprintf('<p>Test</p><img class="wp-image-%d" src="%s" />', $id, $src);
        $filtered = apply_filters('the_content', $content);
        $this->assertStringContainsString('<picture>', $filtered);
        $this->assertMatchesRegularExpression('/image\/(avif|webp)/', $filtered);
    }
}
