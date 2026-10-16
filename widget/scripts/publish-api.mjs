// Copies the IIFE build to the Laravel app (public/widget/v1/) so the API can
// serve the script tag variant itself.
import { cpSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const target = resolve(root, '../public/widget/v1');
rmSync(target, { recursive: true, force: true });
mkdirSync(target, { recursive: true });
cpSync(resolve(root, 'dist/feedback.iife.js'), resolve(target, 'feedback.js'));
cpSync(resolve(root, 'dist/chunks'), resolve(target, 'chunks'), { recursive: true });
cpSync(resolve(root, 'dist/sri.json'), resolve(target, 'sri.json'));
console.log(`copied widget to ${target}`);
