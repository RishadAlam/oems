import { cp, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const cssDirectory = new URL('../public/assets/css/', import.meta.url);
const manropeSource = new URL('../node_modules/@fontsource-variable/manrope/files/', import.meta.url);
const manropeTarget = new URL('../public/assets/css/files/', import.meta.url);
const phosphorSource = new URL('../node_modules/@phosphor-icons/web/src/regular/Phosphor.woff2', import.meta.url);
const phosphorTarget = new URL('../public/assets/css/Phosphor.woff2', import.meta.url);
const leafletSource = new URL('../node_modules/leaflet/dist/', import.meta.url);
const leafletTarget = new URL('../public/assets/vendor/leaflet/', import.meta.url);

await mkdir(cssDirectory, { recursive: true });
await mkdir(manropeTarget, { recursive: true });

const manropeFiles = (await readdir(manropeSource)).filter((file) => file.endsWith('.woff2'));

await Promise.all(manropeFiles.map((file) => cp(
    new URL(file, manropeSource),
    new URL(file, manropeTarget),
)));

await cp(phosphorSource, phosphorTarget);
await rm(leafletTarget, { recursive: true, force: true });
await mkdir(leafletTarget, { recursive: true });
await Promise.all(['leaflet.js', 'leaflet.js.map'].map((file) => cp(
    new URL(file, leafletSource),
    new URL(file, leafletTarget),
)));
const leafletCss = await readFile(new URL('leaflet.css', leafletSource), 'utf8');
await writeFile(new URL('leaflet.css', leafletTarget), leafletCss.replaceAll('\r\n', '\n'));
await cp(new URL('images/', leafletSource), new URL('images/', leafletTarget), { recursive: true });

console.log(`Copied ${manropeFiles.length + 1} local font files and Leaflet assets to ${join('public', 'assets')}.`);
