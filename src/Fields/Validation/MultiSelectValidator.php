<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

final class MultiSelectValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_array($value)) {
            return $this->invalid($field, sprintf('%s must be an array.', $field->getLabel()));
        }

        $options = $field->getOptions();
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                return $this->invalid($field, sprintf('%s contains an invalid value.', $field->getLabel()));
            }
            $item = (string) $item;
            if ($options !== [] && !array_key_exists($item, $options)) {
                return $this->invalid($field, sprintf('%s contains an invalid selection.', $field->getLabel()));
            }
        }

        return true;
    }
}
