<?php

namespace Gm2\Fields\Sanitizers;

use Gm2\Fields\FieldDefinition;
use InvalidArgumentException;

final class SanitizerRegistry
{
    /**
     * @var array<string, FieldSanitizerInterface>
     */
    private array $sanitizers = [];

    public static function withDefaults(): self
    {
        $registry = new self();
        $registry->register('text', new TextSanitizer());
        $registry->register('textarea', new TextareaSanitizer());
        $registry->register('number', new NumberSanitizer());
        $registry->register('currency', new CurrencySanitizer());
        $registry->register('select', new SelectSanitizer());
        $registry->register('multiselect', new MultiSelectSanitizer());
        $registry->register('radio', new RadioSanitizer());
        $registry->register('checkbox', new CheckboxSanitizer());
        $registry->register('switch', new SwitchSanitizer());
        $registry->register('date', new DateSanitizer());
        $registry->register('time', new TimeSanitizer());
        $registry->register('datetime_tz', new DateTimeTzSanitizer());
        $registry->register('daterange', new DateRangeSanitizer());
        $registry->register('url', new UrlSanitizer());
        $registry->register('email', new EmailSanitizer());
        $registry->register('tel', new TelSanitizer());
        $registry->register('color', new ColorSanitizer());
        $registry->register('image', new ImageSanitizer());
        $registry->register('gallery', new GallerySanitizer());
        $registry->register('file', new FileSanitizer());
        $registry->register('repeater', new RepeaterSanitizer());
        $registry->register('group', new GroupSanitizer());
        $registry->register('relationship_post', new RelationshipPostSanitizer());
        $registry->register('relationship_term', new RelationshipTermSanitizer());
        $registry->register('relationship_user', new RelationshipUserSanitizer());
        $registry->register('geopoint', new GeopointSanitizer());
        $registry->register('address', new AddressSanitizer());
        $registry->register('wysiwyg', new WysiwygSanitizer());
        $registry->register('computed', new ComputedSanitizer());

        return $registry;
    }

    public function register(string $type, FieldSanitizerInterface $sanitizer): void
    {
        $this->sanitizers[$type] = $sanitizer;
    }

    public function get(string $type): FieldSanitizerInterface
    {
        if (!isset($this->sanitizers[$type])) {
            throw new InvalidArgumentException(sprintf('No sanitizer registered for "%s".', $type));
        }

        return $this->sanitizers[$type];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sanitize(FieldDefinition $field, mixed $value, array $context = []): mixed
    {
        $type = $field->getType()->getName();
        if (!isset($this->sanitizers[$type])) {
            return $value;
        }

        return $this->sanitizers[$type]->sanitize($field, $value, $context);
    }
}
