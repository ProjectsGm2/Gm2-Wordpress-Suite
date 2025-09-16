<?php

declare(strict_types=1);

use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Elementor\Widgets\Field;
use Gm2\Elementor\Widgets\LoopCard;
use Gm2\Elementor\Widgets\Map;
use Gm2\Elementor\Widgets\OpeningHours;
use ReflectionMethod;

class WidgetsTest extends WP_UnitTestCase
{
    private int $postId;

    private int $relatedId;

    private int $imageId;

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type('library_book', [ 'public' => true ]);
        update_option('date_format', 'F j, Y');
        update_option('time_format', 'g:i a');
        $this->registerFieldGroups();
        $this->createContent();
        GM2_Dynamic_Tag_Group::instance()->refresh();
    }

    protected function tearDown(): void
    {
        unregister_post_type('library_book');
        delete_option('gm2_field_groups');
        wp_reset_postdata();
        parent::tearDown();
    }

    public function test_widgets_register_with_manager(): void
    {
        $manager = new class {
            public array $registered = [];
            public function register($widget): void
            {
                $this->registered[] = $widget;
            }
            public function register_widget_type($widget): void
            {
                $this->registered[] = $widget;
            }
        };

        do_action('elementor/widgets/register', $manager);

        $names = array_map(static fn ($widget) => $widget->get_name(), $manager->registered);
        $this->assertContains('gm2_field', $names);
        $this->assertContains('gm2_loop_card', $names);
        $this->assertContains('gm2_map', $names);
        $this->assertContains('gm2_opening_hours', $names);
    }

    public function test_field_widget_outputs_formatted_value(): void
    {
        $widget = new Field();
        $widget->set_settings([
            'post_type' => 'library_book',
            'field_key' => 'library_details::blurb',
            'fallback'  => 'N/A',
            'html_tag'  => 'p',
        ]);

        $this->setCurrentPost($this->postId);

        $html = $this->renderWidget($widget);
        $this->assertStringContainsString('Exclusive Deals', $html);
        $this->assertStringContainsString('gm2-field--text', $html);
    }

    public function test_map_widget_builds_custom_provider_link(): void
    {
        $widget = new Map();
        $widget->set_settings([
            'post_type'    => 'library_book',
            'field_key'    => 'library_details::map_coordinates',
            'display'      => 'link',
            'provider_url' => 'https://maps.example.com/?lat={{lat}}&lng={{lng}}',
            'link_text'    => 'Open Map',
        ]);

        $this->setCurrentPost($this->postId);

        $html = $this->renderWidget($widget);
        $this->assertStringContainsString('https://maps.example.com/?lat=37.422&amp;lng=-122.0841', $html);
        $this->assertStringContainsString('Open Map', $html);
    }

    public function test_opening_hours_widget_formats_schedule(): void
    {
        $widget = new OpeningHours();
        $widget->set_settings([
            'post_type'    => 'library_book',
            'field_key'    => 'library_details::opening_hours',
            'closed_label' => 'Closed',
            'fallback_text'=> 'Call for hours',
        ]);

        $this->setCurrentPost($this->postId);

        $html = $this->renderWidget($widget);
        $this->assertStringContainsString('Monday', $html);
        $this->assertStringContainsString('9:00 am', $html);
        $this->assertStringContainsString('Closed', $html);
    }

    public function test_loop_card_widget_renders_meta_and_button(): void
    {
        $widget = new LoopCard();
        $widget->set_settings([
            'post_type'        => 'library_book',
            'layout'           => 'stacked',
            'image_field'      => 'library_details::cover_image',
            'title_field'      => 'library_details::title',
            'subtitle_field'   => 'library_details::price',
            'body_field'       => 'library_details::blurb',
            'meta_fields'      => [
                [
                    'label'     => 'Launch',
                    'field_key' => 'library_details::launch_date',
                    'fallback'  => '',
                ],
            ],
            'button_text'      => 'Buy now',
            'button_url_field' => 'library_details::purchase_url',
        ]);

        $this->setCurrentPost($this->postId);

        $html = $this->renderWidget($widget);
        $this->assertStringContainsString('gm2-loop-card__title', $html);
        $this->assertStringContainsString('Launch', $html);
        $this->assertStringContainsString('Buy now', $html);
        $this->assertStringContainsString('https://example.com/purchase', $html);
    }

    private function renderWidget(object $widget): string
    {
        $method = new ReflectionMethod($widget, 'render');
        $method->setAccessible(true);
        ob_start();
        $method->invoke($widget);

        return (string) ob_get_clean();
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
                    'title' => [ 'type' => 'text', 'label' => 'Title' ],
                    'price' => [
                        'type'      => 'currency',
                        'label'     => 'Price',
                        'symbol'    => '$',
                        'precision' => 2,
                    ],
                    'launch_date' => [
                        'type'  => 'datetime_tz',
                        'label' => 'Launch Date',
                    ],
                    'cover_image' => [ 'type' => 'image', 'label' => 'Cover Image' ],
                    'map_coordinates' => [ 'type' => 'geopoint', 'label' => 'Map Coordinates' ],
                    'purchase_url' => [ 'type' => 'url', 'label' => 'Purchase URL' ],
                    'blurb' => [ 'type' => 'text', 'label' => 'Blurb' ],
                    'opening_hours' => [ 'type' => 'repeater', 'label' => 'Opening Hours' ],
                ],
            ],
        ]);
    }

    private function createContent(): void
    {
        $this->relatedId = self::factory()->post->create([
            'post_type'  => 'library_book',
            'post_title' => 'Related Book',
        ]);

        $this->imageId = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg'
        );

        $this->postId = self::factory()->post->create([
            'post_type'  => 'library_book',
            'post_title' => 'Primary Book',
            'meta_input' => [
                'title'           => 'Primary Book',
                'price'           => '123.45',
                'launch_date'     => '2024-05-01T15:30:00+00:00',
                'cover_image'     => $this->imageId,
                'map_coordinates' => [ 'lat' => 37.4220, 'lng' => -122.0841 ],
                'purchase_url'    => 'https://example.com/purchase',
                'blurb'           => 'Exclusive Deals',
                'opening_hours'   => [
                    [ 'day' => 'Monday', 'start' => '09:00', 'end' => '17:00' ],
                    [ 'day' => 'Tuesday', 'status' => 'closed' ],
                ],
            ],
        ]);
    }
}
