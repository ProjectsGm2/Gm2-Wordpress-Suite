#!/usr/bin/env node
const minimist = require('minimist');
const penthouse = require('penthouse');
const CleanCSS = require('clean-css');

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

  const { url, css, width, height } = args;

  if (!url || !css) {
    console.error('Usage: node critical.js --url <page url> --css <path to css> [--width <width>] [--height <height>]');
    process.exit(1);
  }

  try {
    const critical = await penthouse({ url, css, width, height });
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
