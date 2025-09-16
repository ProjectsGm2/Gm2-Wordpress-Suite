<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class TimeValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return $this->invalid($field, sprintf('%s must be a time string.', $field->getLabel()));
        }

        if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $value)) {
            return $this->invalid($field, sprintf('%s must be a valid time (HH:MM or HH:MM:SS).', $field->getLabel()));
        }

        return true;
    }
}
