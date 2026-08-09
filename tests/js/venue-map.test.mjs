import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/venue-map.js', import.meta.url), 'utf8');

class ElementStub {
    constructor({ value = '' } = {}) {
        this.children = [];
        this.dataset = {};
        this.disabled = false;
        this.hidden = false;
        this.listeners = new Map();
        this.textContent = '';
        this.value = value;
    }

    addEventListener(type, callback) { this.listeners.set(type, callback); }
    removeEventListener(type, callback) {
        if (this.listeners.get(type) === callback) this.listeners.delete(type);
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = [...children]; }
    click() { this.listeners.get('click')?.({ preventDefault() {}, currentTarget: this }); }
    setAttribute(name, value) { this[name] = String(value); }
}

function leafletHarness() {
    const maps = [];
    const markers = [];
    const L = {
        map(element) {
            const map = {
                element,
                events: new Map(),
                removed: false,
                setViewCalls: [],
                on(type, callback) { this.events.set(type, callback); return this; },
                off(type, callback) { if (this.events.get(type) === callback) this.events.delete(type); return this; },
                emit(type, payload) { this.events.get(type)?.(payload); },
                remove() { this.removed = true; },
                setView(point, zoom) { this.setViewCalls.push({ point, zoom }); return this; },
            };
            maps.push(map);
            return map;
        },
        marker(point, options) {
            const marker = {
                point,
                options,
                events: new Map(),
                removed: false,
                addTo() { return this; },
                getLatLng() { return { lat: this.point[0], lng: this.point[1] }; },
                on(type, callback) { this.events.set(type, callback); return this; },
                remove() { this.removed = true; },
                setLatLng(next) { this.point = Array.isArray(next) ? next : [next.lat, next.lng]; return this; },
                drag(lat, lng) { this.point = [lat, lng]; this.events.get('dragend')?.(); },
            };
            markers.push(marker);
            return marker;
        },
        tileLayer() { return { addTo() { return this; } }; },
    };
    return { L, maps, markers };
}

function createHarness({
    container = true,
    latitude = '',
    longitude = '',
    geolocationError = null,
    fetchResults = [],
} = {}) {
    const form = new ElementStub();
    form.dataset.geocodeUrl = '/organizer/venues/geocode';
    form.dataset.csrf = 'csrf-token';
    const map = container ? new ElementStub() : null;
    const fallback = new ElementStub();
    if (map) Object.assign(map.dataset, {
        tileUrl: 'https://tiles.example.test/{z}/{x}/{y}.png',
        tileAttribution: 'Map data',
        defaultLat: '23.8103',
        defaultLng: '90.4125',
        defaultZoom: '11',
    });
    if (map) map.querySelector = (selector) => selector === '.venue-map__fallback' ? fallback : null;
    const search = new ElementStub({ value: 'Bashundhara Dhaka' });
    const find = new ElementStub();
    const results = new ElementStub();
    const status = new ElementStub();
    const useLocation = new ElementStub();
    const clear = new ElementStub();
    const latitudeInput = new ElementStub({ value: latitude });
    const longitudeInput = new ElementStub({ value: longitude });
    const address = new ElementStub({ value: '12 Lake Road' });
    const windowListeners = new Map();
    const requests = [];
    const geoCalls = [];
    const leaflet = leafletHarness();
    const selectors = {
        '[data-venue-map-form]': form,
        '[data-venue-map]': map,
        '[data-venue-search]': search,
        '[data-venue-find]': find,
        '[data-venue-results]': results,
        '[data-venue-status]': status,
        '[data-venue-use-location]': useLocation,
        '[data-venue-clear-pin]': clear,
        '[name="latitude"]': latitudeInput,
        '[name="longitude"]': longitudeInput,
        '[name="address_line"]': address,
    };
    form.querySelector = (selector) => selectors[selector] ?? null;
    const sandbox = {
        console,
        document: {
            querySelector: (selector) => selectors[selector] ?? null,
            createElement: () => new ElementStub(),
        },
        navigator: {
            geolocation: {
                getCurrentPosition(success, error, options) {
                    geoCalls.push(options);
                    if (geolocationError === null) success({ coords: { latitude: 23.8151, longitude: 90.4255 } });
                    else error({ code: geolocationError });
                },
            },
        },
        async fetch(url, options) {
            requests.push({ url, options });
            return { ok: true, status: 200, json: async () => ({ results: fetchResults }) };
        },
        addEventListener(type, callback) { windowListeners.set(type, callback); },
        removeEventListener(type, callback) {
            if (windowListeners.get(type) === callback) windowListeners.delete(type);
        },
        URLSearchParams,
        L: leaflet.L,
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox, { filename: 'venue-map.js' });

    return {
        address,
        clear,
        find,
        fallback,
        geoCalls,
        latitude: latitudeInput,
        leaflet,
        longitude: longitudeInput,
        map,
        requests,
        results,
        search,
        status,
        useLocation,
        pagehide: () => windowListeners.get('pagehide')?.(),
        clickFind: async () => { find.click(); await new Promise((resolve) => setImmediate(resolve)); },
    };
}

test('initializes only with a map container and uses configured or retained coordinates', () => {
    assert.equal(createHarness({ container: false }).leaflet.maps.length, 0);

    const empty = createHarness();
    assert.deepEqual(JSON.parse(JSON.stringify(empty.leaflet.maps[0].setViewCalls[0])), {
        point: [23.8103, 90.4125], zoom: 11,
    });
    assert.equal(empty.leaflet.markers.length, 0);
    assert.equal(empty.fallback.hidden, true);

    const retained = createHarness({ latitude: '23.7465000', longitude: '90.3760000' });
    assert.deepEqual(Array.from(retained.leaflet.markers[0].point), [23.7465, 90.376]);
});

test('map click and marker drag update both exact coordinate fields', () => {
    const harness = createHarness();
    harness.leaflet.maps[0].emit('click', { latlng: { lat: 23.81, lng: 90.41 } });
    assert.equal(harness.latitude.value, '23.8100000');
    assert.equal(harness.longitude.value, '90.4100000');

    harness.leaflet.markers[0].drag(23.8151, 90.4255);
    assert.equal(harness.latitude.value, '23.8151000');
    assert.equal(harness.longitude.value, '90.4255000');
    assert.match(harness.status.textContent, /Pin moved/i);
});

test('address search happens only on button action, renders five results, and selection never rewrites address', async () => {
    const harness = createHarness({ fetchResults: Array.from({ length: 7 }, (_, index) => ({
        label: `Venue ${index + 1}`,
        latitude: String(23 + index / 100),
        longitude: String(90 + index / 100),
    })) });
    assert.equal(harness.requests.length, 0);
    assert.equal(harness.leaflet.markers.length, 0);

    await harness.clickFind();
    assert.equal(harness.requests.length, 1);
    assert.equal(harness.results.children.length, 5);
    harness.results.children[0].click();

    assert.equal(harness.latitude.value, '23.0000000');
    assert.equal(harness.longitude.value, '90.0000000');
    assert.equal(harness.address.value, '12 Lake Road');
    assert.match(harness.status.textContent, /Pin selected/i);
});

test('current position is explicit, reports success or denial, and clear pin removes both values', () => {
    const success = createHarness();
    assert.equal(success.geoCalls.length, 0);
    success.useLocation.click();
    assert.equal(success.geoCalls.length, 1);
    assert.equal(success.latitude.value, '23.8151000');
    assert.match(success.status.textContent, /Current position/i);
    success.clear.click();
    assert.equal(success.latitude.value, '');
    assert.equal(success.longitude.value, '');
    assert.match(success.status.textContent, /Pin cleared/i);

    const denied = createHarness({ geolocationError: 1 });
    denied.useLocation.click();
    assert.match(denied.status.textContent, /permission/i);
});

test('pagehide cleans up map and marker resources', () => {
    const harness = createHarness({ latitude: '23.8', longitude: '90.4' });
    harness.pagehide();
    assert.equal(harness.leaflet.maps[0].removed, true);
    assert.equal(harness.leaflet.markers[0].removed, true);
});
