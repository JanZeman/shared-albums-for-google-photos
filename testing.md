# Automated Testing Plan

## Assessment

Most bugs found during lightbox development were interaction bugs: wrong button shown,
wrong handler fired, wrong CSS state after close. These map well to browser-level E2E
tests. A smaller but useful category (shortcode parsing, config defaults) maps to PHP
unit tests that need no browser at all.

**Recommendation for now:** start with PHP unit tests only (low setup cost, immediate
value). Add Playwright E2E when a second developer joins or when v3 introduces major
new UI surface.

---

## Layer 1: PHP Unit Tests (PHPUnit)

### What they cover
- Shortcode attribute parsing and default resolution
- Config assembly logic (the class-orchestrator.php layer)
- HTML output structure from class-renderer.php

### Why useful
These tests would have caught the bug where `fullscreen-toggle` defaulted to
`button-only` even when `lightbox-toggle` was explicitly set, causing dual buttons
to appear unexpectedly.

### Setup
1. Add `phpunit/phpunit` to `composer.json` (dev dependency)
2. Add a `phpunit.xml` config pointing at a `tests/` directory
3. Bootstrap WordPress via `yoast/wp-test-utils` or a minimal stub
4. No browser, no running server needed — runs in CI in seconds

### Test cases to write

**Config defaults**
- `lightbox-toggle` set, `fullscreen-toggle` not set: config must have `fullscreen-toggle = disabled`
- `fullscreen-toggle` explicitly set alongside `lightbox-toggle`: both respected
- `interaction-lock="true"`: both lightbox and fullscreen report disabled regardless of other params

**HTML output assertions**
- Slider + lightbox only: rendered HTML contains `.swiper-button-lightbox`, does NOT contain `.swiper-button-fullscreen`
- Slider + dual expand: rendered HTML contains both buttons, container has class `jzsa-has-dual-expand`
- Carousel + lightbox only: rendered HTML does NOT contain `.swiper-button-lightbox` at container level (per-tile only)
- Gallery + lightbox only: `data-lightbox-toggle` attribute present, `data-fullscreen-toggle="disabled"`

**Attribute parsing edge cases**
- Invalid `lightbox-toggle` value falls back to `disabled`
- `lightbox-max-width` with non-numeric value is ignored
- `lightbox-corner-radius` negative value clamped to 0

### Effort estimate
Setup: 4-6 hours. Writing the test cases above: 3-4 hours. Total: roughly one day.

---

## Layer 2: E2E Tests (Playwright)

### What they cover
All 13 cases in `lightbox-manual-test.md`, plus regression checks for:
- Ghost button visibility after close
- Carousel layout restore after lightbox close
- Focus trap behavior
- Scroll position restore

### Why useful
These would have caught every bug found in this session automatically on each commit.

### Setup requirements
1. A WordPress instance accessible to Playwright (Docker is ideal, same setup already used)
2. A fixture WordPress page containing all test shortcodes with known album links
3. A cached album response (or a dedicated test album that never changes)
4. Playwright installed as a dev dependency (`npm install --save-dev @playwright/test`)
5. A `playwright.config.ts` pointing at the local WordPress URL

### Architecture
```
tests/
  e2e/
    fixtures/
      album-cache.json        # pre-cached album data so tests don't hit Google
    lightbox-slider.spec.ts
    lightbox-gallery.spec.ts
    lightbox-carousel.spec.ts
    lightbox-close.spec.ts
    lightbox-keyboard.spec.ts
    lightbox-dual-expand.spec.ts
```

### Example test (Playwright)
```typescript
test('gallery: no ghost button after lightbox close', async ({ page }) => {
  await page.goto('/test-fixtures/lightbox-gallery');
  const items = page.locator('.jzsa-gallery-item');

  // Open lightbox on first item
  await items.nth(0).hover();
  await items.nth(0).locator('.jzsa-gallery-thumb-fs-btn').first().click();
  await expect(page.locator('.jzsa-lightbox-backdrop')).toBeVisible();

  // Close via Esc
  await page.keyboard.press('Escape');
  await expect(page.locator('.jzsa-lightbox-backdrop')).not.toBeVisible();

  // No other item should show its button
  for (let i = 1; i < 9; i++) {
    await expect(items.nth(i).locator('.jzsa-gallery-thumb-fs-btn')).not.toBeVisible();
  }
});
```

### Album caching strategy
The biggest test fragility risk is depending on a live Google Photos URL. Two options:

**Option A (simpler):** Use a dedicated private test album that you control. If it ever
breaks tests, you know why.

**Option B (robust):** Intercept the album fetch in Playwright using `page.route()` to
return a fixture JSON response. Requires understanding the internal fetch format but
makes tests fully offline and deterministic.

### Effort estimate
- WordPress + Playwright scaffolding: 1-2 days
- Writing all 13 test cases: 1 day
- Album caching / fixture setup: half a day
- CI via GitHub Actions: half a day
- Total: roughly 3-4 days

### When to do it
Consider starting when any of these are true:
- A second developer contributes to the plugin
- A release introduces major new UI surface (e.g. v3)
- A regression ships to production that a test would have caught
- The manual test plan grows beyond 20 cases

---

## CI integration (both layers)

Once either layer exists, add a GitHub Actions workflow:

```yaml
# .github/workflows/test.yml
on: [push, pull_request]
jobs:
  php-unit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: vendor/bin/phpunit

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker compose up -d   # start WordPress
      - run: npm ci
      - run: npx playwright install --with-deps
      - run: npx playwright test
```

PHP unit tests run in under 10 seconds. Playwright tests for 13 cases run in under
2 minutes. Both can block merging a PR if they fail.
