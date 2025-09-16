<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;

abstract class AbstractRelationshipValidator extends AbstractFieldValidator
{
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|
    \WP_Error
    {
        $multiple = (bool) ($field->getSettings()['multiple'] ?? true);

        if ($value === null || $value === '') {
            return true;
        }

        if ($multiple) {
            if (!is_array($value)) {
                return $this->invalid($field, sprintf('%s must be an array of IDs.', $field->getLabel()));
            }
            foreach ($value as $item) {
                if (!is_numeric($item)) {
                    return $this->invalid($field, sprintf('%s must only contain numeric IDs.', $field->getLabel()));
                }
            }
            return true;
        }

        if (!is_numeric($value)) {
            return $this->invalid($field, sprintf('%s must reference an ID.', $field->getLabel()));
        }

        return true;
    }
}
