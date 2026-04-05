# Photo Metadata - Current Implementation

## Goal
Extract per-photo metadata from Google Photos HTML and surface it through the
plugin's info overlay system without using private Google APIs.

## Status - 2026-04-05
The feature is implemented.

What remains is optional follow-up work such as additional placeholders or more
advanced overlap handling. The old 6-box plan is no longer the current model.

## Current shipped model

### Wave 1 - metadata available during initial album fetch
`includes/class-data-provider.php` extracts metadata directly from the album HTML
that is already fetched for the album:

- media id
- timestamp
- width
- height
- filesize
- filename, when Google still exposes it in the album HTML
- EXIF fields, when Google still exposes them in the album HTML

`includes/class-orchestrator.php` passes these fields through into the frontend
photo objects used by slider, carousel, and gallery mode.

### Wave 2 - background metadata enrichment
When a visible format string uses any of these placeholders:

- `{filename}`
- `{name}`
- `{camera}`
- `{aperture}`
- `{shutter}`
- `{focal}`
- `{iso}`

the frontend triggers background metadata enrichment:

- `assets/js/swiper-init.js` runs a 3-worker queue
- it calls the `jzsa_fetch_photo_meta` AJAX endpoint
- PHP fetches the individual Google Photos page when needed
- EXIF is parsed from that page
- filename is resolved from Google media response headers
- results are cached in PHP transients and JS memory

This means filename/EXIF placeholders may appear with a short delay the first
time, then render immediately from cache on later visits.

### Cache hydration on later page loads
Before rendering the album, `includes/class-orchestrator.php` merges cached
per-photo metadata back into the album items for the visible entries. This lets
previously fetched filename/EXIF values render immediately on initial page load
without waiting for Wave 2 again.

## Current overlay and placeholder model

### Overlay zones
The current overlay system is a 3-zone model, not the old 6-box corner model.

Inline zones:

- `info-bottom`
- `info-top`
- `info-top-secondary`
- `gallery-page-bottom` (gallery pagination bar only)

Fullscreen siblings:

- `fullscreen-info-bottom`
- `fullscreen-info-top`
- `fullscreen-info-top-secondary`

Typography controls:

- `info-font-size`
- `info-font-family`
- `fullscreen-info-font-size`
- `fullscreen-info-font-family`

`gallery-page-bottom` is separate from the per-photo overlay stack and is used
only in paginated gallery rows.

### Mode behavior

- Slider: `info-top` and `info-top-secondary` render as container-level overlay
  boxes. `info-bottom` is rendered through Swiper pagination text.
- Carousel inline: overlay boxes are rendered per visible tile. Container-level
  overlay stacks are hidden in non-fullscreen carousel mode.
- Carousel fullscreen: normal single-slide overlay rules apply again.
- Gallery: each tile renders its own top and bottom info boxes. When paginated
  gallery rows are enabled, `gallery-page-bottom` renders in the page navigation
  bar and supports `{page}` / `{pages}`.

## Documented placeholders

Immediate / page-state placeholders:

- `{item}`
- `{items}`
- `{page}`
- `{pages}`
- `{album-title}`

Metadata available from initial album fetch:

- `{date}`
- `{dimensions}`
- `{megapixels}`
- `{filesize}`

Metadata that may appear with delay:

- `{filename}`
- `{name}`
- `{camera}`
- `{aperture}`
- `{shutter}`
- `{focal}`
- `{iso}`

## Hidden or internal behavior

- `{album-name}` is accepted as an alias for `{album-title}` but is not
  documented in the UI.
- `{author}` currently exists in JS as a placeholder key but resolves to an
  empty string and is not documented.

## Backward compatibility that still exists

- `show-counter` and `show-title` still derive the default `info-bottom` value
  when `info-bottom` is not explicitly set.
- `info-top-1` and `info-top-2` are still accepted as legacy aliases for
  `info-top` and `info-top-secondary`.
- `fullscreen-info-top-1` and `fullscreen-info-top-2` are still accepted as
  legacy aliases for `fullscreen-info-top` and
  `fullscreen-info-top-secondary`.
- `slideshow="enabled"` still maps to `manual`.

## Backward compatibility that does not currently exist

- `show-name`
- `fullscreen-show-name`

These names appeared in older planning notes, but there is no implemented parser
path for them in the current code. They should be treated as unsupported unless
explicitly reintroduced.

## Key files

- `includes/class-data-provider.php`
- `includes/class-orchestrator.php`
- `includes/class-renderer.php`
- `assets/js/swiper-init.js`
- `assets/css/swiper-style.css`
- `includes/class-settings-page.php`
- `assets/js/admin-settings.js`
- `assets/css/admin-settings.css`

## Remaining future work

- Additional placeholders such as `{location}`, `{lens}`, or `{description}`
- Decide whether `{author}` should be properly implemented or removed
- More advanced collision / overlap handling if real-world layouts need it

## Summary
The metadata feature is implemented, shipped, and documented in the Settings
page. What was out of date was `photo-metadata.md`, which previously described
an older 6-box "token" plan instead of the current 3-zone "placeholder" system.
