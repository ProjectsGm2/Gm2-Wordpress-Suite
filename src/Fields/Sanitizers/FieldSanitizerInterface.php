<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

interface FieldSanitizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed;
}
