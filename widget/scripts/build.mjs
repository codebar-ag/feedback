// Builds dist/feedback.js (ESM + lazy chunk), dist/feedback.iife.js (global
// CodebarFeedback), the type definitions and dist/sri.json.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const dist = resolve(root, 'dist');
const pkg = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8'));
const define = { __VERSION__: JSON.stringify(pkg.version) };
const common = { root, configFile: false, logLevel: 'warn', define };

rmSync(dist, { recursive: true, force: true });

await build({
  ...common,
  build: {
    outDir: dist,
    emptyOutDir: false,
    target: 'es2022',
    minify: true,
    sourcemap: false,
    lib: { entry: resolve(root, 'src/index.ts'), formats: ['es'], fileName: () => 'feedback.js' },
    rolldownOptions: {
      output: { chunkFileNames: 'chunks/capture-[hash].js', minify: true },
    },
  },
});

const chunk = readdirSync(resolve(dist, 'chunks')).find((f) => /^capture-.*\.js$/.test(f));
if (!chunk) throw new Error('capture chunk missing');

await build({
  ...common,
  define: { ...define, __CAPTURE_CHUNK__: JSON.stringify(`./chunks/${chunk}`) },
  build: {
    outDir: dist,
    emptyOutDir: false,
    target: 'es2022',
    minify: true,
    sourcemap: false,
    lib: { entry: resolve(root, 'src/iife.ts'), formats: ['iife'], name: 'CodebarFeedback', fileName: () => 'feedback.iife.js' },
  },
});

execFileSync(process.execPath, [resolve(root, 'node_modules/typescript/bin/tsc'), '-p', resolve(root, 'tsconfig.build.json')], {
  stdio: 'inherit',
});
rmSync(resolve(dist, 'iife.d.ts'), { force: true });

const sri = (file) =>
  'sha384-' +
  createHash('sha384')
    .update(readFileSync(resolve(dist, file)))
    .digest('base64');
writeFileSync(
  resolve(dist, 'sri.json'),
  JSON.stringify(
    { version: pkg.version, 'feedback.iife.js': sri('feedback.iife.js'), [`chunks/${chunk}`]: sri(`chunks/${chunk}`) },
    null,
    2,
  ) + '\n',
);
console.log(`built ${pkg.name}@${pkg.version} (chunk ${chunk})`);
