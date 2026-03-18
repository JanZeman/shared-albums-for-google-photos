# Video Feature Alignment Plan

## Decision Log

- **Plyr**: Adopted as 3rd-party video player (v3.7.8, MIT license). Bundled locally in `assets/vendor/plyr/`. Provides consistent cross-browser controls, replacing native `<video>` chrome.
- **`preload="none"`**: Chosen over `preload="metadata"` for fastest page load. Duration and other metadata are fetched asynchronously after page idle (see Preloading Strategy below).
- **Terminology**: The Swiper-based single/slideshow mode is called "slider" (not "player").

---

## Completed Steps

### Step 1 — Extract shared `buildVideoHtml()` helper ✅

Created one function that returns the video DOM fragment for any context.
`buildSlidesHtml()`, `buildUniformGallery()`, and `renderJustifiedRows()` all call it.

### Step 2 — Gallery: use `buildVideoHtml()` in `buildUniformGallery()` ✅

Gallery uniform layout now uses the shared wrapper+video structure.

### Step 3 — Gallery: use `buildVideoHtml()` in `renderJustifiedRows()` ✅

Gallery justified layout now uses the shared wrapper+video structure.

### Step 4 — CSS: video wrapper+player fit inside gallery items ✅

`.jzsa-video-wrapper` and `.jzsa-video-player` work inside `.jzsa-gallery-item-video`
for both uniform and justified layouts. Debug backgrounds (green/blue) and red border active.

### Step 5 — Update gallery click guards for new DOM structure ✅

`isGalleryVideoTarget()` and `isGalleryVideoInteractionTarget()` updated to match
`.jzsa-gallery-item-video .jzsa-video-wrapper`.

### Step 6 — Add poster attribute to gallery videos ✅

`buildVideoHtml()` accepts a `poster` option. Currently using a hardcoded orange
placeholder (`placehold.co`) for debug — to be replaced with real `photo.preview` URL.

### Step 7–9 — Plyr replaces badge/native controls ✅ (approach changed)

**Original plan** (steps 7–9): custom play badge overlay + hide native controls + wire badge click handler.
**Actual approach**: Adopted Plyr, which handles all of this out of the box — large play button overlay, controls bar, click-to-play. Steps 7–9 are superseded.

- Plyr initialized via `initPlyrInContainer()` / destroyed via `destroyPlyrInContainer()`
- SVG sprite icons served locally (`assets/vendor/plyr/plyr.svg`)
- Plyr enqueued in PHP (`includes/class-orchestrator.php`)
- z-index layering: Plyr controls at z-index 15, above video wrapper at z-index 11

---

## Remaining Steps

### Step 10 — Background metadata preloading (Tier 1)

After page load + idle, asynchronously fetch metadata (duration, dimensions) for all
videos on the page using a hidden `<video>` element. Update Plyr UI with duration as
results arrive. One video at a time to avoid network contention.

Applies to: all modes (slider, carousel, gallery).

### Step 11 — Adjacent video preloading for slider/carousel (Tier 2)

When a slide settles, fully preload videos on adjacent slides (next 1–2).
Use `preload="auto"` or `fetch()` to prime the browser cache so playback is instant
when the user navigates. Only applies to slider/carousel — gallery has too many videos.

Priority order:
1. Current slide's video — full preload immediately
2. Adjacent slides (prev/next) — full preload after idle
3. Everything else — metadata only (Tier 1)

### Step 12 — Pause other videos on play

Extract `pauseAllVideos()` from `setupVideoHandling()` into a standalone helper.
Call it when any video starts playing (gallery or slider).
Verify: playing one video pauses any other playing video across the page.

### Step 13 — Gallery: pause videos on page change

When gallery pagination changes (re-render), pause any playing video before destroying DOM.
Verify: navigate gallery page while video plays → video stops, no orphan audio.

### Step 14 — Clean up dead CSS and classes

Remove `jzsa-gallery-video-thumb` class and its CSS (replaced by shared structure).
Remove any now-unused gallery-specific video CSS.
Verify: no visual change, no console errors.

### Step 15 — Remove debug styling

Remove all temporary debug visuals (only after user confirms all behavior is correct):
- Green background on `.jzsa-video-wrapper` (CSS)
- Blue background applied via JS after Plyr init
- Red 2px border on `.jzsa-video-player` (CSS)
- Orange "POSTER" placeholder image in `buildVideoHtml()` (JS)
- Hotpink background on `.plyr__control--overlaid` (CSS)
- 16px padding on `.jzsa-video-wrapper` (CSS)
- Console.log debug messages in `initPlyrInContainer()` (JS)

### Step 16 — Plyr accent color customization

Replace default Plyr blue with project-appropriate color via CSS custom property
(`--plyr-color-main`).

---

## Preloading Strategy

```
Page load:  preload="none" on all <video> elements (zero network cost)
     │
     ▼
Page idle:  6 parallel workers, each processes one video at a time:
     │        1. Fetch metadata (duration) → update Plyr UI + duration label
     │        2. Buffer full video via probe <video> → label turns green
     │        3. Move to next video
     │
     ▼
Order:      Slider/carousel adjacent slides first (by distance),
            then all remaining videos in DOM order (top to bottom)
```

---

## Future Work

### URL expiry handling

Google Photos serves video (and image) URLs as time-limited signed URLs
(~1 hour lifetime). If a user leaves a tab open and returns later, all
video URLs are stale and playback fails. A proper fix would detect expiry
and re-fetch fresh URLs from the Google Photos API. This affects images
too but is less visible since they are already rendered.

---

## Files involved

- `assets/js/swiper-init.js` — all JS changes
- `assets/css/swiper-style.css` — CSS adjustments
- `includes/class-orchestrator.php` — Plyr asset enqueuing
- `assets/vendor/plyr/` — Plyr 3.7.8 (plyr.min.js, plyr.css, plyr.svg)
