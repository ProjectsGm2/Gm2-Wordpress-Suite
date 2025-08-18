<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Ajax_Upload {
    public static function init(): void {
        add_action('wp_ajax_gm2_async_upload', [ __CLASS__, 'handle' ]);
    }

    public static function handle(): void {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Permission denied.', 'gm2-wordpress-suite'), 403);
        }

        check_ajax_referer('gm2_async_upload', 'nonce');

        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded.', 'gm2-wordpress-suite'), 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($_FILES['file'], [ 'test_form' => false ]);
        if (isset($uploaded['error'])) {
            wp_send_json_error($uploaded['error'], 400);
        }

        $attachment = [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name(basename($uploaded['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message(), 500);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        gm2_queue_thumbnail_regeneration($attachment_id);
        gm2_queue_image_optimization($attachment_id);

        wp_send_json_success([ 'attachment_id' => $attachment_id ]);
    }
}
