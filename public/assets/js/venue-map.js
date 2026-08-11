(function (global) {
    'use strict';

    const document = global.document;
    if (!document) return;

    let cleanupMap = null;

    const createVenueMap = () => {
        const form = document.querySelector('[data-venue-map-form]');
        const mapElement = document.querySelector('[data-venue-map]');
        if (!form || !mapElement || !global.L?.map) return null;

        const searchInput = form.querySelector('[data-venue-search]');
        const findButton = form.querySelector('[data-venue-find]');
        const resultsElement = form.querySelector('[data-venue-results]');
        const statusElement = form.querySelector('[data-venue-status]');
        const useLocationButton = form.querySelector('[data-venue-use-location]');
        const clearButton = form.querySelector('[data-venue-clear-pin]');
        const latitudeInput = form.querySelector('[name="latitude"]');
        const longitudeInput = form.querySelector('[name="longitude"]');
        const defaultLatitude = Number(mapElement.dataset.defaultLat);
        const defaultLongitude = Number(mapElement.dataset.defaultLng);
        const defaultZoom = Number(mapElement.dataset.defaultZoom);
        const map = global.L.map(mapElement).setView(
            [Number.isFinite(defaultLatitude) ? defaultLatitude : 23.8103,
                Number.isFinite(defaultLongitude) ? defaultLongitude : 90.4125],
            Number.isFinite(defaultZoom) ? defaultZoom : 11,
        );
        let marker = null;
        let tileLayer = null;
        let handleTileLoad = null;
        let handleTileError = null;
        let destroyed = false;
        const fallback = mapElement.querySelector?.('.venue-map__fallback');
        if (fallback) fallback.hidden = false;

        const setStatus = (message) => {
            if (statusElement) statusElement.textContent = message;
        };

        const coordinate = (value, minimum, maximum) => {
            if (value === '') return null;
            const parsed = Number(value);
            return Number.isFinite(parsed) && parsed >= minimum && parsed <= maximum ? parsed : null;
        };

        const setPin = (latitude, longitude, message) => {
            if (!latitudeInput || !longitudeInput) return;
            const normalizedLatitude = coordinate(latitude, -90, 90);
            const normalizedLongitude = coordinate(longitude, -180, 180);

            if (normalizedLatitude === null || normalizedLongitude === null) {
                setStatus('The selected coordinates are invalid.');
                return;
            }

            const point = [normalizedLatitude, normalizedLongitude];
            latitudeInput.value = normalizedLatitude.toFixed(7);
            longitudeInput.value = normalizedLongitude.toFixed(7);

            if (marker) {
                marker.setLatLng(point);
            } else {
                marker = global.L.marker(point, {
                    draggable: true,
                    keyboard: true,
                    title: 'Venue pin',
                }).addTo(map);
                marker.on('dragend', () => {
                    const moved = marker.getLatLng();
                    setPin(moved.lat, moved.lng, 'Pin moved. Exact coordinates updated.');
                });
            }

            map.setView(point, Math.max(defaultZoom || 11, 15));
            setStatus(message);
        };

        const existingLatitude = coordinate(latitudeInput?.value ?? '', -90, 90);
        const existingLongitude = coordinate(longitudeInput?.value ?? '', -180, 180);
        if (existingLatitude !== null && existingLongitude !== null) {
            setPin(existingLatitude, existingLongitude, 'Saved pin loaded.');
        }

        if (mapElement.dataset.tileUrl) {
            let tileFailed = false;
            tileLayer = global.L.tileLayer(mapElement.dataset.tileUrl, {
                attribution: String(mapElement.dataset.tileAttribution || ''),
            });
            handleTileLoad = () => {
                if (!tileFailed && fallback) fallback.hidden = true;
            };
            handleTileError = () => {
                tileFailed = true;
                if (fallback) fallback.hidden = false;
                setStatus('Map tiles are unavailable. Place the pin manually or enter both coordinates.');
            };
            tileLayer.on('load', handleTileLoad);
            tileLayer.on('tileerror', handleTileError);
            tileLayer.addTo(map);
        } else {
            setStatus('Map tiles are unavailable. Place the pin manually or enter both coordinates.');
        }

        const handleMapClick = (event) => {
            setPin(event?.latlng?.lat, event?.latlng?.lng, 'Pin placed. Exact coordinates updated.');
        };
        map.on('click', handleMapClick);

        const renderResults = (results) => {
            if (!resultsElement) return;
            resultsElement.replaceChildren();

            for (const result of results.slice(0, 5)) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'venue-search-result';
                button.textContent = String(result.label || 'Address result');
                button.addEventListener('click', () => {
                    setPin(result.latitude, result.longitude, 'Pin selected. Review the written address before saving.');
                });
                resultsElement.append(button);
            }
        };

        const handleFind = async () => {
            if (!searchInput || !findButton || !global.fetch) return;
            const query = searchInput.value.trim();
            if (query.length < 3 || query.length > 160) {
                setStatus('Enter an address between 3 and 160 characters.');
                return;
            }

            findButton.disabled = true;
            setStatus('Searching for that address.');

            try {
                const body = new URLSearchParams({
                    query,
                    _token: String(form.dataset.csrf || ''),
                });
                const response = await global.fetch(String(form.dataset.geocodeUrl || ''), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString(),
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok || !Array.isArray(payload.results)) {
                    const error = payload?.errors?.location?.[0];
                    throw new Error(typeof error === 'string' ? error : 'Address search is unavailable.');
                }

                renderResults(payload.results);
                setStatus(payload.results.length === 0
                    ? 'No address results found. You can place the pin manually.'
                    : `${Math.min(payload.results.length, 5)} address results found. Choose one to place the pin.`);
            } catch (error) {
                renderResults([]);
                setStatus(error instanceof Error ? error.message : 'Address search is unavailable.');
            } finally {
                findButton.disabled = false;
            }
        };

        findButton?.addEventListener('click', handleFind);

        const handleUseLocation = () => {
            if (global.isSecureContext === false) {
                setStatus('Current position requires HTTPS. Open this page securely or place the pin manually.');
                return;
            }

            if (!global.navigator?.geolocation?.getCurrentPosition) {
                setStatus('This browser does not support location access.');
                return;
            }

            setStatus('Finding your current position.');
            global.navigator.geolocation.getCurrentPosition((position) => {
                setPin(position.coords.latitude, position.coords.longitude, 'Current position selected. Review the pin before saving.');
            }, (error) => {
                setStatus(error?.code === 1
                    ? 'Location permission was denied. Place the pin manually instead.'
                    : 'Your current position could not be determined. Place the pin manually instead.');
            }, {
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 300000,
            });
        };
        useLocationButton?.addEventListener('click', handleUseLocation);

        const handleClear = () => {
            if (latitudeInput) latitudeInput.value = '';
            if (longitudeInput) longitudeInput.value = '';
            marker?.remove();
            marker = null;
            setStatus('Pin cleared. The written address is unchanged.');
        };
        clearButton?.addEventListener('click', handleClear);

        const cleanup = () => {
            if (destroyed) return;
            destroyed = true;
            findButton?.removeEventListener('click', handleFind);
            useLocationButton?.removeEventListener('click', handleUseLocation);
            clearButton?.removeEventListener('click', handleClear);
            map.off('click', handleMapClick);
            if (tileLayer && handleTileLoad && handleTileError) {
                tileLayer.off('load', handleTileLoad);
                tileLayer.off('tileerror', handleTileError);
            }
            marker?.remove();
            map.remove();
            cleanupMap = null;
        };

        return cleanup;
    };

    const initialize = () => {
        if (cleanupMap === null) cleanupMap = createVenueMap();
    };

    const handlePageHide = () => cleanupMap?.();
    const handlePageShow = (event) => {
        if (event?.persisted) initialize();
    };

    global.addEventListener?.('pagehide', handlePageHide);
    global.addEventListener?.('pageshow', handlePageShow);
    initialize();
}(typeof window !== 'undefined' ? window : globalThis));
