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

        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Gm2 Diagnostics detected issues:', 'gm2-wordpress-suite') . '</strong><br />';
        if (!empty($this->conflicts)) {
            echo esc_html__('Conflicting SEO plugins: ', 'gm2-wordpress-suite') . esc_html(implode(', ', $this->conflicts)) . '.<br />';
        }
        if (!empty($this->integrity_errors)) {
            echo esc_html__('Missing plugin files: ', 'gm2-wordpress-suite') . esc_html(implode(', ', $this->integrity_errors)) . '.<br />';
        }
        if (!empty($this->hook_issues)) {
            echo esc_html__('Theme hooks removed: ', 'gm2-wordpress-suite') . esc_html(implode(', ', $this->hook_issues)) . '.<br />';
        }
        echo esc_html__('Please resolve these issues to ensure all SEO features work as expected.', 'gm2-wordpress-suite');
        echo '</p></div>';
    }
}
