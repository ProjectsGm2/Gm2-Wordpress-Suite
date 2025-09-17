<?php

namespace Gm2\SEO\Schema\Mapper;

use WP_Error;
use WP_Post;

interface MapperInterface
{
    public function getPostType(): string;

    public function getSchemaType(): string;

    public function getOptionName(): string;

    public function getLabel(): string;

    /**
     * Build schema data for a singular post.
     *
     * @param WP_Post $post Post to map.
     * @return array|WP_Error
     */
    public function map(WP_Post $post);

    /**
     * Retrieve a map of required field keys to human readable labels.
     *
     * @return array<string,string>
     */
    public function getRequiredFieldMap(): array;
}
