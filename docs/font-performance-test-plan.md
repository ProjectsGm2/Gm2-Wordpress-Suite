# Font Performance Test Plan

## Display-swap Injection

1. Load a page using Google Fonts.
2. Verify the `display=swap` parameter is appended to each font URL.
3. Confirm fonts render with `display: swap` by observing fallback text then webfont swap.
4. Measure layout stability in Lighthouse; cumulative layout shift should remain minimal.
5. Temporarily disable the injection and note any flash of invisible text (FOIT) or increased CLS.

## Self-host Workflow

1. Trigger the font self-host command or UI action.
2. Ensure font files download to the local `/fonts/` directory.
3. Confirm a local stylesheet is enqueued in place of the remote Google Fonts CSS.
4. Verify the original remote stylesheet is deregistered and no external font requests occur.

## Variant Limiting

1. Configure the plugin to limit loaded font variants (weights/styles).
2. Load the page and inspect network requests to ensure only the chosen variants are fetched.
3. Check the font stylesheet to confirm unselected variants are absent.

## Preloading WOFF2 Files

1. Add preload tags for up to three WOFF2 font files in the document head.
2. Load the page and confirm each preload is requested before render.
3. Ensure additional font files beyond the first three are not preloaded.

## REST Caching Headers and Server Rules

1. Request the font REST endpoint.
2. Inspect response headers to confirm `Cache-Control` and `ETag` values are set for caching.
3. Verify server rules (e.g., `.htaccess` or Nginx config) send proper caching headers for font files.

### Web Server Configuration

To mirror the REST endpoint's long‑term caching when fonts are served directly by the web server, add rules similar to the following:

**Apache**

```
<FilesMatch "\.(woff2?|ttf|otf)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
    Header set Cross-Origin-Resource-Policy "cross-origin"
</FilesMatch>
```

**Nginx**

```
location ~* \.(woff2?|ttf|otf)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    add_header Cross-Origin-Resource-Policy "cross-origin";
}
```

These directives ensure static font files receive the same caching directives as the plugin's REST endpoint.
