<?php
use Gm2\Gm2_SEO_Admin;

class GeneratedFieldsTest extends WP_UnitTestCase {
    public function test_description_generated_when_missing() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => 'desc']] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create([
            'post_title'   => 'Title',
            'post_content' => 'Content text',
        ]);

        $admin = new Gm2_SEO_Admin();
        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_seo_description'] = '';
        $admin->save_post_meta($post_id, get_post($post_id));

        remove_filter('pre_http_request', $filter, 10);

        $this->assertSame('desc', get_post_meta($post_id, '_gm2_description', true));
    }

    public function test_alt_generated_on_upload() {
        update_option('gm2_auto_fill_alt', '1');
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => 'alt text']] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $filename = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($filename);

        $admin = new Gm2_SEO_Admin();
        $admin->auto_fill_alt_on_upload($attachment_id);

        remove_filter('pre_http_request', $filter, 10);

        $this->assertSame('alt text', get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    }

    public function test_filename_is_cleaned_on_upload() {
        update_option('gm2_clean_image_filenames', '1');
        $filename = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($filename);
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => 'My Image Name',
        ]);

        $admin = new Gm2_SEO_Admin();
        $admin->auto_fill_alt_on_upload($attachment_id);

        $path = get_attached_file($attachment_id);
        $this->assertSame('my-image-name.jpg', wp_basename($path));
    }

    public function test_toggle_field_save_and_retrieve() {
        $post_id = self::factory()->post->create();
        $field   = new GM2_Field_Toggle('gm2_toggle');
        $field->save($post_id, '1');
        $this->assertSame('1', get_post_meta($post_id, 'gm2_toggle', true));

        $field->save($post_id, '0');
        $this->assertSame('', get_post_meta($post_id, 'gm2_toggle', true));
    }

    public function test_date_token_renders_using_site_timezone() {
        $prev_tz = get_option('timezone_string');
        update_option('timezone_string', 'America/Chicago');

        $field  = [ 'default_template' => '{date:Y-m-d}' ];
        $value  = gm2_resolve_default($field);
        $expect = wp_date('Y-m-d', time(), wp_timezone());

        $this->assertSame($expect, $value);

        update_option('timezone_string', $prev_tz);
    }
}

