# Asset Build Workflow

This directory contains scripts used to bundle front‑end assets with [esbuild](https://esbuild.github.io/).

## Usage

```bash
npm run build:assets
```

The build step generates the following files in `assets/dist/`:

- `ae-main.modern.js` – ES module targeting modern browsers (`es2020`).
- `ae-main.legacy.js` – ES5 IIFE build. Enqueue only when the "Send Legacy (nomodule) Bundle" option (`ae_js_nomodule_legacy`) is enabled.
- `contact.js`, `product.js`, `blog.js` – page‑specific bundles.
- `polyfills.js` – loaded at runtime only when `needPolyfills()` detects missing browser features.
