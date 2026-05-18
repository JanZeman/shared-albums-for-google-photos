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
if ( ! function_exists( 'md5' ) ) {
    // md5 is a PHP built-in; only here to document the dependency.
}

// Load plugin classes (orchestrator depends on the others being present).
$includes = dirname( __DIR__ ) . '/includes/';
require_once $includes . 'class-data-provider.php';
require_once $includes . 'class-renderer.php';
require_once $includes . 'class-community.php';
require_once $includes . 'class-admin-pages.php';
require_once $includes . 'class-orchestrator.php';
