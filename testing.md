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

**PHPUnit** (`tests/Unit/`) -- 378 tests

- `OrchestratorToggleModeTest.php` -- toggle mode resolution, paired-key fallback
- `OrchestratorCacheTest.php` -- cache/expiry/backup key generation, TTL constants, cache-refresh parsing
- `RendererSliderTest.php` -- inline styles, data attributes for slider/carousel mode
- `RendererGalleryTest.php` -- gallery-mode container and all gallery-specific parameters
- `RendererButtonsTest.php` -- lightbox/fullscreen button render conditions, dual-expand class
- `RendererMosaicTest.php` -- mosaic wrapper positions, strip element, mosaic data attributes
- `RendererInfoBoxTest.php` -- info-box format strings, typography CSS props, halo effects
- `RendererLightboxAttrTest.php` -- all lightbox data attributes, interaction-lock suppression
- `CommunityValidationTest.php` -- all 8 field validators, URL helpers, tag normalization
- `DataProviderParseTest.php` -- URL extraction, metadata enrichment, video detection, title cleaning, camera formatting, individual photo meta; uses `tests/fixtures/google/album.html` (real recorded response)

**Playwright** (`tests/e2e/`) -- 152 tests

- `lightbox.spec.ts` -- slider click/button-only trigger, dual expand, gallery lightbox
- `fullscreen.spec.ts` -- fullscreen button presence, dual-expand interaction, close methods
- `slideshow.spec.ts` -- data attributes, play/pause button, auto-advance and manual-hold
- `gallery.spec.ts` -- data attributes (layout/columns/scrollable/rows), items, hover button, slideshow player open/navigate/close
- `navigation.spec.ts` -- arrow visibility, slide advance, keyboard, interaction-lock, download/link buttons
- `mosaic.spec.ts` -- wrapper position classes, strip presence, data attributes, thumbnail-to-slide sync
- `info-overlay.spec.ts` -- pagination text substitution ({item}/{items}/{album-title}), info-top box
- `admin.spec.ts` -- Guide page lazy previews, Parameters table rows, Community page structure
- `community.spec.ts` -- page structure, not-connected vs connected account state, browse AJAX, sort/search, publish form validation (6 rules), My entries empty state

### Infrastructure

- `playwright.config.ts` -- Chromium only, 1 worker, retries: 1, globalSetup validates all fixture pages, `PLAYWRIGHT_BASE_URL` supported
- `tests/fixtures/google/album.html` -- recorded Google Photos album response (1 MB); used by DataProviderParseTest
- `tests/Live/DataProviderLiveTest.php` -- 5 live smoke tests; run manually to detect Google format changes
- `tests/e2e/global-setup.ts` -- validates 6 fixture pages (lightbox, slideshow, gallery, mosaic, info, feature)
- `tests/e2e/README.md` -- documents all required fixture pages, shortcode order, and e2e environment variables
- PHPUnit bootstrap stubs all WordPress functions so unit tests run without WordPress
- Fixture pages (all require `mode="slider"` for slider-mode features): slideshow-fixture, mosaic-fixture, feature-fixture, info-fixture; gallery-fixture uses default gallery mode
- Admin/community tests use two WP users by default: `dev`/`test123` (connected to community), `testuser-noc`/`testpass123` (never connected). Override with `JZSA_E2E_*` environment variables.

### Verification findings from branch review

Compared `feature/community-lightbox-tests` against `feature/community-and-lightbox`. The test branch adds PHPUnit, Playwright, fixtures, `test.sh`, and documentation with only small production-code changes.

Local verification:

```bash
./test.sh --unit  # passed: 378 tests, 552 assertions
./test.sh --e2e   # passed with 151 passed, 1 flaky before stability fixes
```

Issues found and addressed:

- `PLAYWRIGHT_BASE_URL` was only used by `global-setup.ts`; Playwright itself still hardcoded `http://127.0.0.1:8080`. This could validate one site and test another.
- `tests/e2e/README.md` documented only `lightbox-fixture`, while `global-setup.ts` required six fixture pages.
- `npm test` exited with "no test specified" despite Playwright being installed.
- Admin/community specs duplicated login logic and hardcoded users directly in spec files.
- One community e2e test was flaky on first login navigation (`page.waitForURL` timed out once, retry passed).

Remaining risks:

- E2E still depends on a prepared local WordPress database, fixture pages, and connected/disconnected account state.
- Community e2e still exercises the real community/backend state unless a mock layer is added.
- Frontend e2e currently runs Chromium only.
- Some tests assert DOM/data attributes rather than fully observable behavior. That is useful for cheap regression coverage, but not a substitute for integration coverage of the complete shortcode path.

---

## Completed plan

### Unit tests now covered (PHPUnit)

| File | What it covers |
|---|---|
| `RendererSliderTest.php` | Every slider/carousel parameter maps to the correct `data-*` attribute or CSS custom property |
| `RendererGalleryTest.php` | Every gallery-specific parameter (`gallery-columns`, `gallery-layout`, `gallery-rows`, etc.) |
| `RendererButtonsTest.php` | Which buttons render under which conditions (fullscreen, lightbox, download, link, dual-expand class) |
| `RendererMosaicTest.php` | Mosaic parameters (`mosaic-position`, `mosaic-count`, `fullscreen-mosaic-layout`, etc.) |
| `RendererInfoBoxTest.php` | All `info-*` parameters, per-box halo overrides, font/color/align inheritance |
| `OrchestratorCacheTest.php` | Cache key generation (MD5 of URL), TTL edge cases, paired-key additional coverage |
| `CommunityValidationTest.php` | All 8 field validators: title (3-120), tags (max 5, 2-30 chars), display name (3+ letters), URLs, description length |

The renderer tests are the highest-value target. With 80+ parameters each mapping to a `data-*` attribute, parametrized PHPUnit tests can cover the full surface cheaply. A renderer bug that misconfigures a `data-lightbox-max-width` attribute would never show up in the current test suite.

### E2E tests now covered (Playwright)

| File | What it covers | Fixture page slug |
|---|---|---|
| `fullscreen.spec.ts` | button presence/absence, dual-expand interaction, lightbox close methods | `lightbox-fixture` |
| `gallery.spec.ts` | Grid and justified layouts, opening the slideshow player, navigating within it, close | `gallery-fixture` |
| `slideshow.spec.ts` | Auto-advance, play/pause button, manual mode hold | `slideshow-fixture` |
| `navigation.spec.ts` | Arrow buttons, keyboard arrows, interaction-lock, download/link buttons | `feature-fixture` |
| `mosaic.spec.ts` | Clicking a mosaic thumbnail advances the main slider, all four positions | `mosaic-fixture` |
| `info-overlay.spec.ts` | `{item}`, `{items}`, `{album-title}` substitution visible in rendered text | `info-fixture` |
| `community.spec.ts` (done) | Browse list loads, search/filter, connect flow, publish form validation | WordPress admin URL |
| `admin.spec.ts` (done) | Guide page loads previews, Parameters page renders the table | WordPress admin URL |

### Fixture pages needed

Each e2e spec file lists its own `FIXTURE_URL` constant at the top. `globalSetup` verifies all required fixture pages before any spec runs.

| Slug | Shortcodes | Purpose |
|---|---|---|
| `lightbox-fixture` | 5 (exists) | lightbox tests |
| `gallery-fixture` | 5: grid, justified, scrollable, paginated (gallery-rows), click-to-lightbox gallery | gallery interaction tests |
| `slideshow-fixture` | 3: auto delay=1, manual, disabled (control) | slideshow tests; use `slideshow-delay="1"` so tests don't wait 5s |
| `mosaic-fixture` | 4: bottom, top, left, right | mosaic strip tests |
| `info-fixture` | 3: various info-top/info-bottom format strings | placeholder rendering tests |
| `feature-fixture` | 3-4: navigation, download, link, interaction-lock | feature flag tests |

Community and admin tests use WordPress admin URLs directly, no shortcode fixture page needed.

---

## Next coverage plan

### Phase 1 -- stability and CI readiness

1. Seed or generate e2e fixture pages/users automatically so the suite does not depend on a hand-prepared database.
2. Add a deterministic mock layer for community API responses.
3. Split e2e into smoke vs full groups so CI can block on fast deterministic tests and run live smoke tests separately.
4. Add Firefox/WebKit projects after the deterministic path is stable.

### Phase 2 -- high-value missing unit/integration coverage

1. Community AJAX handlers with mocked `wp_remote_get` / `wp_remote_post`: publish success/failure, update, delete, delete account, browse, rating, display name/url update, auth/connect errors.
2. Real WordPress shortcode integration tests: `do_shortcode` through orchestrator/cache/provider/renderer together.
3. Security/escaping cases for shortcode attrs, album titles, descriptions, info strings, URLs, and community fields.
4. Error states: invalid Google link, empty album, Google format failure, HTTP failure, deprecated short links, stale backup cache fallback.

### Phase 3 -- frontend behavior coverage

1. Video/Plyr controls and mixed image/video albums.
2. Download warning threshold and download/link button interactions.
3. EXIF/photo-meta lazy AJAX behavior and chunk loading/retry paths.
4. Lightbox accessibility: focus handling, Escape behavior, tab flow, ARIA labels, keyboard-only navigation.
5. Responsive/mobile gallery behavior and mobile button display modes.

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
      - run: npx playwright install --with-deps chromium
      - run: npx playwright test
```

PHPUnit runs in under 10 seconds. Playwright for the current 17 tests runs in under 20 seconds. Both should block merging if they fail.
