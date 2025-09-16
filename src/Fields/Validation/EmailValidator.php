<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class EmailValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !is_scalar($value)) {
            return $this->invalid($field, sprintf('%s must be an email string.', $field->getLabel()));
        }

        $value = (string) $value;
        if (!is_email($value)) {
            return $this->invalid($field, sprintf('%s must be a valid email.', $field->getLabel()));
        }

        return true;
    }
}
