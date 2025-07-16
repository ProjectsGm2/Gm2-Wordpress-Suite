# Gm2 WordPress Suite Updates

This release adds several new SEO and AI options:

- **Project Description** and **Custom Prompts** fields under **SEO → Context**. The project description falls back to the site tagline or a snippet of post content if empty.
- Each context field now includes a guiding question so users know what to enter.
- Additional context fields: **Core Offerings**, **Geographic Focus**, **Keyword Data**, **Competitor Landscape**, **Success Metrics** and **Buyer Personas**.
- Additional meta fields on post and taxonomy edit screens for Search Intent, Focus Keyword Limit, Number of Words and an "Improve Readability" checkbox.
- New helper functions `gm2_get_project_description()` and `gm2_ai_send_prompt()` supporting custom language models (`gpt-3.5-turbo` or `gpt-4`) and temperature settings.

Existing prompt logic automatically includes these options via `gm2_get_seo_context()`.

- The **Gm2 Qnty Discounts** widget now offers typography, color, shadow, padding and background controls for quantity labels and prices with Normal, Hover and Active tabs. Choose an icon for the currency symbol and style it alongside new box options for each quantity button.
