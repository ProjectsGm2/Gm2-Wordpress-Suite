import { build } from 'esbuild';
import { readdir, writeFile, mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';
import { createHash } from 'node:crypto';

const debug = process.env.WP_DEBUG === 'true' ||
  process.env.WP_DEBUG === '1' ||
  process.argv.includes('--debug');

const shared = {
  bundle: true,
  minify: true,
  sourcemap: debug ? 'inline' : false,
  platform: 'browser'
};

const manifest = {};

async function buildFile(entryPoint, outFile, options = {}) {
  const result = await build({
    entryPoints: [entryPoint],
    write: false,
    ...shared,
    ...options
  });

  const contents = result.outputFiles[0].text;
  const hash = createHash('md5').update(contents).digest('hex').slice(0, 8);
  const hashed = outFile.replace(/\.js$/, `.${hash}.js`);
  const dest = `assets/dist/${hashed}`;

  await mkdir(dirname(dest), { recursive: true });
  await writeFile(dest, contents);

  manifest[outFile] = hashed;
}

await buildFile('assets/src/ae-main.js', 'ae-main.modern.js', {
  format: 'esm',
  target: ['es2020']
});

await buildFile('assets/src/ae-main.js', 'ae-main.legacy.js', {
  format: 'iife',
  target: ['es5']
});

for (const name of ['contact', 'product', 'blog']) {
  await buildFile(`assets/src/${name}.js`, `${name}.js`, {
    format: 'esm',
    target: ['es2020']
  });
}

await buildFile('assets/src/polyfills.js', 'polyfills.js', {
  format: 'iife',
  target: ['es5']
});

await buildFile('assets/src/ae-lazy.js', 'ae-lazy.js', {
  format: 'esm',
  target: ['es2020'],
  external: ['./modules/*']
});

await buildFile('assets/src/vanilla-helpers.js', 'vanilla-helpers.js', {
  format: 'esm',
  target: ['es2020']
});

const modules = await readdir('assets/src/modules');
for (const file of modules) {
  if (!file.endsWith('.js')) {
    continue;
  }
  await buildFile(`assets/src/modules/${file}`, `modules/${file}`, {
    format: 'esm',
    target: ['es2020']
  });
}

await mkdir('assets/build', { recursive: true });
await writeFile('assets/build/manifest.json', JSON.stringify(manifest, null, 2) + '\n');

