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

    public function test_breadcrumb_override_used_in_html_and_json_ld() {
        $parent_id = self::factory()->post->create([
            'post_title'   => 'Parent Title',
            'post_content' => 'Parent Content',
        ]);
        $child_id = self::factory()->post->create([
            'post_title'   => 'Child Title',
            'post_content' => 'Child Content',
            'post_parent'  => $parent_id,
        ]);

        update_post_meta($parent_id, '_gm2_breadcrumb_title', '  Parent Override  ');
        update_post_meta($child_id, '_gm2_breadcrumb_title', ' <b>Child Override</b> ');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($child_id));
        setup_postdata(get_post($child_id));

        $output = $seo->gm2_breadcrumbs_shortcode();

        $this->assertStringContainsString('Parent Override', $output);
        $this->assertStringContainsString('Child Override', $output);
        $this->assertStringNotContainsString('<b>', $output);

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $matches);
        $this->assertNotEmpty($matches[1]);
        $breadcrumb_json = $matches[1][count($matches[1]) - 1];
        $data = json_decode($breadcrumb_json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('itemListElement', $data);
        $names = array_column($data['itemListElement'], 'name');
        $this->assertContains('Parent Override', $names);
        $this->assertSame('Child Override', end($names));

        wp_reset_postdata();
    }

    public function test_breadcrumb_items_filter_modifies_output() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Filter Example',
            'post_content' => 'Content',
        ]);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));

        add_filter('gm2_breadcrumb_items', function ($items) {
            $items[] = [
                'name' => 'Filtered Item',
                'url'  => 'https://example.com/filtered',
            ];
            return $items;
        });

        $output = $seo->gm2_breadcrumbs_shortcode();
        $this->assertStringContainsString('Filtered Item', $output);
    }
}
