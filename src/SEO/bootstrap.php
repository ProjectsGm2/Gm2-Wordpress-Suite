<?php

namespace Gm2\SEO;

use Gm2\SEO\Schema\Manager;
use Gm2\SEO\Schema\Mapper\CourseMapper;
use Gm2\SEO\Schema\Mapper\DirectoryMapper;
use Gm2\SEO\Schema\Mapper\EventMapper;
use Gm2\SEO\Schema\Mapper\JobMapper;
use Gm2\SEO\Schema\Mapper\RealEstateMapper;

if (!defined('ABSPATH')) {
    return;
}

/**
 * Bootstrap the schema manager with default mappers.
 */
function bootstrap_schema_manager(): Manager
{
    $mappers = [
        new DirectoryMapper(),
        new EventMapper(),
        new JobMapper(),
        new CourseMapper(),
        new RealEstateMapper(),
    ];

    /** @var array<int, \Gm2\SEO\Schema\Mapper\MapperInterface> $mappers */
    $mappers = apply_filters('gm2_seo_schema_mappers', $mappers);

    return Manager::bootstrap($mappers);
}

bootstrap_schema_manager();
