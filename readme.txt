=== Shared Albums for Google Photos (by JanZeman) ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 2.0.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display publicly shared Google Photos albums with a modern, responsive Swiper-based gallery viewer.

== Description ==

Shared Albums for Google Photos (by JanZeman) allows you to easily display publicly shared Google Photos albums in your WordPress posts and pages using a simple shortcode. The plugin uses the modern Swiper library to provide a beautiful, touch-enabled gallery experience.

**Note:** This plugin is not affiliated with or endorsed by Google LLC. Google Photos™ is a trademark of Google LLC.

= Features =

* **Google Photos Gallery And Slider** - Display public Google Photos albums as responsive galleries or sliders
* **Photo And Video Support** - Supports both images and videos from shared Google Photos albums
* **Fullscreen Viewer** - Mobile-friendly fullscreen viewing with touch gestures, keyboard controls, and slideshow support
* **Photo Info Overlays** - Dynamic placeholders for counters, filenames, dimensions, dates, and EXIF data
* **Download And Link Buttons** - Optional inline and fullscreen action buttons
* **Performance Features** - Lazy loading, progressive loading, caching, and large album support
* **Shortcode Playground** - Admin-only sandbox on the Settings page for experimenting with `[jzsa-album]` shortcodes and previews
* **Mosaic Strip** - Optional mosaic thumbnail strip alongside the main viewer

Many more customization parameters and samples are available on the plugin's Settings & Onboarding page.

= How It Works =

The plugin fetches your public Google Photos album and creates a responsive gallery. Simply paste the share link from Google Photos into the shortcode.

= Security & Error Handling =

* SSRF protection - validates Google Photos URLs
* Proper output escaping for XSS prevention
* WordPress coding standards compliant
* Swiper library bundled locally
* User-friendly error messages for invalid album links

== Installation ==

1. Install & Activate the plugin
2. Open the plugin's Settings & Onboarding page. It includes a very very large number of customization parameters, many samples, and a live shortcode playground.
3. Start with a sample there, then use your own Google Photos albums in posts and pages.

== Usage ==

= Basic Usage =

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]`

= Common Example =

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" show-link-button="true" show-download-button="true"]`

= Shortcode Parameters =

The only required parameter is **link** - the Google Photos share URL.

All other parameters are optional.

This readme intentionally keeps shortcode examples short to avoid drift.

For the complete and current parameter reference, defaults, inheritance rules, a very very large number of customization parameters, many samples, and the shortcode playground, use the plugin's **Settings & Onboarding** page in WordPress admin:

`Settings -> Shared Albums for Google Photos`

= Getting Your Album Share Link =

1. Open Google Photos and select an album
2. Click the share button (or three-dot menu > Share)
3. Click "Create link" or "Get link"
4. Copy the album share link and paste it into the shortcode:

`[jzsa-album link="https://photos.google.com/share/AF1QipNxLo..."]`

**Important:** The album must be public (shared via link) for the plugin to access it.

== Frequently Asked Questions ==

= Does this work with private albums? =

No, the album must be shared publicly via a link. Google Photos does not provide API access to private albums without OAuth authentication.

= How many photos can I embed? =

The plugin can handle up to 300 photos per album. This is a limitation from Google Photos, which typically returns around 300 photos in the initial page load.

For performance and stability reasons, **very old iOS devices using legacy WebKit** may automatically be limited to 25 photos on the client side, even if the server-side limit is higher. All other platforms (desktop, Android, modern iOS/iPadOS) can use the full per-album limit you configure.

= Will this slow down my site? =

No. The plugin uses lazy loading, progressive image loading, and local bundled frontend assets. Album data is cached, and the refresh interval is configurable with `cache-refresh` (default: 7 days).

= Can I customize the appearance? =

Yes! You can override the CSS by adding custom styles to your theme. The main container class is `.jzsa-album`.

= Does it work on mobile? =

Absolutely! The gallery is fully responsive and supports touch gestures (swipe, pinch-to-zoom).

= How does the download button work? =

When enabled with `show-download-button="true"`, a download button appears in inline (non-fullscreen) view. Clicking it downloads the current full-resolution photo to your device. The download uses a server-side proxy to bypass CORS restrictions from Google Photos.

Use `fullscreen-show-download-button` to control the fullscreen download button separately. If omitted, it inherits from `show-download-button`.

= How does the play/pause button work? =

In fullscreen mode, a play/pause button appears above the photo counter at the bottom center. Click it or press the spacebar to toggle slideshow on/off. The button shows a play icon (▶) when paused and a pause icon (⏸) when playing. This works regardless of the `fullscreen-slideshow` setting - if slideshow is disabled, the button lets you start it manually.

= What happens if I update the shortcode? =

The cache is automatically cleared when you save the post, so changes take effect immediately.

= What if I use the wrong URL format? =

The plugin provides clear feedback:

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

= 2.0.12 =
* Google Photo description can be supported after all :-)
* Introduce a halo effect to improve text readability

= 2.0.11 =
* Swiper loop navigation

= 2.0.10 =
* Lighter loading for large sliders

= 2.0.9 =
* New `fullscreen-display-max-width` and `fullscreen-display-max-height`
* New `info-wrap` and info text alignment parameters
* New `gallery-buttons-on-mobile` behavior for touch devices
* Responsive layout improvements

= 2.0.8 =
* File name bug fix

= 2.0.7 =
* New dynamic photo info overlays
* EXIF placeholders with background loading
* Slider, carousel, gallery and fullscreen photo info
* All Settings page samples are editable by now, not only the Playground

= 2.0.6 =
* Touch devices: Controls appear on tap and fade out on inactivity

= 2.0.5 =
* Fullscreen vs inline controls
* Video download support
* Download UX & settings improvements

= 2.0.4 =
* New: Mosaic thumbnail strip (`mosaic="true"`) for slider and carousel modes
* Mosaic feature inspired by Mateusz Starzak's fork
* Added `fullscreen-background-color` (default `#000`) to control fullscreen background separately
* Fixed gallery mode where `show-download-button="true"` did not render the download button
* Fixed slideshow option logic: use `disabled`, `manual`, or `auto` for `slideshow` and `fullscreen-slideshow`
* Fixed `fullscreen-toggle="click"` for video slides in gallery mode
* Improved iPhone pseudo-fullscreen behavior, including fullscreen arrow navigation
* Added restore-to-last-viewed position when closing fullscreen
* Thanks to Peter and Ulf for detailed bug reports and testing

= 2.0.3 =
* New parameter: "cache-refresh"
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
* User-friendly error messages for invalid album URLs
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
