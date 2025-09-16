<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;
use WP_Error;

abstract class AbstractFieldValidator implements FieldValidatorInterface
{
    protected function invalid(FieldDefinition $field, string $message, string $code = 'gm2_field_invalid'): WP_Error
    {
        return new WP_Error($code, $message, [ 'field' => $field->getKey() ]);
    }
}
