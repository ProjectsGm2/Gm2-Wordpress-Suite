<?php
/**
 * Apply a patch to a plugin file.
 *
 * This function uses the WordPress filesystem abstraction to modify
 * files within the plugin directory. The patch string must be a unified
 * diff which will be parsed and applied to the file. Success and failure
 * are logged via error_log.
 *
 * @param string $file  Relative path to the plugin file.
 * @param string $patch Patch contents in unified diff format.
 * @return true|\WP_Error True on success, WP_Error on failure.
 */
function gm2_apply_patch($file, $patch) {
    if (!current_user_can('manage_options')) {
        $error = new \WP_Error('permission', __('You do not have permission to apply patches.', 'gm2-wordpress-suite'));
        error_log('gm2_apply_patch: ' . $error->get_error_message());
        return $error;
    }
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    global $wp_filesystem;
    if (!WP_Filesystem()) {
        $error = new \WP_Error('fs_init_failed', __('Unable to initialize filesystem', 'gm2-wordpress-suite'));
        error_log('gm2_apply_patch: ' . $error->get_error_message());
        return $error;
    }

    $path = realpath(GM2_PLUGIN_DIR . ltrim($file, '/'));
    if ($path === false || strpos($path, realpath(GM2_PLUGIN_DIR)) !== 0) {
        $error = new \WP_Error('invalid_file', __('Invalid file path', 'gm2-wordpress-suite'));
        error_log('gm2_apply_patch: ' . $error->get_error_message());
        return $error;
    }

    $current = $wp_filesystem->get_contents($path);
    if ($current === false) {
        $error = new \WP_Error('read_error', __('Unable to read file', 'gm2-wordpress-suite'));
        error_log('gm2_apply_patch: ' . $error->get_error_message());
        return $error;
    }

    $new = gm2_apply_unified_diff($current, $patch);
    if (is_wp_error($new)) {
        error_log('gm2_apply_patch: ' . $new->get_error_message());
        return $new;
    }

    if (!$wp_filesystem->put_contents($path, $new, FS_CHMOD_FILE)) {
        $error = new \WP_Error('write_error', __('Unable to write file', 'gm2-wordpress-suite'));
        error_log('gm2_apply_patch: ' . $error->get_error_message());
        return $error;
    }

    error_log(sprintf('gm2_apply_patch: %s patched successfully', $path));
    return true;
}

/**
 * Apply a unified diff to a string.
 *
 * @param string $original Original file contents.
 * @param string $patch    Unified diff.
 * @return string|\WP_Error Patched contents or WP_Error on failure.
 */
function gm2_apply_unified_diff($original, $patch) {
    $lines        = preg_split('/\r?\n/', $original);
    $patch_lines  = preg_split('/\r?\n/', $patch);
    $i            = 0;
    $offset       = 0;

    while ($i < count($patch_lines)) {
        $line = $patch_lines[$i];

        if (preg_match('/^@@\s+-(\d+)(?:,(\d+))?\s+\+(\d+)(?:,(\d+))?\s+@@/', $line, $m)) {
            $old_start = (int) $m[1];
            $i++;
            $hunk = [];
            while ($i < count($patch_lines) && (isset($patch_lines[$i][0]) && $patch_lines[$i][0] !== '@')) {
                $hunk[] = $patch_lines[$i];
                $i++;
            }

            $expected    = [];
            $replacement = [];
            foreach ($hunk as $hunk_line) {
                if ($hunk_line === '' || $hunk_line[0] === '\\') {
                    continue;
                }
                if ($hunk_line[0] === ' ' || $hunk_line[0] === '-') {
                    $expected[] = substr($hunk_line, 1);
                }
                if ($hunk_line[0] === ' ' || $hunk_line[0] === '+') {
                    $replacement[] = substr($hunk_line, 1);
                }
            }

            $index = $old_start - 1 + $offset;
            $slice = array_slice($lines, $index, count($expected));
            if ($slice !== $expected) {
                return new \WP_Error('patch_mismatch', __('Patch does not apply cleanly', 'gm2-wordpress-suite'));
            }
            array_splice($lines, $index, count($expected), $replacement);
            $offset += count($replacement) - count($expected);
        } else {
            // Skip non-hunk lines such as file headers.
            $i++;
        }
    }

    return implode("\n", $lines);
}
