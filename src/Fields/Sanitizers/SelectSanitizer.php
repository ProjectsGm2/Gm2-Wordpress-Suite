<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class SelectSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $value   = (string) $value;
        $options = $field->getOptions();
        if ($options !== [] && !array_key_exists($value, $options)) {
            return null;
        }

        return sanitize_text_field($value);
    }
}
