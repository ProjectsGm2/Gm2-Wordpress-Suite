# Testing

To validate script loading and consent workflow:

- In Lighthouse, confirm JS execution time decreases and analytics scripts stay absent on idle load.
- Focus a `data-recaptcha` form and verify reCAPTCHA loads within ~200 ms.
- Change `aeConsent` value and dispatch `aeConsentChanged` to ensure analytics imports after consent.

To verify jQuery handling:

- Visit a page with no scripts depending on jQuery; confirm `jquery.js` and `jquery-migrate.js` are absent and `js-optimizer.log` records their removal.
- Visit a page using Elementor or another jQuery-dependent plugin or theme; confirm both jQuery files remain loaded.

