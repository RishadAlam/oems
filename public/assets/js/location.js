(function (global) {
    'use strict';

    const document = global.document;
    if (!document) return;

    const useLocation = document.querySelector('[data-location-use]');
    const locationStatus = document.querySelector('[data-location-status]');
    const locationForm = document.querySelector('[data-location-form]');
    const viewButtons = Array.from(document.querySelectorAll('[data-event-view]'));
    const discovery = document.querySelector('[data-event-discovery]');
    const results = document.querySelector('[data-event-results]');
    const mapPanel = document.querySelector('[data-event-map-panel]');
    const mapElement = document.querySelector('[data-event-map]');
    const viewStatus = document.querySelector('[data-event-view-status]');
    const payloadElement = document.querySelector('#event-map-data')
        || document.querySelector('#event-detail-map-data');
    const cards = Array.from(document.querySelectorAll('[data-event-id]'));
    const reduceMotion = global.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const compactViewQuery = global.matchMedia?.('(max-width: 1023px)');
    let map = null;
    let tileLayer = null;
    let handleTileLoad = null;
    let handleTileError = null;
    let mapReady = false;
    let mapAvailabilityMessage = '';
    let payload = null;
    let locationSubmitted = false;
    let cardFocusCleanups = [];

    const setStatus = (message) => {
        if (locationStatus) locationStatus.textContent = message;
    };

    const setViewStatus = (message) => {
        if (viewStatus) viewStatus.textContent = message;
    };

    if (payloadElement) {
        try {
            const parsed = JSON.parse(payloadElement.textContent || '');
            if (!parsed || !Array.isArray(parsed.markers)
                || !parsed.config || typeof parsed.config !== 'object' || Array.isArray(parsed.config)) {
                throw new TypeError('Invalid map payload.');
            }
            payload = parsed;
        } catch (error) {
            setViewStatus('The map is unavailable. The event list is still available.');
        }
    }

    if (useLocation && locationForm) {
        useLocation.addEventListener('click', () => {
            if (locationSubmitted || useLocation.disabled) return;

            if (global.isSecureContext === false) {
                setStatus('Current position requires HTTPS. Open this page securely or continue with the event list.');
                return;
            }

            if (!global.navigator?.geolocation?.getCurrentPosition) {
                setStatus('This browser does not support location access.');
                return;
            }

            useLocation.disabled = true;
            setStatus('Finding your location…');
            global.navigator.geolocation.getCurrentPosition((position) => {
                const latitude = locationForm.querySelector('[name="latitude"]');
                const longitude = locationForm.querySelector('[name="longitude"]');

                if (!latitude || !longitude) {
                    useLocation.disabled = false;
                    setStatus('Your location could not be applied.');
                    return;
                }

                latitude.value = Number(position.coords.latitude).toFixed(3);
                longitude.value = Number(position.coords.longitude).toFixed(3);
                locationSubmitted = true;
                setStatus('Location found. Updating nearby events.');
                locationForm.requestSubmit();
            }, (error) => {
                useLocation.disabled = false;
                const message = error?.code === 1
                    ? 'Location permission was denied. You can continue with the event list.'
                    : error?.code === 3
                        ? 'Location request timed out. Try again when your signal improves.'
                        : 'Your location could not be determined. Try again or use the event list.';
                setStatus(message);
            }, {
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 300000,
            });
        });
    }

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const selectedMapView = () => viewButtons.length === 0
        || viewButtons.some((button) => button.dataset.view === 'map'
            && button.getAttribute('aria-pressed') === 'true');

    const setMapAvailability = (ready, message) => {
        mapReady = ready;
        mapAvailabilityMessage = message || '';
        const fallback = mapElement?.querySelector?.('[data-map-fallback]');
        if (fallback) fallback.hidden = ready;
        if (mapAvailabilityMessage) setViewStatus(mapAvailabilityMessage);

        if (results) {
            const compact = compactViewQuery?.matches ?? false;
            results.hidden = ready && compact && selectedMapView();
        }
    };

    const initializeMap = () => {
        if (map) {
            map.invalidateSize();
            if (!mapReady && mapAvailabilityMessage) setViewStatus(mapAvailabilityMessage);
            return mapReady;
        }

        if (!payload || !mapElement || !global.L?.map) {
            setViewStatus('The map is unavailable. The event list is still available.');
            return false;
        }

        const config = payload.config;
        map = global.L.map(mapElement).setView(
            [Number(config.default_lat), Number(config.default_lng)],
            Number(config.default_zoom),
        );

        const cardById = new Map(cards.map((card) => [String(card.dataset.eventId), card]));
        const markerById = new Map();
        const markerPoints = [];
        let syncingFocus = false;

        for (const item of payload.markers) {
            if (!item || typeof item !== 'object' || Array.isArray(item)) continue;

            const latitude = Number(item.latitude);
            const longitude = Number(item.longitude);
            if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90
                || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) {
                continue;
            }

            const markerTitle = String(item.title || 'Event');
            const marker = global.L.marker([latitude, longitude], {
                alt: markerTitle,
                keyboard: true,
                title: markerTitle,
            })
                .bindPopup(`<a href="${escapeHtml(item.href || '#')}">${escapeHtml(item.title || 'Event')}</a>`)
                .addTo(map);
            const card = cardById.get(String(item.id));
            const coordinates = [latitude, longitude];
            markerById.set(String(item.id), { marker, coordinates });
            markerPoints.push({ marker, coordinates });

            marker.on('click', () => {
                if (!card) return;
                if (results?.hidden) setView('list');
                syncingFocus = true;
                card.focus();
                card.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'nearest' });
                syncingFocus = false;
            });
        }

        for (const card of cards) {
            const handleCardFocus = () => {
                if (syncingFocus) return;
                const point = markerById.get(String(card.dataset.eventId));
                if (!point) return;
                point.marker.openPopup();
                map.panTo(point.coordinates, { animate: !reduceMotion });
            };
            card.addEventListener('focusin', handleCardFocus);
            cardFocusCleanups.push(() => card.removeEventListener('focusin', handleCardFocus));
        }

        if (markerPoints.length > 1) {
            const bounds = global.L.featureGroup(markerPoints.map((point) => point.marker)).getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding: [28, 28], animate: !reduceMotion });
        } else if (markerPoints.length === 1) {
            map.setView(markerPoints[0].coordinates, Math.max(Number(config.default_zoom), 13));
        }

        if (markerPoints.length === 0) {
            setMapAvailability(false, 'No public event locations can be mapped. The complete event list is still available.');
            return false;
        }

        if (typeof config.tile_url !== 'string' || config.tile_url === '') {
            setMapAvailability(false, 'The map tiles are unavailable. The complete event list is still available.');
            return false;
        }

        tileLayer = global.L.tileLayer(config.tile_url, {
            attribution: String(config.tile_attribution || ''),
        });
        let tileFailed = false;
        handleTileLoad = () => {
            if (!tileFailed) setMapAvailability(true);
        };
        handleTileError = () => {
            tileFailed = true;
            setMapAvailability(false, 'The map tiles could not load. The complete event list is still available.');
        };
        tileLayer.on('load', handleTileLoad);
        tileLayer.on('tileerror', handleTileError);
        tileLayer.addTo(map);

        return mapReady;
    };

    const destroyMap = () => {
        for (const cleanup of cardFocusCleanups) cleanup();
        cardFocusCleanups = [];
        if (tileLayer && handleTileLoad && handleTileError) {
            tileLayer.off('load', handleTileLoad);
            tileLayer.off('tileerror', handleTileError);
        }
        tileLayer = null;
        handleTileLoad = null;
        handleTileError = null;
        map?.remove();
        map = null;
        mapReady = false;
        mapAvailabilityMessage = '';
    };

    const setView = (view) => {
        if (discovery) discovery.dataset.eventDiscoveryView = view;
        const showMap = view === 'map';
        const eventCount = cards.length;
        for (const button of viewButtons) {
            button.setAttribute('aria-pressed', String(button.dataset.view === view));
        }

        if (!showMap) {
            if (mapPanel) mapPanel.hidden = true;
            if (results) results.hidden = false;
            setViewStatus(`List view shown. ${eventCount} events available.`);
            return;
        }

        if (mapPanel) mapPanel.hidden = false;
        const compact = compactViewQuery?.matches ?? false;
        setViewStatus(compact
            ? `Map view shown. ${eventCount} events remain available in List view.`
            : `Map shown alongside ${eventCount} events.`);
        const usable = initializeMap();
        if (results) results.hidden = usable && compact;
    };

    const syncViewToViewport = () => {
        const selectedView = viewButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.view;
        if (selectedView === 'map') setView('map');
    };

    compactViewQuery?.addEventListener?.('change', syncViewToViewport);

    for (const button of viewButtons) {
        button.addEventListener('click', () => setView(button.dataset.view));
    }

    if (viewButtons.length === 0 && payload && mapElement) initializeMap();

    global.addEventListener?.('pagehide', () => {
        destroyMap();
    });

    global.addEventListener?.('pageshow', (event) => {
        if (!event.persisted) return;

        if (viewButtons.length === 0) {
            initializeMap();
            return;
        }

        const selectedView = viewButtons.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.view;
        if (selectedView === 'map') setView('map');
    });
}(typeof window !== 'undefined' ? window : globalThis));
