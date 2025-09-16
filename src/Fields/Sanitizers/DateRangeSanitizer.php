<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class DateRangeSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if (!is_array($value)) {
            return [ 'start' => '', 'end' => '' ];
        }

        $start = isset($value['start']) ? sanitize_text_field((string) $value['start']) : '';
        $end   = isset($value['end']) ? sanitize_text_field((string) $value['end']) : '';

        return [ 'start' => $start, 'end' => $end ];
    }
}
