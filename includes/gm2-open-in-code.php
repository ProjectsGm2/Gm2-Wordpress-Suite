<?php
/**
 * Open in Code helpers.
 *
 * Provides a small UI for viewing generated PHP or JSON and copying or downloading the code.
 */

if (!function_exists('gm2_render_open_in_code')) {
    /**
     * Render an "Open in Code" button with hidden PHP/JSON blocks.
     *
     * @param string $php_code  Generated PHP code.
     * @param string $json_code Generated JSON code.
     * @return string HTML output.
     */
    function gm2_render_open_in_code($php_code, $json_code)
    {
        $php  = htmlspecialchars($php_code, ENT_QUOTES, 'UTF-8');
        $json = htmlspecialchars($json_code, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <div class="gm2-open-in-code">
            <button type="button" class="gm2-open-in-code__trigger">
                <?php esc_html_e('Open in Code', 'gm2-wordpress-suite'); ?>
            </button>
            <div class="gm2-open-in-code__modal" style="display:none;">
                <textarea class="gm2-open-in-code__php" readonly><?php echo $php; ?></textarea>
                <textarea class="gm2-open-in-code__json" readonly><?php echo $json; ?></textarea>
                <button type="button" class="gm2-open-in-code__copy" data-target="php">
                    <?php esc_html_e('Copy PHP', 'gm2-wordpress-suite'); ?>
                </button>
                <button type="button" class="gm2-open-in-code__copy" data-target="json">
                    <?php esc_html_e('Copy JSON', 'gm2-wordpress-suite'); ?>
                </button>
                <a class="gm2-open-in-code__download" href="data:text/plain;charset=utf-8,<?php echo rawurlencode($php_code); ?>" download="code.php">
                    <?php esc_html_e('Download PHP', 'gm2-wordpress-suite'); ?>
                </a>
                <a class="gm2-open-in-code__download" href="data:application/json;charset=utf-8,<?php echo rawurlencode($json_code); ?>" download="code.json">
                    <?php esc_html_e('Download JSON', 'gm2-wordpress-suite'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('gm2_enqueue_open_in_code_assets')) {
    /**
     * Enqueue scripts for the "Open in Code" UI.
     */
    function gm2_enqueue_open_in_code_assets()
    {
        wp_enqueue_script(
            'gm2-open-in-code',
            GM2_PLUGIN_URL . 'admin/js/gm2-open-in-code.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
    }
    add_action('admin_enqueue_scripts', 'gm2_enqueue_open_in_code_assets');
}
