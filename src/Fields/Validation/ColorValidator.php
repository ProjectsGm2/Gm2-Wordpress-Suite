<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class ColorValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !is_scalar($value)) {
            return $this->invalid($field, sprintf('%s must be a string.', $field->getLabel()));
        }

        $value = (string) $value;
        if (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
            return $this->invalid($field, sprintf('%s must be a valid hex color.', $field->getLabel()));
        }

        return true;
    }
}
