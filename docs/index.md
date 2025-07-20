# Gm2 WordPress Suite Updates

This release adds several new SEO and AI options:

- **Project Description** and **Custom Prompts** fields under **SEO → Context**. The project description falls back to the site tagline or a snippet of post content if empty.
- **AI Prompt** builder that compiles your Context answers into a single prompt for generating a short business summary.
- Each context field now includes a guiding question so users know what to enter.
- Additional context fields: **Core Offerings**, **Geographic Focus**, **Keyword Data**, **Competitor Landscape**, **Success Metrics** and **Buyer Personas**.
- Additional meta fields on post and taxonomy edit screens for Search Intent, Focus Keyword Limit, Number of Words and an "Improve Readability" checkbox.
- New helper functions `gm2_get_project_description()` and `gm2_ai_send_prompt()` supporting custom language models (`gpt-3.5-turbo` or `gpt-4`) and temperature settings.

Existing prompt logic automatically includes these options via `gm2_get_seo_context()`.

- The **Gm2 Qnty Discounts** widget now offers typography, color, shadow, padding and background controls for quantity labels and prices with Normal, Hover and Active tabs. Choose an icon for the currency symbol and style it alongside new box options for each quantity button, plus an **Icon Margin** option to control spacing around the currency icon. A new **Icon Color** style option lets you set colors for the currency icon in Normal, Hover and Active states.
- The price section now uses flexbox by default and includes responsive **Horizontal** and **Vertical** alignment controls to adjust `justify-content` and `align-items`.
- New **Wrap Options** control lets you enable flex wrapping so quantity buttons can stack on tablets and mobile.

## Building the AI Prompt

Before using the AI Prompt builder make sure the ChatGPT feature is enabled and your API key and model are configured under **Gm2 → ChatGPT**. Then open **SEO → Context** and click **Build AI Prompt** below the AI Prompt field. The plugin assembles a single prompt from all of your Context answers and sends it to ChatGPT. The resulting text appears in the textarea ready for review and editing.
