<?php
/**
 * Apply a patch to a plugin file.
 *
 * This function uses the WordPress filesystem abstraction to modify
 * files within the plugin directory. The patch string is simply appended
 * to the existing file contents. The change is logged via error_log.
 *
 * @param string $file  Relative path to the plugin file.
 * @param string $patch Patch contents to append.
 * @return true|\WP_Error True on success, WP_Error on failure.
 */
function gm2_apply_patch($file, $patch) {
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    global $wp_filesystem;
    if (!WP_Filesystem()) {
        return new \WP_Error('fs_init_failed', __('Unable to initialize filesystem', 'gm2-wordpress-suite'));
    }
    $path = realpath(GM2_PLUGIN_DIR . ltrim($file, '/'));
    if ($path === false || strpos($path, realpath(GM2_PLUGIN_DIR)) !== 0) {
        return new \WP_Error('invalid_file', __('Invalid file path', 'gm2-wordpress-suite'));
    }
    $current = $wp_filesystem->get_contents($path);
    if ($current === false) {
        return new \WP_Error('read_error', __('Unable to read file', 'gm2-wordpress-suite'));
    }
    $new = $current . "\n" . $patch . "\n";
    if (!$wp_filesystem->put_contents($path, $new, FS_CHMOD_FILE)) {
        return new \WP_Error('write_error', __('Unable to write file', 'gm2-wordpress-suite'));
    }
    error_log(sprintf('gm2_apply_patch: %s modified', $path));
    return true;
}
