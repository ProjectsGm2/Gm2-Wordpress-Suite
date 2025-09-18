<?php

declare(strict_types=1);

use Gm2\Performance\QueryCache;
use Gm2\WP_Query_Adapter;

class QueryCacheTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_cache_flush();
        QueryCache::init();
    }

    protected function tearDown(): void
    {
        remove_all_filters('gm2_query_cache_bypass');
        remove_all_filters('gm2_query_cache_use_transients');
        remove_all_filters('gm2_query_cache_expiration');
        wp_cache_flush();
        parent::tearDown();
    }

    public function test_query_builder_primes_and_hits_cache(): void
    {
        self::factory()->post->create(['post_status' => 'publish']);

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];

        $query  = \Gm2\gm2_run_query($args);
        $config = $query->get('gm2_query_cache');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('key', $config);
        $this->assertFalse((bool) $query->get('gm2_query_cache_hit'));

        $payload = QueryCache::get($config['key']);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('posts', $payload);
        $this->assertSame($query->posts[0]->ID, $payload['posts'][0]->ID);

        $second = \Gm2\gm2_run_query($args);
        $this->assertTrue((bool) $second->get('gm2_query_cache_hit'));
        $this->assertSame($query->posts[0]->ID, $second->posts[0]->ID);
    }

    public function test_pagination_creates_unique_keys(): void
    {
        self::factory()->post->create_many(3, ['post_status' => 'publish']);

        $argsPage1 = [
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'paged'          => 1,
        ];

        $argsPage2 = $argsPage1;
        $argsPage2['paged'] = 2;

        $pageOne   = \Gm2\gm2_run_query($argsPage1);
        $configOne = $pageOne->get('gm2_query_cache');
        $this->assertIsArray($configOne);

        $pageTwo   = \Gm2\gm2_run_query($argsPage2);
        $configTwo = $pageTwo->get('gm2_query_cache');
        $this->assertIsArray($configTwo);

        $this->assertNotSame($configOne['key'], $configTwo['key']);

        $payloadOne = QueryCache::get($configOne['key']);
        $payloadTwo = QueryCache::get($configTwo['key']);
        $this->assertSame($pageOne->posts[0]->ID, $payloadOne['posts'][0]->ID);
        $this->assertSame($pageTwo->posts[0]->ID, $payloadTwo['posts'][0]->ID);

        $repeatSecondPage = \Gm2\gm2_run_query($argsPage2);
        $this->assertTrue((bool) $repeatSecondPage->get('gm2_query_cache_hit'));
    }

    public function test_cache_bypass_filter(): void
    {
        self::factory()->post->create(['post_status' => 'publish']);

        add_filter(
            'gm2_query_cache_bypass',
            static function ($bypass, array $args, array $context) {
                if (($context['source'] ?? '') === 'gm2_query_builder') {
                    return true;
                }

                return $bypass;
            },
            10,
            3
        );

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 1,
        ];

        $query = \Gm2\gm2_run_query($args);
        $this->assertNull($query->get('gm2_query_cache'));

        $key = QueryCache::generateKey($args, [
            'source'  => 'gm2_query_builder',
            'adapter' => WP_Query_Adapter::class,
        ]);
        $this->assertNull(QueryCache::get($key));
    }

    public function test_post_save_invalidation_removes_cache(): void
    {
        $postIds = self::factory()->post->create_many(2, ['post_status' => 'publish']);

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 2,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];

        $query  = \Gm2\gm2_run_query($args);
        $config = $query->get('gm2_query_cache');
        $this->assertIsArray($config);
        $this->assertNotNull(QueryCache::get($config['key']));

        wp_update_post([
            'ID'           => $postIds[0],
            'post_content' => 'Updated content',
        ]);

        $this->assertNull(QueryCache::get($config['key']));
    }

    public function test_term_edit_invalidation_removes_cache(): void
    {
        $category = self::factory()->term->create(['taxonomy' => 'category']);
        $postIds  = self::factory()->post->create_many(2, ['post_status' => 'publish']);
        wp_set_object_terms($postIds[0], [$category], 'category');
        wp_set_object_terms($postIds[1], [$category], 'category');

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 2,
            'tax_query'      => [
                [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => [$category],
                ],
            ],
        ];

        $query  = \Gm2\gm2_run_query($args);
        $config = $query->get('gm2_query_cache');
        $this->assertIsArray($config);
        $this->assertNotNull(QueryCache::get($config['key']));

        wp_update_term($category, 'category', ['name' => 'Updated Category']);

        $this->assertNull(QueryCache::get($config['key']));
    }
}
