=== Shared Albums for Google Photos ===
Contributors: janzeman
Tags: google-photos, album, gallery, embed, swiper
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 2.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed shared Google Photos albums directly from the source, without uploading copies to WordPress.

== Description ==

Embed a Google Photos shared album directly from the source, without uploading copies to your WordPress media library.
Add one shortcode, choose a gallery, slider, or carousel layout, and the plugin turns your album into a responsive photo experience for desktop and mobile visitors.

It is built for photographers, bloggers, clubs, families, travel sites, event pages,
and anyone who already organizes photos in Google Photos and wants to show them beautifully on a WordPress site.

= Why Install It =

* **No duplicate uploads** - Keep your photos in Google Photos and show them on your WordPress site.
* **Less gallery maintenance** - Update the album at the source instead of rebuilding galleries by hand.
* **A better visitor experience** - Give people a responsive gallery, slider, carousel, fullscreen view, or lightbox.
* **Photo and video support** - Share mixed albums without splitting them into separate WordPress galleries.
* **Mobile-friendly browsing** - Let visitors swipe, tap, zoom, navigate, and enjoy slideshows naturally.
* **Useful presentation options** - Show descriptions, dates, counters, buttons, and image details when they help.
* **Built for real albums** - Caching, lazy loading, and progressive loading help larger albums stay practical.

= Why People Use It =

Google Photos is convenient for storing and sharing albums.
WordPress is where your audience is.
This plugin connects the two.

Instead of exporting photos, uploading them to the media library, rebuilding galleries,
and repeating the work after every album change, you can share the Google Photos album once and embed it where you need it.

= What Happens After Installing =

The plugin adds its own WordPress admin menu with a friendly Guide page,
ready-to-use examples, a live shortcode playground, and the current parameter reference.

Start with a sample, paste your own Google Photos share link,
preview the result, and place the shortcode where you want the album to appear.

= Community Inspiration =

The optional Community Directory helps you discover what other users are building with the plugin.

* Browse real shortcode examples for layout ideas.
* Copy a configuration and adapt it to your own album.
* Rate useful examples and share your own.
* Add a sample page URL if you want curious visitors to find your site.

Community features are optional. You can use the gallery plugin without joining or publishing anything.

== Installation ==

1. Install and activate the plugin.
2. Open the new `Shared Albums for Google Photos` menu in WordPress admin.
3. Start with the Guide page and its live examples.

== Screenshots ==

1. Slider layout with navigation and paging
2. Slider layout with mosaic thumbnails
3. Gallery layout
4. Carousel layout with video support
5. Fullscreen view with description and photo info
6. Progressive loading from preview to high-resolution image
7. Live preview on the Guide page
8. Custom description styling
9. Fullscreen mosaic view
10. Overlay mosaic strip
11. Community Directory for browsing and sharing examples

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

Yes. The plugin includes ready-made examples and many presentation options.
You can also add your own CSS when you want full visual control.

= Does it work on mobile? =

Yes. The gallery is responsive and supports touch gestures, fullscreen viewing, and mobile-friendly navigation.

= Is this an official Google plugin? =

No. This plugin is not affiliated with or endorsed by Google LLC. Google Photos™ is a trademark of Google LLC.

== Changelog ==

= 2.4.1 =
* Lightbox is new recommended viewer and the default for new installations.
* Existing installations keep their current Fullscreen default. Updating the plugin does not require a shortcode change.
* The Guide includes a validator-backed Migration Tool, viewer comparison samples, and a reversible site default.
* New viewer parameters make Lightbox and Fullscreen selection, triggers, and mode-specific overrides explicit.

= 2.3.7 =
* Improved plugin intro
* Improve warning about "gallery-columns" in the "justified" mode

= 2.3.4 =
* Email-based login for the Community feature
* Extensive unit and end-to-end testing

= 2.3.1 =
* Tested with WordPress 7.0
* New: Lightbox - an alternative to native fullscreen
* Basic shortcode real-time validation
* Preparation of the Community feature for the release

= 2.2.0 =
* New: Community Directory - browse, copy, and publish shortcode configurations and inspire others :-)
* Privacy by design: your email is never sent; account identity uses one-way hashes, and publishing data is sent only when you choose to share
* Passwordless authentication: Connect from your WordPress admin, with no email or external account required
* Interaction points and shortcode ratings are community fun, not a competition
* Delete your account and all published entries at any time

= 2.1.8 =
* Fullscreen support of mosaic
* Warning added: Google truncates descriptions to 100 chars

= 2.1.7 =
* Fixed info-wrap bug

= 2.1.6 =
* Fixed slider mode getting stuck on loading spinner (regression from 2.1.5)

= 2.1.5 =
* Reworked Guide page loading to reduce blocking and improve responsiveness
* Improved cache/help guidance on the settings page

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
* New `fullscreen-max-width` and `fullscreen-max-height`
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
* Added `fullscreen-background-color` (default `#000`) to control fullscreen background separately
* Fixed gallery mode where `show-download-button="true"` did not render the download button
* Fixed slideshow option logic: use `disabled`, `manual`, or `auto` for `slideshow` and `fullscreen-slideshow`
* Fixed `fullscreen-toggle="click"` for video slides in gallery mode
* Improved iPhone pseudo-fullscreen behavior, including fullscreen arrow navigation
* Added restore-to-last-viewed position when closing fullscreen

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

== Community Acknowledgements ==

This plugin has been shaped not only by code, but also by people willing to test early versions, question design decisions, and explain their real-world needs. Their ideas and thoughtful feedback continue to make it more useful and easier to use.

= Special Recognition =

* **[Naveen Bachwani](https://profiles.wordpress.org/naveenbachwani/) (`@naveenbachwani`)** - An early adopter with a sharp eye and analytical mind. His feedback often improved usability while new features were still being designed, before they reached a public release. His many feature ideas, extensive testing, UX feedback, and detailed design discussions have helped shape photo information and descriptions, mobile and Fullscreen behavior, the Community Directory, and the current Lightbox and Fullscreen experience.

Personal note: It has been a genuine pleasure working with you, Naveen!
  
= Key Contributors =

* **[Peter Berger](https://profiles.wordpress.org/peterpolow/) (`@peterpolow`)** - Detailed feature requests and patient real-device testing that helped drive video support, Gallery mode, separate inline and Fullscreen controls, and more discoverable button behavior on iPhone and other mobile devices.
* **[Ulf](https://profiles.wordpress.org/toonwolf/) (`@toonwolf`)** - Real-world testing and thoughtful presentation requests based on using the plugin for digital signage on a screen in his tennis club cafe.

= Additional Acknowledgements =

* **[Valter Bruno](https://profiles.wordpress.org/valterbruno/) (`@valterbruno`)** - The original Lightbox feature request and continued real-world testing of the plugin and its shortcode workflow.
* **[Luis](https://profiles.wordpress.org/luisbenitez777/) (`@luisbenitez777`)** - The request to support mosaic thumbnails in Fullscreen, which became the Fullscreen mosaic feature.
* **[Mateusz Starzak](https://github.com/matiaspl) (GitHub `@matiaspl`)** - A public fork that inspired the original mosaic feature.
* **[GMRobbins](https://profiles.wordpress.org/gmrobbins/) (`@gmrobbins`)** - An early usability review that led to clearer onboarding and helped inspire Gallery mode and the carousel-to-single experience.

== Credits ==

* Uses [Swiper](https://swiperjs.com/) - MIT License
* Uses [Plyr](https://plyr.io/) - MIT License

== Privacy Policy ==

This plugin does not collect or store any user data for its core gallery functionality.

= Use of external Google services =

* The plugin fetches public Google Photos album pages from `https://photos.google.com` and image files from `*.googleusercontent.com` in order to render the galleries.
* Only publicly shared album links are supported; the plugin has no access to private albums or any content that is not already available via a public share link.
* The plugin does not collect, store, or transmit user credentials or personal data. It only caches album HTML and image URLs in WordPress transients for performance, and this cache is stored locally in your WordPress database.

= Community Directory (optional, opt-in) =

The Community Directory is optional. Browsing samples loads public examples from sa.janzeman.com.
Sign-in, ratings, and publishing are used only when you choose to connect to the community.

If you publish an entry, the community service stores the information you submit,
such as title, shortcode, description, tags, optional sample page URL, and optional display name.
You can delete your community account from the plugin admin page.

The community backend does not use cookies, analytics, advertising, or newsletters.

== Support ==

* **Bug reports:** [Open an issue on GitHub](https://github.com/JanZeman/shared-albums-for-google-photos/issues/new)
* **Feature requests:** [Post on the support forum](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/)
* **Privacy, account, or sensitive questions:** email [support@janzeman.com](mailto:support@janzeman.com)
* **Leave a rating:** [Review on WordPress.org](https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post)
* **Buy Me a Coffee:** [buymeacoffee.com/janzeman](https://www.buymeacoffee.com/janzeman)
