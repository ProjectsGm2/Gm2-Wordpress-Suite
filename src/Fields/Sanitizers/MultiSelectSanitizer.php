<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class MultiSelectSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return [];
        }

        $options = $field->getOptions();
        $sanitized = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = (string) $item;
            if ($options !== [] && !array_key_exists($item, $options)) {
                continue;
            }
            $sanitized[] = sanitize_text_field($item);
        }

        return array_values(array_unique($sanitized));
    }
}
