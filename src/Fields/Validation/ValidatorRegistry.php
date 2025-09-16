<?php

namespace Gm2\Fields\Validation;

use Gm2\Fields\FieldDefinition;
use InvalidArgumentException;
use WP_Error;

final class ValidatorRegistry
{
    /**
     * @var array<string, FieldValidatorInterface>
     */
    private array $validators = [];

    public static function withDefaults(): self
    {
        $registry = new self();
        $registry->register('text', new TextValidator());
        $registry->register('textarea', new TextareaValidator());
        $registry->register('number', new NumberValidator());
        $registry->register('currency', new CurrencyValidator());
        $registry->register('select', new SelectValidator());
        $registry->register('multiselect', new MultiSelectValidator());
        $registry->register('radio', new RadioValidator());
        $registry->register('checkbox', new CheckboxValidator());
        $registry->register('switch', new SwitchValidator());
        $registry->register('date', new DateValidator());
        $registry->register('time', new TimeValidator());
        $registry->register('datetime_tz', new DateTimeTzValidator());
        $registry->register('daterange', new DateRangeValidator());
        $registry->register('url', new UrlValidator());
        $registry->register('email', new EmailValidator());
        $registry->register('tel', new TelValidator());
        $registry->register('color', new ColorValidator());
        $registry->register('image', new ImageValidator());
        $registry->register('gallery', new GalleryValidator());
        $registry->register('file', new FileValidator());
        $registry->register('repeater', new RepeaterValidator());
        $registry->register('group', new GroupValidator());
        $registry->register('relationship_post', new RelationshipPostValidator());
        $registry->register('relationship_term', new RelationshipTermValidator());
        $registry->register('relationship_user', new RelationshipUserValidator());
        $registry->register('geopoint', new GeopointValidator());
        $registry->register('address', new AddressValidator());
        $registry->register('wysiwyg', new WysiwygValidator());
        $registry->register('computed', new ComputedValidator());

        return $registry;
    }

    public function register(string $type, FieldValidatorInterface $validator): void
    {
        $this->validators[$type] = $validator;
    }

    public function get(string $type): FieldValidatorInterface
    {
        if (!isset($this->validators[$type])) {
            throw new InvalidArgumentException(sprintf('No validator registered for "%s".', $type));
        }

        return $this->validators[$type];
    }

    /**
     * @param array<string, mixed> $context
     * @return true|WP_Error
     */
    public function validate(FieldDefinition $field, mixed $value, array $context = []): true|WP_Error
    {
        $type = $field->getType()->getName();
        if (!isset($this->validators[$type])) {
            return true;
        }

        return $this->validators[$type]->validate($field, $value, $context);
    }
}
