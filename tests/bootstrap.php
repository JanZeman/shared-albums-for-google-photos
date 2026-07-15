<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs so the plugin classes load
 * without a running WordPress installation.
 */

// WordPress constants the plugin guards against.
define( 'ABSPATH', __DIR__ . '/' );
define( 'JZSA_VERSION', '2.4.0' );
define( 'JZSA_PLUGIN_FILE', dirname( __DIR__ ) . '/janzeman-shared-albums-for-google-photos.php' );
define( 'JZSA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'JZSA_PLUGIN_URL', 'https://site.example/wp-content/plugins/jzsa/' );
define( 'JZSA_COMMUNITY_API_URL', 'https://community.test' );
define( 'JZSA_COMMUNITY_PLUGIN_READ_KEY', 'test-read-key' );
define( 'JZSA_VERSION_OPTION', 'jzsa_plugin_version' );
define( 'JZSA_DEFAULT_VIEWER_OPTION', 'jzsa_default_viewer' );
define( 'JZSA_VIEWER_MIGRATION_NOTICE_OPTION', 'jzsa_viewer_migration_notice' );
define( 'JZSA_VIEWER_MIGRATION_CUTOFF_VERSION', '2.4.0' );

function jzsa_get_default_viewer() {
    $value = get_option( JZSA_DEFAULT_VIEWER_OPTION, 'lightbox' );
    return in_array( $value, array( 'lightbox', 'fullscreen' ), true ) ? $value : 'lightbox';
}

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
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() { return false; }
}
if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen() {
        $screen_id = $GLOBALS['jzsa_test_current_screen_id'] ?? null;
        return $screen_id ? (object) array( 'id' => $screen_id ) : null;
    }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $GLOBALS['jzsa_test_options'][ $option ] ?? $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        $GLOBALS['jzsa_test_options'][ $option ] = $value;
        return true;
    }
}
if ( ! function_exists( 'add_option' ) ) {
    function add_option( $option, $value = '', $deprecated = '', $autoload = null ) {
        if ( isset( $GLOBALS['jzsa_test_options'][ $option ] ) ) {
            return false;
        }
        $GLOBALS['jzsa_test_options'][ $option ] = $value;
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        unset( $GLOBALS['jzsa_test_options'][ $option ] );
        return true;
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $filename ) {
        $filename = preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $filename );
        $filename = preg_replace( '/[^A-Za-z0-9._ -]/', '', $filename );
        return trim( $filename ) ?: 'media.bin';
    }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $display = true ) {
        $result = (string) $checked === (string) $current ? ' checked="checked"' : '';
        if ( $display ) { echo $result; }
        return $result;
    }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( $text ) { return addslashes( (string) $text ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return filter_var( (string) $url, FILTER_SANITIZE_URL ); }
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
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) { return dirname( (string) $file ) . '/'; }
}
if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin = '' ) {
        return 'https://site.example/wp-content/plugins/jzsa/' . ltrim( (string) $path, '/' );
    }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = '' ) { return 1 === (int) $number ? $single : $plural; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
}
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
}
if ( ! function_exists( 'wp_kses' ) ) {
    function wp_kses( $string, $allowed_html ) { return $string; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $data ) { return $data; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) { return $GLOBALS['jzsa_test_current_user_can'] ?? false; }
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() { return $GLOBALS['jzsa_test_is_user_logged_in'] ?? false; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value ) {
        $filters = $GLOBALS['jzsa_test_filters'][ $hook ] ?? null;
        if ( is_callable( $filters ) ) {
            return $filters( $value );
        }
        if ( null !== $filters ) {
            return $filters;
        }

        return $value;
    }
}
if ( ! function_exists( 'wp_rand' ) ) {
    function wp_rand( $min = 0, $max = 0 ) {
        return rand( $min, $max );
    }
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
if ( ! function_exists( '__return_true' ) ) {
    function __return_true() { return true; }
}
if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
        $GLOBALS['jzsa_test_rest_routes'][] = compact( 'namespace', 'route', 'args', 'override' );
        return true;
    }
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $response ) {
        return $response;
    }
}
if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id ) {
        return $GLOBALS['jzsa_test_posts'][ $post_id ] ?? null;
    }
}
if ( ! function_exists( 'do_shortcode' ) ) {
    function do_shortcode( $content ) {
        $GLOBALS['jzsa_test_do_shortcode_calls'][] = $content;
        return $GLOBALS['jzsa_test_do_shortcode_output'] ?? '';
    }
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
    function wp_doing_ajax() { return $GLOBALS['jzsa_test_doing_ajax'] ?? false; }
}
if ( ! function_exists( 'nocache_headers' ) ) {
    function nocache_headers() {
        $GLOBALS['jzsa_test_nocache_headers_sent'] = true;
    }
}
if ( ! function_exists( 'sanitize_url' ) ) {
    function sanitize_url( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) { return trim( strip_tags( (string) $str ) ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return strtolower( trim( filter_var( (string) $email, FILTER_SANITIZE_EMAIL ) ?: '' ) );
    }
}
if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
    }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return $GLOBALS['jzsa_test_current_user_id'] ?? 1; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key = '', $single = false ) {
        $store = $GLOBALS['jzsa_test_user_meta'][ $user_id ] ?? array();
        if ( '' === $key ) {
            return $store;
        }
        $value = $store[ $key ] ?? '';
        return $single ? $value : array( $value );
    }
}
if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( $user_id, $key, $value ) {
        $GLOBALS['jzsa_test_user_meta'][ $user_id ][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'delete_user_meta' ) ) {
    function delete_user_meta( $user_id, $key ) {
        unset( $GLOBALS['jzsa_test_user_meta'][ $user_id ][ $key ] );
        return true;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        $GLOBALS['jzsa_test_transients'][ $transient ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        return $GLOBALS['jzsa_test_transients'][ $transient ] ?? false;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $transient ) {
        unset( $GLOBALS['jzsa_test_transients'][ $transient ] );
        return true;
    }
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return true; }
}
if ( ! class_exists( 'JZSA_Test_JSON_Response' ) ) {
    class JZSA_Test_JSON_Response extends Exception {
        public bool $success;
        public mixed $data;
        public ?int $status_code;

        public function __construct( bool $success, mixed $data = null, ?int $status_code = null ) {
            parent::__construct( $success ? 'wp_send_json_success' : 'wp_send_json_error' );
            $this->success     = $success;
            $this->data        = $data;
            $this->status_code = $status_code;
        }
    }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
        throw new JZSA_Test_JSON_Response( true, $data, $status_code );
    }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
        throw new JZSA_Test_JSON_Response( false, $data, $status_code );
    }
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user() {
        return (object) array(
            'ID'         => get_current_user_id(),
            'user_email' => $GLOBALS['jzsa_test_current_user_email'] ?? 'admin@example.test',
            'display_name' => $GLOBALS['jzsa_test_current_user_display_name'] ?? 'Admin User',
        );
    }
}
if ( ! function_exists( 'home_url' ) ) {
    function home_url( $path = '', $scheme = null ) { return 'https://site.example' . $path; }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '', $scheme = 'rest' ) { return 'https://site.example/wp-json/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) { return 'https://site.example/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'get_admin_page_title' ) ) {
    function get_admin_page_title() { return $GLOBALS['jzsa_test_admin_page_title'] ?? 'Shared Albums'; }
}
if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
        $GLOBALS['jzsa_test_admin_menu_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
        return 'toplevel_page_' . $menu_slug;
    }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
        $GLOBALS['jzsa_test_admin_submenu_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
        return $parent_slug . '_page_' . $menu_slug;
    }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
        $GLOBALS['jzsa_test_enqueued_styles'][] = compact( 'handle', 'src', 'deps', 'ver', 'media' );
    }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
        $GLOBALS['jzsa_test_enqueued_scripts'][] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
    }
}
if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        $GLOBALS['jzsa_test_localized_scripts'][] = compact( 'handle', 'object_name', 'l10n' );
        return true;
    }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
        return substr( str_repeat( 'a', max( 1, $length ) ), 0, $length );
    }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) { return 'test-nonce'; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) {
        return ( $GLOBALS['jzsa_test_nonce_valid'] ?? true ) ? 1 : false;
    }
}
if ( ! function_exists( 'jzsa_get_admin_capability' ) ) {
    function jzsa_get_admin_capability() { return 'edit_pages'; }
}
if ( ! function_exists( 'jzsa_clear_album_caches' ) ) {
    function jzsa_clear_album_caches() {
        $GLOBALS['jzsa_test_clear_cache_calls'][] = 'album';
        return $GLOBALS['jzsa_test_clear_album_result'] ?? array(
            'album_transient_rows'      => 0,
            'photo_meta_transient_rows' => 0,
            'expiry_rows'               => 0,
        );
    }
}
if ( ! function_exists( 'jzsa_clear_photo_meta_caches' ) ) {
    function jzsa_clear_photo_meta_caches() {
        $GLOBALS['jzsa_test_clear_cache_calls'][] = 'photo_meta';
        return $GLOBALS['jzsa_test_clear_photo_meta_result'] ?? array(
            'album_transient_rows'      => 0,
            'photo_meta_transient_rows' => 0,
            'expiry_rows'               => 0,
        );
    }
}
if ( ! function_exists( 'jzsa_clear_all_plugin_caches' ) ) {
    function jzsa_clear_all_plugin_caches() {
        $GLOBALS['jzsa_test_clear_cache_calls'][] = 'all';
        $album_result = jzsa_clear_album_caches();
        $photo_result = jzsa_clear_photo_meta_caches();

        return array(
            'album_transient_rows'      => (int) ( $album_result['album_transient_rows'] ?? 0 ),
            'photo_meta_transient_rows' => (int) ( $photo_result['photo_meta_transient_rows'] ?? 0 ),
            'expiry_rows'               => (int) ( $album_result['expiry_rows'] ?? 0 ),
        );
    }
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
$GLOBALS['jzsa_test_http_requests']  = array();
$GLOBALS['jzsa_test_options']        = array();

	if ( ! class_exists( 'WP_Error' ) ) {
	    class WP_Error {
	        private $code;
	        private $message;
	        private $data;
	        public function __construct( $code = '', $message = '', $data = null ) {
	            $this->code    = $code;
	            $this->message = $message;
	            $this->data    = $data;
	        }
	        public function get_error_message() { return $this->message; }
	        public function get_error_code()    { return $this->code; }
	        public function get_error_data()    { return $this->data; }
	    }
	}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        $GLOBALS['jzsa_test_http_requests'][] = array(
            'method' => 'GET',
            'url'    => $url,
            'args'   => $args,
        );
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
if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( $url, $args = array() ) {
        $GLOBALS['jzsa_test_http_requests'][] = array(
            'method' => 'POST',
            'url'    => $url,
            'args'   => $args,
        );
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
if ( ! function_exists( 'wp_remote_request' ) ) {
    function wp_remote_request( $url, $args = array() ) {
        $GLOBALS['jzsa_test_http_requests'][] = array(
            'method' => $args['method'] ?? 'GET',
            'url'    => $url,
            'args'   => $args,
        );
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
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        if ( is_wp_error( $response ) ) {
            return 0;
        }
        return (int) ( $response['response']['code'] ?? $response['code'] ?? 0 );
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
if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
    function wp_remote_retrieve_header( $response, $header ) {
        if ( is_wp_error( $response ) ) {
            return '';
        }
        $headers = $response['headers'] ?? array();
        foreach ( $headers as $key => $value ) {
            if ( 0 === strcasecmp( (string) $key, (string) $header ) ) {
                return is_array( $value ) ? end( $value ) : $value;
            }
        }
        return '';
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
require_once $includes . 'class-shortcode-tools.php';
require_once $includes . 'class-community.php';
require_once $includes . 'class-admin-pages.php';
require_once $includes . 'class-orchestrator.php';
require_once $includes . 'plugin-lifecycle.php';
