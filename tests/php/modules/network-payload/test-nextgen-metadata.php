<?php
use Gm2\NetworkPayload\Module;

// Ensure base image editor class is available for the mock implementation.
require_once ABSPATH . WPINC . '/class-wp-image-editor.php';

class NextGenMetadataTest extends WP_UnitTestCase {
    public static function use_mock_editor($editors) {
        return [Mock_WP_Image_Editor::class];
    }

    /**
     * Ensure next-gen metadata added for JPEG and PNG attachments.
     */
    public function test_generates_nextgen_metadata_for_jpeg_and_png(): void {
        // JPEG sample.
        $jpeg_id   = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');
        $jpeg_file = get_attached_file($jpeg_id);
        $jpeg_meta = wp_generate_attachment_metadata($jpeg_id, $jpeg_file);
        add_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $jpeg_meta = Module::add_nextgen_variants($jpeg_meta, $jpeg_id);
        remove_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $this->assertArrayHasKey('gm2_nextgen', $jpeg_meta);
        foreach ($jpeg_meta['sizes'] as $size => $data) {
            $this->assertArrayHasKey($size, $jpeg_meta['gm2_nextgen']);
            foreach (['webp','avif'] as $fmt) {
                $this->assertArrayHasKey($fmt, $jpeg_meta['gm2_nextgen'][$size]);
                $this->assertFileExists(dirname($jpeg_file) . '/' . $jpeg_meta['gm2_nextgen'][$size][$fmt]);
            }
        }
        foreach (['webp','avif'] as $fmt) {
            $this->assertArrayHasKey($fmt, $jpeg_meta['gm2_nextgen']['full']);
            $this->assertFileExists(dirname($jpeg_file) . '/' . $jpeg_meta['gm2_nextgen']['full'][$fmt]);
        }

        // PNG sample generated dynamically.
        $tmp_png = tempnam(sys_get_temp_dir(), 'gm2png') . '.png';
        $img = imagecreatetruecolor(600, 600);
        imagepng($img, $tmp_png);
        imagedestroy($img);
        $png_id   = self::factory()->attachment->create_upload_object($tmp_png);
        $png_file = get_attached_file($png_id);
        $png_meta = wp_generate_attachment_metadata($png_id, $png_file);
        add_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $png_meta = Module::add_nextgen_variants($png_meta, $png_id);
        remove_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $this->assertArrayHasKey('gm2_nextgen', $png_meta);
        foreach ($png_meta['sizes'] as $size => $data) {
            $this->assertArrayHasKey($size, $png_meta['gm2_nextgen']);
            foreach (['webp','avif'] as $fmt) {
                $this->assertArrayHasKey($fmt, $png_meta['gm2_nextgen'][$size]);
                $this->assertFileExists(dirname($png_file) . '/' . $png_meta['gm2_nextgen'][$size][$fmt]);
            }
        }
        foreach (['webp','avif'] as $fmt) {
            $this->assertArrayHasKey($fmt, $png_meta['gm2_nextgen']['full']);
            $this->assertFileExists(dirname($png_file) . '/' . $png_meta['gm2_nextgen']['full'][$fmt]);
        }
    }

    /**
     * Animated GIFs should skip AVIF generation.
     */
    public function test_skips_avif_for_animated_gif(): void {
        $upload = wp_upload_dir();
        $gif_file = $upload['path'] . '/animated.gif';
        $gif_content = 'GIF89a' . str_repeat("\x00\x21\xF9\x04", 2);
        file_put_contents($gif_file, $gif_content);
        $att_id = wp_insert_attachment([
            'post_mime_type' => 'image/gif',
            'post_title'     => 'gif',
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $gif_file);
        update_attached_file($att_id, $gif_file);
        $size_file = $upload['path'] . '/animated-150x150.gif';
        copy($gif_file, $size_file);
        $meta = [
            'file' => basename($gif_file),
            'width' => 1,
            'height' => 1,
            'sizes' => [
                'thumbnail' => ['file' => basename($size_file), 'width' => 1, 'height' => 1],
            ],
        ];
        add_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $meta = Module::add_nextgen_variants($meta, $att_id);
        remove_filter('wp_image_editors', [__CLASS__, 'use_mock_editor']);
        $this->assertArrayHasKey('gm2_nextgen', $meta);
        $this->assertArrayHasKey('thumbnail', $meta['gm2_nextgen']);
        $this->assertArrayHasKey('webp', $meta['gm2_nextgen']['thumbnail']);
        $this->assertFileExists($upload['path'] . '/' . $meta['gm2_nextgen']['thumbnail']['webp']);
        $this->assertArrayNotHasKey('avif', $meta['gm2_nextgen']['thumbnail']);
    }
}

class Mock_WP_Image_Editor extends WP_Image_Editor {
    public static function test( $args = [] ) { return true; }
    public static function supports_mime_type( $mime_type ) { return true; }
    // Stub methods so _wp_image_editor_choose() sees requested methods.
    public static function Imagick() {}
    public static function GD() {}
    public function load() { return true; }
    public function save( $destfilename = null, $mime_type = null ) {
        $dest = $destfilename ?: $this->file;
        copy( $this->file, $dest );
        return [
            'path' => $dest,
            'file' => wp_basename( $dest ),
            'width' => 1,
            'height' => 1,
            'mime-type' => $mime_type ?: 'image/jpeg',
            'filesize' => @filesize( $dest ),
        ];
    }
    public function resize( $max_w, $max_h, $crop = false ) { return true; }
    public function multi_resize( $sizes ) { return []; }
    public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) { return true; }
    public function rotate( $angle ) { return true; }
    public function flip( $horz, $vert ) { return true; }
    public function stream( $mime_type = null ) { return true; }
}
