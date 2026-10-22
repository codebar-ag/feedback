// Builds dist/feedback.iife.js (global CodebarFeedback) with its lazy screenshot
// chunk and copies both to the API (resources/widget/v1), which serves them at
// /widget/v1/feedback.js. dist/feedback.js (ESM) is used by the tests and the
// playground only.
import { cpSync, mkdirSync, readFileSync, readdirSync, rmSync } from 'node:fs';
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

const api = resolve(root, '../resources/widget/v1');
rmSync(api, { recursive: true, force: true });
mkdirSync(api, { recursive: true });
cpSync(resolve(dist, 'feedback.iife.js'), resolve(api, 'feedback.js'));
cpSync(resolve(dist, 'chunks'), resolve(api, 'chunks'), { recursive: true });
// Older deploys copied to public/, where the web server answered without CORS.
rmSync(resolve(root, '../public/widget'), { recursive: true, force: true });
console.log(`built widget ${pkg.version} (chunk ${chunk}) and copied it to resources/widget/v1`);
