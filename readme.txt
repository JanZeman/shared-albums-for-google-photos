=== Shared Albums for Google Photos (by JanZeman) ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 2.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed shared Google Photos albums directly from the source, without uploading copies to WordPress.

== Description ==

Embed a Google Photos shared album directly from the source, without uploading copies to your WordPress media library.
Add one shortcode, choose a gallery, slider, or carousel layout, and the plugin turns your album into a responsive photo experience for desktop and mobile visitors.

It is built for photographers, bloggers, clubs, families, travel sites, event pages,
and anyone who already organizes photos in Google Photos and wants to show them beautifully on a WordPress site.

= What It Does =

* **Embeds Google Photos albums** - Use a public Google Photos share link in a WordPress shortcode.
* **Keeps your workflow simple** - Manage the album in Google Photos; show it on your site.
* **Looks good on any screen** - Responsive gallery, slider, carousel, fullscreen, and lightbox views.
* **Supports photos and videos** - Display mixed albums without building a separate gallery.
* **Feels natural to browse** - Swipe, tap, zoom, use keyboard navigation, or start a slideshow.
* **Includes useful display options** - Show counters, descriptions, dates, image info, download buttons, link buttons, and more.
* **Handles performance details** - Lazy loading, caching, and progressive loading help large albums feel smooth.

= Why People Use It =

Google Photos is convenient for storing and sharing albums.
WordPress is where your audience is.
This plugin connects the two.

Instead of exporting photos, uploading them to the media library, rebuilding galleries,
and repeating the work after every album change, you can share the Google Photos album once and embed it where you need it.

= Setup Is Simple =

1. Share an album in Google Photos and copy its share link.
2. Add the link to the `[jzsa-album]` shortcode.
3. Publish the post or page.

Example:

`[jzsa-album link="https://photos.google.com/share/AF1QipNxLo..."]`

The plugin also includes a Settings & Onboarding page with ready-made examples,
a live shortcode playground, and the full parameter reference.

= Community Inspiration =

The optional Community Directory helps you discover what other users are building with the plugin.

* Browse real shortcode examples for layout ideas.
* Copy a configuration and adapt it to your own album.
* Rate useful examples and share your own.
* Add a sample page URL if you want curious visitors to find your site.

Community features are optional. You can use the gallery plugin without joining or publishing anything.

== Installation ==

1. Install and activate the plugin.
2. Open `Settings -> Shared Albums for Google Photos`.
3. Start with one of the examples, paste in your own Google Photos album link, and preview the result.
4. Add the shortcode to any post or page.

== Usage ==

= Basic Shortcode =

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]`

= Slider Example =

`[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" show-link-button="true" show-download-button="true"]`

= Shortcode Options =

The only required parameter is **link** - your Google Photos share URL.

Everything else is optional.
Use the Settings & Onboarding page for the current parameter reference, defaults, examples, and live previews:

`Settings -> Shared Albums for Google Photos`

= Getting Your Album Share Link =

1. Open an album in Google Photos.
2. Click the share button or use the three-dot menu.
3. Choose "Create link" or "Get link".
4. Copy the link and paste it into the shortcode:

`[jzsa-album link="https://photos.google.com/share/AF1QipNxLo..."]`

**Important:** The album must be shared by link. Private albums cannot be embedded.

== Frequently Asked Questions ==

= Does this work with private albums? =

No. The album must be shared by link in Google Photos.

= How many photos can I embed? =

The plugin can handle up to 300 photos per album.
Google Photos usually exposes about 300 items from the shared album page.

= Will this slow down my site? =

It is designed to be light in normal use.
Album data is cached, images load progressively, and visitors do not download the whole album at once.

= Can I customize the appearance? =

Yes. Start with the examples on the Settings & Onboarding page,
then adjust shortcode options or add your own CSS.
The main container class is `.jzsa-album`.

= Does it work on mobile? =

Yes. The gallery is responsive and supports touch gestures, fullscreen viewing, and mobile-friendly navigation.

= How does the download button work? =

Enable it with `show-download-button="true"` when you want visitors to download the current photo.
Fullscreen download behavior can be controlled separately with `fullscreen-show-download-button`.

= How does the play/pause button work? =

In fullscreen mode, visitors can start or pause a slideshow with the play/pause button or the spacebar.

= What happens if I update the shortcode? =

The plugin clears the cache for that post when you save it, so shortcode changes take effect immediately.

= What if I use the wrong URL format? =

The plugin shows an error message instead of a broken gallery.
Most problems are caused by private albums, expired links, empty albums, or links that are not from Google Photos.

= Is this an official Google plugin? =

No. This plugin is not affiliated with or endorsed by Google LLC. Google Photos™ is a trademark of Google LLC.

== Screenshots ==

1. Slider layout with navigation and paging
2. Slider layout with mosaic thumbnails
3. Gallery layout
4. Carousel layout with video support
5. Fullscreen view with description and photo info
6. Progressive loading from preview to high-resolution image
7. Live preview in the Settings & Onboarding page
8. Custom description styling
9. Fullscreen mosaic view
10. Overlay mosaic strip
11. Community Directory for browsing and sharing examples

== Changelog ==

= 2.3.4 =
* Email-based login for the Community feature
* Extensive unit and end-to-end testing

= 2.3.1 =
* Tested with WordPress 7.0
* New: Lightbox - an alternative to native fullscreen
* Thanks to `@valterbruno` for the Lightbox feature request!
* Basic shortcode real-time validation
* Preparation of the Community feature for the release
* Once more huge thanks to `@naveenbachwani` for sharp and inspiring feedback about this feature!

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

The "Share Your Shortcode" community feature is entirely optional. Browsing community samples loads entries from jzsa.janzeman.com. Account, rating, and publishing features are active only when you choose to connect to the community.

* **Email sign-in.** When you sign in to the community, the email address you enter is sent to jzsa.janzeman.com so the community server can send a one-time confirmation link and identify your account. It is used only for sign-in and account-related messages, not newsletters or marketing.
* **Installation identity.** The plugin also sends a one-way installation secret hash during account connection so the community server can authorize this WordPress site without storing the local secret itself.
* **Site verification and correlation.** Your site's home URL is transmitted during connection so the community server can verify that the request really came from that WordPress site. The backend stores only a separate SHA-256 hash of the site URL. This hash cannot be reversed to recover the URL. Its purpose is abuse prevention, such as detecting if multiple accounts originate from the same WordPress installation.
* **Community profile.** If you provide a community display name while connecting or later editing your community profile, it is stored by the community server and may be shown publicly with your shared entries. This field is optional and can be changed.
* **User-provided account and entry data.** If you publish an entry, the following is stored on jzsa.janzeman.com: title, shortcode, album link extracted from the shortcode, optional description, optional tags, optional sample page URL, optional entry info, plugin version, and whether you submitted the page for future public site showcase consideration. Entries are shown under your community display name when one is set. Entry sample URLs are shown when provided. You control this data.
* **Album-link masking.** Public community responses show the shared shortcode with the Google Photos URL replaced by `link="hidden-album-link"`. This makes it clear that the album link is intentionally hidden. The real shared album link is still stored by the community server so previews can render. Authenticated community users may receive a private preview shortcode containing the real link, but the visible shared shortcode remains masked.
* **Anonymous interaction signals.** When someone copies, applies, rates, or previews a community entry, an interaction event may be recorded. The community backend hashes the request IP it sees each day (SHA-256 of IP + date) and never stores it in plain text. Because WordPress proxies these calls, this is normally the WordPress site's server IP rather than the browser user's direct IP.
* **Star ratings.** Authenticated community users can rate entries (1-5 stars). The rating is stored linked to the user's identity hash, not to any personal data.
* **Account deletion.** You can delete your community account at any time from the plugin's admin page. Account deletion removes the stored identity hash, site hash, display name, and ratings you submitted. You can choose whether published entries are preserved as community samples or hidden at the same time.
* **No tracking.** The community backend does not use cookies, analytics, or advertising.

== Support ==

* **Bug reports:** [Open an issue on GitHub](https://github.com/JanZeman/shared-albums-for-google-photos/issues/new)
* **Feature requests:** [Post on the support forum](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/)
* **Privacy, account, or sensitive questions:** email [support@janzeman.com](mailto:support@janzeman.com)
* **Leave a rating:** [Review on WordPress.org](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post)
* **Buy Me a Coffee:** [buymeacoffee.com/janzeman](https://www.buymeacoffee.com/janzeman)
