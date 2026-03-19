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

### Step 4 — CSS: video wrapper fit inside gallery items ✅

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

## Remaining Steps — All Complete

### Step 10 — Background metadata preloading (Tier 1) ✅

After page load + idle, asynchronously fetch metadata (duration, dimensions) for all
videos on the page using a hidden `<video>` element. Update Plyr UI with duration as
results arrive. One video at a time to avoid network contention.

Applies to: all modes (slider, carousel, gallery).

### Step 11 — Adjacent video preloading for slider/carousel (Tier 2) ✅

When a slide settles, fully preload videos on adjacent slides (next 1–2).
Use `preload="auto"` or `fetch()` to prime the browser cache so playback is instant
when the user navigates. Only applies to slider/carousel — gallery has too many videos.

Priority order:
1. Current slide's video — full preload immediately
2. Adjacent slides (prev/next) — full preload after idle
3. Everything else — metadata only (Tier 1)

### Step 12 — Pause other videos on play ✅

Extract `pauseAllVideos()` from `setupVideoHandling()` into a standalone helper.
Call it when any video starts playing (gallery or slider).
Verify: playing one video pauses any other playing video across the page.

### Step 13 — Gallery: pause videos on page change ✅

When gallery pagination changes (re-render), pause any playing video before destroying DOM.
Added pause loop at top of `renderCurrentGalleryPage()`.

### Step 14 — Clean up dead CSS and classes ✅

Removed `jzsa-gallery-video-thumb` class and its CSS (replaced by shared structure).

### Step 15 — Remove debug styling ✅

All debug visuals removed. All `console.log/warn/error` calls commented out
(except `jzsaDebug()` which is behind the `JZSA_DEBUG` flag).

### Step 16 — Plyr accent color customization ✅

Plyr accent color set via `--plyr-color-main: var(--jzsa-accent-color, #00b2ff)`.

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

## Release Checklist — Manual Testing Plan

Use an album that contains a mix of photos and videos.
Shortcode examples for each mode:

```
[jzsa-album link="YOUR_ALBUM" mode="slider"]
[jzsa-album link="YOUR_ALBUM" mode="carousel"]
[jzsa-album link="YOUR_ALBUM" mode="gallery" gallery-layout="uniform"]
[jzsa-album link="YOUR_ALBUM" mode="gallery" gallery-layout="justified"]
```

### 1. Basic playback — all modes

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1.1 | Slider: play video | Open slider, navigate to a video slide, tap play | Video plays with Plyr controls, blue accent play button disappears |
| 1.2 | Slider: pause/resume | Tap pause in Plyr controls, then play again | Playback pauses and resumes; blue play button reappears on pause |
| 1.3 | Carousel: play video | Open carousel, navigate to a video, tap play | Video plays inline in the carousel cell |
| 1.4 | Gallery uniform: play video | Open uniform gallery, tap play on a video thumbnail | Video plays inside its grid cell |
| 1.5 | Gallery justified: play video | Open justified gallery, tap play on a video thumbnail | Video plays inside its justified cell |
| 1.6 | Video completes | Let a short video play to the end | Plyr controls reappear, blue play button returns |

### 2. Fullscreen

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 2.1 | Enter fullscreen, play video | Tap fullscreen button, navigate to video, play | Video plays in fullscreen with scaled-up controls |
| 2.2 | Play video, then enter fullscreen | Start video in inline mode, tap fullscreen | Video continues playing in fullscreen without restart |
| 2.3 | Exit fullscreen while playing | Play video in fullscreen, press Esc / tap exit | Video continues or pauses gracefully, no orphan audio |
| 2.4 | Pseudo-fullscreen (iPhone) | On iPhone, tap fullscreen button | Pseudo-fullscreen activates, video plays correctly |

### 3. Multi-video interactions

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 3.1 | Only one video plays at a time | Play video A, then play video B (same or different album) | Video A pauses automatically when B starts |
| 3.2 | Gallery page change pauses video | Play a video in gallery, navigate to next page | Playing video stops, no audio continues after page change |
| 3.3 | Slider navigation pauses video | Play a video in slider, swipe to next slide | Video pauses when leaving the slide |

### 4. URL expiry & recovery

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 4.1 | Fresh playback | Load page, immediately play a video | Plays without delay |
| 4.2 | Expired URL recovery | Load page, wait 5+ minutes, then play a video | Plugin detects stale URL, fetches fresh URL from Google, plays after brief delay |
| 4.3 | Refresh button | If playback fails, check if `[jzsa-refresh]` button appears/works | Fresh URLs are fetched, video becomes playable |

### 5. Preloading

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 5.1 | No network on initial load | Open DevTools Network tab, load page | No video requests fire immediately (`preload="none"`) |
| 5.2 | Metadata preloading | Wait a few seconds after page load, check Network tab | Metadata requests appear for videos (small byte ranges) |
| 5.3 | Adjacent preloading (slider) | In slider mode, wait on a slide next to a video | Adjacent video's full data starts buffering |
| 5.4 | Instant playback after preload | Wait for preload to finish, then play the video | Playback starts instantly (no loading spinner or delay) |

### 6. Mobile & touch

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 6.1 | iOS Safari | Open test page on iPhone, play a video | Plyr controls render, video plays inline (not native fullscreen) |
| 6.2 | Android Chrome | Open test page on Android, play a video | Plyr controls render, video plays inline |
| 6.3 | Touch controls | Tap play, tap pause, scrub progress bar on mobile | All touch interactions work smoothly |
| 6.4 | Pinch-to-zoom on photo, then navigate to video | Zoom into a photo, swipe to video slide | Zoom resets, video plays normally |

### 7. Edge cases

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 7.1 | Album with only videos | Create/use an album containing no photos | Plugin renders all items as videos, no errors |
| 7.2 | Album with no videos | Use an album with only photos | No video-related UI appears, no console errors |
| 7.3 | Video as first item | Ensure a video is the first item in the album | Play button visible on initial load, plays correctly |
| 7.4 | Video as last item | Navigate to the last item (a video) | Plays correctly, loop wraps to first item after |
| 7.5 | Very short video (<3s) | Play a very short clip | Plays and ends cleanly, play button returns |
| 7.6 | Long video (>5 min) | Play a longer video, scrub forward | Seeking works, no buffering issues |

### 8. Assets & performance

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 8.1 | No 404s | Open DevTools Network tab, load page with videos | No failed requests for plyr.min.js, plyr.css, plyr.svg |
| 8.2 | No console errors | Open DevTools Console, interact with videos | No errors (console.log calls are commented out, only `jzsaDebug` behind flag) |
| 8.3 | Page with many videos | Load a gallery with 20+ videos | Page loads fast, no jank, preloading is gradual |
| 8.4 | Plyr CSS isolation | Check that Plyr styles don't leak to non-video elements | No visual regressions on photos or gallery layout |

### 9. Controls appearance

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 9.1 | Play button accent color | Check blue play button matches `--jzsa-accent-color` | Consistent accent color across all modes |
| 9.2 | Loading spinner | Tap play on an un-buffered video | Blue circle pulses (smaller-normal-smaller), spinner appears inside |
| 9.3 | Fullscreen control scaling | Enter fullscreen, check all controls | All controls scaled up by `--jzsa-controls-fs-scale` (1.5x) |
| 9.4 | Control opacity on hover | Hover over nav arrows, fullscreen, download buttons | Opacity transitions from 0.8 to 0.9 on hover |
| 9.5 | No Plyr control bar leak | Check video thumbnails in gallery | No 1px line visible at bottom edge of video cells |

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
