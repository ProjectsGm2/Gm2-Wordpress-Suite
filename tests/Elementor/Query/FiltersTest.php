<?php

declare(strict_types=1);

class FiltersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        register_post_type('event', ['public' => true]);
        register_post_type('job', ['public' => true]);
        register_post_type('property', ['public' => true]);
        register_taxonomy('property_status', 'property', ['public' => true]);
        register_post_type('listing', ['public' => true]);
        register_post_type('course', ['public' => true]);
    }

    protected function tearDown(): void
    {
        unregister_post_type('event');
        unregister_post_type('job');
        unregister_post_type('property');
        unregister_taxonomy('property_status');
        unregister_post_type('listing');
        unregister_post_type('course');
        parent::tearDown();
    }

    public function test_upcoming_events_enforces_future_dates(): void
    {
        $query = new WP_Query();
        $query->set('posts_per_page', 0);

        do_action('elementor/query/gm2_upcoming_events', $query);

        $this->assertSame('event', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(6, $query->get('posts_per_page'));
        $this->assertSame('start_date', $query->get('meta_key'));
        $this->assertSame('meta_value', $query->get('orderby'));
        $this->assertSame('ASC', $query->get('order'));

        $metaQuery = $query->get('meta_query');
        $this->assertIsArray($metaQuery);
        $this->assertArrayHasKey(0, $metaQuery);
        $clause = $metaQuery[0];
        $this->assertSame('start_date', $clause['key']);
        $this->assertSame('>=', $clause['compare']);
        $this->assertSame('DATETIME', $clause['type']);
        $this->assertGreaterThanOrEqual(current_time('timestamp') - 60, strtotime($clause['value']));
    }

    public function test_past_events_use_descending_order(): void
    {
        $query = new WP_Query();

        do_action('elementor/query/gm2_past_events', $query);

        $this->assertSame('event', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(6, $query->get('posts_per_page'));
        $this->assertSame('start_date', $query->get('meta_key'));
        $this->assertSame('meta_value', $query->get('orderby'));
        $this->assertSame('DESC', $query->get('order'));

        $metaQuery = $query->get('meta_query');
        $this->assertSame('<', $metaQuery[0]['compare']);
    }

    public function test_open_jobs_apply_status_and_search(): void
    {
        $query = new WP_Query();
        $query->set('gm2_job_search', 'Engineer');

        do_action('elementor/query/gm2_open_jobs', $query);

        $this->assertSame('job', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(10, $query->get('posts_per_page'));
        $this->assertSame('date', $query->get('orderby'));
        $this->assertSame('DESC', $query->get('order'));
        $this->assertSame('Engineer', $query->get('s'));

        $metaQuery = $query->get('meta_query');
        $this->assertSame('status', $metaQuery[0]['key']);
        $this->assertSame('open', $metaQuery[0]['value']);
    }

    public function test_properties_for_sale_filter_by_status_taxonomy(): void
    {
        $query = new WP_Query();

        do_action('elementor/query/gm2_properties_sale', $query);

        $this->assertSame('property', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(12, $query->get('posts_per_page'));
        $this->assertSame('price', $query->get('meta_key'));
        $this->assertSame('meta_value_num', $query->get('orderby'));
        $this->assertSame('ASC', $query->get('order'));

        $taxQuery = $query->get('tax_query');
        $this->assertIsArray($taxQuery);
        $this->assertSame('property_status', $taxQuery[0]['taxonomy']);
        $this->assertSame(['for-sale'], $taxQuery[0]['terms']);
    }

    public function test_directory_nearby_adds_geo_bounding_box(): void
    {
        $query = new WP_Query();
        $query->set('gm2_lat', '51.5');
        $query->set('gm2_lng', '-0.1');
        $query->set('gm2_radius', '10');

        do_action('elementor/query/gm2_directory_nearby', $query);

        $this->assertSame('listing', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(12, $query->get('posts_per_page'));
        $this->assertSame('title', $query->get('orderby'));
        $this->assertSame('ASC', $query->get('order'));

        $metaQuery = $query->get('meta_query');
        $this->assertCount(2, array_filter($metaQuery, 'is_array'));
        $latClause = $metaQuery[0];
        $lngClause = $metaQuery[1];

        $this->assertSame('latitude', $latClause['key']);
        $this->assertSame('BETWEEN', $latClause['compare']);
        $this->assertCount(2, $latClause['value']);
        $this->assertLessThan($latClause['value'][1], 52);
        $this->assertGreaterThan($latClause['value'][0], 51);

        $this->assertSame('longitude', $lngClause['key']);
        $this->assertSame('BETWEEN', $lngClause['compare']);
        $this->assertCount(2, $lngClause['value']);
        $this->assertLessThan($lngClause['value'][1], 0.2);
        $this->assertGreaterThan($lngClause['value'][0], -0.3);
    }

    public function test_courses_active_apply_status_and_search_default(): void
    {
        $query = new WP_Query();
        $query->set('gm2_search', 'Design');

        do_action('elementor/query/gm2_courses_active', $query);

        $this->assertSame('course', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));
        $this->assertSame(9, $query->get('posts_per_page'));
        $this->assertSame('date', $query->get('orderby'));
        $this->assertSame('DESC', $query->get('order'));
        $this->assertSame('Design', $query->get('s'));

        $metaQuery = $query->get('meta_query');
        $this->assertSame('status', $metaQuery[0]['key']);
        $this->assertSame('active', $metaQuery[0]['value']);
    }
}
