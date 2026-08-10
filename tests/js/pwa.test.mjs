import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const registrationSource = await readFile(new URL('../../public/assets/js/pwa.js', import.meta.url), 'utf8');
const workerSource = await readFile(new URL('../../public/service-worker.js', import.meta.url), 'utf8');

const windowListeners = new Map();
const buttonListeners = new Map();
const button = { hidden: true, disabled: false, addEventListener: (type, listener) => buttonListeners.set(type, listener) };
const registrations = [];
const registrationContext = {
    window: { addEventListener: (type, listener) => windowListeners.set(type, listener) },
    document: { querySelector: (selector) => selector === '[data-pwa-install]' ? button : null },
    navigator: { serviceWorker: { register: async (...args) => registrations.push(args) } },
    console: { error() {} },
};
vm.runInNewContext(registrationSource, registrationContext);
await windowListeners.get('load')();
assert.equal(registrations.length, 1);
assert.equal(registrations[0][0], '/service-worker.js');
assert.equal(registrations[0][1].scope, '/');
assert.equal(registrations[0][1].updateViaCache, 'none');

let prevented = false;
let prompted = 0;
await windowListeners.get('beforeinstallprompt')({
    preventDefault: () => { prevented = true; },
    prompt: async () => { prompted += 1; },
    userChoice: Promise.resolve({ outcome: 'accepted' }),
});
assert.equal(prevented, true);
assert.equal(button.hidden, false);
await buttonListeners.get('click')();
assert.equal(prompted, 1);
assert.equal(button.hidden, true);
windowListeners.get('appinstalled')();
assert.equal(button.hidden, true);

const unsupportedListeners = new Map();
assert.doesNotThrow(() => vm.runInNewContext(registrationSource, {
    window: { addEventListener: (type, listener) => unsupportedListeners.set(type, listener) },
    document: { querySelector: () => null },
    navigator: {},
    console: { error() {} },
}));
await unsupportedListeners.get('load')();

const workerListeners = new Map();
const opened = [];
const deleted = [];
const matched = [];
const put = [];
let skipWaiting = 0;
let claimed = 0;
let fetchCalls = [];
let fetchImplementation = async request => ({ ok: true, type: 'basic', clone: () => ({ cloned: request.url }) });
const cachesApi = {
    open: async name => {
        opened.push(name);
        return {
            addAll: async paths => { opened.push([...paths]); },
            put: async (...args) => put.push(args),
            match: async value => { matched.push(value); return value === '/offline.html' ? { offline: true } : null; },
        };
    },
    keys: async () => ['oems-public-static-old', 'unrelated-cache'],
    delete: async name => { deleted.push(name); return true; },
};
const workerContext = {
    self: {
        location: { origin: 'https://events.example.test' },
        addEventListener: (type, listener) => workerListeners.set(type, listener),
        skipWaiting: async () => { skipWaiting += 1; },
        clients: { claim: async () => { claimed += 1; } },
    },
    caches: cachesApi,
    fetch: async request => { fetchCalls.push(request); return fetchImplementation(request); },
    URL,
};
vm.runInNewContext(workerSource, workerContext);

const lifecycle = async type => {
    let promise;
    workerListeners.get(type)({ waitUntil: value => { promise = value; } });
    await promise;
};
await lifecycle('install');
assert.equal(skipWaiting, 1);
assert.equal(opened[0], 'oems-public-static-20260810-week4');
assert.equal(JSON.stringify(opened[1]), JSON.stringify(['/offline.html', '/assets/css/app.css', '/assets/js/theme.js', '/assets/js/app.js', '/assets/js/pwa.js', '/assets/icons/oems-192.png', '/assets/icons/oems-512.png']));
await lifecycle('activate');
assert.deepEqual(deleted, ['oems-public-static-old']);
assert.equal(claimed, 1);

const dispatchFetch = async request => {
    let response;
    workerListeners.get('fetch')({ request, respondWith: value => { response = value; } });
    return response === undefined ? undefined : await response;
};
assert.equal(await dispatchFetch({ method: 'POST', url: 'https://events.example.test/login', mode: 'navigate' }), undefined);
for (const url of [
    'https://events.example.test/api/events',
    'https://events.example.test/dashboard',
    'https://events.example.test/participant/tickets/1/pdf',
    'https://events.example.test/assets/css/app.css?v=private',
    'https://tiles.example.test/1/2/3.png',
]) {
    assert.equal(await dispatchFetch({ method: 'GET', url, mode: 'cors' }), undefined, url);
}

fetchImplementation = async () => { throw new Error('offline'); };
const offline = await dispatchFetch({ method: 'GET', url: 'https://events.example.test/events', mode: 'navigate' });
assert.equal(offline.offline, true);
assert.equal(put.length, 0);

fetchImplementation = async request => ({ ok: true, type: 'basic', clone: () => ({ cloned: request.url }) });
const staticResponse = await dispatchFetch({ method: 'GET', url: 'https://events.example.test/assets/css/app.css', mode: 'cors' });
assert.equal(staticResponse.ok, true);
assert.equal(put.length, 1);
assert.equal(put[0][0], '/assets/css/app.css');

console.log('PASS PWA registration and static-only service-worker lifecycle');
