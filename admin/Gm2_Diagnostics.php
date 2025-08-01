<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Diagnostics {
    private $conflicts = [];
    private $integrity_errors = [];
    private $hook_issues = [];

    public function run() {
        add_action('admin_init', [$this, 'diagnose']);
        add_action('admin_notices', [$this, 'display_notice']);
    }

    public function diagnose() {
        $this->check_plugins();
        $this->check_files();
        $this->check_theme_hooks();
    }

    private function check_plugins() {
        // Skip conflict checks when the SEO module is disabled.
        if (get_option('gm2_enable_seo', '1') !== '1') {
            return;
        }
        $active    = (array) get_option('active_plugins');
        $patterns  = ['wordpress-seo', 'all-in-one-seo', 'aioseo', 'rank-math', 'seopress'];
        foreach ($active as $plugin) {
            foreach ($patterns as $pattern) {
                if (stripos($plugin, $pattern) !== false) {
                    $this->conflicts[] = $plugin;
                    break;
                }
            }
        }
    }

    private function check_files() {
        $required = [
            'admin/Gm2_SEO_Admin.php',
            'includes/Gm2_Keyword_Planner.php',
            'admin/Gm2_Admin.php',
            'includes/Gm2_Google_OAuth.php',
        ];
        foreach ($required as $file) {
            if (!file_exists(GM2_PLUGIN_DIR . $file)) {
                $this->integrity_errors[] = $file;
            }
        }
    }

    private function check_theme_hooks() {
        $theme_dir = get_stylesheet_directory();
        $header    = $theme_dir . '/header.php';
        if (is_readable($header)) {
            $content = file_get_contents($header);
            if (strpos($content, 'wp_head(') === false) {
                $this->hook_issues[] = 'wp_head';
            }
        }

        if (!has_filter('the_title')) {
            $this->hook_issues[] = 'the_title';
        }
    }

    public function display_notice() {
        if (empty($this->conflicts) && empty($this->integrity_errors) && empty($this->hook_issues)) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Gm2 Diagnostics detected issues:', 'gm2-wordpress-suite') . '</strong></p><ul>';
        if (!empty($this->conflicts)) {
            echo '<li>' . esc_html__('Conflicting SEO plugins:', 'gm2-wordpress-suite') . ' ' . esc_html(implode(', ', $this->conflicts)) . '. ' . esc_html__('Disable them or turn off the SEO module.', 'gm2-wordpress-suite') . '</li>';
        }
        if (!empty($this->integrity_errors)) {
            echo '<li>' . esc_html__('Missing plugin files:', 'gm2-wordpress-suite') . ' ' . esc_html(implode(', ', $this->integrity_errors)) . '. ' . esc_html__('Restore these files or reinstall the plugin.', 'gm2-wordpress-suite') . '</li>';
        }
        if (!empty($this->hook_issues)) {
            echo '<li>' . esc_html__('Theme hooks removed:', 'gm2-wordpress-suite') . ' ' . esc_html(implode(', ', $this->hook_issues)) . '. ' . esc_html__('Add the hooks back to your theme templates.', 'gm2-wordpress-suite') . '</li>';
        }
        echo '</ul><p>' . esc_html__('Please resolve these issues to ensure all SEO features work as expected.', 'gm2-wordpress-suite') . '</p></div>';
    }

    public function get_conflicts() {
        return $this->conflicts;
    }

    public function get_integrity_errors() {
        return $this->integrity_errors;
    }

    public function get_hook_issues() {
        return $this->hook_issues;
    }
}
