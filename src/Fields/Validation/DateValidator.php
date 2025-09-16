<?php

namespace Gm2\Fields\Validation;

use DateTimeImmutable;
use Gm2\Fields\FieldDefinition;

final class DateValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return $this->invalid($field, sprintf('%s must be a date string.', $field->getLabel()));
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return $this->invalid($field, sprintf('%s must be a valid date (Y-m-d).', $field->getLabel()));
        }

        return true;
    }
}
