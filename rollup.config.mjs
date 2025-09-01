import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import babel from '@rollup/plugin-babel';
import { terser } from 'rollup-plugin-terser';

const input = 'assets/src/optimizer/index.js';

export default [
    {
        input,
        output: {
            file: 'assets/dist/optimizer-modern.js',
            format: 'es'
        },
        plugins: [
            resolve(),
            commonjs(),
            babel({
                babelHelpers: 'bundled',
                presets: [
                    ['@babel/preset-env', { targets: { esmodules: true } }]
                ]
            }),
            terser()
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
            commonjs(),
            babel({
                babelHelpers: 'bundled',
                presets: [
                    ['@babel/preset-env', { targets: { ie: '11' } }]
                ]
            }),
            terser()
        ]
    }
];
