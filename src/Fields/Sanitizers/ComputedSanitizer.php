<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class ComputedSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        return $value;
    }
}
