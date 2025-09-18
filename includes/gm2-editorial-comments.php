<?php
/**
 * Editorial comments with @mention support.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse @mentions from a message.
 *
 * @param string $message Comment content.
 * @return int[] Array of user IDs mentioned.
 */
function gm2_editorial_parse_mentions($message) {
    $user_ids = [];
    if (preg_match_all('/@([A-Za-z0-9_\.\-]+)/', $message, $matches)) {
        foreach ($matches[1] as $login) {
            $user = get_user_by('login', $login);
            if ($user) {
                $user_ids[] = (int) $user->ID;
            }
        }
    }
    return array_unique($user_ids);
}

/**
 * Send notification emails to mentioned users.
 *
 * @param int[] $user_ids Array of user IDs.
 * @param int   $comment_id Comment ID.
 */
function gm2_editorial_notify_mentions($user_ids, $comment_id) {
    $comment = get_comment($comment_id);
    if (!$comment) {
        return;
    }
    $post = get_post($comment->comment_post_ID);
    $author = wp_get_current_user();
    foreach ($user_ids as $uid) {
        $user = get_user_by('id', $uid);
        if (!$user) {
            continue;
        }
        $subject = sprintf(
            /* translators: %s: post title */
            __('You were mentioned in "%s"', 'gm2-wordpress-suite'),
            $post ? $post->post_title : ''
        );
        $message = sprintf(
            "%s\n\n%s\n\n%s",
            sprintf(__('Comment by %s:', 'gm2-wordpress-suite'), $author->display_name),
            $comment->comment_content,
            $post ? get_edit_post_link($post->ID) : ''
        );
        wp_mail($user->user_email, $subject, $message);
    }
}

/**
 * Insert an editorial comment and handle mentions.
 *
 * @param int    $post_id Post ID.
 * @param string $message Comment text.
 * @param string $context Optional field or block context.
 * @return int|false Comment ID on success, false on failure.
 */
function gm2_add_editorial_comment($post_id, $message, $context = '') {
    if (!current_user_can('edit_post', $post_id)) {
        return false;
    }

    $comment_id = wp_insert_comment([
        'comment_post_ID' => $post_id,
        'comment_content' => $message,
        'user_id'         => get_current_user_id(),
        'comment_type'    => 'gm2_editorial',
    ]);
    if ($comment_id && $context) {
        add_comment_meta($comment_id, 'gm2_context', $context);
    }
    if ($comment_id) {
        $mentions = gm2_editorial_parse_mentions($message);
        if ($mentions) {
            gm2_editorial_notify_mentions($mentions, $comment_id);
        }
    }
    return $comment_id;
}

/**
 * Retrieve editorial comments for a post and context.
 *
 * @param int    $post_id Post ID.
 * @param string $context Context key.
 * @return array Array of comment data.
 */
function gm2_get_editorial_comments($post_id, $context = '') {
    if (!current_user_can('edit_post', $post_id)) {
        return [];
    }

    $args = [
        'post_id' => $post_id,
        'type'    => 'gm2_editorial',
        'status'  => 'approve',
        'orderby' => 'comment_date_gmt',
        'order'   => 'ASC',
    ];
    if ($context !== '') {
        $args['meta_key']   = 'gm2_context';
        $args['meta_value'] = $context;
    }
    $comments = get_comments($args);
    $out = [];
    foreach ($comments as $c) {
        $out[] = [
            'id'      => $c->comment_ID,
            'content' => $c->comment_content,
            'author'  => get_comment_author($c),
            'date'    => $c->comment_date,
        ];
    }
    return $out;
}

/**
 * AJAX handler to add a comment.
 */
function gm2_ajax_add_editorial_comment() {
    check_ajax_referer('gm2_editorial_comment');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $message = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';
    if (!$post_id || $message === '') {
        wp_send_json_error();
    }
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error([
            'message' => __('You are not allowed to edit this post.', 'gm2-wordpress-suite'),
        ], 403);
    }
    $comment_id = gm2_add_editorial_comment($post_id, $message, $context);
    $comments   = gm2_get_editorial_comments($post_id, $context);
    wp_send_json_success($comments);
}
add_action('wp_ajax_gm2_add_editorial_comment', 'gm2_ajax_add_editorial_comment');

/**
 * AJAX handler to fetch comments.
 */
function gm2_ajax_get_editorial_comments() {
    check_ajax_referer('gm2_editorial_comment');
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    $context = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : '';
    if (!$post_id) {
        wp_send_json_error();
    }
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error([
            'message' => __('You are not allowed to edit this post.', 'gm2-wordpress-suite'),
        ], 403);
    }
    $comments = gm2_get_editorial_comments($post_id, $context);
    wp_send_json_success($comments);
}
add_action('wp_ajax_gm2_get_editorial_comments', 'gm2_ajax_get_editorial_comments');

/**
 * Enqueue admin script for editorial comments.
 */
function gm2_enqueue_editorial_comments_assets($hook) {
    $screen = get_current_screen();
    if (!$screen || 'post' !== $screen->base) {
        return;
    }
    wp_enqueue_script(
        'gm2-editorial-comments',
        GM2_PLUGIN_URL . 'admin/js/gm2-editorial-comments.js',
        ['jquery'],
        GM2_VERSION,
        true
    );
    wp_localize_script(
        'gm2-editorial-comments',
        'GM2EditorialComments',
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gm2_editorial_comment'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'gm2_enqueue_editorial_comments_assets');
