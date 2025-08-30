import resolve from '@rollup/plugin-node-resolve';
import babel from '@rollup/plugin-babel';

const input = 'assets/src/optimizer/index.js';

export default [
    {
        input,
        output: {
            file: 'assets/dist/optimizer-modern.js',
            format: 'esm'
        },
        plugins: [
            resolve(),
            babel({
                babelHelpers: 'bundled',
                presets: [
                    ['@babel/preset-env', { targets: { esmodules: true } }]
                ]
            })
        ]
    },
    {
        input,
        output: {
            file: 'assets/dist/optimizer-legacy.js',
            format: 'iife',
            name: 'AeSeoOptimizer'
        },
        plugins: [
            resolve(),
            babel({
                babelHelpers: 'bundled',
                presets: [
                    ['@babel/preset-env', { targets: { ie: '11' } }]
                ]
            })
        ]
    }
];
