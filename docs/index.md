# Gm2 WordPress Suite Updates

This release adds several new SEO and AI options:

- **Project Description** and **Custom Prompts** fields under **SEO → Context**. The project description falls back to the site tagline or a snippet of post content if empty.
 - **Business Context Prompt** builder that compiles your Context answers into a single prompt for generating a short business summary.
- Each context field now includes a guiding question so users know what to enter.
- Additional context fields: **Core Offerings**, **Geographic Focus**, **Keyword Data**, **Competitor Landscape**, **Success Metrics** and **Buyer Personas**.
- Additional meta fields on post and taxonomy edit screens for Search Intent, Focus Keyword Limit, Number of Words and an "Improve Readability" checkbox.
- New helper functions `gm2_get_project_description()` and `gm2_ai_send_prompt()` supporting custom language models (`gpt-3.5-turbo` or `gpt-4`) and temperature settings.

Existing prompt logic automatically includes these options via `gm2_get_seo_context()`.

- The **Gm2 Qnty Discounts** widget now offers typography, color, shadow, padding and background controls for quantity labels and prices with Normal, Hover and Active tabs. Choose an icon for the currency symbol and style it alongside new box options for each quantity button, plus an **Icon Margin** option to control spacing around the currency icon. A new **Icon Color** style option lets you set colors for the currency icon in Normal, Hover and Active states.
- The price section now uses flexbox by default and includes responsive **Horizontal** and **Vertical** alignment controls to adjust `justify-content` and `align-items`.
- New **Wrap Options** control lets you enable flex wrapping so quantity buttons can stack on tablets and mobile.
- Bulk AI research requests now use JSON-formatted AJAX calls for more robust error handling.
- Bulk AI now supports categories and product categories on the **Bulk AI Taxonomies** page.

## Bulk AI

- Batching or rate-limiting of AI requests.
- The new "Apply All" button for applying suggestions in bulk.
- The global progress indicator tracking analysis and apply actions.
- Reuse of cached AI results with options to refresh or clear them.
- Progress messages now use translation functions for localization.
- Each row in the table includes a "Select All" checkbox to quickly apply that post's suggestions.
- Rows highlight after applying suggestions so you can see what changed.

### Bulk AI Task Summary

- **Edit Post links** – titles link to each edit screen from `display_bulk_ai_page()` in `admin/Gm2_SEO_Admin.php`.
- **Usage instructions** – short description printed in the same function before the settings form.
- **Spinner during analysis** – handled by `showSpinner()` and `hideSpinner()` in `admin/js/gm2-bulk-ai.js`.
- **Fade-out rows** – `gm2-applied` class removed after a delay in `gm2-bulk-ai.js` with styles in `admin/css/gm2-seo.css`.
- **Cancel analysis** – `#gm2-bulk-cancel` listener in `gm2-bulk-ai.js` stops the queue.
- **Missing metadata filters** – checkboxes `gm2_missing_title` and `gm2_missing_description` saved in `display_bulk_ai_page()`.
- **ARIA progress bar** – `<progress>` element with `role="progressbar"` and button `aria-label` attributes in `display_bulk_ai_page()`.
- **Select None control** – `#gm2-select-none` button in `gm2-bulk-ai.js` clears all selections.
- **Search field** – `gm2_search_title` input filters posts by title.
- **CSV export** – `handle_bulk_ai_export()` outputs a `gm2-bulk-ai.csv` file.
- **User settings** – page size and filters stored per user via `update_user_meta()`.
- **Improved AJAX errors** – `.fail()` blocks in `gm2-bulk-ai.js` display parsed messages.
- **Scheduled via WP‑Cron** – planned feature to queue analysis jobs.
- **Undo option** – `.gm2-undo-btn` triggers `ajax_bulk_ai_undo()` in `Gm2_SEO_Admin.php`.
- **Color-coded statuses** – row classes `gm2-status-new`, `gm2-status-analyzed` and `gm2-status-applied` styled in `admin/css/gm2-seo.css`.

## Building the Business Context Prompt

Before using the Business Context Prompt builder make sure the ChatGPT feature is enabled and your API key and model are configured under **Gm2 → ChatGPT**. Then open **SEO → Context** and click **Generate AI Prompt** below the Business Context Prompt field. The plugin assembles a single prompt from all of your Context answers and sends it to ChatGPT. The resulting text appears in the textarea ready for review and editing.

## ChatGPT Logging

When logging is enabled, the **Gm2 → ChatGPT** page shows a table of recent prompts and responses. This data is written to `chatgpt.log` inside the plugin directory. Use the **Enable Logging** checkbox on the ChatGPT settings page to turn logging on or off.

Each log row now includes small expand/collapse controls. Click the **Prompt sent** or **Response received** label to toggle the full text of that entry. This makes it easier to browse long conversations without leaving the page cluttered.

If you need to clear the log and start over, click the **Reset Logs** button below the table.

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

## Planned Features

- Accessible tabs with ARIA roles and keyboard controls.
- Spinner feedback during AI SEO research.
- SEO input placeholders with help tooltips.
- Live search snippet preview in the SEO meta box.
- "Test Connection" button on the Google Connect screen.
- Clear 404 Logs button on the Redirects tab.
- Link counts for all public post types.
- Sitemap Path field placeholder with tooltip.
- Real-time character counts in the SEO meta box.
