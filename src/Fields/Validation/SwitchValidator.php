<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class SwitchValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if (is_null($value) || is_bool($value) || is_numeric($value) || $value === '') {
            return true;
        }

        if (is_string($value)) {
            return true;
        }

        return $this->invalid($field, sprintf('%s must be a boolean value.', $field->getLabel()));
    }
}
