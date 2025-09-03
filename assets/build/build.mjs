import { build } from 'esbuild';

const shared = {
  bundle: true,
  minify: true,
  sourcemap: false,
  platform: 'browser'
};

await build({
  entryPoints: ['assets/src/ae-main.js'],
  outfile: 'assets/dist/ae-main.modern.js',
  format: 'esm',
  target: ['es2020'],
  ...shared
});

await build({
  entryPoints: ['assets/src/ae-main.js'],
  outfile: 'assets/dist/ae-main.legacy.js',
  format: 'iife',
  target: ['es5'],
  ...shared
});

for (const name of ['contact', 'product', 'blog']) {
  await build({
    entryPoints: [`assets/src/${name}.js`],
    outfile: `assets/dist/${name}.js`,
    format: 'esm',
    target: ['es2020'],
    ...shared
  });
}

await build({
  entryPoints: ['assets/src/polyfills.js'],
  outfile: 'assets/dist/polyfills.js',
  format: 'iife',
  target: ['es5'],
  ...shared
});

await build({
  entryPoints: ['assets/src/ae-lazy.js'],
  outfile: 'assets/dist/ae-lazy.js',
  format: 'esm',
  target: ['es2020'],
  ...shared
});
