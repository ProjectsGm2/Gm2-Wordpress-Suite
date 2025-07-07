<?php
use Gm2\Gm2_SEO_Public;
class BreadcrumbsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        remove_all_actions('wp_footer');
    }

    public function test_footer_breadcrumbs_disabled() {
        update_option('gm2_show_footer_breadcrumbs', '0');
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $this->assertFalse( has_action('wp_footer', [$seo, 'output_breadcrumbs']) );
    }

    public function test_footer_breadcrumbs_enabled() {
        update_option('gm2_show_footer_breadcrumbs', '1');
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $this->assertNotFalse( has_action('wp_footer', [$seo, 'output_breadcrumbs']) );
    }

    public function test_shortcode_outputs_ordered_list_with_json_ld() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        $output = $seo->gm2_breadcrumbs_shortcode();

        $this->assertStringContainsString('<ol>', $output);
        $this->assertStringContainsString('</ol>', $output);
        $this->assertStringContainsString('<script type="application/ld+json">', $output);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $json = $m[1] ?? '';
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('BreadcrumbList', $data['@type']);
    }
}
