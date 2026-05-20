# E2E Test Fixtures

Playwright tests depend on published WordPress pages with fixed slugs and
shortcodes in a specific order. `global-setup.ts` verifies the required pages
exist before any test runs, so missing fixtures fail early with a clear error.

Replace `YOUR_LINK` with a shared Google Photos album URL. The same link can be
used for all shortcodes. Use an album with at least 2 photos; gallery, mosaic,
slideshow, and navigation tests need multiple items.

## Environment

Default target:

```bash
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080
```

Default WordPress users:

```bash
JZSA_E2E_ADMIN_USER=dev
JZSA_E2E_ADMIN_PASS=test123
JZSA_E2E_CONNECTED_USER=dev
JZSA_E2E_CONNECTED_PASS=test123
JZSA_E2E_DISCONNECTED_USER=testuser-noc
JZSA_E2E_DISCONNECTED_PASS=testpass123
```

The connected user must already have a valid community JWT in user meta. The
disconnected user should be an administrator with no community JWT.

## Automated Setup

The fixture pages and default users can be created or refreshed without WP-CLI:

```bash
docker compose exec wordpress php \
  /var/www/html/wp-content/plugins/janzeman-shared-albums-for-google-photos/tests/e2e/setup-fixtures.php
```

The setup script upserts all six published fixture pages, ensures the default
admin and disconnected users exist, and removes community connection metadata
from the disconnected user. It preserves the connected user's existing community
JWT by default. To seed a connected JWT explicitly:

```bash
docker compose exec -e JZSA_E2E_CONNECTED_JWT=your-jwt wordpress php \
  /var/www/html/wp-content/plugins/janzeman-shared-albums-for-google-photos/tests/e2e/setup-fixtures.php
```

Optional setup variables:

```bash
JZSA_E2E_ALBUM_URL=https://photos.google.com/share/...
JZSA_E2E_ADMIN_USER=dev
JZSA_E2E_ADMIN_PASS=test123
JZSA_E2E_DISCONNECTED_USER=testuser-noc
JZSA_E2E_DISCONNECTED_PASS=testpass123
WP_ROOT=/var/www/html
```

## Browser Matrix

The default Playwright run executes the full suite in Chromium. Tests tagged
`@cross-browser` also run in Firefox and WebKit smoke projects. These smoke tests
cover browser-sensitive behavior such as fullscreen/lightbox interaction, mobile
gallery layout, downloads, and slideshow timing.

Install all required browsers before running the full e2e matrix:

```bash
npx playwright install chromium firefox webkit
```

## Fixture Pages

Create these published pages with exactly these slugs and shortcode order.

### `lightbox-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" lightbox-toggle="click" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" mode="slider" lightbox-toggle="button-only" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" mode="slider" lightbox-toggle="button-only" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="disabled"]

[jzsa-album link="YOUR_LINK" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="button-only"]
```

### `slideshow-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" slideshow="auto" slideshow-delay="1"]

[jzsa-album link="YOUR_LINK" mode="slider" slideshow="manual"]

[jzsa-album link="YOUR_LINK" mode="slider" slideshow="disabled"]
```

### `gallery-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="gallery" gallery-columns="3" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" gallery-layout="justified" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" gallery-scrollable="true" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" gallery-rows="2" fullscreen-toggle="button-only"]

[jzsa-album link="YOUR_LINK" mode="gallery" lightbox-toggle="click" fullscreen-toggle="disabled"]
```

### `mosaic-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" mosaic="true"]

[jzsa-album link="YOUR_LINK" mode="slider" mosaic="true" mosaic-position="left"]

[jzsa-album link="YOUR_LINK" mode="slider" mosaic="true" mosaic-position="top"]

[jzsa-album link="YOUR_LINK" mode="slider" mosaic="true" mosaic-position="bottom"]
```

The first mosaic shortcode intentionally omits `mosaic-position`; the integrated
shortcode parser currently defaults that value to `bottom`.

### `info-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" info-bottom="{item} / {items}"]

[jzsa-album link="YOUR_LINK" mode="slider" info-top="{album-title}"]

[jzsa-album link="YOUR_LINK" mode="slider" info-bottom="{item}" info-top="{album-title}"]
```

### `feature-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" show-navigation="true"]

[jzsa-album link="YOUR_LINK" mode="slider" show-download-button="true" show-link-button="true"]

[jzsa-album link="YOUR_LINK" mode="slider" interaction-lock="true"]

[jzsa-album link="YOUR_LINK" mode="slider" show-navigation="false"]
```

## Notes

- Tests address shortcodes by index, so order matters.
- Gallery albums initialize lazily when scrolled into view.
- A cold local run can fetch album data from Google Photos before WordPress cache
  is warm. Subsequent runs should be faster.
