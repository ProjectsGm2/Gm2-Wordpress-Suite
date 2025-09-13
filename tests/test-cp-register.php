<?php
/**
 * Tests for gm2_cp_register_type() and gm2_cp_register_taxonomy().
 */
class CpRegisterHelpersTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        if ( ! function_exists( 'gm2_cp_register_type' ) ) {
            require_once GM2_PLUGIN_DIR . 'includes/class-gm2-cp-register.php';
        }
        delete_option( 'gm2_custom_posts_config' );
    }

    public function tearDown(): void {
        if ( post_type_exists( 'book' ) ) {
            unregister_post_type( 'book' );
        }
        if ( taxonomy_exists( 'genre' ) ) {
            unregister_taxonomy( 'genre' );
        }
        delete_option( 'gm2_custom_posts_config' );
        parent::tearDown();
    }

    public function test_type_and_taxonomy_arguments_respected() {
        gm2_cp_register_type( 'book', [
            'labels' => [
                'name'          => ' Books ',
                'singular_name' => ' Book ',
            ],
            'supports' => [ 'title ', ' editor' ],
            'menu_icon' => ' dashicons-book ',
            'show_in_rest' => '1',
            'rewrite' => [
                'slug'       => ' Library & Books ',
                'with_front' => '0',
                'feeds'      => '1',
                'pages'      => '0',
            ],
            'has_archive' => ' My Books ',
            'map_meta_cap' => '1',
            'capabilities' => [
                'edit_post' => ' edit_book ',
                'read_post' => ' read_book ',
            ],
            'public' => true,
        ] );

        gm2_cp_register_taxonomy( 'genre', 'book', [
            'labels' => [
                'name'          => ' Genres ',
                'singular_name' => ' Genre ',
            ],
            'show_in_rest' => '1',
            'rewrite' => [
                'slug'        => ' Topics ',
                'with_front'  => '1',
                'hierarchical'=> '1',
            ],
            'hierarchical' => '1',
            'capabilities' => [
                'manage_terms' => ' manage_genres ',
                'edit_terms'   => ' edit_genres ',
            ],
            'public' => false,
        ] );

        $pt = get_post_type_object( 'book' );
        $this->assertSame( sanitize_text_field( ' Books ' ), $pt->labels->name );
        $this->assertSame( sanitize_text_field( ' Book ' ), $pt->labels->singular_name );
        $this->assertTrue( post_type_supports( 'book', 'title' ) );
        $this->assertTrue( post_type_supports( 'book', 'editor' ) );
        $this->assertSame( sanitize_text_field( ' dashicons-book ' ), $pt->menu_icon );
        $this->assertTrue( $pt->show_in_rest );
        $this->assertSame( [
            'slug'       => sanitize_title( ' Library & Books ' ),
            'with_front' => false,
            'feeds'      => true,
            'pages'      => false,
        ], $pt->rewrite );
        $this->assertSame( sanitize_title( ' My Books ' ), $pt->has_archive );
        $this->assertTrue( $pt->map_meta_cap );
        $this->assertSame( sanitize_text_field( ' edit_book ' ), $pt->cap->edit_post );
        $this->assertSame( sanitize_text_field( ' read_book ' ), $pt->cap->read_post );
        $this->assertTrue( $pt->public );

        $tax = get_taxonomy( 'genre' );
        $this->assertSame( sanitize_text_field( ' Genres ' ), $tax->labels->name );
        $this->assertSame( sanitize_text_field( ' Genre ' ), $tax->labels->singular_name );
        $this->assertTrue( $tax->show_in_rest );
        $this->assertSame( [
            'slug'        => sanitize_title( ' Topics ' ),
            'with_front'  => true,
            'hierarchical'=> true,
        ], $tax->rewrite );
        $this->assertTrue( $tax->hierarchical );
        $this->assertSame( sanitize_text_field( ' manage_genres ' ), $tax->cap->manage_terms );
        $this->assertSame( sanitize_text_field( ' edit_genres ' ), $tax->cap->edit_terms );
        $this->assertSame( [ 'book' ], $tax->object_type );
        $this->assertFalse( $tax->public );
    }
}
