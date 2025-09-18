# Elementor Query Presets

The Gm2 WordPress Suite registers preset query IDs for Elementor's Posts, Loop Grid, and Archive Posts widgets so you can load curated views of each content blueprint without writing PHP. Entering one of the IDs below inside the widget's **Query → Query ID** field applies the corresponding `WP_Query` adjustments automatically.【F:src/Elementor/Query/Filters.php†L30-L40】

## Using a preset

1. Drop a Posts, Loop Grid, or Archive Posts widget onto your layout.
2. Open the **Query** panel and leave **Source** set to *Posts*.
3. Enter the desired preset (for example `gm2_upcoming_events`) inside **Query ID**.
4. Adjust **Posts Per Page** or add additional filters as needed—widget controls override the defaults because the presets only fill values when Elementor leaves them empty.【F:src/Elementor/Query/Filters.php†L319-L324】
5. Publish the template or page. The frontend query will now include the preset filters.

> **Tip:** Search-driven presets read query vars such as `?gm2_event_search=` or the shared `?gm2_search=` parameter. Pair your loop with a search form or custom links that append those keys to the page URL.【F:src/Elementor/Query/Filters.php†L55-L55】【F:src/Elementor/Query/Filters.php†L105-L105】【F:src/Elementor/Query/Filters.php†L151-L151】【F:src/Elementor/Query/Filters.php†L191-L191】【F:src/Elementor/Query/Filters.php†L266-L266】

## Preset reference

### Upcoming events (`gm2_upcoming_events`)

**What it does:** Shows published `event` posts scheduled for the future, limited to six items and ordered by the `start_date` field ascending.【F:src/Elementor/Query/Filters.php†L46-L65】

**Required data:**

- Custom post type `event` with a `start_date` DateTime field so the preset can compare against the current time and sort chronologically.【F:src/Elementor/Query/Filters.php†L54-L65】【F:docs/presets/events.md†L5-L23】

**Elementor setup:**

1. Assign the preset by entering `gm2_upcoming_events` in **Query ID**.
2. Optionally surface event search by linking to the archive with a `?gm2_event_search=` parameter or a generic `?gm2_search=` query string so visitors can filter the loop.【F:src/Elementor/Query/Filters.php†L55-L55】
3. Use the widget's layout controls to display the next six events; increasing **Posts Per Page** overrides the default cap.【F:src/Elementor/Query/Filters.php†L54-L54】【F:src/Elementor/Query/Filters.php†L319-L324】

### Past events (`gm2_past_events`)

**What it does:** Lists published `event` posts whose `start_date` is before today, ordered by most recent first with the same six item default.【F:src/Elementor/Query/Filters.php†L71-L90】

**Required data:**

- The `event` type and `start_date` DateTime field from the Events preset so the query can filter to past timestamps.【F:src/Elementor/Query/Filters.php†L71-L90】【F:docs/presets/events.md†L5-L23】

**Elementor setup:**

1. Set **Query ID** to `gm2_past_events` inside your widget.
2. Add archive links like `?gm2_event_search=webinar` to help visitors search historic sessions using the preset's built-in keyword support.【F:src/Elementor/Query/Filters.php†L80-L80】
3. Increase or decrease the number of results by changing **Posts Per Page** if you need more than the default six.【F:src/Elementor/Query/Filters.php†L79-L79】【F:src/Elementor/Query/Filters.php†L319-L324】

### Open jobs (`gm2_open_jobs`)

**What it does:** Returns published `job` posts tagged with a `status` meta value of `open`, limits the loop to ten entries, and sorts by the publish date descending so the most recent listing appears first.【F:src/Elementor/Query/Filters.php†L96-L112】

**Required data:**

- Custom post type `job` with a `status` text field that stores `open` for active roles so the preset knows which listings to display.【F:src/Elementor/Query/Filters.php†L96-L112】
- Fields like `date_posted`, `employment_type`, and `company` from the Jobs preset help populate the loop output.【F:docs/presets/jobs.md†L5-L24】

**Elementor setup:**

1. Enter `gm2_open_jobs` as the widget's **Query ID**.
2. Allow visitors to filter the board by appending `?gm2_job_search=` or the shared `?gm2_search=` parameter to the listing page URL; the preset maps either key to Elementor's search argument.【F:src/Elementor/Query/Filters.php†L105-L105】
3. Adjust **Posts Per Page** or add additional taxonomy filters in the widget if you want to narrow results beyond the `status` check.【F:src/Elementor/Query/Filters.php†L100-L112】【F:src/Elementor/Query/Filters.php†L319-L324】

### Properties for sale (`gm2_properties_sale`)

**What it does:** Loads published `property` posts assigned to the `property_status` term slugged `for-sale`, enforces a twelve item default, and orders results by the numeric `price` meta key ascending.【F:src/Elementor/Query/Filters.php†L118-L125】【F:src/Elementor/Query/Filters.php†L279-L293】

**Required data:**

- Custom post type `property` with a required numeric `price` field so ordering works as expected.【F:src/Elementor/Query/Filters.php†L279-L293】【F:docs/presets/real-estate.md†L5-L23】
- A taxonomy named `property_status` that includes a term whose slug is `for-sale`; apply that term to properties you want the preset to display.【F:src/Elementor/Query/Filters.php†L279-L293】

**Elementor setup:**

1. Set **Query ID** to `gm2_properties_sale` in your Posts or Loop Grid widget.
2. Create “Buy” archive links that add `?gm2_property_search=` or `?gm2_search=` to let shoppers search by keyword while staying inside the sale preset.【F:src/Elementor/Query/Filters.php†L284-L284】
3. Override the default page size or combine the preset with additional widget-level taxonomy filters if you want to drill down by `property_type` or location.【F:src/Elementor/Query/Filters.php†L279-L293】【F:src/Elementor/Query/Filters.php†L319-L324】

### Properties for rent (`gm2_properties_rent`)

**What it does:** Mirrors the sale preset but filters `property_status` to the `for-rent` slug so only rental listings appear; ordering and limits match the sale configuration.【F:src/Elementor/Query/Filters.php†L130-L137】【F:src/Elementor/Query/Filters.php†L279-L293】

**Required data:**

- The same `property` post type, `price` field, and `property_status` taxonomy as above, with a term whose slug is `for-rent` applied to each rental listing.【F:src/Elementor/Query/Filters.php†L130-L137】【F:src/Elementor/Query/Filters.php†L279-L293】【F:docs/presets/real-estate.md†L5-L23】

**Elementor setup:**

1. Enter `gm2_properties_rent` into the widget's **Query ID**.
2. Link to the template with `?gm2_property_search=` filters to spotlight specific cities or amenities while staying inside the rental loop.【F:src/Elementor/Query/Filters.php†L284-L284】
3. Use Elementor controls to change the number of listings per page or to stack additional taxonomy/field filters on top of the preset defaults.【F:src/Elementor/Query/Filters.php†L279-L293】【F:src/Elementor/Query/Filters.php†L319-L324】

### Nearby properties (`gm2_properties_nearby`)

**What it does:** Returns published `property` posts positioned within a latitude/longitude bounding box calculated from the `gm2_lat`, `gm2_lng`, and `gm2_radius` query vars, defaults to twelve results, sorts alphabetically by title, and maps the preset search field to `gm2_property_search` or the shared `gm2_search` parameter.【F:src/Elementor/Query/Filters.php†L142-L176】

**Required data:**

- Custom post type `property` with numeric `latitude` and `longitude` meta fields so the preset can apply the bounding box filters.【F:presets/real-estate/blueprint.json†L541-L572】
- Frontend controls that provide `gm2_lat`, `gm2_lng`, and `gm2_radius` query vars to describe the search centre and radius in kilometres.【F:presets/real-estate/blueprint.json†L1313-L1323】【F:src/Elementor/Query/Filters.php†L153-L173】
- Real Estate blueprint metadata for price, address, and imagery to populate each card alongside the geo filters.【F:presets/real-estate/blueprint.json†L176-L519】

**Elementor setup:**

1. Set **Query ID** to `gm2_properties_nearby` inside the Posts, Loop Grid, or Archive Posts widget.【F:presets/real-estate/blueprint.json†L1313-L1323】
2. Pass latitude, longitude, and radius values through the page URL—for example `/properties/?gm2_lat=34.05&gm2_lng=-118.24&gm2_radius=15`—so the preset can calculate the bounding box.【F:src/Elementor/Query/Filters.php†L153-L173】
3. Allow visitors to refine by keyword using `?gm2_property_search=` or the shared `?gm2_search=` query parameter; the preset assigns either value to Elementor's search argument before running the geo filters.【F:src/Elementor/Query/Filters.php†L148-L151】

### Nearby directory listings (`gm2_directory_nearby`)

**What it does:** Surfaces published `listing` posts within a latitude/longitude bounding box derived from the visitor-supplied centre point and radius, defaults to twelve results, and orders them alphabetically by title.【F:src/Elementor/Query/Filters.php†L182-L217】

**Required data:**

- Custom post type `listing` with numeric `latitude` and `longitude` meta values so the preset can apply the bounding-box filter.【F:src/Elementor/Query/Filters.php†L188-L211】
- Frontend controls (a form or map) that populate the `gm2_lat`, `gm2_lng`, and `gm2_radius` query vars used to scope the search area.【F:src/Elementor/Query/Filters.php†L188-L211】
- Address and contact fields from the Directory preset supply useful card details alongside the geo filters.【F:docs/presets/directory.md†L5-L24】

**Elementor setup:**

1. Enter `gm2_directory_nearby` into the widget's **Query ID**.
2. Build a form or buttons that pass `gm2_lat`, `gm2_lng`, and `gm2_radius` in the URL—for example `/directory/?gm2_lat=51.5&gm2_lng=-0.1&gm2_radius=10`—so the preset can calculate the bounding box.【F:src/Elementor/Query/Filters.php†L188-L213】
3. Provide a search box wired to `?gm2_directory_search=` or the shared `?gm2_search=` parameter for keyword filtering on top of the geo fence.【F:src/Elementor/Query/Filters.php†L191-L191】

### Directory listings by category (`gm2_directory_by_category`)

**What it does:** Displays published `listing` posts assigned to the `listing_category` terms supplied via preset-specific query vars, keeps the twelve item default, and sorts results alphabetically by title.【F:src/Elementor/Query/Filters.php†L222-L252】

**Required data:**

- Custom post type `listing` with the hierarchical `listing_category` taxonomy from the Directory preset so the query can filter by slug.【F:presets/directory/blueprint.json†L68-L87】【F:src/Elementor/Query/Filters.php†L233-L249】
- Links or UI that expose category slugs to visitors—for example cards that link to `/directory/?gm2_listing_category=restaurants`—to drive the taxonomy filter.【F:src/Elementor/Query/Filters.php†L233-L249】

**Elementor setup:**

1. Enter `gm2_directory_by_category` into the widget's **Query ID** to activate the preset filters.【F:src/Elementor/Query/Filters.php†L222-L249】
2. Pass category slugs through `?gm2_listing_category=` or `?gm2_directory_category=` query vars (the preset also honours Elementor's native `listing_category` argument) to control which listings appear.【F:src/Elementor/Query/Filters.php†L233-L249】
3. Layer keyword search by appending `?gm2_directory_search=` or the shared `?gm2_search=` parameter—the preset maps the values to Elementor's search field before applying the taxonomy clause.【F:src/Elementor/Query/Filters.php†L231-L232】【F:src/Elementor/Query/Filters.php†L243-L249】

### Active courses (`gm2_courses_active`)

**What it does:** Lists published `course` posts whose `status` meta value is `active`, uses nine items per page, and sorts by publish date descending so the freshest course appears first.【F:src/Elementor/Query/Filters.php†L257-L274】

**Required data:**

- Custom post type `course` with a `status` field that marks active entries so the preset can filter to live offerings.【F:src/Elementor/Query/Filters.php†L257-L274】
- Supporting fields like `provider`, `course_code`, and `course_url` from the Courses preset enrich each loop item.【F:docs/presets/courses.md†L5-L24】

**Elementor setup:**

1. Enter `gm2_courses_active` into **Query ID**.
2. Support keyword filtering by linking to URLs with `?gm2_course_search=` or the shared `?gm2_search=` query parameter; the preset translates either key into Elementor's search term.【F:src/Elementor/Query/Filters.php†L266-L266】
3. Change **Posts Per Page** or add taxonomy filters inside Elementor when you need more than nine results or subject-specific loops.【F:src/Elementor/Query/Filters.php†L265-L274】【F:src/Elementor/Query/Filters.php†L319-L324】

## Combining presets with custom filters

The helper methods that underpin each preset append meta and taxonomy clauses instead of replacing existing widget filters, so you can safely layer additional controls (for example a custom taxonomy filter inside Elementor) without losing the preset behaviour.【F:src/Elementor/Query/Filters.php†L330-L363】 This makes it easy to tailor archive templates per audience while still benefiting from the sensible defaults baked into each Gm2 blueprint.
