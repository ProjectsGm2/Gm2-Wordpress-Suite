<?php
class FieldGroupRepeaterTest extends WP_UnitTestCase {
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
    public function test_group_renders_and_saves_children() {
        $fields = [
            'info' => [
                'type' => 'group',
                'fields' => [
                    'child_a' => [ 'type' => 'text' ],
                    'child_b' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'relation' => 'AND',
                                'conditions' => [
                                    [
                                        'relation' => 'AND',
                                        'target'   => 'child_a',
                                        'operator' => '=',
                                        'value'    => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        ob_start();
        gm2_render_field_group($fields, $this->post_id, 'post');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="info[child_a]"', $html);
        $this->assertStringContainsString('name="info[child_b]"', $html);

        $_POST['info'] = ['child_a' => 'hide', 'child_b' => 'x'];
        gm2_save_field_group($fields, $this->post_id, 'post');
        $this->assertSame('hide', get_post_meta($this->post_id, 'child_a', true));
        $this->assertSame('', get_post_meta($this->post_id, 'child_b', true));

        $_POST['info'] = ['child_a' => 'show', 'child_b' => 'ok'];
        gm2_save_field_group($fields, $this->post_id, 'post');
        $this->assertSame('show', get_post_meta($this->post_id, 'child_a', true));
        $this->assertSame('ok', get_post_meta($this->post_id, 'child_b', true));
    }

    public function test_repeater_renders_and_saves_rows() {
        $fields = [
            'items' => [
                'type' => 'repeater',
                'sub_fields' => [
                    'name' => [ 'type' => 'text' ],
                    'count' => [ 'type' => 'number' ],
                    'note' => [
                        'type' => 'text',
                        'conditions' => [
                            [
                                'relation' => 'AND',
                                'conditions' => [
                                    [
                                        'relation' => 'AND',
                                        'target'   => 'count',
                                        'operator' => '>',
                                        'value'    => '1',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        ob_start();
        gm2_render_field_group($fields, $this->post_id, 'post');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="items[__i__][name]"', $html);

        $_POST['items'] = [
            [ 'name' => 'Alpha', 'count' => '2', 'note' => 'n1' ],
            [ 'name' => 'Beta',  'count' => '1', 'note' => 'n2' ],
        ];
        gm2_save_field_group($fields, $this->post_id, 'post');
        $saved = get_post_meta($this->post_id, 'items', true);
        $this->assertSame([
            [ 'name' => 'Alpha', 'count' => '2', 'note' => 'n1' ],
            [ 'name' => 'Beta',  'count' => '1' ],
        ], $saved);
    }
}
