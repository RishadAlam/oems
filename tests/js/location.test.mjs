import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/location.js', import.meta.url), 'utf8');

class ElementStub {
    constructor({ value = '', textContent = '' } = {}) {
        this.attributes = new Map();
        this.dataset = {};
        this.disabled = false;
        this.focusCalls = 0;
        this.hidden = false;
        this.listeners = new Map();
        this.parentElement = null;
        this.scrollCalls = [];
        this.textContent = textContent;
        this.value = value;
    }

    addEventListener(type, callback) {
        const callbacks = this.listeners.get(type) ?? [];
        callbacks.push(callback);
        this.listeners.set(type, callbacks);
    }

    removeEventListener(type, callback) {
        const callbacks = this.listeners.get(type) ?? [];
        this.listeners.set(type, callbacks.filter((candidate) => candidate !== callback));
    }

    dispatch(type, detail = {}) {
        const event = { preventDefault() {}, target: this, bubbles: true, ...detail };
        this.dispatchEvent(type, event);
    }

    dispatchEvent(type, event) {
        for (const callback of this.listeners.get(type) ?? []) {
            callback({ ...event, currentTarget: this });
        }

        if (event.bubbles !== false) this.parentElement?.dispatchEvent(type, event);
    }

    click() { this.dispatch('click'); }
    focus() {
        this.focusCalls += 1;
        this.dispatch('focus', { bubbles: false });
        this.dispatch('focusin');
    }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    removeAttribute(name) { this.attributes.delete(name); }
    scrollIntoView(options) { this.scrollCalls.push(options); }
}

function createLeafletHarness(tileOutcome = 'load') {
    const maps = [];
    const markers = [];
    const tileLayers = [];

    const L = {
        map(element) {
            const map = {
                element,
                fitBoundsCalls: [],
                invalidateSizeCalls: 0,
                panToCalls: [],
                removed: false,
                setViewCalls: [],
                fitBounds(bounds, options) { this.fitBoundsCalls.push({ bounds, options }); return this; },
                invalidateSize() { this.invalidateSizeCalls += 1; return this; },
                panTo(coordinates, options) { this.panToCalls.push({ coordinates, options }); return this; },
                remove() { this.removed = true; },
                setView(coordinates, zoom) { this.setViewCalls.push({ coordinates, zoom }); return this; },
            };
            maps.push(map);
            return map;
        },
        marker(coordinates, options = {}) {
            const marker = {
                coordinates,
                options,
                events: new Map(),
                popup: '',
                openPopupCalls: 0,
                addTo() { return this; },
                bindPopup(value) { this.popup = value; return this; },
                on(type, callback) { this.events.set(type, callback); return this; },
                openPopup() { this.openPopupCalls += 1; return this; },
                emit(type) { this.events.get(type)?.(); },
            };
            markers.push(marker);
            return marker;
        },
        tileLayer(url, options) {
            const layer = {
                events: new Map(),
                options,
                url,
                addTo() { this.emit(tileOutcome); return this; },
                emit(type) { this.events.get(type)?.(); },
                off(type, callback) {
                    if (this.events.get(type) === callback) this.events.delete(type);
                    return this;
                },
                on(type, callback) { this.events.set(type, callback); return this; },
            };
            tileLayers.push(layer);
            return layer;
        },
        featureGroup() {
            return { getBounds: () => ({ isValid: () => true }) };
        },
    };

    return { L, maps, markers, tileLayers };
}

function createHarness({
    errorCode = null,
    latitude = 23.810331,
    longitude = 90.412521,
    malformedPayload = false,
    markers = null,
    mobile = false,
    reducedMotion = false,
    secureContext = true,
    supported = true,
    tileOutcome = 'load',
} = {}) {
    let viewportWidth = mobile ? 1023 : 1024;
    const narrowViewportQuery = '(max-width: 1023px)';
    const useLocation = new ElementStub();
    const status = new ElementStub();
    const discovery = new ElementStub();
    discovery.dataset.eventDiscoveryView = 'list';
    const viewStatus = new ElementStub();
    const form = new ElementStub();
    const latitudeInput = new ElementStub();
    const longitudeInput = new ElementStub();
    const radiusInput = new ElementStub({ value: '25' });
    const tokenInput = new ElementStub({ value: 'csrf-token' });
    const listToggle = new ElementStub({ value: 'list' });
    const mapToggle = new ElementStub({ value: 'map' });
    const list = new ElementStub();
    const panel = new ElementStub();
    panel.hidden = true;
    const mapContainer = new ElementStub();
    const mapFallback = new ElementStub();
    mapContainer.querySelector = (selector) => selector === '[data-map-fallback]' ? mapFallback : null;
    const payload = new ElementStub({
        textContent: malformedPayload ? '{bad json' : JSON.stringify({
            config: {
                tile_url: 'https://tiles.example.test/{z}/{x}/{y}.png',
                tile_attribution: 'Map data',
                default_lat: 23.8103,
                default_lng: 90.4125,
                default_zoom: 11,
            },
            markers: markers ?? [{
                id: 501,
                title: 'Future Craft',
                href: '/events/future-craft',
                latitude: '23.8103',
                longitude: '90.4125',
            }],
        }),
    });
    const card = new ElementStub();
    card.dataset.eventId = '501';
    const cardLink = new ElementStub();
    cardLink.parentElement = card;
    const requests = [];
    const windowListeners = new Map();
    const geolocationCalls = [];
    const leaflet = createLeafletHarness(tileOutcome);

    listToggle.dataset.view = 'list';
    mapToggle.dataset.view = 'map';
    listToggle.setAttribute('aria-pressed', 'true');
    mapToggle.setAttribute('aria-pressed', 'false');
    form.querySelector = (selector) => ({
        '[name="latitude"]': latitudeInput,
        '[name="longitude"]': longitudeInput,
        '[name="radius"]': radiusInput,
        '[name="_token"]': tokenInput,
    }[selector] ?? null);
    form.requestSubmit = () => {
        requests.push({
            body: {
                latitude: latitudeInput.value,
                longitude: longitudeInput.value,
                radius: radiusInput.value,
                _token: tokenInput.value,
            },
        });
    };

    const mediaListeners = new Map();
    const sandbox = {
        console,
        document: {
            querySelector: (selector) => ({
                '[data-location-use]': useLocation,
                '[data-location-status]': status,
                '[data-location-form]': form,
                '[data-event-discovery]': discovery,
                '[data-event-results]': list,
                '[data-event-map-panel]': panel,
                '[data-event-map]': mapContainer,
                '[data-event-view-status]': viewStatus,
                '#event-map-data': payload,
            }[selector] ?? null),
            querySelectorAll: (selector) => ({
                '[data-event-view]': [listToggle, mapToggle],
                '[data-event-id]': [card],
            }[selector] ?? []),
        },
        navigator: supported ? {
            geolocation: {
                getCurrentPosition(success, error, options) {
                    geolocationCalls.push(options);
                    if (errorCode === null) success({ coords: { latitude, longitude } });
                    else error({ code: errorCode });
                },
            },
        } : {},
        matchMedia(query) {
            const mediaQuery = {
                get matches() { return query.includes('max-width') ? viewportWidth <= 1023 : reducedMotion; },
                addEventListener(type, callback) {
                    if (type === 'change') mediaListeners.set(query, callback);
                },
                removeEventListener(type, callback) {
                    if (type === 'change' && mediaListeners.get(query) === callback) mediaListeners.delete(query);
                },
            };
            return mediaQuery;
        },
        addEventListener(type, callback) { windowListeners.set(type, callback); },
        setTimeout,
        clearTimeout,
        isSecureContext: secureContext,
        L: leaflet.L,
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox, { filename: 'location.js' });

    return {
        card,
        cardLink,
        discovery,
        geolocationCalls,
        leaflet,
        list,
        listToggle,
        mapFallback,
        mapToggle,
        panel,
        requests,
        status,
        useLocation,
        viewStatus,
        clickUseLocation: async () => { useLocation.click(); await Promise.resolve(); },
        pagehide: (persisted = false) => windowListeners.get('pagehide')?.({ persisted }),
        pageshow: (persisted = false) => windowListeners.get('pageshow')?.({ persisted }),
        resizeToWidth: (nextWidth) => {
            viewportWidth = nextWidth;
            mediaListeners.get(narrowViewportQuery)?.({ matches: nextWidth <= 1023 });
        },
    };
}

test('use my location rounds coordinates and posts csrf payload once', async () => {
    const harness = createHarness({ latitude: 23.810331, longitude: 90.412521 });
    assert.equal(harness.requests.length, 0);

    await harness.clickUseLocation();
    await harness.clickUseLocation();

    assert.equal(harness.requests.length, 1);
    assert.deepEqual(harness.requests[0].body, {
        latitude: '23.810',
        longitude: '90.413',
        radius: '25',
        _token: 'csrf-token',
    });
    assert.deepEqual(JSON.parse(JSON.stringify(harness.geolocationCalls[0])), {
        enableHighAccuracy: false,
        timeout: 10000,
        maximumAge: 300000,
    });
});

test('permission denial and timeout provide actionable status without posting', async () => {
    const denied = createHarness({ errorCode: 1 });
    await denied.clickUseLocation();
    assert.match(denied.status.textContent, /permission/i);
    assert.equal(denied.requests.length, 0);

    const timeout = createHarness({ errorCode: 3 });
    await timeout.clickUseLocation();
    assert.match(timeout.status.textContent, /timed out/i);
    assert.equal(timeout.requests.length, 0);
});

test('use my location requires a secure page before requesting browser geolocation', async () => {
    const harness = createHarness({ secureContext: false });

    await harness.clickUseLocation();

    assert.equal(harness.geolocationCalls.length, 0);
    assert.equal(harness.requests.length, 0);
    assert.match(harness.status.textContent, /HTTPS/i);
});

test('unsupported browser leaves the canonical list usable', async () => {
    const harness = createHarness({ supported: false });
    await harness.clickUseLocation();

    assert.match(harness.status.textContent, /does not support/i);
    assert.equal(harness.requests.length, 0);
    assert.equal(harness.list.hidden, false);
});

test('list and map controls expose pressed state and mobile panel visibility', () => {
    const harness = createHarness({ mobile: true });

    harness.mapToggle.click();
    assert.equal(harness.mapToggle.getAttribute('aria-pressed'), 'true');
    assert.equal(harness.listToggle.getAttribute('aria-pressed'), 'false');
    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.list.hidden, true);

    harness.listToggle.click();
    assert.equal(harness.mapToggle.getAttribute('aria-pressed'), 'false');
    assert.equal(harness.listToggle.getAttribute('aria-pressed'), 'true');
    assert.equal(harness.panel.hidden, true);
    assert.equal(harness.list.hidden, false);
});

test('list and map controls synchronize explicit discovery state on narrow screens', () => {
    const harness = createHarness({ mobile: true });
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');

    harness.mapToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.list.hidden, true);
    assert.match(harness.viewStatus.textContent, /map view/i);

    harness.listToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');
    assert.equal(harness.panel.hidden, true);
    assert.equal(harness.list.hidden, false);
    assert.match(harness.viewStatus.textContent, /list view/i);
});

test('map keeps results beside it at the desktop split breakpoint', () => {
    const harness = createHarness({ mobile: false });
    harness.mapToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.match(harness.viewStatus.textContent, /alongside/i);
});

test('Map hides results below 1024px but retains results at and above it', () => {
    const harness = createHarness({ mobile: true });
    harness.mapToggle.click();
    assert.equal(harness.list.hidden, true);

    harness.resizeToWidth(1024);
    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.list.hidden, false);

    harness.resizeToWidth(1440);
    assert.equal(harness.list.hidden, false);

    harness.resizeToWidth(1023);
    assert.equal(harness.list.hidden, true);
});

test('marker activation reveals a hidden result before focusing its card', () => {
    const harness = createHarness({ mobile: true });
    harness.mapToggle.click();
    harness.leaflet.markers[0].emit('click');
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.card.focusCalls, 1);
});

test('marker and card interactions preserve keyboard focus and disable reduced motion', () => {
    const harness = createHarness({ reducedMotion: true });
    harness.mapToggle.click();
    const marker = harness.leaflet.markers[0];
    const map = harness.leaflet.maps[0];

    marker.emit('click');
    assert.equal(harness.card.focusCalls, 1);
    assert.deepEqual(JSON.parse(JSON.stringify(harness.card.scrollCalls[0])), { behavior: 'auto', block: 'nearest' });

    harness.card.focus();
    assert.equal(marker.openPopupCalls, 1);
    assert.equal(map.panToCalls[0].options.animate, false);
});

test('event markers expose the event title to keyboard and screen-reader users', () => {
    const harness = createHarness();
    harness.mapToggle.click();

    assert.deepEqual(JSON.parse(JSON.stringify(harness.leaflet.markers[0].options)), {
        alt: 'Future Craft',
        keyboard: true,
        title: 'Future Craft',
    });
});

test('focus entering a nested event link activates its marker', () => {
    const harness = createHarness();
    harness.mapToggle.click();
    const marker = harness.leaflet.markers[0];

    harness.cardLink.focus();

    assert.equal(harness.card.focusCalls, 0);
    assert.equal(marker.openPopupCalls, 1);
    assert.equal(harness.leaflet.maps[0].panToCalls.length, 1);
});

test('malformed marker payload keeps the list and renders an inline fallback', () => {
    const harness = createHarness({ malformedPayload: true, mobile: true });
    harness.mapToggle.click();

    assert.equal(harness.leaflet.maps.length, 0);
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.panel.hidden, false);
    assert.match(harness.viewStatus.textContent, /map is unavailable/i);
});

test('zero valid public markers keeps the canonical mobile list and visible fallback', () => {
    const harness = createHarness({ markers: [], mobile: true });
    harness.mapToggle.click();

    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.mapFallback.hidden, false);
    assert.match(harness.viewStatus.textContent, /no public event locations/i);
});

test('tile failure keeps the canonical mobile list and reports provider attribution', () => {
    const harness = createHarness({ mobile: true, tileOutcome: 'tileerror' });
    harness.mapToggle.click();

    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.mapFallback.hidden, false);
    assert.match(harness.viewStatus.textContent, /map tiles could not load/i);
    assert.equal(harness.leaflet.tileLayers[0].options.attribution, 'Map data');
});

test('terminal tile load cannot clear an earlier tile failure', () => {
    const harness = createHarness({ mobile: true, tileOutcome: 'pending' });
    harness.mapToggle.click();
    const tileLayer = harness.leaflet.tileLayers[0];

    tileLayer.emit('tileerror');
    tileLayer.emit('load');

    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.mapFallback.hidden, false);
    assert.match(harness.viewStatus.textContent, /map tiles could not load/i);
});

test('pagehide removes the Leaflet instance', () => {
    const harness = createHarness();
    harness.mapToggle.click();
    assert.equal(harness.leaflet.tileLayers[0].events.size, 2);
    harness.pagehide();

    assert.equal(harness.leaflet.maps[0].removed, true);
    assert.equal(harness.leaflet.tileLayers[0].events.size, 0);
});

test('persisted page restoration recreates the selected map without duplicate card listeners', () => {
    const harness = createHarness({ mobile: true });
    harness.mapToggle.click();
    const firstMap = harness.leaflet.maps[0];
    const firstMarker = harness.leaflet.markers[0];

    harness.pagehide(true);
    harness.pageshow(true);

    assert.equal(firstMap.removed, true);
    assert.equal(harness.leaflet.maps.length, 2);
    assert.equal(harness.mapToggle.getAttribute('aria-pressed'), 'true');
    assert.equal(harness.listToggle.getAttribute('aria-pressed'), 'false');
    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.list.hidden, true);

    harness.cardLink.focus();
    assert.equal(firstMarker.openPopupCalls, 0);
    assert.equal(harness.leaflet.markers[1].openPopupCalls, 1);
});
