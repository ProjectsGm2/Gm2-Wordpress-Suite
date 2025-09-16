<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class AddressSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if (!is_array($value)) {
            $value = [];
        }

        $keys = [ 'line1', 'line2', 'city', 'state', 'postal_code', 'country' ];
        $sanitized = [];
        foreach ($keys as $key) {
            $sanitized[$key] = isset($value[$key]) ? sanitize_text_field((string) $value[$key]) : '';
        }

        return $sanitized;
    }
}
