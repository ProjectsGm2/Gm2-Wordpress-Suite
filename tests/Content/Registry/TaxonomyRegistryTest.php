<?php

use Gm2\Content\Registry\TaxonomyRegistry;
use InvalidArgumentException;
use RuntimeException;

class TaxonomyRegistryTest extends WP_UnitTestCase
{
    private TaxonomyRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('gm2_cp_register_taxonomy')) {
            require_once GM2_PLUGIN_DIR . 'includes/class-gm2-cp-register.php';
        }

        $this->registry = new TaxonomyRegistry();
        delete_option('gm2_custom_posts_config');
    }

    protected function tearDown(): void
    {
        foreach (['topic-items', 'legacy_topic'] as $slug) {
            if (taxonomy_exists($slug)) {
                unregister_taxonomy($slug);
            }
        }

        delete_option('gm2_custom_posts_config');

        parent::tearDown();
    }

    public function test_register_builds_expected_arguments(): void
    {
        $capturedArgs = null;
        $capturedSlug = null;
        $capturedObjectTypes = null;

        $callback = static function (array $args, string $slug, array $objectTypes) use (&$capturedArgs, &$capturedSlug, &$capturedObjectTypes): array {
            $capturedArgs = $args;
            $capturedSlug = $slug;
            $capturedObjectTypes = $objectTypes;

            return $args;
        };

        add_filter('gm2/content/taxonomy_args', $callback, 10, 3);

        $this->registry->register(
            ' topic-items ',
            ' Topic Item ',
            ' Topic Items ',
            ['book', 'library_book', 'book', 123],
            [
                'labels' => [
                    'menu_name' => ' Topics ',
                ],
                'rewrite' => [
                    'slug' => ' Topics & More ',
                    'with_front' => '',
                    'hierarchical' => '1',
                ],
                'hierarchical' => '1',
                'capability_type' => ' topic ',
                'public' => false,
            ]
        );

        remove_filter('gm2/content/taxonomy_args', $callback, 10);

        $this->assertSame('topic-items', $capturedSlug);
        $this->assertSame(['book', 'library_book'], $capturedObjectTypes);
        $this->assertTrue($capturedArgs['show_in_rest']);
        $this->assertSame([
            'slug' => sanitize_title(' Topics & More '),
            'with_front' => false,
            'hierarchical' => true,
        ], $capturedArgs['rewrite']);
        $this->assertTrue($capturedArgs['hierarchical']);
        $this->assertSame([
            'manage_terms' => 'manage_topic_terms',
            'edit_terms' => 'edit_topic_terms',
            'delete_terms' => 'delete_topic_terms',
            'assign_terms' => 'assign_topic_terms',
        ], $capturedArgs['capabilities']);
        $this->assertFalse($capturedArgs['public']);
        $this->assertSame('Topic Items', $capturedArgs['labels']['name']);
        $this->assertSame('Topic Item', $capturedArgs['labels']['singular_name']);
        $this->assertSame('Topics', $capturedArgs['labels']['menu_name']);

        $taxonomy = get_taxonomy('topic-items');
        $this->assertNotNull($taxonomy);
        $this->assertTrue($taxonomy->show_in_rest);
        $this->assertTrue($taxonomy->hierarchical);
    }

    public function test_register_rejects_invalid_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The taxonomy key cannot be empty.');

        $this->registry->register('***', 'Example', 'Examples', ['post']);
    }

    public function test_register_detects_existing_slug(): void
    {
        gm2_cp_register_taxonomy('legacy_topic', 'post', ['public' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Taxonomy "legacy_topic" already exists.');

        $this->registry->register('legacy_topic', 'Legacy Topic', 'Legacy Topics', ['post']);
    }
}
