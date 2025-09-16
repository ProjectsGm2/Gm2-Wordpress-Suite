<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class NumberSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}
