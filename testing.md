# Testing

## Current state

Both layers are operational. Run everything with:

```bash
./test.sh           # PHPUnit + Playwright
./test.sh --unit    # PHPUnit only
./test.sh --e2e     # Playwright only
```

Live Google format tests (not run in normal CI -- require internet):

```bash
vendor/bin/phpunit tests/Live/   # calls real Google Photos to detect format changes
```

### What exists

**PHPUnit** (`tests/Unit/`) -- 607 tests

- `AdminPagesTest.php` -- admin menu/page URL registration, admin asset enqueue gating/localization, lazy Guide preview placeholders and escaping
- `OrchestratorToggleModeTest.php` -- toggle mode resolution, paired-key fallback
- `OrchestratorAssetsTest.php` -- frontend asset handles/dependencies, localized AJAX config/i18n, admin preview asset gating
- `OrchestratorCacheTest.php` -- cache/expiry/backup key generation, TTL constants, cache-refresh parsing
- `OrchestratorAdminAjaxTest.php` -- admin preview guardrails, clear-cache AJAX messages, post-save album/photo-meta cache invalidation
- `OrchestratorConfigTest.php` -- shortcode config defaults, bounds, aliases, legacy compatibility, paired fullscreen/lightbox fallbacks
- `OrchestratorShortcodeTest.php` -- public shortcode flow with fake provider/renderer: fresh fetch, cache hit, cache-duration invalidation, stale backup fallback, fetch error
- `OrchestratorProgressiveTest.php` -- progressive slider activation rules and chunk payload preparation
- `OrchestratorMediaAjaxTest.php` -- photo-meta AJAX, progressive chunk AJAX, refresh-URLs AJAX, download warnings/hard limits, legacy download fallback, media URL/filename helpers
- `RendererSliderTest.php` -- inline styles, data attributes for slider/carousel mode
- `RendererGalleryTest.php` -- gallery-mode container and all gallery-specific parameters
- `RendererEscapingTest.php` -- escaped renderer attributes, photo JSON payload attributes, error title/link markup
- `RendererButtonsTest.php` -- lightbox/fullscreen button render conditions, dual-expand class
- `RendererMosaicTest.php` -- mosaic wrapper positions, strip element, mosaic data attributes
- `RendererInfoBoxTest.php` -- info-box format strings, typography CSS props, halo effects
- `RendererLightboxAttrTest.php` -- all lightbox data attributes, interaction-lock suppression
- `CommunityValidationTest.php` -- all 8 field validators, URL helpers, tag normalization
- `CommunityAjaxTest.php` -- real community AJAX/REST handlers with mocked HTTP: connect, browse, publish/update/delete, profile, interactions, rating, challenge validation, malformed responses
- `DataProviderParseTest.php` -- URL extraction, duplicate filtering, non-Google URL filtering, metadata enrichment, filename extraction, EXIF scoping, video detection, title cleaning, camera formatting, individual photo meta; uses `tests/fixtures/google/album.html` (real recorded response)

**Playwright** (`tests/e2e/`) -- full Chromium project plus Firefox/WebKit smoke executions, including a Firefox-only fullscreen regression

- `lightbox.spec.ts` -- slider click/button-only trigger, dual expand, gallery lightbox, dialog/close-button accessibility attributes, keyboard gallery lightbox activation, and advanced accessibility (focus trapping, focus restoration on close, Escape close inside focusable elements)
- `fullscreen.spec.ts` -- fullscreen button presence, dual-expand interaction, close methods, Firefox native fullscreen display-limit regression
- `slideshow.spec.ts` -- data attributes, play/pause button, auto-advance and manual-hold
- `gallery.spec.ts` -- data attributes (layout/columns/scrollable/rows), items, responsive columns, hover button, slideshow player open/navigate/close
- `navigation.spec.ts` -- arrow visibility, slide advance, keyboard, interaction-lock, download/link buttons, safe external-link attributes, mocked download proxy clicks, proxy error status, and large-download retry confirmation
- `shortcode-integration.spec.ts` -- real WordPress shortcode rendering, asset enqueue/localization, parsed shortcode attributes, gallery-mode output
- `mosaic.spec.ts` -- wrapper position classes, strip presence, data attributes, thumbnail-to-slide sync
- `info-overlay.spec.ts` -- pagination text substitution ({item}/{items}/{album-title}), info-top box, lazy photo metadata AJAX/DOM refresh/failure handling
- `admin.spec.ts` -- Guide page lazy previews, Parameters table rows, Community page structure
- `community.spec.ts` -- page structure, not-connected vs connected account state, live browse AJAX, sort/search, publish form validation (6 rules), My entries terminal state
- `community-mocked.spec.ts` -- deterministic mocked community AJAX for browse rendering, search/sort payloads, publish, rating, owned-entry update/delete
- `video.spec.ts` -- deterministic mixed image/video album behavior with Plyr stub: initialization, play state, navigation stop/reset, fullscreen stop, lightbox close stop

### Infrastructure

- `playwright.config.ts` -- full Chromium project plus Firefox/WebKit smoke projects for tests tagged `@cross-browser`, 1 worker, retries: 1, globalSetup validates all fixture pages, `PLAYWRIGHT_BASE_URL` supported
- `tests/fixtures/google/album.html` -- recorded Google Photos album response (1 MB); used by DataProviderParseTest
- `tests/Live/DataProviderLiveTest.php` -- 5 live smoke tests; run manually to detect Google format changes
- `tests/e2e/global-setup.ts` -- seeds deterministic fixtures by default, validates 7 fixture pages (lightbox, slideshow, video, gallery, mosaic, info, feature), and verifies seeded login users
- `tests/e2e/setup-fixtures.php` -- deterministic WordPress fixture seeder for the 7 e2e pages plus default admin/disconnected users; preserves connected JWT unless `JZSA_E2E_CONNECTED_JWT` is provided
- `tests/e2e/README.md` -- documents automated setup, all required fixture pages, shortcode order, and e2e environment variables
- PHPUnit bootstrap stubs all WordPress functions so unit tests run without WordPress
- Fixture pages (all require `mode="slider"` for slider-mode features): slideshow-fixture, video-fixture, mosaic-fixture, feature-fixture, info-fixture; gallery-fixture uses default gallery mode
- Admin/community tests use two WP users by default: `dev`/`test123` (connected to community), `testuser-noc`/`testpass123` (never connected). Override with `JZSA_E2E_*` environment variables.

### Verification findings from branch review

Compared `feature/community-lightbox-tests` against `feature/community-and-lightbox`. The test branch adds PHPUnit, Playwright, fixtures, `test.sh`, and documentation with only small production-code changes.

Local verification:

```bash
./test.sh         # runs PHPUnit and Playwright together
./test.sh --unit  # passed: 607 tests, 1300 assertions
npx playwright test tests/e2e/navigation.spec.ts  # passed: 20 tests
npx playwright test  # passed after deterministic setup: 192 passed, 3 flaky retries, 2 expected skips
npx playwright test tests/e2e/info-overlay.spec.ts --project=chromium  # passed: 14 tests
npx playwright test tests/e2e/admin.spec.ts --project=chromium  # passed after auth hardening: 14 tests
npx playwright test tests/e2e/community.spec.ts --project=chromium  # passed after auth hardening: 35 tests
```

Issues found and addressed:

- `PLAYWRIGHT_BASE_URL` was only used by `global-setup.ts`; Playwright itself still hardcoded `http://127.0.0.1:8080`. This could validate one site and test another.
- `tests/e2e/README.md` documented only `lightbox-fixture`, while `global-setup.ts` required six fixture pages.
- `npm test` exited with "no test specified" despite Playwright being installed.
- Admin/community specs duplicated login logic and hardcoded users directly in spec files.
- Admin login e2e was flaky on first navigation when waiting for a specific redirect event; the shared login helper now submits, waits for load, explicitly lands in `/wp-admin/`, and asserts the admin bar.
- Fixture specs waited for full page `load`, which could block on external media and make page navigation flaky.
- Gallery slideshow tests clicked controls before the player had left its fullscreen-loading gate.
- The lightbox close button sat below the full-resolution loader overlay, so the overlay could intercept close clicks while media was still loading.
- Community AJAX handlers were mostly covered only indirectly through browser tests; deterministic unit coverage now exercises success, validation, server-error, auth, and local-state branches.
- Renderer escaping/security boundaries were not explicitly covered; dedicated tests now lock down unsafe titles, info strings, JSON payload attributes, and error titles.
- Lightbox and external-link browser tests checked presence/behavior but not key accessibility and safe-link attributes; e2e now covers dialog semantics, close labels, `target`, and `rel`.
- Shortcode parsing had little direct coverage; unit tests now lock down defaults, legacy `show-title`/`show-counter` behavior, false aliases such as `lightbox-toggle="no"`, dimension/bounds clamping, and paired fullscreen/lightbox inheritance.
- Inline `source-width` / `source-height` accepted zero/negative values through raw `intval`; they now fall back to defaults like paired fullscreen/lightbox source dimensions.
- Public shortcode flow was not covered without a real provider; fake provider/renderer tests now verify cache writes, cache hits, cache duration invalidation, stale backup recovery, and error rendering.
- Progressive slider loading had no dedicated tests; activation is now covered for large sliders, threshold boundary, gallery/mosaic/video exclusions, and visible-index chunk payloads.
- Media AJAX endpoints had only browser-level coverage; unit tests now lock down photo-meta nonce/URL guards, cache-hit behavior, partial EXIF cache refresh, empty response handling, album chunk cache miss/failure paths, refresh-URL cache mutation/failure paths, and download warning/fetch-failure guardrails.
- Admin AJAX and save-post cache invalidation were under-tested; unit tests now cover shortcode preview permission/shortcode guards, preview success, clear-cache scope routing/messages, and deletion of album, backup, expiry, and per-photo metadata caches for post shortcodes.
- Community REST/auth challenge coverage now verifies route registration, missing/invalid/reused challenges, one-time valid challenge consumption, malformed API responses, partial profile sync fallback, and interaction JWT/count behavior.
- Download tests now cover both warning threshold and hard-limit enforcement from `content-length` and actual body size, plus legacy `image_url` compatibility.
- Browser coverage now verifies gallery responsive column behavior at desktop/tablet/mobile widths and keyboard activation of gallery lightbox thumbnail controls.
- Browser coverage now clicks the slider download button against a deterministic mocked WordPress AJAX endpoint, verifies the proxy payload, locks down large-download confirmation retry behavior with `allow_large_download=true`, and verifies proxy error payloads appear in the user-facing status message.
- The download client now inspects blob-like error responses before falling back to a direct download path for both slider and gallery thumbnail download buttons.
- Firefox dropped grouped fullscreen display-limit CSS selectors containing WebKit's prefixed pseudo-class; e2e now covers the real-user native fullscreen limited-presentation regression from commit `f7d5911`.
- Browser video behavior now has deterministic coverage using static mixed-media fixture markup and a Plyr stub: mixed image/video rendering, Plyr initialization, play-state UI, stopping/resetting on navigation, stopping before native fullscreen, and stopping when the lightbox closes.
- Lazy photo metadata placeholders were only unit-tested; browser coverage now verifies the `jzsa_fetch_photo_meta` request payload, info-box refresh from AJAX metadata, updated `data-all-photos`, and non-fatal slider navigation after a metadata request failure.
- E2E setup is now deterministic by default: Playwright global setup runs the WordPress seeder through Docker Compose, validates every fixture page, and verifies the seeded admin/disconnected login credentials before specs start. `JZSA_E2E_SKIP_SETUP=1` preserves support for already prepared remote targets.

Remaining risks:

- E2E fixture pages and users can now be seeded deterministically with `tests/e2e/setup-fixtures.php`; connected community state still needs either an existing local JWT or `JZSA_E2E_CONNECTED_JWT`.
- Community e2e now has a deterministic mocked-flow spec for browse/publish/rate/update/delete, while `community.spec.ts` still provides a smaller live-state smoke layer.
- Frontend e2e runs the full suite in Chromium and a tagged browser-sensitive smoke subset in Firefox/WebKit.
- Some tests assert DOM/data attributes rather than fully observable behavior. That is useful for cheap regression coverage, but not a substitute for integration coverage of the complete shortcode path.

---

## Completed plan

### Unit tests now covered (PHPUnit)

| File | What it covers |
|---|---|
| `AdminPagesTest.php` | Admin page URL/menu registration, admin asset gating/localization, and lazy Guide preview placeholders with escaped shortcode attributes |
| `OrchestratorAssetsTest.php` | Frontend asset handles/dependencies, cache-busting versions, localized AJAX nonces/i18n/Plyr URL, and admin preview-page asset gating |
| `RendererSliderTest.php` | Every slider/carousel parameter maps to the correct `data-*` attribute or CSS custom property |
| `RendererGalleryTest.php` | Every gallery-specific parameter (`gallery-columns`, `gallery-layout`, `gallery-rows`, etc.) |
| `RendererEscapingTest.php` | Escaping at HTML attribute boundaries for titles, info strings, photo JSON payloads, numeric casts, and error markup |
| `RendererButtonsTest.php` | Which buttons render under which conditions (fullscreen, lightbox, download, link, dual-expand class) |
| `RendererMosaicTest.php` | Mosaic parameters (`mosaic-position`, `mosaic-count`, `fullscreen-mosaic-layout`, etc.) |
| `RendererInfoBoxTest.php` | All `info-*` parameters, per-box halo overrides, font/color/align inheritance |
| `OrchestratorAdminAjaxTest.php` | Admin shortcode preview, clear-cache AJAX scope/message logic, post-content cache invalidation |
| `OrchestratorCacheTest.php` | Cache key generation (MD5 of URL), TTL edge cases, paired-key additional coverage |
| `OrchestratorConfigTest.php` | Shortcode attribute normalization: defaults, legacy info defaults, aliases, bounds, source dimensions, paired fullscreen/lightbox values |
| `OrchestratorShortcodeTest.php` | Public `handle_shortcode()` flow with fake dependencies: fresh fetch/cache/backup/error branches |
| `OrchestratorProgressiveTest.php` | Progressive loading activation and album chunk preparation |
| `OrchestratorMediaAjaxTest.php` | Photo-meta, album-chunk, refresh-URL, and download AJAX guardrails plus media filename/cache helpers and hard-limit filters |
| `CommunityValidationTest.php` | All 8 field validators: title (3-120), tags (max 5, 2-30 chars), display name (3+ letters), URLs, description length |
| `CommunityAjaxTest.php` | AJAX/REST permissions, JWT handling, mocked HTTP payloads, validation short-circuits, server errors, profile/local-state updates, auth challenge lifecycle |

The renderer tests are the highest-value target. With 80+ parameters each mapping to a `data-*` attribute, parametrized PHPUnit tests can cover the full surface cheaply. A renderer bug that misconfigures a `data-lightbox-max-width` attribute would never show up in the current test suite.

### E2E tests now covered (Playwright)

| File | What it covers | Fixture page slug |
|---|---|---|
| `fullscreen.spec.ts` | button presence/absence, dual-expand interaction, lightbox close methods, Firefox native fullscreen display caps | `lightbox-fixture` |
| `gallery.spec.ts` | Grid and justified layouts, responsive column counts, opening the slideshow player, navigating within it, close | `gallery-fixture` |
| `slideshow.spec.ts` | Auto-advance, play/pause button, manual mode hold | `slideshow-fixture` |
| `navigation.spec.ts` | Arrow buttons, keyboard arrows, interaction-lock, download/link buttons, safe link attributes, mocked download proxy click/error/retry behavior | `feature-fixture` |
| `shortcode-integration.spec.ts` | Actual WordPress shortcode rendering into plugin markup, frontend asset enqueue/localization, parsed attributes, gallery output | `feature-fixture`, `gallery-fixture` |
| `mosaic.spec.ts` | Clicking a mosaic thumbnail advances the main slider, all four positions | `mosaic-fixture` |
| `info-overlay.spec.ts` | `{item}`, `{items}`, `{album-title}` substitution visible in rendered text plus lazy photo metadata request/update/failure behavior | `info-fixture` |
| `lightbox.spec.ts` | Lightbox trigger behavior, dialog ARIA, close-button labels, gallery lightbox cases, keyboard activation, and advanced accessibility (focus trapping, focus restoration, Escape close inside focusable elements) | `lightbox-fixture` |
| `video.spec.ts` | Mixed image/video rendering, Plyr initialization, play state, navigation stop/reset, fullscreen stop, lightbox close stop | `video-fixture` |
| `community.spec.ts` (done) | Browse list loads, search/filter, connect flow, publish form validation | WordPress admin URL |
| `community-mocked.spec.ts` (done) | Mocked community browse, publish, rating, update, and delete flows without the external API | WordPress admin URL |
| `admin.spec.ts` (done) | Guide page loads previews, Parameters page renders the table | WordPress admin URL |

### Fixture pages needed

Each e2e spec file lists its own `FIXTURE_URL` constant at the top. `globalSetup` verifies all required fixture pages before any spec runs.

| Slug | Shortcodes | Purpose |
|---|---|---|
| `lightbox-fixture` | 5 (exists) | lightbox tests |
| `gallery-fixture` | 5: grid, justified, scrollable, paginated (gallery-rows), click-to-lightbox gallery | gallery interaction tests |
| `slideshow-fixture` | 3: auto delay=1, manual, disabled (control) | slideshow tests; use `slideshow-delay="1"` so tests don't wait 5s |
| `video-fixture` | 2 static video albums plus 1 asset-enqueue shortcode | deterministic video tests without real video downloads |
| `mosaic-fixture` | 4: bottom, top, left, right | mosaic strip tests |
| `info-fixture` | 4: various info-top/info-bottom format strings plus lazy metadata placeholders | placeholder rendering and lazy metadata tests |
| `feature-fixture` | 3-4: navigation, download, link, interaction-lock | feature flag tests |

Community and admin tests use WordPress admin URLs directly, no shortcode fixture page needed.

---

## Next coverage plan

### Highest value

1. **Real WordPress integration for shortcode rendering**
   Current unit tests stub WordPress heavily. Add a small WordPress integration layer that runs actual `do_shortcode()` with WordPress shortcode parsing, escaping, enqueue behavior, and post content. This would catch issues hidden by `tests/bootstrap.php`.

2. **Deterministic e2e setup (Completed)**
   Playwright global setup now creates fixture pages and users automatically through `setup-fixtures.php`, validates the rendered pages, and verifies the seeded admin/disconnected login credentials before specs start. Remaining related risk: connected community state still needs either an existing local JWT or `JZSA_E2E_CONNECTED_JWT`.

3. **Community e2e with mocked API**
   Unit coverage is strong, but browser coverage still depends on local/community state. A deterministic mock/filter for browse, publish, update, delete, rate, and connect would make full community flows CI-safe.

4. **Lazy metadata and progressive loading behavior in browser (Partially Completed)**
   E2E now covers actual lazy EXIF/photo-meta requests, request flags, DOM refresh from returned metadata, `data-all-photos` mutation, and non-fatal slider navigation after metadata failure. Remaining work: progressive chunk loading as the user navigates deep into a large album, retry-specific UX, and cache-hit behavior across repeated albums.

### Good next tier

5. **Lightbox accessibility depth (Partially Completed)**
   Current tests cover ARIA, basic keyboard activation, and now include robust coverage for focus trapping, focus restoration to originating trigger element on keyboard close, and Escape closability when inner components are focused. Remaining areas: Escape behavior across nested iframe video states, and keyboard navigation inside Swiper player.

6. **Mobile/touch behavior**
   Current responsive gallery tests are good, but touch gestures, mobile button visibility modes, pseudo-fullscreen on iPhone-like viewports, and small-screen lightbox layout are still likely risk areas.

7. **Admin UI behavior beyond presence**
   Admin e2e checks structure and lazy previews. Add tests for the shortcode playground preview, copy/apply/revert flows, cache-clear button behavior, and parameter/reference navigation.

8. **Failure-state rendering**
   Add browser-level tests for fetch errors, stale backup display, deprecated short-link warning visibility for admins only, no-photos errors, and malformed Google payload fallback.

---

## Community e2e strategy

The community feature calls an external API. Two options:

**Real API (simpler):** use a dedicated connected test account. Tests are honest but fragile on network issues. Good for a small number of smoke tests run manually.

**WordPress filter mock (robust):** add a filter hook that substitutes a fake HTTP response in test mode. Fully offline, deterministic. Slightly more setup but makes the full community flow testable in CI.

Recommendation: filter mock for the full publish/browse/rate flow, one real-API smoke test for the connect (magic link) flow.

---

## Known assertions to be careful about

- **Opacity-based hiding:** gallery buttons use `opacity: 0` with `display: flex` always set. Playwright's `toBeVisible()` does not check opacity. Use `toHaveCSS('opacity', '0')` to assert hidden state, not `not.toBeVisible()`.
- **IntersectionObserver lazy init:** gallery albums only initialize when scrolled into the viewport. Always call `await album.scrollIntoViewIfNeeded()` before asserting on gallery state (already handled in `waitForAlbum`).
- **DOM move on lightbox open:** `openLightbox` moves the album element into the backdrop div. Any locator that was resolved before open will point to the wrong element after the move. Assert inside `backdrop(page).locator(...)` instead.
- **`force: true` for lightbox button clicks:** the cover-fit slide image overlaps the button bounding box. Playwright's pointer-events hit-test fails without `{ force: true }`.
- **Slideshow delay in tests:** use `slideshow-delay="1"` on fixture shortcodes so auto-advance tests complete in ~1 second instead of 5.

---

## CI (not yet configured)

When ready, add a GitHub Actions workflow:

```yaml
on: [push, pull_request]
jobs:
  unit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction
      - run: vendor/bin/phpunit

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker compose up -d
      - run: npm ci
      - run: npx playwright install --with-deps chromium firefox webkit
      - run: npx playwright test
```

PHPUnit runs in under 10 seconds. Playwright currently schedules 197 tests across full Chromium plus Firefox/WebKit smoke projects. The latest local full run after deterministic setup passed with 192 passed, 3 flaky retries, and 2 expected skips for the Firefox-only fullscreen regression outside Firefox. The admin and community specs passed cleanly after auth-helper hardening. Both layers should block merging once CI runs the fixture setup script before Playwright.
