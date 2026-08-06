import { cp, mkdir, readdir } from 'node:fs/promises';
import { join } from 'node:path';

const cssDirectory = new URL('../public/assets/css/', import.meta.url);
const manropeSource = new URL('../node_modules/@fontsource-variable/manrope/files/', import.meta.url);
const manropeTarget = new URL('../public/assets/css/files/', import.meta.url);
const phosphorSource = new URL('../node_modules/@phosphor-icons/web/src/regular/Phosphor.woff2', import.meta.url);
const phosphorTarget = new URL('../public/assets/css/Phosphor.woff2', import.meta.url);

await mkdir(cssDirectory, { recursive: true });
await mkdir(manropeTarget, { recursive: true });

const manropeFiles = (await readdir(manropeSource)).filter((file) => file.endsWith('.woff2'));

await Promise.all(manropeFiles.map((file) => cp(
    new URL(file, manropeSource),
    new URL(file, manropeTarget),
)));

await cp(phosphorSource, phosphorTarget);

console.log(`Copied ${manropeFiles.length + 1} local font files to ${join('public', 'assets', 'css')}.`);
