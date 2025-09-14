<?php
use Gm2\Gm2_CP_Schema;

class CPSchemaMappingsTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_cp_schema_map');
        foreach (['business', 'event'] as $pt) {
            if (post_type_exists($pt)) {
                unregister_post_type($pt);
            }
        }
        remove_filter('gm2_seo_cp_schema', '__return_true');
        parent::tearDown();
    }

    public function test_local_business_schema_outputs_mapped_values() {
        register_post_type('business');
        update_option('gm2_cp_schema_map', [
            'business' => [
                'type' => 'LocalBusiness',
                'map'  => [
                    'name' => 'business_name',
                    'address.streetAddress' => 'street',
                    'address.addressLocality' => 'city',
                ],
            ],
        ]);
        $post_id = self::factory()->post->create([
            'post_type' => 'business',
            'post_title' => 'Biz',
        ]);
        update_post_meta($post_id, 'business_name', 'Acme Co');
        update_post_meta($post_id, 'street', '123 Main');
        update_post_meta($post_id, 'city', 'Metropolis');
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertIsArray($data);
        $this->assertSame('LocalBusiness', $data['@type']);
        $this->assertSame('Acme Co', $data['name']);
        $this->assertSame('123 Main', $data['address']['streetAddress']);
        $this->assertSame('Metropolis', $data['address']['addressLocality']);
    }

    public function test_event_archive_outputs_item_list() {
        register_post_type('event', ['has_archive' => true]);
        update_option('gm2_cp_schema_map', [
            'event' => [
                'type' => 'Event',
                'map'  => [
                    'name'      => 'event_name',
                    'startDate' => 'start',
                    'endDate'   => 'end',
                ],
            ],
        ]);
        $ids = [];
        $ids[] = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'First Event',
        ]);
        $ids[] = self::factory()->post->create([
            'post_type' => 'event',
            'post_title' => 'Second Event',
        ]);
        update_post_meta($ids[0], 'event_name', 'Alpha');
        update_post_meta($ids[0], 'start', '2024-01-01');
        update_post_meta($ids[0], 'end', '2024-01-02');
        update_post_meta($ids[1], 'event_name', 'Beta');
        update_post_meta($ids[1], 'start', '2024-02-01');
        update_post_meta($ids[1], 'end', '2024-02-02');
        $this->go_to('/?post_type=event');
        ob_start();
        Gm2_CP_Schema::archive_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertIsArray($data);
        $this->assertSame('ItemList', $data['@type']);
        $this->assertCount(2, $data['itemListElement']);
        $this->assertSame('Alpha', $data['itemListElement'][0]['item']['name']);
        $this->assertSame('Event', $data['itemListElement'][0]['item']['@type']);
    }

    public function test_schema_disabled_via_filter_prevents_duplicates() {
        register_post_type('business');
        update_option('gm2_cp_schema_map', [
            'business' => [
                'type' => 'LocalBusiness',
                'map'  => [ 'name' => 'business_name' ],
            ],
        ]);
        $post_id = self::factory()->post->create([
            'post_type' => 'business',
            'post_title' => 'Biz',
        ]);
        update_post_meta($post_id, 'business_name', 'Solo');
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_filter('gm2_seo_cp_schema', '__return_true', 10, 3);
        ob_start();
        echo '<script type="application/ld+json">{"@type":"WebPage"}</script>';
        Gm2_CP_Schema::singular_schema();
        $output = ob_get_clean();
        $this->assertSame(1, substr_count($output, '<script type="application/ld+json">'));
        $this->assertStringNotContainsString('LocalBusiness', $output);
    }
}
