<?php

namespace Gm2;

use Gm2\Performance\AutoloadManager;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Script_Attributes {
    private array $attributes = [];
    private array $resolved = [];

    public static function init(): self {
        return new self();
    }

    public function __construct() {
        add_option('gm2_script_attributes', [], '', AutoloadManager::get_autoload_flag('gm2_script_attributes'));
        $this->load_attributes();
        add_action('updated_option', [$this, 'handle_option_updated'], 10, 3);
        add_action('added_option', [$this, 'handle_option_added'], 10, 2);
        add_action('deleted_option', [$this, 'handle_option_deleted']);
        add_filter('script_loader_tag', [$this, 'filter'], 10, 3);
    }

    public function filter(string $tag, string $handle, string $src): string {
        $attr = $this->determine_attribute($handle);

        if ($attr === 'async' || $attr === 'defer') {
            if (strpos($tag, $attr) === false) {
                $tag = str_replace('<script ', '<script ' . $attr . ' ', $tag);
            }
        }
        return $tag;
    }

    private function determine_attribute(string $handle): string {
        if (isset($this->resolved[$handle])) {
            return $this->resolved[$handle];
        }
        $this->resolved[$handle] = 'none';

        $attr = $this->attributes[$handle] ?? 'defer';
        if ($attr === 'blocking') {
            return $this->resolved[$handle] = 'blocking';
        }

        global $wp_scripts;
        if (!$wp_scripts instanceof \WP_Scripts) {
            $wp_scripts = wp_scripts();
        }
        $registered = $wp_scripts->registered[$handle] ?? null;
        if ($registered && !empty($registered->deps)) {
            foreach ($registered->deps as $dep) {
                $dep_attr = $this->determine_attribute($dep);
                if ($dep_attr === 'blocking') {
                    return $this->resolved[$handle] = 'blocking';
                }
                if ($dep_attr !== 'defer') {
                    return $this->resolved[$handle] = 'none';
                }
            }
        }

        return $this->resolved[$handle] = $attr;
    }

    private function load_attributes($value = null): void {
        if (is_array($value)) {
            $this->attributes = $value;
        } else {
            $this->attributes = get_option('gm2_script_attributes', []);
        }
        $this->resolved = [];
    }

    public function handle_option_updated(string $option, $old_value, $value): void {
        if ($option === 'gm2_script_attributes') {
            $this->load_attributes($value);
        }
    }

    public function handle_option_added(string $option, $value): void {
        if ($option === 'gm2_script_attributes') {
            $this->load_attributes($value);
        }
    }

    public function handle_option_deleted(string $option): void {
        if ($option === 'gm2_script_attributes') {
            $this->load_attributes([]);
        }
    }
}
