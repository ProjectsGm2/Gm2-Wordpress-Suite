<?php

class FieldColorTest extends WP_UnitTestCase {
    protected $post_id;

    public function setUp(): void {
        parent::setUp();
        $this->post_id = self::factory()->post->create();
    }

    public function tearDown(): void {
        parent::tearDown();
        wp_delete_post($this->post_id, true);
        $_POST    = [];
        $_REQUEST = [];
    }

    public function test_color_field_saves_and_retrieves_value() {
        $fields = [
            'favorite_color' => [ 'type' => 'color' ],
        ];

        gm2_save_field_group($fields, $this->post_id, 'post', [
            'favorite_color' => '#123456',
        ]);

        $this->assertSame('#123456', get_post_meta($this->post_id, 'favorite_color', true));
        $this->assertSame('#123456', gm2_field('favorite_color', '', $this->post_id));
    }

    public function test_invalid_color_clears_value() {
        $fields = [
            'favorite_color' => [ 'type' => 'color' ],
        ];

        gm2_save_field_group($fields, $this->post_id, 'post', [
            'favorite_color' => '#abcdef',
        ]);
        $this->assertSame('#abcdef', get_post_meta($this->post_id, 'favorite_color', true));

        gm2_save_field_group($fields, $this->post_id, 'post', [
            'favorite_color' => 'not-a-color',
        ]);

        $this->assertSame('', get_post_meta($this->post_id, 'favorite_color', true));
        $this->assertSame('', gm2_field('favorite_color', '', $this->post_id));
    }
}
