<?php

namespace Gm2\Fields;

use Gm2\Fields\Types\FieldTypeInterface;
use InvalidArgumentException;

final class FieldTypeRegistry
{
    /**
     * @var array<string, callable(array<string, mixed>): FieldTypeInterface|class-string<FieldTypeInterface>>
     */
    private array $factories = [];

    public static function withDefaults(): self
    {
        $registry = new self();
        $registry->register('text', \Gm2\Fields\Types\TextFieldType::class);
        $registry->register('textarea', \Gm2\Fields\Types\TextareaFieldType::class);
        $registry->register('number', \Gm2\Fields\Types\NumberFieldType::class);
        $registry->register('currency', \Gm2\Fields\Types\CurrencyFieldType::class);
        $registry->register('select', \Gm2\Fields\Types\SelectFieldType::class);
        $registry->register('multiselect', \Gm2\Fields\Types\MultiSelectFieldType::class);
        $registry->register('radio', \Gm2\Fields\Types\RadioFieldType::class);
        $registry->register('checkbox', \Gm2\Fields\Types\CheckboxFieldType::class);
        $registry->register('switch', \Gm2\Fields\Types\SwitchFieldType::class);
        $registry->register('date', \Gm2\Fields\Types\DateFieldType::class);
        $registry->register('time', \Gm2\Fields\Types\TimeFieldType::class);
        $registry->register('datetime_tz', \Gm2\Fields\Types\DateTimeTzFieldType::class);
        $registry->register('daterange', \Gm2\Fields\Types\DateRangeFieldType::class);
        $registry->register('url', \Gm2\Fields\Types\UrlFieldType::class);
        $registry->register('email', \Gm2\Fields\Types\EmailFieldType::class);
        $registry->register('tel', \Gm2\Fields\Types\TelFieldType::class);
        $registry->register('color', \Gm2\Fields\Types\ColorFieldType::class);
        $registry->register('image', \Gm2\Fields\Types\ImageFieldType::class);
        $registry->register('gallery', \Gm2\Fields\Types\GalleryFieldType::class);
        $registry->register('file', \Gm2\Fields\Types\FileFieldType::class);
        $registry->register('repeater', \Gm2\Fields\Types\RepeaterFieldType::class);
        $registry->register('group', \Gm2\Fields\Types\GroupFieldType::class);
        $registry->register('relationship_post', \Gm2\Fields\Types\RelationshipPostFieldType::class);
        $registry->register('relationship_term', \Gm2\Fields\Types\RelationshipTermFieldType::class);
        $registry->register('relationship_user', \Gm2\Fields\Types\RelationshipUserFieldType::class);
        $registry->register('geopoint', \Gm2\Fields\Types\GeopointFieldType::class);
        $registry->register('address', \Gm2\Fields\Types\AddressFieldType::class);
        $registry->register('wysiwyg', \Gm2\Fields\Types\WysiwygFieldType::class);
        $registry->register('computed', \Gm2\Fields\Types\ComputedFieldType::class);

        return $registry;
    }

    /**
     * @param callable(array<string, mixed>): FieldTypeInterface|class-string<FieldTypeInterface> $factory
     */
    public function register(string $type, callable|string $factory): void
    {
        $this->factories[$type] = $factory;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function create(string $type, array $settings): FieldTypeInterface
    {
        if (!isset($this->factories[$type])) {
            throw new InvalidArgumentException(sprintf('Unknown field type "%s".', $type));
        }

        $factory = $this->factories[$type];

        if (is_string($factory)) {
            $instance = new $factory();
            if (!$instance instanceof FieldTypeInterface) {
                throw new InvalidArgumentException(sprintf('Factory for "%s" must return FieldTypeInterface.', $type));
            }

            return $instance;
        }

        $instance = $factory($settings);
        if (!$instance instanceof FieldTypeInterface) {
            throw new InvalidArgumentException(sprintf('Factory for "%s" must return FieldTypeInterface.', $type));
        }

        return $instance;
    }
}
