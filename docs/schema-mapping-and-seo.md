# Schema Mapping and SEO

This guide explains how to map content types to schema.org models and expose SEO metadata.

## Mapping UI Walkthrough and Vertical Presets

Open **Gm2 → Schema Mapping** in the WordPress admin to map custom post types and taxonomies to schema.org properties.

1. Select a post type from the dropdown.
2. Choose a vertical preset such as **Course**, **Directory**, **Event**, **Job** or **Real Estate** to pre‑populate common properties.
3. Use the field picker to match each schema property with a meta field or block attribute.
4. Save your map to generate JSON‑LD automatically on the front end.

Presets provide a quick starting point for popular verticals while still allowing individual fields to be customised.

### Preset property reference

| Preset | Key properties pre-filled |
| --- | --- |
| LocalBusiness | `name`, `image`, `address`, `geo`, `telephone`, `openingHoursSpecification`, `url`, `sameAs`, `priceRange` |
| Event | `name`, `startDate`, `endDate`, `location`, `image`, `offers`, `organizer` |
| RealEstateListing | `name`, `description`, `url`, `address`, `geo`, `price`, `offers` |
| JobPosting | `title`, `description`, `datePosted`, `validThrough`, `employmentType`, `jobLocation`, `baseSalary`, `hiringOrganization` |
| Course | `name`, `description`, `provider`, `url`, `courseCode`, `courseInstance.startDate`, `courseInstance.endDate`, `courseInstance.location.name`, `courseInstance.offers.price` |

Use dotted paths (for example `courseInstance.startDate`) to target nested objects; the suite automatically infers the correct `@type` for nested structures such as `Place`, `Offer`, and `CourseInstance`.

## JSON‑LD Output Examples

### Singular Page

```json
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Sample Event",
  "startDate": "2024-08-01T19:00:00",
  "location": {
    "@type": "Place",
    "name": "Example Venue"
  }
}
```

### Archive Page

```json
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Event Archive",
  "hasPart": [{
    "@type": "Event",
    "name": "Sample Event"
  }]
}
```

The singular schema is filtered through `gm2_cp_schema_data` while archives use `gm2_cp_schema_archive_data`. Use `gm2_seo_cp_schema` to disable output entirely or swap in a custom schema array.

## Meta Templates and SEO Plugin Hooks

The suite renders title, description and keyword tags based on the post context. Developers can customise the markup or inject tags from third‑party SEO plugins.

```php
add_filter( 'gm2_meta_tags', function ( $html, $data ) {
    // Replace the default tags with output from a different SEO plugin.
    return my_seo_plugin_render_tags( $data );
}, 10, 2 );
```

Templates accept tokens like `{title}`, `{excerpt}` and `{site_name}`. Override them globally via the `gm2_meta_title_template` and `gm2_meta_description_template` filters or per post type with `gm2_meta_template_{post_type}`.

Third‑party plugins can also modify JSON‑LD before it is printed:

```php
add_filter( 'gm2_cp_schema_data', function ( $schema, $post_id ) {
    $schema['publisher'] = [
        '@type' => 'Organization',
        'name'  => get_bloginfo( 'name' )
    ];
    return $schema;
}, 10, 2 );
```

These hooks allow SEO platforms like Yoast SEO or Rank Math to integrate with the suite without disabling its built‑in features.

