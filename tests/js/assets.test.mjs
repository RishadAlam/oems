import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';

const build = spawnSync(process.execPath, ['./scripts/copy-fonts.mjs'], {
    cwd: new URL('../..', import.meta.url),
    encoding: 'utf8',
});

assert.equal(build.status, 0, build.stderr || build.stdout);
const sourceLicense = await readFile(new URL('../../node_modules/leaflet/LICENSE', import.meta.url), 'utf8');
const redistributedLicense = await readFile(new URL('../../public/assets/vendor/leaflet/LICENSE', import.meta.url), 'utf8');
assert.equal(redistributedLicense.replaceAll('\r\n', '\n'), sourceLicense.replaceAll('\r\n', '\n'));

console.log('PASS asset build redistributes the Leaflet license notice');
