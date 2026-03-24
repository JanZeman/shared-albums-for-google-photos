=== Shared Albums for Google Photos (by JanZeman) ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display publicly shared Google Photos albums with a modern, responsive Swiper-based gallery viewer.

== Description ==

Shared Albums for Google Photos (by JanZeman) allows you to easily display publicly shared Google Photos albums in your WordPress posts and pages using a simple shortcode. The plugin uses the modern Swiper library to provide a beautiful, touch-enabled gallery experience.

**Note:** This plugin is not affiliated with or endorsed by Google LLC. Google Photos™ is a trademark of Google LLC.

**IMPORTANT:** Short links (photos.app.goo.gl) might stop working in the future. While both full and short link formats work today, we strongly recommend using the full link format `https://photos.google.com/share/` to ensure your galleries continue working. The plugin will display a warning banner when short links are detected.

= Features =

* **Gallery Mode (Default)** - Display photos as a thumbnail gallery with uniform/justified layouts
* **Gallery Paging Or Scrolling** - Use `gallery-rows` with optional `gallery-scroll` to choose page controls or a single scrollable gallery
* **Modern Gallery Viewer** - Powered by Swiper 11, a modern mobile-friendly slider
* **Responsive Design** - Works perfectly on desktop, tablet, and mobile devices
* **Shortcode Playground** - Admin-only sandbox on the Settings page for experimenting with `[jzsa-album]` shortcodes and previews
* **Touch Gestures** - Swipe navigation on touch devices
* **Play/Pause Control** - Fullscreen play/pause button with spacebar keyboard shortcut
* **Fullscreen Navigation** - In fullscreen, single-click/tap navigates previous/next photos
* **Fullscreen Toggle Modes** - `full-screen-toggle` supports `button-only` (default), `single-click`, `double-click`, and `disabled`
* **Fullscreen Image Fit** - `full-screen-image-fit` supports `fit` (default), `contain`, and `cover`
* **Dual-Mode Slideshow** - Separate slideshow settings for normal and fullscreen modes
* **Zoom Support** - Pinch-to-zoom on touch devices
* **Keyboard Navigation** - Use arrow keys to navigate, spacebar to play/pause in fullscreen
* **Progressive Loading** - Loads preview images first, then full-resolution for optimal performance
* **Smart Preloading** - Automatically preloads adjacent slides for smooth navigation
* **Lazy Loading** - Images load as needed for optimal performance
* **Error Recovery** - Graceful fallback with placeholder when images fail to load
* **Download Button** - Optional download button to save photos to your device
* **Customizable** - Control dimensions, slideshow, delays, and more
* **Cached for Performance** - Gallery HTML is cached for 24 hours
* **Large Album Support** - Handles up to 300 photos per album (Google Photos limitation; very old iOS/WebKit devices may be limited to 25 photos for stability)

= How It Works =

The plugin fetches your public Google Photos album and creates a responsive gallery. Simply paste the share link from Google Photos into the shortcode.

= Security & Error Handling =

* SSRF protection - validates Google Photos URLs
* Proper output escaping for XSS prevention
* WordPress coding standards compliant
* Swiper library bundled locally
* User-friendly error messages for invalid or short links
* Automatic detection and warning for deprecated goo.gl links

== Installation ==

1. Install & Activate the plugin
2. Open Settings page of this plugin. It is a rich onboarding guide as well.
3. Once comprotable use your own Google Photo albums on your own pages and posts.

== Usage ==

= Basic Usage =

**RECOMMENDED:** Use the full `photos.google.com/share/` URL format for future compatibility.

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]`

Note: Short links (photos.app.goo.gl) are still supported but will show a deprecation warning.

= Advanced Usage =

`[jzsa-album
    link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"
    background-color="#000000"
    image-fit="contain"
    width="500"
    height="375"
    slideshow="false"
    slideshow-delay="5"
    slideshow-inactivity-timeout="60"
    start-at="1"
    full-screen-slideshow="false"
    full-screen-slideshow-delay="5"
    full-screen-image-fit="fit"
    full-screen-toggle="button-only"
    show-title="true"
    show-counter="true"
    show-link-button="true"
    show-download-button="true"
]`

Tip: `full-screen-image-fit` defaults to `fit`, which preserves the full photo (no crop) while scaling it to fill at least one fullscreen axis.

= Shortcode Parameters =

The only required parameter is **link** — the Google Photos share URL.

All other parameters are optional and control appearance, slideshow, fullscreen behavior, display options, gallery mode, and gallery layout.

For the complete list of all parameters with descriptions and defaults, visit the plugin's **Settings** page in your WordPress admin (Settings → Shared Albums for Google Photos). The Settings page also includes a Playground for experimenting with shortcodes and live Samples you can copy.

= Getting Your Album Share Link =

1. Open Google Photos and select an album
2. Click the share button (or three-dot menu > Share)
3. Click "Create link" or "Get link"
4. Copy the FULL share link (format: `https://photos.google.com/share/AF1QipN...`)

**IMPORTANT:** Google Photos may show a short link like `https://photos.app.goo.gl/abc123`. These short links **might stop working in the future**.

**Current Behavior:**
- Short links still work but display a warning banner
- We strongly recommend using full links to avoid future issues

**How to get the full link:**
- On web: Right-click the album share link and choose "Copy link address" to get the full link
- Or visit the short link in your browser and copy the full link from the address bar

**Example:**
Full link (recommended): `https://photos.google.com/share/AF1QipNxLo...` ✅
Short link (works with warning): `https://photos.app.goo.gl/abc123` ⚠️

Use the shortcode with full link:
`[jzsa-album link="https://photos.google.com/share/AF1QipNxLo..."]`

Or with short link (not recommended):
`[jzsa-album link="https://photos.app.goo.gl/abc123"]`

**Important:** The album must be public (shared via link) for the plugin to access it.

== Frequently Asked Questions ==

= Does this work with private albums? =

No, the album must be shared publicly via a link. Google Photos does not provide API access to private albums without OAuth authentication.

= How many photos can I embed? =

The plugin can handle up to 300 photos per album. This is a limitation from Google Photos, which typically returns around 300 photos in the initial page load.

For performance and stability reasons, **very old iOS devices using legacy WebKit** may automatically be limited to 25 photos on the client side, even if the server-side limit is higher. All other platforms (desktop, Android, modern iOS/iPadOS) can use the full per-album limit you configure.

= Why does my gallery show a warning banner? =

If you see a yellow warning banner above your gallery, it means you're using a short link (photos.app.goo.gl).

**Current status:** Short links still work but this format might stop working in the future.

**What to do:** Update your shortcode to use a full Google Photos share link (format: https://photos.google.com/share/...) to remove the warning and ensure continued functionality.

= Will this slow down my site? =

No. The plugin uses lazy loading (only loads visible images) and caches the gallery HTML for 24 hours. The Swiper library is bundled locally for optimal performance.

= Can I customize the appearance? =

Yes! You can override the CSS by adding custom styles to your theme. The main container class is `.jzsa-album`.

= Does it work on mobile? =

Absolutely! The gallery is fully responsive and supports touch gestures (swipe, pinch-to-zoom).

= How does the download button work? =

When enabled with `show-download-button="true"`, a download button appears in the top-left corner. Clicking it downloads the current full-resolution photo to your device. The download uses a server-side proxy to bypass CORS restrictions from Google Photos.

= How does the play/pause button work? =

In fullscreen mode, a play/pause button appears above the photo counter at the bottom center. Click it or press the spacebar to toggle slideshow on/off. The button shows a play icon (▶) when paused and a pause icon (⏸) when playing. This works regardless of the `full-screen-slideshow` setting - if slideshow is disabled, the button lets you start it manually.

= What happens if I update the shortcode? =

The cache is automatically cleared when you save the post, so changes take effect immediately.

= What if I use the wrong URL format? =

The plugin provides clear feedback:

**Warnings (gallery still works):**
- Short link detected: Yellow warning banner appears above the gallery

**Errors (gallery won't display):**
- Invalid URL: Not a valid Google Photos link
- Album is private or link expired: "Unable to Load Album" error
- Empty album: "No Photos Found" error

== Screenshots ==

1. Gallery in normal view with navigation
2. Fullscreen mode
3. Mobile responsive view
4. Shortcode example in post editor

== Changelog ==

= 2.0.4 =
* New feature: Mosaic thumbnail preview (mosaic="true") for slider and carousel modes
* Mosaic feature inspired by Mateusz Starzak's fork (https://github.com/matiaspl)

= 2.0.3 =
* New parameter: "cache-refresh"

= 2.0.2 =
* Clear Cache button added

= 2.0.1 =
* Fixed album titles being truncated (dates and special characters are now preserved)

= 2.0.0 =
* Gallery mode support
* Experimental video support
* Shortcode parameters and their default values changed (Breaking. Apologies!)

= 1.0.6 =
* New animated logo

= 1.0.3 =
* Improved Settings page with more intuitive onboarding and richer, example-driven documentation
* Added Shortcode Playground on the Settings page to test and preview `[jzsa-album]` shortcodes without leaving admin

= 1.0.2 =
* Initial Settings page and onboarding content

= 1.0.1 =
* Release related improvements

= 1.0.0 =
* Initial release
* Modern Swiper 11 library integration
* Fullscreen mode with dedicated button
* Play/pause button in fullscreen with spacebar keyboard shortcut
* Download button with server-side proxy (optional, disabled by default)
* Zoom support (pinch on touch devices)
* Keyboard navigation (arrows to navigate, spacebar to play/pause in fullscreen)
* Lazy loading for optimal performance
* Progressive image loading with error recovery and placeholders
* Click-to-fullscreen option
* Random start position for galleries
* SSRF protection and proper escaping
* WordPress coding standards compliance
* 24-hour caching mechanism
* Supports both full and short link formats (short links show deprecation warning)
* User-friendly error messages and warning banners for invalid/deprecated URLs
* Responsive design with touch gestures

== Credits ==

* Uses [Swiper](https://swiperjs.com/) - MIT License
* Uses [Plyr](https://plyr.io/) - MIT License
* Developed by Jan Zeman

== Privacy Policy ==

This plugin does not collect or store any user data.

= Use of external Google services =

* The plugin fetches public Google Photos album pages from `https://photos.google.com` and image files from `*.googleusercontent.com` in order to render the galleries.
* Only publicly shared album links are supported; the plugin has no access to private albums or any content that is not already available via a public share link.
* The plugin does not collect, store, or transmit user credentials or personal data. It only caches album HTML and image URLs in WordPress transients for performance, and this cache is stored locally in your WordPress database.

== Support ==

* **Bug reports:** [Open an issue on GitHub](https://github.com/JanZeman/shared-albums-for-google-photos/issues/new)
* **Feature requests:** [Post on the support forum](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/)
* **Leave a rating:** [Review on WordPress.org](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post)
* **Buy Me a Coffee:** [buymeacoffee.com/janzeman](https://www.buymeacoffee.com/janzeman)
