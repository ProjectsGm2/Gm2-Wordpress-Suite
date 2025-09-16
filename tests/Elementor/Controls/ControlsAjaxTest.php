<?php

declare(strict_types=1);

use Gm2\Elementor\Controls\MetaKeySelect;
use Gm2\Elementor\Controls\PostTypeSelect;
use Gm2\Elementor\Controls\TaxonomyTermMulti;

class ControlsAjaxTest extends WP_Ajax_UnitTestCase
{
    public static function wpSetUpBeforeClass($factory): void
    {
        register_post_type('book', ['public' => true]);
        register_post_type('movie', ['public' => true]);
        register_post_type('private_item', ['public' => false]);
        register_taxonomy('genre', ['book'], ['public' => true]);

        PostTypeSelect::register();
        TaxonomyTermMulti::register();
        MetaKeySelect::register();
    }

    public static function wpTearDownAfterClass(): void
    {
        unregister_taxonomy('genre');
        unregister_post_type('book');
        unregister_post_type('movie');
        unregister_post_type('private_item');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->_setRole('administrator');
    }

    public function test_post_type_ajax_returns_public_types(): void
    {
        $_POST['nonce'] = wp_create_nonce('gm2_elementor_controls');
        $_REQUEST['nonce'] = $_POST['nonce'];

        try {
            $this->_handleAjax('gm2_elementor_post_types');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_send_json_* terminating execution.
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $values = wp_list_pluck($response['data'], 'value');
        $this->assertContains('book', $values);
        $this->assertContains('movie', $values);
        $this->assertNotContains('private_item', $values);
    }

    public function test_taxonomy_mode_lists_taxonomies(): void
    {
        $_POST['nonce'] = wp_create_nonce('gm2_elementor_controls');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['mode'] = 'taxonomy';

        try {
            $this->_handleAjax('gm2_elementor_taxonomy_terms');
        } catch (WPAjaxDieContinueException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $values = wp_list_pluck($response['data'], 'value');
        $this->assertContains('genre', $values);
    }

    public function test_terms_mode_lists_requested_terms(): void
    {
        $term_one = wp_insert_term('Fiction', 'genre');
        $term_two = wp_insert_term('Non Fiction', 'genre');

        $_POST['nonce'] = wp_create_nonce('gm2_elementor_controls');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['mode'] = 'terms';
        $_POST['taxonomy'] = 'genre';

        try {
            $this->_handleAjax('gm2_elementor_taxonomy_terms');
        } catch (WPAjaxDieContinueException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $values = wp_list_pluck($response['data'], 'value');
        $this->assertContains((string) $term_one['term_id'], $values);
        $this->assertContains((string) $term_two['term_id'], $values);
    }

    public function test_meta_key_ajax_respects_post_type_filter(): void
    {
        $book = self::factory()->post->create(['post_type' => 'book']);
        $movie = self::factory()->post->create(['post_type' => 'movie']);
        update_post_meta($book, 'rating', '5');
        update_post_meta($book, 'pages', '120');
        update_post_meta($movie, 'rating', '4');
        update_post_meta($movie, 'duration', '90');

        $_POST['nonce'] = wp_create_nonce('gm2_elementor_controls');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['post_types'] = ['book'];

        try {
            $this->_handleAjax('gm2_elementor_meta_keys');
        } catch (WPAjaxDieContinueException $e) {
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $values = wp_list_pluck($response['data'], 'value');
        $this->assertContains('rating', $values);
        $this->assertContains('pages', $values);
        $this->assertNotContains('duration', $values);
    }
}
