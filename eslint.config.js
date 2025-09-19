const globals = require('globals');

module.exports = [
  {
    ignores: [
      'node_modules/',
      'vendor/',
      'public/',
      'assets/dist/',
      'frontend/build/',
      'tests/e2e/',
    ],
  },
  {
    files: ['**/*.js', '**/*.mjs'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jest,
        ajaxurl: 'readonly',
        jQuery: 'readonly',
        wp: 'readonly',
      },
    },
    linterOptions: {
      reportUnusedDisableDirectives: 'off',
    },
    rules: {},
  },
];
