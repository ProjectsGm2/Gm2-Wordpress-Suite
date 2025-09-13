# Gm2 WordPress Suite Updates

See [model-cli.md](model-cli.md) for managing custom post types, taxonomies and fields via WP-CLI.
For a reference on field types, conditional logic and validation hooks, see [fields-and-validation.md](fields-and-validation.md).
For guidance on using the suite with Elementor, see [using-with-elementor.md](using-with-elementor.md).

For font optimization checks, review [font-performance-test-plan.md](font-performance-test-plan.md).

This release adds several new SEO and AI options:

- Added an index on the `timestamp` column of the `gm2_analytics_log` table for faster lookups. Existing installations upgrade automatically.
- Added an `ip_address` index to the `wc_ac_carts` table to speed up grouping cart sessions by visitor.
- Additional database indexes for frequently queried meta keys and custom tables,
  plus lazy metadata loading via `gm2_get_meta_value()`. See
  [caching.md](caching.md) for details on the caching strategy.
- Developer option to scaffold basic Twig and Blade templates in `theme-integration/` using registered field groups.
- **Project Description** and **Custom Prompts** fields under **SEO → Context**. The project description falls back to the site tagline or a snippet of post content if empty.
 - **Business Context Prompt** builder that compiles your Context answers into a single prompt for generating a short business summary.
- Each context field now includes a guiding question so users know what to enter.
- Additional context fields: **Core Offerings**, **Geographic Focus**, **Keyword Data**, **Competitor Landscape**, **Success Metrics** and **Buyer Personas**.
- Additional meta fields on post and taxonomy edit screens for Search Intent, Focus Keyword Limit, Number of Words and an "Improve Readability" checkbox.
- Taxonomy configuration now supports an `ordering` toggle. When enabled, term queries default to sorting by the `_gm2_order` meta value.
- New helper functions `gm2_get_project_description()` and `gm2_ai_send_prompt()` supporting custom language models (`gpt-3.5-turbo` or `gpt-4`) and temperature settings.

Existing prompt logic automatically includes these options via `gm2_get_seo_context()`.
- Repeated calls to `gm2_get_seo_context()` now reuse a cached array within each request for fewer database queries.

- The **Gm2 Qnty Discounts** widget now offers typography, color, shadow, padding and background controls for quantity labels and prices with Normal, Hover and Active tabs. Choose an icon for the currency symbol and style it alongside new box options for each quantity button, plus an **Icon Margin** option to control spacing around the currency icon. A new **Icon Color** style option lets you set colors for the currency icon in Normal, Hover and Active states.
- New **Registration/Login** Elementor widget displays WooCommerce login and registration forms with optional Google login when Site Kit is configured.
- The price section now uses flexbox by default and includes responsive **Horizontal** and **Vertical** alignment controls to adjust `justify-content` and `align-items`.
- New **Wrap Options** control lets you enable flex wrapping so quantity buttons can stack on tablets and mobile.
- Bulk AI research requests now use JSON-formatted AJAX calls for more robust error handling.
- Bulk AI now supports categories and product categories on the **Bulk AI Taxonomies** page.
- The **Bulk AI Taxonomies** screen defaults to the `manage_categories` capability which can be adjusted via the `gm2_bulk_ai_tax_capability` filter.
- Missing metadata filters on this screen let you show only terms without an SEO title or description. Preferences are stored as `gm2_bulk_ai_tax_missing_title` and `gm2_bulk_ai_tax_missing_description`.
- Taxonomy terms can be scheduled for background AI research via WP‑Cron using the **Schedule Batch** and **Cancel Batch** controls.
- Terms can be exported as a CSV via `admin-post.php?action=gm2_bulk_ai_tax_export`.

## Real-time Google Merchant Centre Data

Price, availability and inventory fields now update in real time. When a WooCommerce product's metadata changes, the values are stored and made available through a REST endpoint at `/gm2/v1/gmc/realtime`. The front-end script `public/js/gm2-gmc-realtime.js` polls this endpoint and dispatches a `gm2GmcRealtimeUpdate` event with the latest data.

Use the `gm2_gmc_realtime_fields` filter to add or remove fields from the real-time list:

```php
add_filter('gm2_gmc_realtime_fields', function ($fields) {
    $fields[] = 'sale_price';
    return $fields;
});
```

## Bulk AI

- Batching or rate-limiting of AI requests.
- The new "Apply All" button for applying suggestions in bulk.
- A "Select Analyzed" button to quickly mark analyzed suggestions before applying them in bulk.
- The global progress indicator tracking analysis and apply actions.
- Reuse of cached AI results with options to refresh or clear them.
- Progress messages now use translation functions for localization.
- Each row in the table includes a "Select All" checkbox to quickly apply that post's suggestions.
- Rows highlight after applying suggestions so you can see what changed.
- The review table now displays Focus Keywords and Long Tail Keywords columns alongside Title, SEO Title and Description.

### Bulk AI for Taxonomies

Use the **Bulk AI Taxonomies** page under **Gm2 → Bulk AI Taxonomies** to generate AI SEO titles, descriptions and keyword suggestions for taxonomy terms. The review table now includes **Focus Keywords** and **Long Tail Keywords** columns alongside SEO Title and Description. Use the **Only terms missing SEO Title** and **Only terms missing Description** filters to show only terms lacking metadata. After selecting terms, click **Schedule Batch** to queue them for background processing via WP‑Cron or **Cancel Batch** to remove pending jobs. The **Export CSV** button downloads a `gm2-bulk-ai-tax.csv` file listing `term_id`, `name`, `seo_title`, `description`, `focus_keywords`, `long_tail_keywords` and `taxonomy`.

### Bulk AI Task Summary

- **Edit Post links** – titles link to each edit screen from `display_bulk_ai_page()` in `admin/Gm2_SEO_Admin.php`.
- **Usage instructions** – short description printed in the same function before the settings form.
- **Spinner during analysis** – handled by `showSpinner()` and `hideSpinner()` in `admin/js/gm2-bulk-ai.js`.
- **Fade-out rows** – `gm2-applied` class removed after a delay in `gm2-bulk-ai.js` with styles in `admin/css/gm2-seo.css`.
- **Cancel analysis** – `.gm2-bulk-cancel` listener in `gm2-bulk-ai.js` stops the queue.
- **Missing metadata filters** – checkboxes `gm2_missing_title` and `gm2_missing_description` saved in `display_bulk_ai_page()`.
- **ARIA progress bar** – `<progress>` element with `role="progressbar"` and button `aria-label` attributes in `display_bulk_ai_page()`.
- **Search field** – `gm2_search_title` input filters posts by title.
- **CSV export** – `handle_bulk_ai_export()` outputs a `gm2-bulk-ai.csv` file containing `ID`, `Title`, `SEO Title`, `Description`, `Focus Keywords` and `Long Tail Keywords` columns.
- **Taxonomy CSV export** – `handle_bulk_ai_tax_export()` outputs a `gm2-bulk-ai-tax.csv` file.
- **User settings** – page size and filters stored per user via `update_user_meta()`.
- **Improved AJAX errors** – `.fail()` blocks in `gm2-bulk-ai.js` display parsed messages.
- **Undo option** – `.gm2-undo-btn` triggers `ajax_bulk_ai_undo()` in `Gm2_SEO_Admin.php`.
- **Color-coded statuses** – row classes `gm2-status-new`, `gm2-status-analyzed` and `gm2-status-applied` styled in `admin/css/gm2-seo.css`.

## Building the Business Context Prompt

Before using the Business Context Prompt builder make sure the ChatGPT feature is enabled and your API key and model are configured under **Gm2 → ChatGPT**. Then open **SEO → Context** and click **Generate AI Prompt** below the Business Context Prompt field. The plugin assembles a single prompt from all of your Context answers and sends it to ChatGPT. The resulting text appears in the textarea ready for review and editing.

## ChatGPT Logging

When logging is enabled, the **Gm2 → ChatGPT** page shows a table of recent prompts and responses. This data is written to `chatgpt.log` inside the plugin directory. Use the **Enable Logging** checkbox on the ChatGPT settings page to turn logging on or off.

Each log row now includes small expand/collapse controls. Click the **Prompt sent** or **Response received** label to toggle the full text of that entry. This makes it easier to browse long conversations without leaving the page cluttered.

If you need to clear the log and start over, click the **Reset Logs** button below the table.

## Abandoned Carts

Enable the module from **Gm2 → Dashboard** to begin tracking cart sessions. Activation creates four tables—`wp_wc_ac_carts`, `wp_wc_ac_email_queue`, `wp_wc_ac_recovered` and `wp_wc_ac_cart_activity`—that store carts, queued messages, recovered orders and item‑level activity.

Select an Elementor popup under **Gm2 → Cart Settings** to ask shoppers for an email address or phone number before they leave. The plugin records contact details from that popup and from the checkout billing fields so each cart is linked to an email and/or phone when available.

The **Gm2 → Abandoned Carts** screen groups records by IP address so multiple visits from the same shopper appear as a single row with combined browsing time and revisit counts. An index on `ip_address` in the carts table speeds up these lookups. Click a row’s **Cart Activity Log** link to view the add/remove/quantity events and revisit entry/exit actions pulled from the activity table. Visit entries now record the returning IP address along with entry and exit URLs and timestamps for each session.

The activity log loads entries 50 at a time through the `gm2_ac_get_activity` AJAX action. Pass `page` and `per_page` values to paginate through activity and visit records; the admin UI requests additional pages as you scroll.

Captured email and phone values appear in their own columns on this page, making it easy to follow up with shoppers who abandon their carts.

Activity pings that mark carts as active are throttled to one request every 30 seconds by default. Adjust this interval with the `gm2_ac_active_interval_ms` filter if you need more or less frequent updates.

Developers can adjust the inactivity window using the `gm2_ac_mark_abandoned_interval` filter and send custom recovery emails by hooking into `gm2_ac_send_message` when the hourly `gm2_ac_process_queue` task runs. A default handler, `gm2_ac_send_default_email`, sends a WooCommerce-styled message via `wp_mail`. Use `remove_action( 'gm2_ac_send_message', 'Gm2\\gm2_ac_send_default_email' )` to disable it and the `gm2_ac_default_email_subject` and `gm2_ac_default_email_body` filters to customize the content.

Use the `gm2_ac_skip_admin` filter to include administrator sessions while testing abandoned cart features. It defaults to `true` so admin carts are ignored unless the filter returns `false`.

To finalize statuses on demand run `wp gm2 ac process` from the command line or click the **Process Pending Carts** button on the **Gm2 → Abandoned Carts** screen. Both trigger the same logic that WP‑Cron uses to mark inactive carts as abandoned.

If messages fail to send after hitting their retry limit you can attempt them again with `wp gm2 ac retry-failed`.

## Exporting SEO Settings

Use the **Export Settings** button on the SEO dashboard to download a `gm2-seo-settings.json` file containing all options that start with `gm2_`. The matching **Import Settings** form accepts the same JSON format and updates each option. The file is a simple key/value object:

```json
{
  "gm2_sitemap_enabled": "1",
  "gm2_schema_product": "1"
}
```

Nonce fields on both forms ensure only authorized users can perform these actions.

## Seed Keywords Format

The first AI SEO response should return the `seed_keywords` value as an array of strings:

```json
{
  "seed_keywords": ["alpha", "beta"]
}
```

These seed keywords are refined with Google Ads data before the final results are presented.

## SEO Tools

Focus keywords used across posts and terms are now tracked. AI Research includes
the list of existing keywords in its prompt and filters them from suggestions.
The "Check Rules" action fails when a focus keyword is already in use.

## Development Task List

The following tasks track upcoming enhancements for the Gm2 WordPress Suite. Each item briefly explains the goal of the improvement so contributors know what to tackle next.

1. **Bulk AI scheduling via WP‑Cron** – queue analysis jobs in the background so large batches can run automatically.

For guidance on using the suite with Elementor, see [using-with-elementor.md](using-with-elementor.md).
