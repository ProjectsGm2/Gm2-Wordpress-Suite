<?php

declare(strict_types=1);

use Elementor\Modules\DynamicTags\Module;
use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Elementor\DynamicTags\Tag\AddressMapLink;
use Gm2\Elementor\DynamicTags\Tag\ComputedValue;
use Gm2\Elementor\DynamicTags\Tag\FieldValue;

class DynamicTagsTest extends WP_UnitTestCase
{
    private int $bookId;

    private int $relatedBookId;

    private int $imageId;

    private ?WP_Post $originalPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type('library_book', [ 'public' => true, 'label' => 'Library Book' ]);
        update_option('date_format', 'F j, Y');
        update_option('time_format', 'g:i a');
        update_option('timezone_string', 'America/New_York');
        $this->originalPost = $GLOBALS['post'] ?? null;

        $this->registerFieldGroups();
        $this->createContent();
    }

    protected function tearDown(): void
    {
        unregister_post_type('library_book');
        delete_option('gm2_field_groups');
        $GLOBALS['post'] = $this->originalPost;
        wp_reset_postdata();
        parent::tearDown();
    }

    public function test_group_registration_registers_tags(): void
    {
        $module = new class extends Module {
            public array $groups = [];
            public array $tags   = [];

            public function register_group($name, $args = [])
            {
                $this->groups[$name] = $args;
            }

            public function register_tag($tag)
            {
                $this->tags[] = $tag;
            }
        };

        do_action('elementor/dynamic_tags/register', $module);

        $groupName = GM2_Dynamic_Tag_Group::instance()->getGroupName();
        $this->assertArrayHasKey($groupName, $module->groups);
        $this->assertContains(FieldValue::class, $module->tags);
        $this->assertContains(ComputedValue::class, $module->tags);
        $this->assertContains(AddressMapLink::class, $module->tags);
    }

    public function test_field_value_registers_controls(): void
    {
        $tag = new FieldValue();
        $tag->register_controls();
        $controls = $tag->get_registered_controls();

        $this->assertArrayHasKey('post_type', $controls);
        $this->assertArrayHasKey('field_key', $controls);
        $this->assertArrayHasKey('fallback', $controls);
        $this->assertArrayHasKey('options', $controls['field_key']);
        $this->assertArrayHasKey('library_details::price', $controls['field_key']['options']);
    }

    public function test_field_value_formats_currency_datetime_media_and_relationship(): void
    {
        $this->setCurrentPost($this->bookId);

        $priceTag = $this->createFieldTag('library_details::price');
        $this->assertSame('$123.45', $priceTag->get_value());

        $dateTag = $this->createFieldTag('library_details::launch_date');
        $expectedDate = wp_date(
            get_option('date_format') . ' ' . get_option('time_format') . ' T',
            strtotime('2024-05-01T15:30:00+00:00')
        );
        $this->assertSame($expectedDate, $dateTag->get_value());

        $imageTag = $this->createFieldTag('library_details::cover_image');
        $imageValue = $imageTag->get_value();
        $this->assertIsArray($imageValue);
        $this->assertSame($this->imageId, $imageValue['id']);
        $this->assertSame(wp_get_attachment_url($this->imageId), $imageValue['url']);

        $relationshipTag = $this->createFieldTag('library_details::related_books');
        $relatedTitle    = wp_strip_all_tags(get_post($this->relatedBookId)->post_title);
        $this->assertSame($relatedTitle, $relationshipTag->get_value());

        $textTag = $this->createFieldTag('library_details::blurb');
        $this->assertSame('Exclusive Deals', $textTag->get_value());
    }

    public function test_field_value_returns_fallback_for_unknown_field(): void
    {
        $this->setCurrentPost($this->bookId);
        $tag = new FieldValue();
        $tag->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::missing_field',
            'fallback'  => 'Not Provided',
        ]);

        $this->assertSame('Not Provided', $tag->get_value());
    }

    public function test_computed_value_resolves_dependency_graph(): void
    {
        $this->setCurrentPost($this->bookId);

        $totalTag = new ComputedValue();
        $totalTag->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::total',
            'fallback'  => '0',
        ]);
        $this->assertSame('$130.00', $totalTag->get_value());

        $grandTag = new ComputedValue();
        $grandTag->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::grand_total',
            'fallback'  => '0',
        ]);
        $this->assertSame('$260.00', $grandTag->get_value());
    }

    public function test_address_map_link_generates_map_url_and_fallback(): void
    {
        $this->setCurrentPost($this->bookId);

        $tag = new AddressMapLink();
        $tag->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::map_coordinates',
            'fallback'  => 'https://example.com/fallback-map',
        ]);

        $value = $tag->get_value();
        $this->assertIsArray($value);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=37.422,-122.0841',
            $value['url']
        );

        delete_post_meta($this->bookId, 'map_coordinates');
        $this->setCurrentPost($this->bookId);
        $fallbackValue = $tag->get_value();
        $this->assertSame('https://example.com/fallback-map', $fallbackValue['url']);
    }

    public function test_field_value_uses_loop_context_in_archive(): void
    {
        $query = new WP_Query([
            'post_type'      => 'library_book',
            'p'              => $this->bookId,
            'posts_per_page' => 1,
        ]);

        $tag = new FieldValue();
        $tag->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::price',
            'fallback'  => 'N/A',
        ]);

        $this->assertTrue($query->have_posts());
        while ($query->have_posts()) {
            $query->the_post();
            $this->assertSame('$123.45', $tag->get_value());
        }
        wp_reset_postdata();
    }

    private function createFieldTag(string $fieldKey): FieldValue
    {
        $tag = new FieldValue();
        $tag->set_settings([
            'post_type' => 'library_book',
            'field_key' => $fieldKey,
            'fallback'  => '',
        ]);

        return $tag;
    }

    private function setCurrentPost(int $postId): void
    {
        $GLOBALS['post'] = get_post($postId);
    }

    private function registerFieldGroups(): void
    {
        update_option('gm2_field_groups', [
            'library_details' => [
                'title'    => 'Library Details',
                'contexts' => [ 'post' => [ 'library_book' ] ],
                'fields'   => [
                    'price' => [
                        'type'    => 'currency',
                        'label'   => 'Price',
                        'symbol'  => '$',
                        'precision' => 2,
                    ],
                    'tax' => [
                        'type'    => 'currency',
                        'label'   => 'Tax',
                        'symbol'  => '$',
                        'precision' => 2,
                    ],
                    'launch_date' => [
                        'type'  => 'datetime_tz',
                        'label' => 'Launch Date',
                    ],
                    'cover_image' => [
                        'type'  => 'image',
                        'label' => 'Cover Image',
                    ],
                    'related_books' => [
                        'type'     => 'relationship_post',
                        'label'    => 'Related Books',
                        'multiple' => true,
                    ],
                    'map_coordinates' => [
                        'type'  => 'geopoint',
                        'label' => 'Map Coordinates',
                    ],
                    'blurb' => [
                        'type'  => 'text',
                        'label' => 'Blurb',
                    ],
                    'total' => [
                        'type'     => 'computed',
                        'label'    => 'Total',
                        'computed' => [
                            'dependencies' => [ 'price', 'tax' ],
                            'formula'      => '{price} + {tax}',
                            'return_type'  => 'number',
                            'format'       => 'currency',
                            'symbol'       => '$',
                            'precision'    => 2,
                        ],
                    ],
                    'grand_total' => [
                        'type'     => 'computed',
                        'label'    => 'Grand Total',
                        'computed' => [
                            'dependencies' => [ 'total' ],
                            'formula'      => '{total} * 2',
                            'return_type'  => 'number',
                            'format'       => 'currency',
                            'symbol'       => '$',
                            'precision'    => 2,
                        ],
                    ],
                ],
            ],
        ]);

        GM2_Dynamic_Tag_Group::instance()->refresh();
    }

    private function createContent(): void
    {
        $this->relatedBookId = self::factory()->post->create([
            'post_type'  => 'library_book',
            'post_title' => 'Related <strong>Book</strong>',
        ]);

        $this->imageId = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );

        $this->bookId = self::factory()->post->create([
            'post_type'  => 'library_book',
            'post_title' => 'Primary Book',
            'meta_input' => [
                'price'           => '123.45',
                'tax'             => '6.55',
                'launch_date'     => '2024-05-01T15:30:00+00:00',
                'cover_image'     => $this->imageId,
                'related_books'   => [ $this->relatedBookId ],
                'map_coordinates' => [ 'lat' => 37.4220, 'lng' => -122.0841 ],
                'blurb'           => '<strong>Exclusive</strong> Deals',
            ],
        ]);
    }
}
