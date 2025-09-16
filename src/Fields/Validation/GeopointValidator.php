<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class GeopointValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_array($value)) {
            return $this->invalid($field, sprintf('%s must be an object with lat/lng.', $field->getLabel()));
        }

        $lat = $value['lat'] ?? null;
        $lng = $value['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->invalid($field, sprintf('%s must contain numeric latitude and longitude.', $field->getLabel()));
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->invalid($field, sprintf('%s coordinates are out of range.', $field->getLabel()));
        }

        return true;
    }
}
