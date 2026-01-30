=== Shared Albums for Google Photos (by JanZeman) ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display publicly shared Google Photos albums with a modern, responsive Swiper-based gallery viewer.

== Description ==

Shared Albums for Google Photos (by JanZeman) allows you to easily display publicly shared Google Photos albums in your WordPress posts and pages using a simple shortcode. The plugin uses the modern Swiper library to provide a beautiful, touch-enabled gallery experience.

**Note:** This plugin is not affiliated with or endorsed by Google LLC. Google Photos™ is a trademark of Google LLC.

**IMPORTANT:** Short links (photos.app.goo.gl) might stop working in the future. While both full and short link formats work today, we strongly recommend using the full link format `https://photos.google.com/share/` to ensure your galleries continue working. The plugin will display a warning banner when short links are detected.

= Features =

* **Modern Gallery Viewer** - Powered by Swiper 11, a modern mobile-friendly slider
* **Responsive Design** - Works perfectly on desktop, tablet, and mobile devices
* **Touch Gestures** - Swipe navigation on touch devices
* **Fullscreen Mode** - Click to view photos in fullscreen
* **Play/Pause Control** - Fullscreen play/pause button with spacebar keyboard shortcut
* **Click Navigation** - Optional click/double-click on left/right areas to navigate between photos
* **Dual-Mode Autoplay** - Separate autoplay settings for normal and fullscreen modes
* **Zoom Support** - Pinch-to-zoom and double-click to zoom on photos
* **Keyboard Navigation** - Use arrow keys to navigate, spacebar to play/pause in fullscreen
* **Progressive Loading** - Loads preview images first, then full-resolution for optimal performance
* **Smart Preloading** - Automatically preloads adjacent slides for smooth navigation
* **Lazy Loading** - Images load as needed for optimal performance
* **Error Recovery** - Graceful fallback with placeholder when images fail to load
* **Download Button** - Optional download button to save photos to your device
* **Customizable** - Control dimensions, autoplay, delays, and more
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
2. Use this shortcode in your posts or pages: [jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]
3. Follow the guide in plugin settings to use your own Google Photo albums

== Usage ==

= Basic Usage =

**RECOMMENDED:** Use the full `photos.google.com/share/` URL format for future compatibility.

`[jzsa-album link="https://photos.google.com/share/YOUR_LONG_ALBUM_ID"]`

Concrete example with a real album link (for testing):

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]`

Note: Short links (photos.app.goo.gl) are still supported but will show a deprecation warning.

= Advanced Usage =

`[jzsa-album
    link="https://photos.google.com/share/YOUR_LONG_ALBUM_ID"
    background-color="#000000"
    crop-to-fill="false"
    width="500"
    height="375"
    autoplay="true"
    autoplay-delay="5"
    autoplay-inactivity-timeout="60"
    start-at="1"
    full-screen-autoplay="true"
    full-screen-autoplay-delay="3"
    full-screen-switch="double-click"
    full-screen-navigation="single-click"
    show-title="true"
    show-counter="true"
    show-link-button="true"
    show-download-button="true"
]`

= Shortcode Parameters =

**Required:**

* **link** - Google Photos share URL (supports both full and short link formats)

**Gallery Appearance:**

* **background-color** - Background color hex code or "transparent" (default: #FFFFFF)
* **crop-to-fill** - Crop images to fill container (maintains aspect ratio): "true" or "false" (default: true)
* **width** - Gallery width in pixels or "auto" (default: 267)
* **height** - Gallery height in pixels or "auto" (default: 200)
* **media-items-stretch** - Stretch images (may distort): "true" or "false" (default: false)

**Image Quality:**

* **image-width** - Full-resolution photo width to fetch from Google (default: 1920)
* **image-height** - Full-resolution photo height to fetch from Google (default: 1440)
* **preview-width** - Preview/thumbnail photo width for faster initial load (default: 800)
* **preview-height** - Preview/thumbnail photo height for faster initial load (default: 600)

**Autoplay Settings:**

* **autoplay** - Enable autoplay in normal mode: "true" or "false" (default: true)
* **autoplay-delay** - Autoplay delay in normal mode, in seconds, supports ranges like "4-12" (default: "4-12")
* **autoplay-inactivity-timeout** - Time in seconds after which autoplay resumes following user interaction (default: 30)
* **start-at** - Starting photo: "random" (default) or a 1-based photo index like "1" or "12". Values out of range fall back to 1.

**Fullscreen Settings:**

* **full-screen-autoplay** - Enable autoplay in fullscreen mode: "true" or "false" (default: true)
* **full-screen-autoplay-delay** - Autoplay delay in fullscreen mode, in seconds, supports ranges like "3-5" or single values (default: 3)
* **full-screen-switch** - Full screen switch mode: "button-only" (button only), "single-click" (single-click), or "double-click" (default: double-click). Works both in and out of full screen mode.
* **full-screen-navigation** - Full screen navigation mode: "buttons-only" (navigation buttons only), "single-click" (click left/right areas to navigate), or "double-click" (double-click left/right areas to navigate) (default: single-click). Only works when in full screen mode.

**Display Options:**

* **show-title** - Display album title: "true" or "false" (default: true)
* **show-counter** - Show the photo counter (e.g., "4 / 50" or "Trip to Bali: 4 / 50"): "true" or "false" (default: true)
* **show-link-button** - Show external link button to open album in Google Photos: "true" or "false" (default: false)
* **show-download-button** - Show download button to save current photo: "true" or "false" (default: false)

**Gallery Mode:**

* **mode** - Gallery mode (default: gallery-player):
  - "gallery-player": Single photo viewer with zoom support (pinch/double-click to zoom)
  - "carousel": Multiple photos visible at once (1 on mobile, 2 on tablet, 3 on desktop)

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

In fullscreen mode, a play/pause button appears above the photo counter at the bottom center. Click it or press the spacebar to toggle autoplay on/off. The button shows a play icon (▶) when paused and a pause icon (⏸) when playing. This works regardless of the `full-screen-autoplay` setting - if autoplay is disabled, the button lets you start it manually.

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

= 1.0.3 =
* Even simpler onboarding

= 1.0.2 =
* Richer Settings page

= 1.0.1 =
* Release related improvements

= 1.0.0 =
* Initial release
* Modern Swiper 11 library integration
* Fullscreen mode with dedicated button
* Play/pause button in fullscreen with spacebar keyboard shortcut
* Download button with server-side proxy (optional, disabled by default)
* Zoom support (pinch and double-click)
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
* Developed by Jan Zeman

== Privacy Policy ==

This plugin does not collect or store any user data.

= Use of external Google services =

* The plugin fetches public Google Photos album pages from `https://photos.google.com` and image files from `*.googleusercontent.com` in order to render the galleries.
* Only publicly shared album links are supported; the plugin has no access to private albums or any content that is not already available via a public share link.
* The plugin does not collect, store, or transmit user credentials or personal data. It only caches album HTML and image URLs in WordPress transients for performance, and this cache is stored locally in your WordPress database.

== Support ==

For support, please visit the [plugin support forum](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/) or [GitHub repository](https://github.com/JanZeman/shared-albums-for-google-photos).
