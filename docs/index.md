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

## Bulk AI

- Batching or rate-limiting of AI requests.
- The new "Apply All" button for applying suggestions in bulk.
- The global progress indicator tracking analysis and apply actions.
- Reuse of cached AI results with options to refresh or clear them.

## Building the Business Context Prompt

Before using the Business Context Prompt builder make sure the ChatGPT feature is enabled and your API key and model are configured under **Gm2 → ChatGPT**. Then open **SEO → Context** and click **Generate AI Prompt** below the Business Context Prompt field. The plugin assembles a single prompt from all of your Context answers and sends it to ChatGPT. The resulting text appears in the textarea ready for review and editing.

## ChatGPT Logging

When logging is enabled, the **Gm2 → ChatGPT** page shows a table of recent prompts and responses. This data is written to `chatgpt.log` inside the plugin directory. Use the **Enable Logging** checkbox on the ChatGPT settings page to turn logging on or off.

Each log row now includes small expand/collapse controls. Click the **Prompt sent** or **Response received** label to toggle the full text of that entry. This makes it easier to browse long conversations without leaving the page cluttered.

If you need to clear the log and start over, click the **Reset Logs** button below the table.

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
