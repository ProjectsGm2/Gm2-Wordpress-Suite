<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Webhooks {
    const OPTION_URLS = 'gm2_webhook_urls';
    const OPTION_MODE = 'gm2_webhook_mode';

    public static function init() : void {
        add_action('save_post', [ __CLASS__, 'handle_save' ], 10, 3);
        add_action('deleted_post', [ __CLASS__, 'handle_delete' ], 10, 1);
        add_action('transition_post_status', [ __CLASS__, 'handle_transition' ], 10, 3);
    }

    public static function handle_save(int $post_id, \WP_Post $post, bool $update) : void {
        $action = $update ? 'update' : 'create';
        self::fire($action, $post);
    }

    public static function handle_delete(int $post_id) : void {
        $post = get_post($post_id);
        self::fire('delete', $post);
    }

    public static function handle_transition(string $new_status, string $old_status, \WP_Post $post) : void {
        if ($new_status !== $old_status) {
            self::fire('transition', $post, [ 'from' => $old_status, 'to' => $new_status ]);
        }
    }

    protected static function fire(string $event, ?\WP_Post $post, array $extra = []) : void {
        $urls = (array) get_option(self::OPTION_URLS, []);
        if (!$post || empty($urls)) {
            return;
        }
        $mode = get_option(self::OPTION_MODE, 'raw');
        $data = self::serialize_post($post, $mode);
        $payload = [
            'event' => $event,
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'data' => $data,
            'extra' => $extra,
        ];
        foreach ($urls as $url) {
            wp_remote_post($url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode($payload),
                'timeout' => 5,
            ]);
        }
    }

    protected static function serialize_post(\WP_Post $post, string $mode) : array {
        $data = [];
        if ('rendered' === $mode) {
            $data['content'] = apply_filters('the_content', $post->post_content);
        } else {
            $data['content'] = $post->post_content;
        }
        $data['title'] = $post->post_title;
        $data['status'] = $post->post_status;
        $data['type'] = $post->post_type;
        if ('media' === $mode) {
            $media = [];
            $attachments = get_attached_media('', $post);
            foreach ($attachments as $att) {
                $media[] = [
                    'id'  => $att->ID,
                    'url' => wp_get_attachment_url($att->ID),
                    'mime' => get_post_mime_type($att->ID),
                ];
            }
            $data['media'] = $media;
        }
        return $data;
    }
}
