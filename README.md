# Shared Albums for Google Photos (by JanZeman)

[![WordPress Plugin Version](https://img.shields.io/badge/version-2.0.6-blue.svg)](https://wordpress.org/plugins/janzeman-shared-albums-for-google-photos/)
[![WordPress Compatibility](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)](https://wordpress.org/plugins/janzeman-shared-albums-for-google-photos/)
[![PHP Version](https://img.shields.io/badge/php-7.0%2B-blue.svg)](https://wordpress.org/plugins/janzeman-shared-albums-for-google-photos/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](LICENSE)

A WordPress plugin that displays publicly shared Google Photos albums with a modern, responsive Swiper-based gallery viewer.

---

## 📖 Full Documentation

**→ See [readme.txt](readme.txt) for complete documentation, features, usage examples, and FAQs.**

The `readme.txt` file contains the official WordPress.org plugin documentation with:

- Complete feature list
- Detailed usage instructions and shortcode parameters
- Installation guide
- FAQ section
- Changelog

---

## Quick Start

### Installation

**From WordPress.org (Recommended):**

1. Go to **Plugins** > **Add New** in WordPress admin
2. Search for "Shared Albums for Google Photos (by JanZeman)"
3. Click **Install Now** and then **Activate**

**Manual Installation:**

1. Download the latest release
2. Upload to `/wp-content/plugins/janzeman-shared-albums-for-google-photos/`
3. Activate through WordPress admin

**From GitHub (Development):**

```bash
cd wp-content/plugins/
git clone https://github.com/JanZeman/shared-albums-for-google-photos.git
```

### Basic Usage

``` shortcode
[jzsa-album link="https://photos.google.com/share/YOUR_ALBUM_ID"]
```

**For all shortcode parameters and advanced usage**, see [readme.txt](readme.txt).

---

## Key Features

- Multiple display modes - thumbnail gallery mode (default, with optional `gallery-rows` paging/scrolling), single-photo slider, or carousel (multi-photo preview that opens single-photo fullscreen)
- Touch gestures, zoom support, keyboard navigation
- Fullscreen image-fit modes with `fit` as the default (no crop, scales to fill one axis)
- Progressive image loading with lazy loading
- Responsive design (mobile, tablet, desktop)
- Customizable via shortcode parameters
- Shortcode Playground on the Settings page for experimenting with `[jzsa-album]` shortcodes and live previews
- Modern Swiper 11 gallery with fullscreen mode (bundled locally, no CDN dependencies)
- WordPress coding standards compliant

---

## Development

### File Structure

``` txt
janzeman-shared-albums-for-google-photos/
├── assets/
│   ├── css/              # Stylesheets
│   └── js/               # JavaScript (Swiper initialization)
├── includes/
│   ├── class-data-provider.php    # Fetches Google Photos data
│   ├── class-orchestrator.php     # Main plugin orchestrator
│   ├── class-renderer.php         # HTML rendering
│   └── class-settings-page.php    # Admin settings page
├── LICENSE
├── README.md             # This file (GitHub overview)
├── readme.txt           # WordPress.org official documentation ⭐
└── janzeman-shared-albums-for-google-photos.php       # Main plugin file
```

### Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Public Google Photos album (shared via link)

### Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

For bug reports and feature requests, use [GitHub Issues](https://github.com/JanZeman/shared-albums-for-google-photos/issues).

---

## Support

- **Documentation**: [readme.txt](readme.txt)
- **WordPress.org Forum**: <https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/>
- **GitHub Issues**: <https://github.com/JanZeman/shared-albums-for-google-photos/issues>

---

## License

GPL v2 or later - see [LICENSE](LICENSE) file.

This plugin is not affiliated with, endorsed by, or in any way officially connected with Google LLC or Google Photos. Google Photos™ is a trademark of Google LLC.

---

## Credits

- Uses [Swiper](https://swiperjs.com/) - MIT License
- Uses [Plyr](https://plyr.io/) - MIT License
- Developed by [Jan Zeman](https://github.com/JanZeman)
