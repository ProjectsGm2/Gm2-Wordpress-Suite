<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;

final class GeopointSanitizer implements FieldSanitizerInterface
{
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        if (!is_array($value)) {
            return [ 'lat' => null, 'lng' => null ];
        }

        $lat = isset($value['lat']) && is_numeric($value['lat']) ? (float) $value['lat'] : null;
        $lng = isset($value['lng']) && is_numeric($value['lng']) ? (float) $value['lng'] : null;

        return [ 'lat' => $lat, 'lng' => $lng ];
    }
}
