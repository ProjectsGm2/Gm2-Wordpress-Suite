<?php

namespace Gm2\Content\Contracts;

use Gm2\Content\Model\Definition;

interface RegistersContent
{
    public function registerPostType(Definition $definition): void;

    public function registerTaxonomy(
        string $slug,
        string $singular,
        string $plural,
        array $objectTypes,
        array $args = []
    ): void;
}
