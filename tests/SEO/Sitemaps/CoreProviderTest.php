<?php

use Gm2\SEO\Sitemaps\CoreProvider;

class CoreProviderTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('gm2_sitemap_enabled', '1');
        CoreProvider::register();
    }

    protected function tearDown(): void
    {
        remove_filter('gm2_supported_post_types', [$this, 'includeListingPostType']);
        parent::tearDown();
    }

    public function test_provider_registration(): void
    {
        $providers = wp_get_sitemap_providers();

        $this->assertArrayHasKey('gm2-posts', $providers, 'GM2 posts provider not registered.');
        $this->assertArrayHasKey('gm2-taxonomies', $providers, 'GM2 taxonomy provider not registered.');
        $this->assertInstanceOf(WP_Sitemaps_Provider::class, $providers['gm2-posts']);
    }

    public function test_query_args_filter_respects_skip_statuses(): void
    {
        $this->registerListingPostType();

        $args = [
            'post_status' => ['publish', 'draft', 'private', 'pending'],
        ];

        $filtered = apply_filters('wp_sitemaps_posts_query_args', $args, 'listing');

        $this->assertSame(['publish'], $filtered['post_status']);

        unregister_post_type('listing');
    }

    public function test_lastmod_and_image_are_populated(): void
    {
        $this->registerListingPostType();

        $attachment_id = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

        $post_id = self::factory()->post->create([
            'post_type'   => 'listing',
            'post_status' => 'publish',
            'post_date'   => '2023-01-01 12:00:00',
        ]);

        set_post_thumbnail($post_id, $attachment_id);
        update_post_meta($post_id, 'address', '123 Main St');
        update_post_meta($post_id, 'city', 'Springfield');
        update_post_meta($post_id, 'region', 'IL');

        $provider = new WP_Sitemaps_Posts();
        $entries  = $provider->get_url_list(1, 'listing');

        $this->assertNotEmpty($entries, 'Sitemap entries should include listing content.');
        $entry = $entries[0];

        $expected_lastmod = get_post_modified_time('c', true, $post_id);
        $this->assertSame($expected_lastmod, $entry['lastmod']);

        $this->assertArrayHasKey('images', $entry);
        $this->assertNotEmpty($entry['images']);

        $image_entry = $entry['images'][0];
        $this->assertSame(wp_get_attachment_url($attachment_id), $image_entry['loc']);
        $this->assertStringContainsString('Springfield', $image_entry['caption']);

        wp_delete_post($post_id, true);
        wp_delete_attachment($attachment_id, true);
        unregister_post_type('listing');
    }

    private function registerListingPostType(): void
    {
        register_post_type('listing', [
            'label'               => 'Listing',
            'public'              => true,
            'show_ui'             => true,
            'exclude_from_search' => false,
            'supports'            => ['title', 'editor', 'thumbnail'],
        ]);

        add_filter('gm2_supported_post_types', [$this, 'includeListingPostType']);
    }

    public function includeListingPostType(array $types): array
    {
        $types[] = 'listing';

        return array_values(array_unique($types));
    }

}
