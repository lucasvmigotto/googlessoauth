/**
 * Build script for the googlessoauth plugin frontend assets.
 *
 * Compiles:
 *   - public/js/gate.ts   -> public/dist/gate.js   (classic IIFE, runs synchronously in <head>)
 *   - public/js/login.ts  -> public/dist/login.js  (ES module, progressive enhancement)
 *   - public/css/login.scss -> public/dist/login.css (compiled & compressed)
 *
 * Runs under Bun. Pass `--watch` to rebuild on change.
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import * as sass from 'sass';

const root = resolve(import.meta.dir, '..');
const outDir = resolve(root, 'public/dist');

const gateEntry = resolve(root, 'public/js/gate.ts');
const loginEntry = resolve(root, 'public/js/login.ts');
const scssEntry = resolve(root, 'public/css/login.scss');

async function buildJs(): Promise<void> {
    // Synchronous gate script: classic IIFE so it executes while <head> is
    // parsed, before the body is painted (no flash of the hidden form).
    const gate = await Bun.build({
        entrypoints: [gateEntry],
        outdir: outDir,
        target: 'browser',
        format: 'iife',
        minify: true,
        naming: '[name].js',
    });

    // Progressive-enhancement module (button loading state, etc.).
    const login = await Bun.build({
        entrypoints: [loginEntry],
        outdir: outDir,
        target: 'browser',
        format: 'esm',
        minify: true,
        naming: '[name].js',
    });

    for (const result of [gate, login]) {
        if (!result.success) {
            for (const log of result.logs) {
                console.error(log);
            }
            throw new Error('JS build failed');
        }
    }
}

async function buildScss(): Promise<void> {
    const compiled = sass.compile(scssEntry, { style: 'compressed' });
    const outFile = resolve(outDir, 'login.css');
    await mkdir(dirname(outFile), { recursive: true });
    await writeFile(outFile, compiled.css);
}

async function buildAll(): Promise<void> {
    await mkdir(outDir, { recursive: true });
    await Promise.all([buildJs(), buildScss()]);
    console.log(`[googlessoauth] assets built into ${outDir}`);
}

await buildAll();

if (process.argv.includes('--watch')) {
    const { watch } = await import('node:fs');
    console.log('[googlessoauth] watching for changes...');
    for (const dir of ['public/js', 'public/css']) {
        watch(resolve(root, dir), { recursive: true }, () => {
            buildAll().catch((err) => console.error(err));
        });
    }
}
