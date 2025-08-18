# Additional Field Types

The suite now includes several presentation-oriented field types that can be
used within field group definitions.

## Gradient

Stores a pair of colors and renders a CSS linear gradient.

```php
[
    'slug' => 'background',
    'type' => 'gradient',
    'label' => 'Background Gradient',
]
```

## Icon

Accepts a Dashicons class and displays the corresponding icon.

```php
[
    'slug' => 'icon',
    'type' => 'icon',
    'label' => 'Icon',
]
```

## Badge

Combines text with a background color to output a small label.

```php
[
    'slug' => 'badge',
    'type' => 'badge',
    'label' => 'Badge',
]
```

## Rating

Renders a star rating between zero and five.

```php
[
    'slug' => 'rating',
    'type' => 'rating',
    'label' => 'Rating',
]
```
