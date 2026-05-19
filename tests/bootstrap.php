<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs so the plugin classes load
 * without a running WordPress installation.
 */

// WordPress constants the plugin guards against.
define( 'ABSPATH', __DIR__ . '/' );
define( 'JZSA_VERSION', '0.0.0-test' );
define( 'JZSA_PLUGIN_FILE', dirname( __DIR__ ) . '/janzeman-shared-albums-for-google-photos.php' );

// WordPress time constants.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS',   3600 );
define( 'DAY_IN_SECONDS',    86400 );
define( 'WEEK_IN_SECONDS',   604800 );
define( 'MONTH_IN_SECONDS',  2592000 );
define( 'YEAR_IN_SECONDS',   31536000 );

// WordPress functions used by the orchestrator constructor and helpers.
// Only the signatures matter; the bodies are intentionally no-ops.
if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() { return false; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) { return true; }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) { return true; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0 ) { return json_encode( $data, $options ); }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $maybeint ) { return abs( (int) $maybeint ); }
}
if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $string ) { return rtrim( $string, '/\\' ) . '/'; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) { return ''; }
}
if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin = '' ) { return ''; }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'wp_kses' ) ) {
    function wp_kses( $string, $allowed_html ) { return $string; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) { return $data; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) { return false; }
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() { return false; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'wp_unique_id' ) ) {
    function wp_unique_id( $prefix = '' ) {
        static $counter = 0;
        return $prefix . ( ++$counter );
    }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        return parse_url( $url, $component );
    }
}
if ( ! function_exists( 'sanitize_url' ) ) {
    function sanitize_url( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
}
if ( ! function_exists( 'jzsa_get_admin_capability' ) ) {
    function jzsa_get_admin_capability() { return 'edit_pages'; }
}
if ( ! function_exists( 'jzsa_get_frontend_i18n_strings' ) ) {
    function jzsa_get_frontend_i18n_strings() {
        return array(
            'playPauseSpace'        => 'Play/Pause (Space)',
            'playPause'             => 'Play/Pause',
            'openInGooglePhotos'    => 'Open in Google Photos',
            'openAlbumGooglePhotos' => 'Open album in Google Photos',
            'openMediaFullscreen'   => 'Open media %d in fullscreen',
            'downloadCurrentMedia'  => 'Download current media',
            'downloadMedia'         => 'Download media %d',
            'largeDownloadWarning'  => 'This file is larger than the configured download warning threshold.',
            'openLightbox'          => 'Open in lightbox',
            'openMediaLightbox'     => 'Open media %d in lightbox',
            'closeLightbox'         => 'Close',
            'lightboxDialogLabel'   => 'Photo viewer',
            'pauseSlideshow'        => 'Pause slideshow',
            'resumeSlideshow'       => 'Resume slideshow',
            'previousGalleryPage'   => 'Previous gallery page',
            'nextGalleryPage'       => 'Next gallery page',
        );
    }
}
if ( ! function_exists( 'md5' ) ) {
    // md5 is a PHP built-in; only here to document the dependency.
}

// HTTP stubs for DataProvider tests.
// Set $GLOBALS['jzsa_test_http_responses'] to an array keyed by URL (or '*' for any URL).
$GLOBALS['jzsa_test_http_responses'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct( $code = '', $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }
        public function get_error_message() { return $this->message; }
        public function get_error_code()    { return $this->code; }
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        $responses = $GLOBALS['jzsa_test_http_responses'] ?? array();
        if ( isset( $responses[ $url ] ) ) {
            return $responses[ $url ];
        }
        if ( isset( $responses['*'] ) ) {
            return $responses['*'];
        }
        return new WP_Error( 'http_request_failed', 'No test response configured for ' . $url );
    }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        if ( is_wp_error( $response ) ) {
            return '';
        }
        return $response['body'] ?? '';
    }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        if ( 'version' === $show ) { return '6.5'; }
        if ( 'url' === $show )     { return 'http://localhost'; }
        return '';
    }
}

// Load plugin classes (orchestrator depends on the others being present).
$includes = dirname( __DIR__ ) . '/includes/';
require_once $includes . 'class-data-provider.php';
require_once $includes . 'class-renderer.php';
require_once $includes . 'class-community.php';
require_once $includes . 'class-admin-pages.php';
require_once $includes . 'class-orchestrator.php';
