<?php

namespace Gm2\Fields\Validation;

use DateTimeImmutable;
use Gm2\Fields\FieldDefinition;

final class DateRangeValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_array($value)) {
            return $this->invalid($field, sprintf('%s must be an array with start and end.', $field->getLabel()));
        }

        $start = $value['start'] ?? null;
        $end   = $value['end'] ?? null;
        if (!is_string($start) || !is_string($end)) {
            return $this->invalid($field, sprintf('%s must include start and end dates.', $field->getLabel()));
        }

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $endDate   = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if ($startDate === false || $startDate->format('Y-m-d') !== $start) {
            return $this->invalid($field, sprintf('%s has an invalid start date.', $field->getLabel()));
        }
        if ($endDate === false || $endDate->format('Y-m-d') !== $end) {
            return $this->invalid($field, sprintf('%s has an invalid end date.', $field->getLabel()));
        }

        if ($endDate < $startDate) {
            return $this->invalid($field, sprintf('%s end date must be after start date.', $field->getLabel()));
        }

        return true;
    }
}
