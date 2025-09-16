<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class TelValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !is_scalar($value)) {
            return $this->invalid($field, sprintf('%s must be a phone string.', $field->getLabel()));
        }

        $value = (string) $value;
        if (!preg_match('/^[0-9+()\s\.-]+$/', $value)) {
            return $this->invalid($field, sprintf('%s must be a valid phone number.', $field->getLabel()));
        }

        return true;
    }
}
