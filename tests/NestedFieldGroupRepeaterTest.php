<?php
class NestedFieldGroupRepeaterTest extends WP_UnitTestCase {
    protected $post_id;

    public function setUp(): void {
        parent::setUp();
        $this->post_id = self::factory()->post->create();
    }

    public function tearDown(): void {
        parent::tearDown();
        wp_delete_post($this->post_id, true);
        $_POST = [];
        $_REQUEST = [];
    }

    public function test_nested_fields_sanitized_and_saved() {
        $fields = [
            'layout' => [
                'type' => 'group',
                'fields' => [
                    'dimensions' => [
                        'type' => 'group',
                        'fields' => [
                            'width' => [ 'type' => 'measurement', 'units' => ['px', 'em'] ],
                        ],
                    ],
                    'shifts' => [
                        'type' => 'repeater',
                        'sub_fields' => [
                            'period' => [ 'type' => 'schedule' ],
                            'note'   => [ 'type' => 'text' ],
                        ],
                    ],
                ],
            ],
        ];

        $_POST['layout'] = [
            'dimensions' => [
                'width' => [ 'value' => '15', 'unit' => 'bad' ],
            ],
            'shifts' => [
                [
                    'period' => [ 'day' => ['Monday'], 'start' => ['09:00'], 'end' => ['17:00'] ],
                    'note'   => 'open',
                ],
                [
                    'period' => [ 'day' => ['Tuesday'], 'start' => [''], 'end' => ['18:00'] ],
                    'note'   => 'invalid',
                ],
            ],
        ];

        gm2_save_field_group($fields, $this->post_id, 'post');

        $this->assertSame(
            [ 'value' => '15', 'unit' => 'px' ],
            get_post_meta($this->post_id, 'width', true)
        );

        $saved = get_post_meta($this->post_id, 'shifts', true);
        $this->assertSame([
            [
                'period' => [ [ 'day' => 'Monday', 'start' => '09:00', 'end' => '17:00' ] ],
                'note'   => 'open',
            ],
            [
                'period' => [],
                'note'   => 'invalid',
            ],
        ], $saved);
    }
}
