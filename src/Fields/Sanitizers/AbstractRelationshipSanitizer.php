<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

abstract class AbstractRelationshipSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        $multiple = (bool) ($field->getSettings()['multiple'] ?? true);
        if ($multiple) {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_filter(
                array_map(
                    static fn ($item) => is_numeric($item) ? (int) $item : null,
                    $value
                ),
                static fn ($item) => $item !== null
            ));
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
