<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class DateSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if (!is_string($value) && !is_scalar($value)) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }
}
