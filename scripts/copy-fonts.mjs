import { cp, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const cssDirectory = new URL('../public/assets/css/', import.meta.url);
const manropeSource = new URL('../node_modules/@fontsource-variable/manrope/files/', import.meta.url);
const manropeTarget = new URL('../public/assets/css/files/', import.meta.url);
const phosphorSource = new URL('../node_modules/@phosphor-icons/web/src/regular/Phosphor.woff2', import.meta.url);
const phosphorTarget = new URL('../public/assets/css/Phosphor.woff2', import.meta.url);
const leafletSource = new URL('../node_modules/leaflet/dist/', import.meta.url);
const leafletLicenseSource = new URL('../node_modules/leaflet/LICENSE', import.meta.url);
const leafletTarget = new URL('../public/assets/vendor/leaflet/', import.meta.url);
const chartSource = new URL('../node_modules/chart.js/dist/chart.umd.min.js', import.meta.url);
const chartLicenseSource = new URL('../node_modules/chart.js/LICENSE.md', import.meta.url);
const chartTarget = new URL('../public/assets/vendor/chartjs/', import.meta.url);

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
const leafletLicense = await readFile(leafletLicenseSource, 'utf8');
await writeFile(new URL('LICENSE', leafletTarget), leafletLicense.replaceAll('\r\n', '\n'));
await rm(chartTarget, { recursive: true, force: true });
await mkdir(chartTarget, { recursive: true });
await cp(chartSource, new URL('chart.umd.min.js', chartTarget));
const chartLicense = await readFile(chartLicenseSource, 'utf8');
await writeFile(new URL('LICENSE.md', chartTarget), chartLicense.replaceAll('\r\n', '\n'));

console.log(`Copied ${manropeFiles.length + 1} local font files, Leaflet assets, and Chart.js to ${join('public', 'assets')}.`);
