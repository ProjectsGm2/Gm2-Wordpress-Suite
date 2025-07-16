# Gm2 WordPress Suite Updates

This release adds several new SEO and AI options:

- **Project Description** and **Custom Prompts** fields under **SEO → Context**. The project description falls back to the site tagline or a snippet of post content if empty.
- Additional meta fields on post and taxonomy edit screens for Search Intent, Focus Keyword Limit, Number of Words and an "Improve Readability" checkbox.
- New helper functions `gm2_get_project_description()` and `gm2_ai_send_prompt()` supporting custom language models (`gpt-3.5-turbo` or `gpt-4`) and temperature settings.

Existing prompt logic automatically includes these options via `gm2_get_seo_context()`.
