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
        add_filter('script_loader_tag', [$this, 'filter'], 10, 3);
    }

    public function filter(string $tag, string $handle, string $src): string {
        $this->attributes = get_option('gm2_script_attributes', []);
        $this->resolved   = [];
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
}
