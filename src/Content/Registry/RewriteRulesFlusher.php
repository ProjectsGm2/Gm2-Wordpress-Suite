<?php

namespace Gm2\Content\Registry;

final class RewriteRulesFlusher
{
    private static bool $hooksRegistered = false;
    private static bool $rewriteScheduled = false;

    public static function registerHooks(): void
    {
        if (self::$hooksRegistered) {
            return;
        }

        if (
            !function_exists('register_activation_hook')
            || !function_exists('register_deactivation_hook')
            || !defined('GM2_PLUGIN_DIR')
        ) {
            return;
        }

        $pluginFile = GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php';

        if (
            !function_exists('plugin_basename')
            || !function_exists('has_action')
            || !file_exists($pluginFile)
        ) {
            return;
        }

        $pluginBase = plugin_basename($pluginFile);

        if (!has_action('activate_' . $pluginBase, 'flush_rewrite_rules')) {
            register_activation_hook($pluginFile, 'flush_rewrite_rules');
        }

        if (!has_action('deactivate_' . $pluginBase, 'flush_rewrite_rules')) {
            register_deactivation_hook($pluginFile, 'flush_rewrite_rules');
        }

        self::$hooksRegistered = true;
    }

    public static function flush(): void
    {
        if (self::$rewriteScheduled || !function_exists('flush_rewrite_rules')) {
            return;
        }

        if (function_exists('did_action') && did_action('init')) {
            flush_rewrite_rules(false);
            self::$rewriteScheduled = true;
            return;
        }

        if (function_exists('add_action')) {
            add_action('init', static function (): void {
                if (function_exists('flush_rewrite_rules')) {
                    flush_rewrite_rules(false);
                }
            }, PHP_INT_MAX);
            self::$rewriteScheduled = true;
        }
    }
}
