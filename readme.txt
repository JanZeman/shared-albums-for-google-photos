=== Shared Albums for Google Photos (by JanZeman) ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 2.3.0
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
* **Mosaic Strip** - Optional mosaic thumbnail strip alongside the main viewer, including a fullscreen mosaic mode
* **Community Directory** - Optional "Share Your Shortcode" feature: browse, copy, and publish shortcode configurations to a public community directory at jzsa.janzeman.com

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

1. Slider mode with navigation and paging
2. Slider mode with mosaic strip
3. Gallery mode
4. Carousel mode (also shows the video capability)
5. Fullscreen mode with description and EXIF info overlay
6. From low- to high-resolution image support
7. Every sample includes a live preview - edit and see the result immediately
8. Description with custom font
9. Fullscreen mosaic support
10. Mosaic strip in the overlay mode

== Changelog ==

= 2.3.0 =
* New: Lightbox - an alternative to native fullscreen. With `lightbox="click"` (or `button-only` / `double-click`) clicking a photo opens it in a dimmed overlay *on top of the page* instead of taking over the screen
* New: `lightbox-max-width` / `lightbox-max-height` - open photos at a predetermined size instead of wall-to-wall
* New: `lightbox-image-fit`, `lightbox-background-color`, `lightbox-corner-radius` for tuning the lightbox box and backdrop
* When the lightbox is enabled it reuses the album's `fullscreen-*` settings; by default it replaces the fullscreen button, but both can be shown side by side by keeping `fullscreen-toggle` enabled; `interaction-lock="true"` disables both
* Thanks to `@valterbruno` for the feature request that started this!

= 2.2.2 =
* Community feature bugfixes

= 2.2.1 =
* Preparation of the Community feature for the release
* Once more thanks to `@naveenbachwani` for valuable discussion about the feature!

= 2.2.0 =
* New: Community Directory - browse, copy, and publish shortcode configurations and inspire others :-)
* Privacy by design: your email is never sent; account identity uses one-way hashes, and publishing data is sent only when you choose to share
* Passwordless authentication: Connect from your WordPress admin, with no email or external account required
* Interaction points and shortcode ratings are community fun, not a competition
* Delete your account and all published entries at any time
* Special thanks to `@naveenbachwani` for the truly inspirational "Gallery links" request!

= 2.1.8 =
* Fullscreen support of mosaic. Many thanks `@luisbenitez777` for sharing the idea.
* Warning added: Google truncates descriptions to 100 chars. Thanks `@naveenbachwani` for the detailed repro steps.

= 2.1.7 =
* Fixed info-wrap bug

= 2.1.6 =
* Fixed slider mode getting stuck on loading spinner (regression from 2.1.5)

= 2.1.5 =
* Reworked Guide page loading to reduce blocking and improve responsiveness
* Improved cache/help guidance on the settings page
* Big thanks to `@naveenbachwani` for detailed testing, UX observations, and support-thread feedback

= 2.1.4 =
* Screenshots added

= 2.1.3 =
* Add "How the cache works" section
* Improve Guide page loading experience

= 2.1.2 =
* Make caching description more clear and prominent

= 2.1.0 =
* Settings page moved to top-level admin menu with subpages for easier navigation and reference
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

This plugin does not collect or store any user data for its core gallery functionality.

= Use of external Google services =

* The plugin fetches public Google Photos album pages from `https://photos.google.com` and image files from `*.googleusercontent.com` in order to render the galleries.
* Only publicly shared album links are supported; the plugin has no access to private albums or any content that is not already available via a public share link.
* The plugin does not collect, store, or transmit user credentials or personal data. It only caches album HTML and image URLs in WordPress transients for performance, and this cache is stored locally in your WordPress database.

= Community Directory (optional, opt-in) =

The "Share Your Shortcode" community feature is entirely optional. Browsing community examples loads entries from jzsa.janzeman.com. Account, rating, and publishing features are active only when you choose to connect to the community.

* **No email is ever transmitted.** Your WordPress admin email and site URL are combined and hashed on your server using SHA-256 before anything is sent. Only this identity hash - from which the original email cannot be recovered - is transmitted.
* **Identity stored as a one-way hash.** The backend applies a second cryptographic layer (HMAC-SHA256 with a server-side secret), so the stored value cannot be reversed even with access to the database.
* **Site verification and correlation.** Your site's home URL is transmitted during connection so the community server can verify that the request really came from that WordPress site. The backend stores only a separate SHA-256 hash of the site URL. This hash cannot be reversed to recover the URL. Its purpose is abuse prevention, such as detecting if multiple accounts originate from the same WordPress installation.
* **Community profile.** If you provide a community display name or display URL while connecting or later editing your community profile, those values are stored by the community server and may be shown publicly with your shared entries. These fields are optional and can be changed or cleared.
* **User-provided account and entry data.** If you publish an entry, the following is stored on jzsa.janzeman.com: title, shortcode, album link extracted from the shortcode, optional description, optional tags, optional sample page URL, optional photographer / creator name, optional short bio, plugin version, and whether you opted into future public showcase consideration. If you provide a photographer / creator name, it is shown as the entry author; otherwise, an explicitly set community display name may be shown. Entry sample URLs are shown when provided; otherwise, your community display URL may be shown. You control this data.
* **Album-link masking.** Public community responses show the shared shortcode with the Google Photos URL replaced by `link="[link]"`. The real shared album link is still stored by the community server so previews can render. Authenticated community users may receive a private preview shortcode containing the real link, but the visible shared shortcode remains masked.
* **Anonymous interaction signals.** When someone copies, applies, rates, or previews a community entry, an interaction event may be recorded. The community backend hashes the request IP it sees each day (SHA-256 of IP + date) and never stores it in plain text. Because WordPress proxies these calls, this is normally the WordPress site's server IP rather than the browser user's direct IP.
* **Star ratings.** Authenticated community users can rate entries (1-5 stars). The rating is stored linked to the user's identity hash, not to any personal data.
* **Account deletion.** You can delete your community account at any time from the plugin's admin page. Account deletion removes the stored identity hash, site hash, display name, display URL, and ratings you submitted. You can choose whether published entries are preserved as community examples or hidden at the same time.
* **No tracking.** The community backend does not use cookies, analytics, or advertising.

== Support ==

* **Bug reports:** [Open an issue on GitHub](https://github.com/JanZeman/shared-albums-for-google-photos/issues/new)
* **Feature requests:** [Post on the support forum](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/)
* **Leave a rating:** [Review on WordPress.org](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post)
* **Buy Me a Coffee:** [buymeacoffee.com/janzeman](https://www.buymeacoffee.com/janzeman)
