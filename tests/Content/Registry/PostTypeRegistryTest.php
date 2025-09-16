<?php

use Gm2\Content\Model\Definition;
use Gm2\Content\Registry\PostTypeRegistry;
use InvalidArgumentException;
use RuntimeException;

class PostTypeRegistryTest extends WP_UnitTestCase
{
    private PostTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('gm2_cp_register_type')) {
            require_once GM2_PLUGIN_DIR . 'includes/class-gm2-cp-register.php';
        }

        $this->registry = new PostTypeRegistry();
        delete_option('gm2_custom_posts_config');
    }

    protected function tearDown(): void
    {
        foreach (['library_book', 'legacy_book'] as $slug) {
            if (post_type_exists($slug)) {
                unregister_post_type($slug);
            }
        }

        delete_option('gm2_custom_posts_config');

        parent::tearDown();
    }

    public function test_register_builds_expected_arguments(): void
    {
        $capturedArgs = null;
        $capturedSlug = null;

        $callback = static function (array $args, string $slug, $legacyArgs = null): array use (&$capturedArgs, &$capturedSlug) {
            $capturedArgs = $args;
            $capturedSlug = $slug;

            return $args;
        };

        add_filter('gm2_register_post_type_args', $callback, 10, 3);

        $definition = new Definition(
            ' library book ',
            ' Library Book ',
            ' Library Books ',
            [
                'menu_name' => ' Library Items ',
            ],
            ['title', 'editor', 'editor', 123],
            ' Library Archive ',
            ' dashicons-book ',
            [
                'slug' => ' Library & Books ',
                'with_front' => '0',
                'hierarchical' => '1',
            ],
            ' story ',
            ['genre', 'topic', 'genre', null],
            [
                'public' => false,
            ]
        );

        $this->registry->register($definition);

        remove_filter('gm2_register_post_type_args', $callback, 10);

        $this->assertSame('library_book', $capturedSlug);
        $this->assertIsArray($capturedArgs);

        $this->assertSame('dashicons-book', $capturedArgs['menu_icon']);
        $this->assertTrue($capturedArgs['show_in_rest']);
        $this->assertTrue($capturedArgs['map_meta_cap']);
        $this->assertSame('story', $capturedArgs['capability_type']);
        $this->assertSame(['title', 'editor'], $capturedArgs['supports']);
        $this->assertSame([
            'slug' => sanitize_title(' Library & Books '),
            'with_front' => false,
            'hierarchical' => true,
        ], $capturedArgs['rewrite']);
        $this->assertSame(['genre', 'topic'], $capturedArgs['taxonomies']);
        $this->assertSame(sanitize_title(' Library Archive '), $capturedArgs['has_archive']);
        $this->assertFalse($capturedArgs['public']);
        $this->assertSame('Library Items', $capturedArgs['labels']['menu_name']);
        $this->assertSame('Library Book', $capturedArgs['labels']['singular_name']);
        $this->assertSame('Library Books', $capturedArgs['labels']['name']);

        $registered = get_post_type_object('library_book');
        $this->assertNotNull($registered);
        $this->assertTrue($registered->show_in_rest);
        $this->assertTrue($registered->map_meta_cap);
        $this->assertSame('story', $registered->capability_type);
    }

    public function test_register_rejects_invalid_slug(): void
    {
        $definition = new Definition('***', 'Example', 'Examples');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The post type key cannot be empty.');

        $this->registry->register($definition);
    }

    public function test_register_detects_existing_slug(): void
    {
        gm2_cp_register_type('legacy_book', ['public' => true]);

        $definition = new Definition('legacy_book', 'Legacy Book', 'Legacy Books');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Post type "legacy_book" already exists.');

        $this->registry->register($definition);
    }
}
