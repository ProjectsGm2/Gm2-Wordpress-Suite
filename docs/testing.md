# Testing

To validate script loading and consent workflow:

- In Lighthouse, confirm JS execution time decreases and analytics scripts stay absent on idle load.
- Focus a `data-recaptcha` form and verify reCAPTCHA loads within ~200 ms.
- Change `aeConsent` value and dispatch `aeConsentChanged` to ensure analytics imports after consent.

