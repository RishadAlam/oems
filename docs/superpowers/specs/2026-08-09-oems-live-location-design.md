# OEMS Meetup-Style Live Location Design

## Goal

Add privacy-first, Meetup-style location discovery to OEMS so participants can find nearby events, organizers can place venues accurately on a map, and authorized attendees can open exact directions without introducing continuous attendee tracking.

## Approved Scope

This feature includes:

- An explicit `Use my location` action on public event discovery
- Nearby event filtering with 5, 10, 25, 50, and 100 kilometre radiuses
- Distance-aware sorting and human-readable distance labels
- A responsive list and map discovery experience
- Interactive event-detail maps for locations the viewer may see
- Organizer address search, map click, current-position selection, and draggable venue pins
- Event-level exact-location privacy for public or confirmed-participant visibility
- Arrival instructions and an external directions action
- Configurable map tiles and geocoding providers
- Accessible permission, loading, empty, denied, unavailable, and error states

The feature does not include continuous GPS watching, background tracking, attendee location broadcasting, friend location sharing, IP-based location lookup, route telemetry, or storing a participant's device coordinates in the application database.

## Existing System Audit

OEMS already has:

- `venues.latitude`, `venues.longitude`, `venues.map_url`, address, city, country, postal code, and capacity
- Organizer-owned reusable venues with create and edit workflows
- Published-event discovery with search, category, city, date, price, and sort filters
- Public event detail pages with venue text
- Participant registration state that can authorize restricted event information
- A custom PHP MVC architecture with PDO repositories, services, dependency injection, role and CSRF middleware, sessions, server-rendered views, and progressive JavaScript
- Tailwind v4, Manrope, Phosphor icons, light and dark themes, responsive layouts, visible focus, and reduced-motion behavior

The implementation extends these models. It must not create a second venue or coordinate source.

## Design Read

This is a targeted product evolution for event participants and organizers. The map is a functional discovery and planning tool, not decoration.

- `DESIGN_VARIANCE: 4` for predictable spatial controls and readable event results
- `MOTION_INTENSITY: 3` for map feedback, panel transitions, and button state only
- `VISUAL_DENSITY: 5` for useful filters without crowding mobile screens
- Design system: the existing OEMS Tailwind v4 product system
- Redesign mode: preserve current navigation, routes, cobalt accent, cool-neutral surfaces, Manrope type, Phosphor icons, radii, and copy voice

The public discovery page uses an asymmetric list/map layout on wide screens and a list-first toggle below 768 pixels. The organizer form keeps labels above fields, visible helpers and errors, and the existing form hierarchy. Map controls use the same button, surface, border, focus, and status language as OEMS rather than adopting Leaflet's default visual treatment unchanged.

## Approaches Considered

### Google Maps and Places

Google offers high-quality venue search, maps, and directions. It also requires API credentials and billing for core geocoding and Places workflows, introduces provider lock-in, and increases secret-management and cost requirements.

### Location filtering without interactive maps

Browser geolocation plus database distance calculations would provide nearby results at low complexity. It would not meet the requested map-led discovery, draggable organizer pin, or venue verification experience.

### Leaflet with provider adapters

This is the selected approach. Leaflet is self-hosted from the npm dependency and supports keyboard-operable markers, map interaction, geolocation, and draggable pins without binding OEMS to one data provider. Tile and geocoding endpoints remain environment-configurable. Development can use policy-compliant OpenStreetMap services at low volume, while production can switch to a hosted or self-managed OpenStreetMap-compatible provider without application changes.

Public OpenStreetMap services are not treated as an unlimited production backend. Tile attribution stays visible. Tile URLs are not hard-coded into view templates. Geocoding is explicit, server-side, rate-limited, cached, and never implemented as client-side autocomplete.

## Architecture

### Location domain boundaries

- `LocationService` validates and rounds coordinates, normalizes allowed radiuses, builds distance labels, produces privacy-safe location presentation, and creates safe directions URLs.
- `LocationPreference` is a short-lived session value containing rounded latitude, rounded longitude, label, source, radius, and expiry. It is never persisted to a user, profile, event, analytics, or log table.
- `GeocoderInterface` defines provider-neutral forward geocoding.
- `NominatimGeocoder` implements the configured development geocoder with a named application user agent, bounded timeout, response validation, and sanitized failures.
- `GeocodingCacheRepository` stores normalized query results and expiry so repeated searches do not repeatedly call the external provider.
- `EventRepository` owns bounding-box and Haversine filtering, distance ordering, public visibility predicates, and venue-coordinate selection.
- `VenueRepository` remains the only persistence boundary for organizer venue coordinates.
- `PublicLocationController` owns session location set and clear actions. It accepts coordinates only from a CSRF-protected POST request.
- `OrganizerVenueController` exposes a rate-limited, role-scoped, CSRF-protected address search endpoint and keeps venue ownership checks in SQL.
- Server-rendered views provide complete non-map fallbacks. JavaScript progressively adds geolocation, interactive maps, marker dragging, map/list switching, and fetch-based geocoding.

Controllers adapt HTTP input. Services own validation and presentation rules. Repositories own prepared SQL and external-response persistence. Views never query the database.

### Frontend dependency boundary

Leaflet is added to `package.json`. The asset build copies its minified CSS, JavaScript, and marker images into `public/assets/vendor/leaflet`. OEMS views reference only local assets. There is no map CDN or remote font dependency.

`public/assets/js/location.js` owns public discovery and detail maps. `public/assets/js/venue-map.js` owns organizer pin selection and address search. Both scripts initialize only when their data attributes exist, avoid global listeners when not needed, clean up active map instances, and expose useful text status when JavaScript or geolocation is unavailable.

## Data Model

### Events

Add:

- `location_visibility ENUM('public', 'registered') NOT NULL DEFAULT 'public'`
- `arrival_notes VARCHAR(500) NULL`

`location_visibility = public` exposes the exact venue address, coordinates, map, directions, and arrival notes on published and completed event pages.

`location_visibility = registered` exposes only venue city and country to public viewers. Exact address, coordinates, marker data, directions, `map_url`, and arrival notes are visible only to:

- A participant with a confirmed registration for that event
- The owning organizer
- A super administrator

Pending and cancelled registrations do not reveal the location. Public distance display for a restricted event uses a coarse radius band such as `Within 10 km`, not an exact decimal distance.

### Venues

Keep existing coordinate columns and add a composite index on `(latitude, longitude)` to support bounding-box filtering. Latitude and longitude must either both be null or both be present. Values remain within -90 to 90 and -180 to 180.

### Geocoding cache

Add `geocoding_cache` with:

- SHA-256 normalized query hash primary key
- Normalized query text
- Provider name
- JSON result payload containing only bounded display name, latitude, and longitude
- `expires_at`, `created_at`, and `updated_at`

Cache entries expire after 30 days. No participant coordinates or device-location searches enter this table. Only organizer-entered venue address searches are cached.

### Migration

A guarded forward migration upgrades an existing populated database and can run twice safely. Fresh schema, seed, demo seed, and migration paths stay consistent. Existing events default to public location visibility, preserving current behavior.

## Participant Discovery Flow

### Permission and session flow

1. The discovery page renders normally with city filtering and no browser permission prompt.
2. The participant selects `Use my location`.
3. JavaScript calls `navigator.geolocation.getCurrentPosition` once. `watchPosition` is never used.
4. The browser asks for permission.
5. On success, JavaScript rounds latitude and longitude to three decimal places before sending them to `POST /events/location` with the current CSRF token.
6. The server validates the coordinates, stores the session preference for no more than 14 days, and redirects to `/events?radius=25&sort=distance`.
7. The page shows the active location, selected radius, a change action, and a clear action.
8. `POST /events/location/clear` removes the complete preference.

Denied, timed-out, unsupported, and unavailable geolocation states display inline guidance and keep city discovery fully usable. No permission prompt occurs on initial page load.

### Search and distance calculation

When a valid session preference exists, `EventRepository::publicSearch`:

- Applies existing published, active-category, non-deleted, and future-event rules
- Requires non-null venue coordinates for a radius filter
- Computes a latitude and longitude bounding box first
- Applies a prepared Haversine expression for the exact radius check
- Supports `sort=distance`
- Returns `distance_km` only as a presentation value, never as an authorization decision

Radius values are allow-listed to 5, 10, 25, 50, and 100 kilometres. Invalid values fall back to 25. Coordinates are bound parameters. Sort clauses remain an allow-list and never interpolate request text.

### Discovery UI

The events page adds:

- `Use my location` and `Clear location` controls
- Radius controls visible only when a session location exists
- `Nearest` sort visible only when distance is available
- Distance or distance-band copy on event results
- `List` and `Map` view controls with one active state
- A desktop split layout with results and map
- A mobile list-first layout with a full-width map panel opened by the toggle
- Marker-to-card and card-to-marker focus coordination
- A map empty state when matches have restricted or missing coordinates

The list remains the semantic source of event results. The map supplements it and does not replace headings, links, dates, prices, or accessible venue text.

## Organizer Venue Flow

The venue form replaces manual-coordinate-first presentation with a map-led section:

- A complete address remains required and editable without JavaScript.
- `Find address` submits one explicit geocoding search. There is no keystroke autocomplete.
- Up to five cached or provider results appear as keyboard-operable buttons.
- Selecting a result moves the pin and writes the latitude and longitude fields.
- Clicking the map or dragging its marker updates both fields.
- `Use current position` requests browser location only after organizer action.
- The latitude and longitude fields remain visible under an advanced disclosure for exact correction and no-JavaScript operation.
- An inline status announces searching, results, selected pin, denied permission, provider unavailable, and invalid coordinates.

Venue name and written address are independent from the pin. Moving the pin does not silently rewrite the address. Saving requires both coordinates or neither. The organizer can clear the pin.

The event form adds exact-location visibility and arrival instructions. Helper copy explains that registered-only mode hides the precise venue until confirmation.

## Event Detail and Directions

An authorized exact-location presentation contains:

- Venue name and complete address
- An interactive map with one marker and non-map address fallback
- Arrival notes when present
- `Open directions`, using a validated organizer `map_url` when available or a generated HTTPS directions URL otherwise

A public viewer of a registered-only event sees city, country, and a concise explanation that the exact venue is shared after registration confirmation. No map container, marker JSON, coordinate attributes, exact address, directions link, arrival notes, or exact-location JSON-LD is rendered.

Published and completed events can render maps. Draft, pending, approved, rejected, cancelled, and deleted events never become public through a location endpoint.

## Privacy and Security

- Browser location is requested only after a deliberate user action.
- `Permissions-Policy: geolocation=(self)` limits geolocation to OEMS.
- Device coordinates are rounded before transmission and not stored in the database.
- Session location expires after 14 days and can be cleared immediately.
- No background tracking, `watchPosition`, IP geolocation, attendee broadcasting, or third-party location analytics are introduced.
- Restricted exact locations are excluded from server-rendered HTML, data attributes, JSON, JSON-LD, maps, directions links, logs, and error messages.
- Public map markers include only published or completed events with public exact locations.
- Organizer geocoding is role-scoped, CSRF-protected, rate-limited, bounded, cached, and escaped.
- Tile and geocoder endpoints are restricted to configured HTTPS origins. Attribution is visible.
- External directions URLs allow only `https` and validated hosts or are generated internally from numeric coordinates.
- Geocoder and map-provider failures are logged with provider, operation, status class, and query hash only. Raw addresses, coordinates, response bodies, credentials, and session identifiers are excluded.
- All repository queries use prepared values and retain ownership, lifecycle, active-category, and soft-delete predicates.

## Configuration

Add documented environment values:

- `MAP_TILE_URL`
- `MAP_TILE_ATTRIBUTION`
- `MAP_DEFAULT_LAT`
- `MAP_DEFAULT_LNG`
- `MAP_DEFAULT_ZOOM`
- `MAP_GEOCODER_URL`
- `MAP_PROVIDER_NAME`
- `MAP_USER_AGENT`
- `MAP_CONTACT_EMAIL`
- `LOCATION_SESSION_TTL`

Development defaults support normal human-driven local testing. Production documentation requires a tile and geocoding provider appropriate for expected traffic. No API key is committed.

## Error Handling and Progressive Enhancement

- The public events page remains fully searchable by city if JavaScript, maps, tiles, or geolocation fail.
- Venue coordinates remain manually editable if map initialization fails.
- Map errors use inline text and never prevent form submission when valid manual values exist.
- Geocoder timeout or provider errors return a bounded 503 response and a retryable UI state.
- Invalid coordinate, radius, search, and route values return validation errors without partial session or database changes.
- Duplicate location submissions are idempotent.
- Provider calls use bounded connection and total timeouts.
- Map loading reserves layout height to avoid cumulative layout shift.

## Accessibility and Responsive Behavior

- Every map has a visible heading and nearby text alternative.
- Map regions use an accessible label and do not trap keyboard focus.
- Leaflet markers retain keyboard behavior and useful titles.
- List results remain the canonical keyboard path.
- Focus coordination never steals focus during ordinary scrolling.
- Controls provide 44-pixel minimum touch targets, visible 3-pixel focus, non-color active states, and WCAG AA contrast in light and dark modes.
- Status changes use an `aria-live` region.
- Map and list controls use buttons with `aria-pressed`.
- Mobile layouts at 320, 768, and 1440 pixels have no horizontal overflow.
- Reduced-motion users receive immediate state changes without animated map-panel transitions.
- Copy uses direct functional language: `Use my location`, `Nearest`, `Open map`, `Clear location`, `Find address`, and `Open directions`.

## Testing Strategy

- Schema and migration tests cover new columns, the coordinate index, geocoding cache, defaults, and repeatable populated upgrades.
- `LocationService` tests cover coordinate bounds, three-decimal rounding, radius allow-listing, session expiry, distance labels, privacy presentation, and directions URL validation.
- Event repository tests cover bounding-box plus Haversine filtering, distance ordering, lifecycle predicates, missing coordinates, active categories, restricted distance bands, and prepared limits.
- Geocoder tests cover request normalization, cache hits, one-request rate limits, timeouts, malformed provider responses, bounded results, and sanitized failures.
- Controller and route tests cover CSRF, role, IDOR, method, invalid input, session set and clear, denied exact-location access, confirmed access, and organizer address search.
- View tests prove restricted coordinates and addresses are absent from HTML and JSON-LD, public markers are escaped, map fallbacks exist, filters preserve state, and UI controls have accessible associations.
- JavaScript tests cover permission success, denial, timeout, unsupported browsers, rounded POST payloads, map/list state, draggable marker synchronization, result selection, cleanup, and reduced motion.
- Native MySQL tests verify distance queries and the forward migration under production PDO settings.
- Browser QA covers 320, 768, and 1440 pixels in light and dark modes, keyboard focus, marker operation, permission-denied fallback, touch layout, contrast, console diagnostics, and provider failure states.
- Release gates include the complete PHPUnit suite, PHP syntax, Composer strict validation/platform/audit, npm asset build, JavaScript syntax and behavior tests, `git diff --check`, tracked-secret scan, live HTTP checks, and configured-server health.

## Delivery Slices and Commits

1. Commit the approved design and implementation plan.
2. Add the guarded schema migration, location domain rules, and database tests.
3. Add session location, distance-aware discovery, controller routes, and repository tests.
4. Add self-hosted Leaflet assets, public list/map discovery, event detail privacy, and JavaScript tests.
5. Add organizer geocoding, draggable venue pins, visibility fields, arrival notes, and workflow tests.
6. Complete demo data, documentation, native MySQL, HTTP, responsive browser QA, release audit, final commit, and GitHub push.

Each slice uses a real red-green-refactor cycle, receives a scoped review, stages only intended project files, and creates its own Git commit. Existing unrelated untracked workspace artifacts remain untouched.

## Completion Criteria

The feature is complete when:

- A guest or participant can explicitly enable location and find published nearby events by an allowed radius.
- Distance filtering and nearest sorting are correct under native MySQL.
- Denying or lacking browser permission leaves city discovery fully usable.
- The public discovery page offers accessible list and map modes without exposing restricted coordinates.
- An organizer can search an address, choose or drag a map pin, manually correct coordinates, and save the venue.
- An organizer can mark event location details public or confirmed-participant-only and add arrival instructions.
- Exact restricted address, marker, coordinates, directions, arrival notes, and JSON-LD remain unavailable until authorization.
- An authorized viewer can see the event map and open directions.
- No participant device coordinates are stored in the database or written to application logs.
- Tile attribution, provider configuration, cache, timeout, rate limit, and failure behavior comply with the documented provider boundary.
- Automated, native MySQL, HTTP, security, accessibility, responsive, light/dark, and browser verification pass.
- Every delivery slice is committed separately and only project files are pushed to the public repository.
