<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class TextSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return $value;
    }
}
