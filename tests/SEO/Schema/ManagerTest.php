<?php

use Gm2\SEO\Schema\Manager;
use Gm2\SEO\Schema\Mapper\DirectoryMapper;

class SchemaManagerTest extends WP_UnitTestCase
{
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = [];
        Manager::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->registered as $slug) {
            if (post_type_exists($slug)) {
                unregister_post_type($slug);
            }
        }

        Manager::reset();
        update_option('gm2_schema_directory', '1');
        \Gm2\SEO\bootstrap_schema_manager();
        parent::tearDown();
    }

    private function registerPostType(string $slug): void
    {
        if (!post_type_exists($slug)) {
            register_post_type($slug, ['public' => true, 'has_archive' => true]);
            $this->registered[] = $slug;
        }
    }

    private function buildDirectoryPost(): int
    {
        $this->registerPostType('listing');
        $post_id = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Test Listing',
            'post_content' => 'Sample description.',
        ]);

        update_post_meta($post_id, 'address', '123 Main St');
        update_post_meta($post_id, 'city', 'Metropolis');

        return $post_id;
    }

    public function test_manager_respects_option_toggle(): void
    {
        $post_id = $this->buildDirectoryPost();
        update_option('gm2_schema_directory', '1');

        $manager = new Manager([ new DirectoryMapper() ]);
        $manager->register();

        $this->go_to(get_permalink($post_id));
        ob_start();
        $manager->render('wp_head');
        $output = ob_get_clean();
        $this->assertStringContainsString('LocalBusiness', $output);

        $manager->unregister();
        update_option('gm2_schema_directory', '0');

        $manager = new Manager([ new DirectoryMapper() ]);
        $manager->register();

        $this->go_to(get_permalink($post_id));
        ob_start();
        $manager->render('wp_head');
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function test_manager_detects_third_party_schema(): void
    {
        $post_id = $this->buildDirectoryPost();
        update_option('gm2_schema_directory', '1');

        $manager = new Manager([ new DirectoryMapper() ]);
        $manager->register();

        $this->go_to(get_permalink($post_id));
        do_action('wpseo_json_ld_output');

        ob_start();
        $manager->render('wp_head');
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function test_manager_outputs_once_for_head_and_footer(): void
    {
        $post_id = $this->buildDirectoryPost();
        update_option('gm2_schema_directory', '1');

        $manager = new Manager([ new DirectoryMapper() ]);
        $manager->register();

        $this->go_to(get_permalink($post_id));

        ob_start();
        $manager->render('wp_head');
        $head = ob_get_clean();

        ob_start();
        $manager->render('wp_footer');
        $footer = ob_get_clean();

        $combined = $head . $footer;
        $this->assertSame(1, substr_count($combined, '<script type="application/ld+json">'));
    }

    public function test_archive_output_emits_single_script(): void
    {
        $this->registerPostType('listing');
        $ids = [];
        $ids[] = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Listing One',
        ]);
        $ids[] = self::factory()->post->create([
            'post_type' => 'listing',
            'post_title' => 'Listing Two',
        ]);

        foreach ($ids as $id) {
            update_post_meta($id, 'address', '123 Main');
        }

        update_option('gm2_schema_directory', '1');
        $manager = new Manager([ new DirectoryMapper() ]);
        $manager->register();

        $this->go_to(home_url('/?post_type=listing'));

        ob_start();
        $manager->render('wp_head');
        $head = ob_get_clean();

        ob_start();
        $manager->render('wp_footer');
        $footer = ob_get_clean();

        $combined = $head . $footer;
        $this->assertSame(1, substr_count($combined, '<script type="application/ld+json">'));
        $this->assertStringContainsString('"ItemList"', $combined);
        $this->assertStringContainsString('Listing One', $combined);
        $this->assertStringContainsString('Listing Two', $combined);
    }
}
