<?php

declare(strict_types=1);

namespace Gm2\Elementor;

use Elementor\Modules\DynamicTags\Module;
use Gm2\Elementor\Controls\MetaKeySelect;
use Gm2\Elementor\Controls\PostTypeSelect;
use Gm2\Elementor\Controls\Price;
use Gm2\Elementor\Controls\TaxonomyTermMulti;
use Gm2\Elementor\Controls\Unit;
use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Elementor\Query\Filters;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Query/Filters.php';

add_action(
    'elementor/dynamic_tags/register',
    static function ($module): void {
        if (!$module instanceof Module) {
            return;
        }

        GM2_Dynamic_Tag_Group::instance()->register($module);
    }
);

Filters::register();

PostTypeSelect::register();
TaxonomyTermMulti::register();
MetaKeySelect::register();
Unit::register();
Price::register();
