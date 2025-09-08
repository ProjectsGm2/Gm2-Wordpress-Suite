#!/usr/bin/env node
const minimist = require('minimist');
const penthouse = require('penthouse');
const CleanCSS = require('clean-css');
const fs = require('fs');

(async () => {
  const args = minimist(process.argv.slice(2), {
    string: ['url', 'css'],
    number: ['width', 'height'],
    alias: {
      u: 'url',
      c: 'css',
      w: 'width',
      h: 'height'
    }
  });

  const { url: rawUrl, css: rawCss, width, height } = args;

  const url = typeof rawUrl === 'string' ? rawUrl.trim() : '';
  const css = typeof rawCss === 'string' ? rawCss.trim() : '';

  if (!url || !css) {
    console.error('Usage: node critical.js --url <page url> --css <path to css> [--width <width>] [--height <height>]');
    process.exit(1);
  }

  let parsedUrl;
  try {
    parsedUrl = new URL(url);
  } catch (e) {
    console.error('Please provide a valid --url.');
    process.exit(1);
  }

  const cssFiles = css.split(',').map(f => f.trim()).filter(Boolean);
  if (!cssFiles.length || !cssFiles.every(f => fs.existsSync(f))) {
    console.error('CSS file not found.');
    process.exit(1);
  }

  try {
    const critical = await penthouse({ url: parsedUrl.href, css: cssFiles.join(','), width, height });
    const output = new CleanCSS().minify(critical);
    if (output.errors && output.errors.length) {
      console.error(output.errors.join('\n'));
      process.exit(1);
    }
    process.stdout.write(output.styles);
  } catch (err) {
    console.error(err.message || err);
    process.exit(1);
  }
})();
