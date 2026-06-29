# E2E Test Fixtures

Playwright tests depend on published WordPress pages with fixed slugs and
shortcodes in a specific order. `global-setup.ts` seeds those fixtures by
default, then verifies the required pages and login users before any test runs.

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
JZSA_E2E_SKIP_SETUP=0
```

The connected user must already have a valid community JWT in user meta. The
disconnected user should be an administrator with no community JWT.

## Automated Setup

Playwright runs this setup automatically through Docker Compose before the e2e
suite starts:

```bash
docker compose exec wordpress php \
  /var/www/html/wp-content/plugins/janzeman-shared-albums-for-google-photos/tests/e2e/setup-fixtures.php
```

The setup script upserts all nine published fixture pages, ensures the default
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

When testing against an already prepared remote or non-Docker WordPress site,
set `JZSA_E2E_SKIP_SETUP=1`. Global setup will skip Docker seeding but still
validate the fixture pages and the configured login credentials.

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

[jzsa-album link="YOUR_LINK" mode="slider" fullscreen-toggle="button-only" fullscreen-max-width="320" fullscreen-max-height="240"]
```

### `slideshow-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" slideshow="auto" slideshow-delay="1"]

[jzsa-album link="YOUR_LINK" mode="slider" slideshow="manual"]

[jzsa-album link="YOUR_LINK" mode="slider" slideshow="disabled"]
```

### `video-fixture`

`setup-fixtures.php` writes deterministic static video-album markup for this
page. The final one-photo shortcode is present only to enqueue the frontend
assets the static fixture needs.

```text
<div id="jzsa-e2e-video-inline" class="jzsa-album swiper jzsa-loader-pending" ...></div>

<div id="jzsa-e2e-video-lightbox" class="jzsa-album swiper jzsa-loader-pending" ...></div>

[jzsa-album link="YOUR_LINK" mode="slider" limit="1"]
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

[jzsa-album link="YOUR_LINK" mode="slider" info-bottom="{item}" info-top="{filename}" info-top-secondary="{camera} {description}"]
```

### `viewer-fixture`

Eight sliders that mirror Guide samples 30 through 37 in `viewer.md`. Keep this
page and the guide-sample tests in the same order so the sample numbers stay
stable over time.

```text
[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="600" viewer-max-height="400"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="600" viewer-max-height="400" fullscreen-max-width="1200" fullscreen-max-height="800"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-image-fit="cover"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" fullscreen-image-fit="cover"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" image-fit="contain" background-color="rgba(128,0,64,0.7)" viewer-toggle="lightbox-button, fullscreen-button" lightbox-corner-radius="16" viewer-max-width="600" viewer-max-height="400" viewer-background-color="rgba(128,0,64,0.7)" lightbox-backdrop-color="rgba(0,128,64,0.7)"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-controls-color="#E63946" lightbox-controls-color="#00A878"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-button" fullscreen-slideshow="auto"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-slideshow="auto" lightbox-slideshow-delay="1" fullscreen-slideshow-delay="7-9"]
```

### `random-fixture`

Ten more varied sliders for stress testing. These are intentionally not tied to
the guide sample order.

```text
[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-double-click"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-button"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-double-click"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-double-click, fullscreen-button"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-mosaic="true" viewer-mosaic-count="4" viewer-mosaic-position="left" viewer-mosaic-layout="overlay" viewer-mosaic-gap="4"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="700" viewer-max-height="420" lightbox-max-width="500" fullscreen-max-width="1100" viewer-image-fit="cover" fullscreen-image-fit="contain" lightbox-controls-color="#00A878" fullscreen-controls-color="#2A9D8F"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-background-color="rgba(128,0,64,0.7)" lightbox-backdrop-color="rgba(0,128,64,0.7)" viewer-controls-color="#E63946" lightbox-controls-color="#00A878" fullscreen-controls-color="#2A9D8F"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-slideshow="auto" lightbox-slideshow-delay="2-4" fullscreen-slideshow-delay="9"]

[jzsa-album link="YOUR_LINK" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-show-navigation="false" viewer-info-top="Wild {item}" viewer-info-bottom="{item} / {items}" viewer-corner-radius="24" lightbox-corner-radius="8" fullscreen-corner-radius="0"]
```

### `feature-fixture`

```text
[jzsa-album link="YOUR_LINK" mode="slider" show-navigation="true"]

[jzsa-album link="YOUR_LINK" mode="slider" show-download-button="true" show-link-button="true"]

[jzsa-album link="YOUR_LINK" mode="slider" interaction-lock="true"]

[jzsa-album link="YOUR_LINK" mode="slider" show-navigation="false"]
```

## Notes

- Viewer Samples tests address `viewer-fixture` shortcodes by index, so order matters.
- `random-fixture` is freeform and may change when the stress coverage grows.
- Gallery albums initialize lazily when scrolled into view.
- A cold local run can fetch album data from Google Photos before WordPress cache
  is warm. Subsequent runs should be faster.
