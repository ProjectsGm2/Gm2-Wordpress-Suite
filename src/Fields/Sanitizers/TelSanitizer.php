<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class TelSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_scalar($value)) {
            return $value;
        }

        $value = (string) $value;

        return preg_replace('/[^0-9+()\s\.-]/', '', $value);
    }
}
