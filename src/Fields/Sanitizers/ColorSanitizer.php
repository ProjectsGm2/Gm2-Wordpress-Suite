<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class ColorSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if (!is_string($value) && !is_scalar($value)) {
            return $value;
        }

        $value = strtoupper((string) $value);
        if (!str_starts_with($value, '#')) {
            $value = '#' . ltrim($value, '#');
        }

        return $value;
    }
}
