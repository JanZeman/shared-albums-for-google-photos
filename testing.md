# Testing

## Current state

Both layers are operational. Run everything with:

```bash
./test.sh           # PHPUnit + Playwright
./test.sh --unit    # PHPUnit only
./test.sh --e2e     # Playwright only
```

### What exists

**PHPUnit** (`tests/Unit/`) -- 297 tests

- `OrchestratorToggleModeTest.php` -- toggle mode resolution, paired-key fallback
- `OrchestratorCacheTest.php` -- cache/expiry/backup key generation, TTL constants, cache-refresh parsing
- `RendererSliderTest.php` -- inline styles, data attributes for slider/carousel mode
- `RendererGalleryTest.php` -- gallery-mode container and all gallery-specific parameters
- `RendererButtonsTest.php` -- lightbox/fullscreen button render conditions, dual-expand class
- `RendererMosaicTest.php` -- mosaic wrapper positions, strip element, mosaic data attributes
- `RendererInfoBoxTest.php` -- info-box format strings, typography CSS props, halo effects
- `RendererLightboxAttrTest.php` -- all lightbox data attributes, interaction-lock suppression
- `CommunityValidationTest.php` -- all 8 field validators, URL helpers, tag normalization

**Playwright** (`tests/e2e/`) -- 135 tests

- `lightbox.spec.ts` -- slider click/button-only trigger, dual expand, gallery lightbox
- `fullscreen.spec.ts` -- fullscreen button presence, dual-expand interaction, close methods
- `slideshow.spec.ts` -- data attributes, play/pause button, auto-advance and manual-hold
- `gallery.spec.ts` -- data attributes (layout/columns/scrollable/rows), items, hover button, slideshow player open/navigate/close
- `navigation.spec.ts` -- arrow visibility, slide advance, keyboard, interaction-lock, download/link buttons
- `mosaic.spec.ts` -- wrapper position classes, strip presence, data attributes, thumbnail-to-slide sync
- `info-overlay.spec.ts` -- pagination text substitution ({item}/{items}/{album-title}), info-top box
- `admin.spec.ts` -- Guide page lazy previews, Parameters table rows, Community page structure
- `community.spec.ts` -- page structure, account disconnected state, browse AJAX, sort/search UI

### Infrastructure

- `playwright.config.ts` -- Chromium only, 1 worker, retries: 1, globalSetup validates all fixture pages
- `tests/e2e/global-setup.ts` -- validates 6 fixture pages (lightbox, slideshow, gallery, mosaic, info, feature)
- `tests/e2e/README.md` -- documents the shortcodes required on the lightbox fixture page
- PHPUnit bootstrap stubs all WordPress functions so unit tests run without WordPress
- Fixture pages (all require `mode="slider"` for slider-mode features): slideshow-fixture, mosaic-fixture, feature-fixture, info-fixture; gallery-fixture uses default gallery mode
- Admin/community tests use WordPress admin credentials: user `dev`, password `test123` (local dev only)

---

## Full plan

### Unit tests to write (PHPUnit)

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

### E2E tests to write (Playwright)

| File | What it covers | Fixture page slug |
|---|---|---|
| `fullscreen.spec.ts` | button-only / click / double-click triggers, native fullscreen API, close methods (Esc, click) | `fullscreen-fixture` |
| `gallery.spec.ts` | Grid and justified layouts, opening the slideshow player, navigating within it, close | `gallery-fixture` |
| `slideshow.spec.ts` | Auto-advance, play/pause button, autoresume after interaction | `slideshow-fixture` |
| `navigation.spec.ts` | Arrow buttons, keyboard arrows, mousewheel, interaction-lock disables all of these | `feature-fixture` |
| `mosaic.spec.ts` | Clicking a mosaic thumbnail advances the main slider, all four positions | `mosaic-fixture` |
| `info-overlay.spec.ts` | `{item}`, `{items}`, `{album-title}` substitution visible in rendered text | `info-fixture` |
| `community.spec.ts` (done) | Browse list loads, search/filter, connect flow, publish form validation | WordPress admin URL |
| `admin.spec.ts` (done) | Guide page loads previews, Parameters page renders the table | WordPress admin URL |

### Fixture pages needed

Each e2e spec file lists its own `FIXTURE_URL` constant at the top. The `globalSetup` in `global-setup.ts` must be extended to verify each fixture exists before that spec runs (or each spec file gets its own setup check).

| Slug | Shortcodes | Purpose |
|---|---|---|
| `lightbox-fixture` | 5 (exists) | lightbox tests |
| `fullscreen-fixture` | 4: button-only, click, double-click, disabled (control) | fullscreen tests |
| `gallery-fixture` | 4: grid, justified, scrollable, paginated (gallery-rows) | gallery interaction tests |
| `slideshow-fixture` | 3: auto delay=1, manual, disabled (control) | slideshow tests; use `slideshow-delay="1"` so tests don't wait 5s |
| `mosaic-fixture` | 4: bottom, top, left, right | mosaic strip tests |
| `info-fixture` | 3: various info-top/info-bottom format strings | placeholder rendering tests |
| `feature-fixture` | 3-4: navigation, download, link, interaction-lock | feature flag tests |

Community and admin tests use WordPress admin URLs directly, no shortcode fixture page needed.

---

## Priority order

### Phase 1 -- high value, low friction

1. `RendererSliderTest.php` and `RendererGalleryTest.php` -- pure PHP, highest parameter density
2. `CommunityValidationTest.php` -- pure PHP, guards brittle validation rules
3. `fullscreen.spec.ts` -- mirrors lightbox structure, reuses `waitForAlbum`, just different triggers

### Phase 2 -- medium friction

1. `gallery.spec.ts` -- gallery player open/close, navigation inside the player
2. `slideshow.spec.ts` -- use `delay="1"` shortcode attribute to keep tests fast
3. `navigation.spec.ts` -- arrows, keyboard, interaction-lock

### Phase 3 -- higher effort

1. `RendererButtonsTest.php`, `RendererMosaicTest.php`, `RendererInfoBoxTest.php`
2. `mosaic.spec.ts` -- thumbnail-to-slide sync assertions
3. `info-overlay.spec.ts` -- placeholder substitution in rendered DOM
4. `community.spec.ts` -- most complex; needs a test account or mocked HTTP responses
5. `admin.spec.ts` -- requires WordPress admin credentials in the test config

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
