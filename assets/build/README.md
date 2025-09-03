# Asset Build Workflow

This directory contains scripts used to bundle front‑end assets with [esbuild](https://esbuild.github.io/).

## Usage

```bash
npm run build:assets
# Inline sourcemaps for debugging
WP_DEBUG=true npm run build:assets
```

The build step generates content‑hashed files in `assets/dist/` (for example, `ae-main.modern.abc123.js`) and records their mapping in `assets/build/manifest.json`.

- `ae-main.modern.[hash].js` – ES module targeting modern browsers (`es2020`).
- `ae-main.legacy.[hash].js` – ES5 IIFE build. Enqueue only when the "Send Legacy (nomodule) Bundle" option (`ae_js_nomodule_legacy`) is enabled.
- `contact.[hash].js`, `product.[hash].js`, `blog.[hash].js` – page‑specific bundles.
- `polyfills.[hash].js` – loaded at runtime only when `needPolyfills()` detects missing browser features.
- `ae-lazy.[hash].js` – on-demand loader for third-party scripts.
- `modules/*.[hash].js` – individual modules loaded by `ae-lazy.js`.
