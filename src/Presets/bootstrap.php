<?php

declare(strict_types=1);

namespace Gm2\Presets;

use function add_action;
use function do_action;
use function file_exists;

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function () {
    $baseDir = GM2_PLUGIN_DIR . 'presets';
    $schema  = $baseDir . '/schema.json';

    $manager = new PresetManager($baseDir, file_exists($schema) ? $schema : null);
    $manager->registerHooks();

    /**
     * Action fired once presets have been bootstrapped.
     */
    do_action('gm2/presets/bootstrapped', $manager);
});
