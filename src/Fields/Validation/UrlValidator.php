<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class UrlValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !is_scalar($value)) {
            return $this->invalid($field, sprintf('%s must be a URL string.', $field->getLabel()));
        }

        $value = (string) $value;
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $this->invalid($field, sprintf('%s must be a valid URL.', $field->getLabel()));
        }

        return true;
    }
}
