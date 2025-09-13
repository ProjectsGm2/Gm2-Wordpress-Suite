# Elementor Query Examples

The Gm2 WordPress Suite adds a **GM2 CP** option to Elementor Pro's Posts widget. Selecting this query ID reads additional controls and converts them into `WP_Query` arguments.

## Examples

1. **Filter by post type and taxonomy**
   - Choose **GM2 CP** as the query.
   - Set *Post Type* to `event`.
   - Select taxonomy `location` with term IDs `12` and `34`.

2. **Price range and meta comparison**
   - Provide a *Meta Key* of `_stock` with compare `>` and value `0` to show in-stock items.
   - Set *Price Min* to `10` and *Price Max* to `100` to restrict results.

3. **Date and geodistance**
   - Enter *Date After* `2024-01-01`.
   - Supply latitude `40.7128`, longitude `-74.0060` and radius `25` (km) to find nearby posts.

These controls map to `post_type`, `tax_query`, `meta_query`, `date_query` and geo bounding boxes, allowing advanced filtering without custom code.
