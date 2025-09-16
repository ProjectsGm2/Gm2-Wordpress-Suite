<?php

namespace Gm2\Fields\Validation;

use DateTimeImmutable;
use Gm2\Fields\FieldDefinition;

final class DateTimeTzValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return $this->invalid($field, sprintf('%s must be a datetime string.', $field->getLabel()));
        }

        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($date === false) {
            $date = new DateTimeImmutable($value);
        }

        if ($date === false) {
            return $this->invalid($field, sprintf('%s must be a valid datetime.', $field->getLabel()));
        }

        return true;
    }
}
