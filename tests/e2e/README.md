# E2E Test Fixture

Playwright tests depend on a WordPress page with slug **lightbox-fixture** that contains five
shortcodes in a specific order. The `globalSetup` script verifies this page exists before any
test runs, so a missing or misconfigured fixture produces a clear error rather than cryptic
timeouts.

## Creating the fixture page

1. Go to **Pages > Add New** in the WordPress admin.
2. Set the slug (permalink) to exactly `lightbox-fixture`.
3. Set status to **Published**.
4. Add the five shortcodes below as the page content, in this exact order.

Replace `YOUR_LINK` with any shared Google Photos album URL (the same link works for all five).
Each album needs at least 2 photos for the gallery interaction tests.

```
[jzsa-album link="YOUR_LINK" lightbox-toggle="click" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" lightbox-toggle="button-only" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" lightbox-toggle="button-only" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="button-only"]
```

The tests address these shortcodes by index (0-4), so the order matters.

| Index | Mode    | lightbox-toggle | fullscreen-toggle | Notes       |
|-------|---------|-----------------|-------------------|-------------|
| 0     | slider  | click           | disabled          |             |
| 1     | slider  | button-only     | disabled          |             |
| 2     | slider  | button-only     | button-only       | dual expand |
| 3     | gallery | button-only     | disabled          |             |
| 4     | gallery | button-only     | button-only       | dual expand |

## Migrating an existing fixture page

If the page already exists (e.g., previously accessed as `/?page_id=15`):

- Edit the page in WordPress admin and change the slug to `lightbox-fixture`.
- No other changes needed.

## Adding new tests that need different shortcode configurations

If a new test requires a shortcode combination not covered by the five above, either:

- Add a sixth shortcode to the fixture page and document it in this table, or
- Create a second fixture page (e.g., `lightbox-fixture-2`) and add a corresponding
  `globalSetup` check and `FIXTURE_URL` constant in the new spec file.
