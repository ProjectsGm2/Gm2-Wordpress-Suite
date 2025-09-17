# Core XML Sitemap Integration

GM2 now relies on the WordPress 5.5+ [core XML sitemap API](https://make.wordpress.org/core/2020/07/22/wordpress-5-5-introduces-xml-sitemaps/) instead of maintaining a custom renderer. The `Gm2\SEO\Sitemaps\CoreProvider` class registers lightweight providers with core during `init` and augments the default post and taxonomy providers with GM2-specific content.

## Filters

The provider exposes several filters to adjust sitemap behaviour:

| Filter | Description |
| ------ | ----------- |
| `gm2_supported_post_types` | Append custom post types that should appear in the sitemap. |
| `gm2_supported_taxonomies` | Append custom taxonomies that should appear in the sitemap. |
| `gm2_sitemaps_skip_statuses` | Modify the list of post statuses excluded from sitemap queries. Useful for removing drafts, private content, or other custom statuses. |
| `gm2_sitemaps_split_taxonomies` | Provide a map of taxonomy names to term counts per sitemap page to further split large term archives. |

## Images and Last Modified Dates

For GM2 post types the provider enriches sitemap entries with:

- A `lastmod` timestamp sourced from `get_post_modified_time()`.
- Image metadata populated from the post thumbnail. When address fields are available the caption/title is derived from existing GM2 field helpers.

When GM2 taxonomy metadata contains `gm2_last_modified` or `gm2_updated_at` values, those timestamps are surfaced as `lastmod` entries. The provider also attempts to resolve representative term images via GM2 field helpers or the `_thumbnail_id` meta value.

## Disabling Sitemaps

All filters short-circuit when the `gm2_sitemap_enabled` option is set to `0`, allowing sites to disable sitemap output entirely while still retaining the legacy manual sitemap generator.
