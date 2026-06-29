<?php
/**
 * Admin Pages Class
 *
 * Provides the plugin admin guide and reference pages.
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Pages Class
 */
class JZSA_Admin_Pages {

	const MENU_SLUG                 = 'janzeman-shared-albums-for-google-photos';
	const SHORTCODE_PARAMETERS_SLUG = 'janzeman-shared-albums-for-google-photos-shortcode-parameters';
	const PLACEHOLDERS_SLUG         = 'janzeman-shared-albums-for-google-photos-placeholders';
	const COMMUNITY_SLUG            = 'janzeman-shared-albums-for-google-photos-community';
	const ANNOUNCEMENT_VERSION      = 'viewer-migration-1';
	const DASHBOARD_ANNOUNCEMENT_META = 'jzsa_viewer_migration_dashboard_dismissed';
	const GUIDE_ANNOUNCEMENT_META     = 'jzsa_viewer_migration_guide_dismissed';

	/**
	 * Whether Guide-page sample shortcodes should emit lazy placeholders instead
	 * of rendering full previews immediately.
	 *
	 * @var bool
	 */
	private $lazy_sample_previews = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_icon_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_init', array( $this, 'redirect_from_settings_alias' ) );
		add_filter( 'pre_do_shortcode_tag', array( $this, 'maybe_render_lazy_sample_placeholder' ), 10, 4 );
		add_action( 'admin_notices', array( $this, 'render_dashboard_announcement' ) );
		add_action( 'wp_ajax_jzsa_dismiss_announcement', array( $this, 'handle_dismiss_announcement' ) );
		add_action( 'wp_ajax_jzsa_dismiss_guide_migration', array( $this, 'handle_dismiss_guide_migration' ) );
	}

	/**
	 * Replace Guide-page sample shortcode output with a lightweight placeholder.
	 *
	 * This keeps the Guide markup unchanged while avoiding eager sample renders.
	 *
	 * @param mixed  $return Short-circuit return value.
	 * @param string $tag    Shortcode tag.
	 * @param array  $attr   Parsed shortcode attributes.
	 * @param array  $match  Regex match data from do_shortcode_tag().
	 * @return mixed
	 */
	public function maybe_render_lazy_sample_placeholder( $return, $tag, $attr, $match ) {
		unset( $attr );

		if ( ! $this->lazy_sample_previews || 'jzsa-album' !== $tag ) {
			return $return;
		}

		$shortcode = isset( $match[0] ) ? trim( $match[0] ) : '';
		if ( '' === $shortcode ) {
			return $return;
		}

		return sprintf(
			'<div class="jzsa-lazy-preview" data-initial-shortcode="%1$s" data-lazy-state="pending"><div class="jzsa-loader jzsa-loader-text-visible" role="status" aria-live="polite" aria-atomic="true" aria-label="%2$s"><div class="jzsa-loader-inner"><div class="jzsa-loader-spinner"></div><div class="jzsa-loader-text" aria-hidden="true">%2$s</div></div></div></div>',
			esc_attr( $shortcode ),
			esc_html__( 'Preview loads as you scroll.', 'janzeman-shared-albums-for-google-photos' )
		);
	}

	private function should_show_viewer_migration_notice( $dismiss_meta_key ) {
		if ( '1' !== get_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '' ) ) {
			return false;
		}

		return self::ANNOUNCEMENT_VERSION !== get_user_meta( get_current_user_id(), $dismiss_meta_key, true );
	}

	private function should_open_guide_migration_tutorial() {
		return $this->should_show_viewer_migration_notice( self::GUIDE_ANNOUNCEMENT_META );
	}

	private function render_fullscreen_migration_steps() {
		?>
		<h3><?php esc_html_e( 'Why This Change?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
		<p><?php echo wp_kses_post( __( 'Lightbox is easier to exit and stays inside the page. Fullscreen uses the browser fullscreen experience and can feel more immersive. A quick search on the internet suggests Lightbox is preferred over Fullscreen by roughly a 75:25 ratio.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
		<h3><?php esc_html_e( 'What to Do?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
		<ul>
				<li><?php esc_html_e( 'Review the Viewer Samples (21-37). They give you a quick understanding.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
			<li><?php esc_html_e( 'Decide which final viewer experience you want: Lightbox, Fullscreen, or both.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
			<li><?php echo wp_kses_post( __( 'Make the shortcode changes if needed.', 'janzeman-shared-albums-for-google-photos' ) ); ?></li>
			<li><?php esc_html_e( 'Check your photos, regardless of whether you made any changes.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
		</ul>
		<?php
	}

	private function render_guide_migration_tutorial() {
		$should_open   = $this->should_open_guide_migration_tutorial();
		$dismiss_nonce = wp_create_nonce( 'jzsa_dismiss_guide_migration' );
		?>
		<div id="jzsa-guide-migration" class="jzsa-section jzsa-viewer-migration-guide">
			<details id="jzsa-guide-migration-details"<?php echo $should_open ? ' open' : ''; ?>>
				<summary><strong><?php esc_html_e( 'Breaking Change: Manual Action Required if You Prefer Fullscreen Over Lightbox', 'janzeman-shared-albums-for-google-photos' ); ?></strong></summary>
					<p><?php esc_html_e( 'Since version 2.4.0 the Lightbox is the default viewer experience over Fullscreen. No action is required if you accept this. Continue reading if you still prefer Fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
				<?php $this->render_fullscreen_migration_steps(); ?>
						<h3 class="jzsa-migration-subheading"><?php esc_html_e( 'Do You Prefer Fullscreen?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'Use <code>viewer-toggle="fullscreen-button"</code> to keep the same functional result through the new recommended setting.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
					<p class="jzsa-migration-example"><?php echo esc_html( '[jzsa-album] -> [jzsa-album viewer-toggle="fullscreen-button"]' ); ?></p>

						<h3 class="jzsa-migration-subheading"><?php esc_html_e( 'Do You Prefer Lightbox?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses_post( __( 'Replace <code>lightbox-toggle</code> with the equivalent <code>viewer-toggle</code>, or drop it entirely since Lightbox is now the default:', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
						<p class="jzsa-migration-example"><?php echo esc_html( 'lightbox-toggle="button-only" -> viewer-toggle="lightbox-button"  (or just remove it)' ); ?></p>
						<p class="jzsa-migration-example"><?php echo esc_html( 'lightbox-toggle="click"       -> viewer-toggle="lightbox-click"' ); ?></p>
						<p class="jzsa-migration-example"><?php echo esc_html( 'lightbox-toggle="double-click" -> viewer-toggle="lightbox-double-click"' ); ?></p>
							<p><?php echo wp_kses_post( __( 'If you also had <code>fullscreen-toggle="disabled"</code> alongside it, drop that too - it is no longer needed. Leaving the old params in place still works, but they are now redundant.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>

					<h3 class="jzsa-migration-subheading"><?php esc_html_e( 'Do You Want Both for Your Visitors?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'Use <code>viewer-toggle="lightbox-button, fullscreen-button"</code> if you want visitors to choose between the two viewer modes.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
					<p class="jzsa-migration-example"><?php echo esc_html( '[jzsa-album] -> [jzsa-album viewer-toggle="lightbox-button, fullscreen-button"]' ); ?></p>
				<?php if ( $should_open ) : ?>
						<p><button type="button" class="button" id="jzsa-dismiss-guide-migration"><?php esc_html_e( 'Collapse this section', 'janzeman-shared-albums-for-google-photos' ); ?></button></p>
				<?php endif; ?>
			</details>
		</div>
		<script>
		( function() {
			var btn = document.getElementById( 'jzsa-dismiss-guide-migration' );
			var details = document.getElementById( 'jzsa-guide-migration-details' );
			if ( ! btn || ! details ) { return; }
				btn.addEventListener( 'click', function() {
					if ( ! window.confirm( '<?php echo esc_js( __( 'The section will be collapsed. You can expand it anytime or check the Parameters page for the details.', 'janzeman-shared-albums-for-google-photos' ) ); ?>' ) ) { return; }
				var data = new FormData();
				data.append( 'action', 'jzsa_dismiss_guide_migration' );
				data.append( 'nonce', '<?php echo esc_js( $dismiss_nonce ); ?>' );
				fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data } );
				details.removeAttribute( 'open' );
				btn.style.display = 'none';
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Add the plugin admin pages to the WordPress admin menu.
	 */
	public function add_admin_pages() {
		add_menu_page(
			'Shared Albums for Google Photos (by JanZeman)',
			'Shared Albums for Google Photos',
			jzsa_get_admin_capability(),
			self::MENU_SLUG,
			array( $this, 'render_guide_page' ),
			'none'
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Shared Albums for Google Photos (by JanZeman)',
			'Guide',
			jzsa_get_admin_capability(),
			self::MENU_SLUG,
			array( $this, 'render_guide_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Shared Albums Shortcode Parameters',
			'Parameters',
			jzsa_get_admin_capability(),
			self::SHORTCODE_PARAMETERS_SLUG,
			array( $this, 'render_shortcode_parameters_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Shared Albums Placeholders',
			'Placeholders',
			jzsa_get_admin_capability(),
			self::PLACEHOLDERS_SLUG,
			array( $this, 'render_placeholders_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Shared Albums Community',
			'Community',
			jzsa_get_admin_capability(),
			self::COMMUNITY_SLUG,
			array( $this, 'render_community_page' )
		);

	}

	/**
	 * Enqueue the custom admin menu icon stylesheet on all admin pages.
	 */
	public function enqueue_admin_menu_icon_style() {
		wp_enqueue_style(
			'jzsa-admin-menu-icon',
			plugins_url( 'assets/css/admin-menu-icon.css', dirname( __FILE__ ) ),
			array(),
			JZSA_VERSION
		);
	}

	/**
	 * Get the canonical Guide page URL.
	 *
	 * @return string
	 */
	public static function get_guide_page_url() {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG );
	}

	/**
	 * Get the Shortcode Parameters page URL.
	 *
	 * @return string
	 */
	public static function get_shortcode_parameters_page_url() {
		return admin_url( 'admin.php?page=' . self::SHORTCODE_PARAMETERS_SLUG );
	}

	/**
	 * Get the Placeholders page URL.
	 *
	 * @return string
	 */
	public static function get_placeholders_page_url() {
		return admin_url( 'admin.php?page=' . self::PLACEHOLDERS_SLUG );
	}

	/**
	 * Get the Community page URL.
	 *
	 * @return string
	 */
	public static function get_community_page_url() {
		return admin_url( 'admin.php?page=' . self::COMMUNITY_SLUG );
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( ! $this->is_plugin_admin_page_request() ) {
			return;
		}

		wp_enqueue_style(
			'jzsa-admin-styles',
			plugins_url( 'assets/css/admin-settings.css', dirname( __FILE__ ) ),
			array(),
			JZSA_VERSION
		);

		wp_enqueue_script(
			'jzsa-admin-settings',
			plugins_url( 'assets/js/admin-settings.js', dirname( __FILE__ ) ),
			array(),
			JZSA_VERSION,
			true
		);

		wp_localize_script(
			'jzsa-admin-settings',
			'jzsaAdminAjax',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'clearCacheNonce' => wp_create_nonce( 'jzsa_clear_cache' ),
				'previewNonce'    => wp_create_nonce( 'jzsa_shortcode_preview' ),
			)
		);
	}

	/**
	 * Check whether the current admin request belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_admin_page_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return in_array(
			$page,
			array(
				self::MENU_SLUG,
				self::SHORTCODE_PARAMETERS_SLUG,
				self::PLACEHOLDERS_SLUG,
				self::COMMUNITY_SLUG,
			),
			true
		);
	}

	/**
	 * Redirect the legacy Settings URL to the canonical top-level page.
	 */
	public function redirect_from_settings_alias() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'options-general.php' !== $pagenow || self::MENU_SLUG !== $page ) {
			return;
		}

		wp_safe_redirect( self::get_guide_page_url() );
		exit;
	}

	/**
	 * Render the shared page shell start.
	 */
	private function render_page_shell_start() {
		?>
		<div class="wrap jzsa-settings-wrap">
			<h1>
				<?php echo esc_html( get_admin_page_title() ); ?>
				<span class="jzsa-version">v<?php echo esc_html( JZSA_VERSION ); ?></span>
			</h1>
			<div class="jzsa-settings-container">
		<?php
	}

	/**
	 * Render the shared page shell end.
	 */
	private function render_page_shell_end() {
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Shortcode Parameters reference block.
	 *
	 * @param bool $show_detached_link Whether to show the open-in-new-tab link.
	 */
	private function render_shortcode_parameters_section( $show_detached_link = false ) {
		?>
		<div class="jzsa-section">
			<h2><?php esc_html_e( 'Shortcode Parameters', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
			<?php if ( $show_detached_link ) : ?>
				<p class="jzsa-help-text" style="margin-top: 0;">
					<a href="<?php echo esc_url( self::get_shortcode_parameters_page_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open this reference in a separate browser tab for faster reference and navigation.', 'janzeman-shared-albums-for-google-photos' ); ?></a>
				</p>
			<?php endif; ?>
			<?php require JZSA_PLUGIN_DIR . 'includes/admin/reference-parameters.php'; ?>
		</div>
		<?php
	}

	/**
	 * Render the Placeholders reference block.
	 *
	 * @param bool $show_detached_link Whether to show the open-in-new-tab link.
	 */
	private function render_placeholders_reference( $show_detached_link = false ) {
		?>
		<h3 style="margin-top: 28px;"><?php esc_html_e( 'What Goes in the Boxes: Placeholders', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
		<?php if ( $show_detached_link ) : ?>
			<p class="jzsa-help-text" style="margin-top: 0;">
				<a href="<?php echo esc_url( self::get_placeholders_page_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open this reference in a separate browser tab for faster reference and navigation.', 'janzeman-shared-albums-for-google-photos' ); ?></a>
			</p>
		<?php endif; ?>
		<?php require JZSA_PLUGIN_DIR . 'includes/admin/reference-placeholders.php'; ?>
		<?php
	}

	/**
	 * Render the "This Plugin Has Potential" feedback / bug-report section.
	 */
	public function render_unhappy_section() {
		?>
		<div class="jzsa-section jzsa-help-section">
			<h2><?php esc_html_e( 'This Plugin Has Potential, But...', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
			<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-warning" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #d63638; flex-shrink: 0;"></span>
				<span><?php esc_html_e( 'Found a bug or something not working right?', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://github.com/JanZeman/shared-albums-for-google-photos/issues/new" target="_blank" rel="noopener"><?php esc_html_e( 'Report it on GitHub', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong>.</span>
			</p>
			<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-lightbulb" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #dba617; flex-shrink: 0;"></span>
				<span><?php esc_html_e( 'Missing a feature or have an idea for improvement?', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/" target="_blank" rel="noopener"><?php esc_html_e( 'Request it on the support forum', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong>.</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the "Enjoying This Plugin" review / coffee section.
	 */
	public function render_happy_section() {
		?>
		<div class="jzsa-section jzsa-happy-section">
			<h2><?php esc_html_e( 'Enjoying This Plugin?', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
			<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #f0ad4e; flex-shrink: 0;"></span>
				<span><?php esc_html_e( 'If this plugin saved you time or made your site better, please help others find it too!', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a 5-star review', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong>.</span>
			</p>
			<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-share" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #1d9bf0; flex-shrink: 0;"></span>
				<span><?php esc_html_e( 'Spread the word and share on', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://twitter.com/intent/tweet?text=My%20website%20now%20shares%20beautiful%20Google%20Photos%20galleries%20thanks%20to%20the%20Shared%20Albums%20for%20Google%20Photos%20plugin%20for%20WordPress!%20%F0%9F%93%B8&url=https%3A%2F%2Fwordpress.org%2Fplugins%2Fjanzeman-shared-albums-for-google-photos%2F" target="_blank" rel="noopener"><?php esc_html_e( 'Twitter/X', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong> <?php esc_html_e( 'or', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwordpress.org%2Fplugins%2Fjanzeman-shared-albums-for-google-photos%2F" target="_blank" rel="noopener"><?php esc_html_e( 'Facebook', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong>.</span>
			</p>
			<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
				<img src="<?php echo esc_url( plugins_url( 'assets/BuyMeACoffee_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
				<span><?php esc_html_e( "I’ll take my family to a local café and somehow justify all the late-night coding ;) ", 'janzeman-shared-albums-for-google-photos' ); ?> <strong><a href="https://www.buymeacoffee.com/janzeman" target="_blank" rel="noopener"><?php esc_html_e( 'Buy me a coffee', 'janzeman-shared-albums-for-google-photos' ); ?></a></strong>.</span>
			</p>
			<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
				<img src="<?php echo esc_url( plugins_url( 'assets/Photographer_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
				<span><?php esc_html_e( 'Made by a hobbyist WordPress developer and occasional photographer. Thank you for using this plugin!', 'janzeman-shared-albums-for-google-photos' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Community page.
	 */
	public function render_community_page() {
		$this->render_page_shell_start();
		JZSA_Community::render_content();
		$this->render_page_shell_end();
	}

	/**
	 * Render the dedicated Shortcode Parameters page.
	 */
	public function render_shortcode_parameters_page() {
		$this->render_page_shell_start();
		$this->render_shortcode_parameters_section();
		$this->render_page_shell_end();
	}

	/**
	 * Render the dedicated Placeholders page.
	 */
	public function render_placeholders_page() {
		$this->render_page_shell_start();
		?>
		<div class="jzsa-section jzsa-photo-info-section">
			<?php $this->render_placeholders_reference(); ?>
		</div>
		<?php
		$this->render_page_shell_end();
	}

	/**
	 * Render the Guide page.
	 */
	public function render_guide_page() {
		$album_sample_link = 'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';
		$video_sample_link = 'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB';
		$info_sample_link  = 'https://photos.google.com/share/AF1QipP01V2WM2fQU0yULcm5tnV4zi-9XEO2Qg7idoHWvD2_bU8aKnrDignNSucfRaMy_w?key=LUlWRm9YdEhnSEtMUGI2MnFIcDRyVElweTJkS0FR';
		$this->lazy_sample_previews = true;

		// Reusable UI strings - define once, echo everywhere below.
		$s_copy            = esc_html__( 'Copy', 'janzeman-shared-albums-for-google-photos' );
		$s_apply           = esc_html__( 'Apply', 'janzeman-shared-albums-for-google-photos' );
		$s_revert          = esc_html__( 'Revert', 'janzeman-shared-albums-for-google-photos' );
		$s_fullscreen_hint = esc_html__( 'Open fullscreen to see the effect', 'janzeman-shared-albums-for-google-photos' );
		$s_lightbox_hint   = esc_html__( 'Click a photo to open the lightbox', 'janzeman-shared-albums-for-google-photos' );
		?>
		<div class="wrap jzsa-settings-wrap">
			<h1>
				<?php echo esc_html( get_admin_page_title() ); ?>
				<span class="jzsa-version">v<?php echo esc_html( JZSA_VERSION ); ?></span>
			</h1>

			<div class="jzsa-settings-container">
				<?php $this->render_guide_migration_tutorial(); ?>

				<!-- Purpose / Scope Section -->
						<div class="jzsa-section jzsa-section-purpose">
							<div class="jzsa-attention-box jzsa-attention-purpose">
								<strong class="jzsa-purpose-heading" style="font-size:18px; margin-bottom:6px;">
									<?php esc_html_e( 'What This Plugin Does - and What It Doesn\'t', 'janzeman-shared-albums-for-google-photos' ); ?>
								</strong>
						<p style="margin: 16px 0 0 0;">
							<?php esc_html_e( 'This plugin renders one Google Photos album per shortcode. It does not provide any layout mechanism for multiple albums. One [jzsa-album] shortcode will always render only one given album. To display many albums together, build your own layout with one shortcode per album - for example using columns, the Query Loop block, or any page builder of your choice.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
						<div class="jzsa-purpose-diagram-wrapper">
							<svg class="jzsa-purpose-diagram" viewBox="0 0 360 121" role="img" aria-labelledby="jzsa-purpose-diagram-title jzsa-purpose-diagram-desc">
								<title id="jzsa-purpose-diagram-title"><?php esc_html_e( 'One album versus multi-album page layout', 'janzeman-shared-albums-for-google-photos' ); ?></title>
								<desc id="jzsa-purpose-diagram-desc"><?php esc_html_e( 'Left: we render your individual albums, one per shortcode. Right: you create the overall layout of multiple albums on your page.', 'janzeman-shared-albums-for-google-photos' ); ?></desc>

								<!-- Left: one album gallery rendered by this plugin -->
								<rect x="1" y="10" width="140" height="99" rx="6" class="jzsa-purpose-panel jzsa-purpose-panel-single" />
								<text x="72" y="23" text-anchor="middle" class="jzsa-purpose-label">
									<?php esc_html_e( 'We take care of', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<image href="<?php echo esc_url( JZSA_PLUGIN_URL . 'assets/icon-256x256.gif' ); ?>" x="36" y="28" width="65" height="65" />
								<text x="72" y="101" text-anchor="middle" class="jzsa-purpose-label">
									<?php esc_html_e( 'one album per shortcode.', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>

								<!-- Right: multi-album page layout built outside this plugin -->
								<rect x="169" y="10" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-1" />
								<rect x="250" y="10" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-2" />
								<rect x="169" y="36" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-3" />
								<rect x="250" y="36" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-4" />
								<rect x="169" y="62" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-5" />
								<rect x="250" y="62" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-6" />
								<rect x="169" y="88" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-7" />
								<rect x="250" y="88" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-8" />

								<text x="207" y="21" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-1">
									<?php esc_html_e( 'You', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="287" y="21" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-2">
									<?php esc_html_e( 'must', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="207" y="47" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-3">
									<?php esc_html_e( 'ensure', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
									<text x="287" y="47" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-4">
										<?php esc_html_e( 'the', 'janzeman-shared-albums-for-google-photos' ); ?>
									</text>
									<text x="207" y="73" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-5">
										<?php esc_html_e( 'best', 'janzeman-shared-albums-for-google-photos' ); ?>
									</text>
									<text x="287" y="73" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-6">
										<?php esc_html_e( 'multi-album', 'janzeman-shared-albums-for-google-photos' ); ?>
									</text>
									<text x="207" y="99" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-7">
										<?php esc_html_e( 'user', 'janzeman-shared-albums-for-google-photos' ); ?>
									</text>
								<text x="287" y="99" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-8">
									<?php esc_html_e( 'experience.', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
							</svg>
						</div>
					</div>
				</div>

				<?php $this->render_unhappy_section(); ?>
				<?php $this->render_happy_section(); ?>

				<!-- Quick Onboarding Section -->
				<div class="jzsa-section">
					<h2><?php esc_html_e( 'Quick Onboarding', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p class="jzsa-intro">
						<?php esc_html_e( 'What you see here on the Settings page is what you and your visitors will get later on. Follow these five quick steps:', 'janzeman-shared-albums-for-google-photos' ); ?>
					</p>

					<div class="jzsa-step">
						<div class="jzsa-step-number">1</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Understand What Your Visitors Will See', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use the Playground below to explore the sample album: try the main controls, enter and exit fullscreen, understand what your visitors will experience.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">2</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Browse the Samples Further Below', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Scroll down to the Samples section to see different shortcode configurations with descriptions, ready to copy and adapt.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">3</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Try the Samples in the Playground', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Copy some of the sample shortcodes, paste them into the Playground textarea on this page and experiment with shortcode modifications.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">4</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Try a Shortcode on Your Own Page', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'When you are happy with a configuration, copy that shortcode into one of your own pages or posts and preview it there.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">5</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Switch to Your Own Albums', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Finally, replace the sample link in the shortcode with share links from your own Google Photos albums so your visitors see your real content.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Playground Section -->
				<div class="jzsa-section jzsa-playground-section">
					<h2><?php esc_html_e( 'Playground', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p class="jzsa-intro">
						<?php esc_html_e( 'Use this area to experiment with the [jzsa-album] shortcode. You can paste your own shortcode here and adjust it before using it on a page or post.', 'janzeman-shared-albums-for-google-photos' ); ?>
					</p>
					<?php
						$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" limit="12" corner-radius="16" mosaic="true" mosaic-count="10"]';
					?>

					<div class="jzsa-code-block jzsa-playground-code-block">
						<code
							id="jzsa-playground-shortcode"
							class="jzsa-editable-code"
							contenteditable="true"
							spellcheck="false"
							role="textbox"
							aria-multiline="true"
							aria-label="<?php esc_attr_e( 'Shortcode to test', 'janzeman-shared-albums-for-google-photos' ); ?>"
						><?php echo esc_html( $sample_shortcode ); ?></code>
						<div class="jzsa-code-block-btns">
							<button type="button" class="jzsa-action-btn" data-jzsa-action="copy"><?php echo $s_copy; ?></button>
							<button type="button" class="jzsa-action-btn" data-jzsa-action="apply"><?php echo $s_apply; ?></button>
							<button type="button" class="jzsa-action-btn" data-jzsa-action="revert"><?php echo $s_revert; ?></button>
						</div>
					</div>

					<div
						class="jzsa-preview-container jzsa-playground-preview"
						aria-live="polite"
					>
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
				</div>

				<!-- Plugin Cache Section -->
				<div class="jzsa-section jzsa-cache-section">
					<h2><?php esc_html_e( 'Plugin Cache', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<div class="jzsa-cache-row">
							<div class="jzsa-cache-info">
								<?php esc_html_e( 'Server-side caching is important because it significantly reduces requests to Google Photos and speeds up page loads. Only clear the cache when you need fresh data from Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?>
							</div>
							<div class="jzsa-cache-action">
								<div class="jzsa-cache-actions-row">
									<button type="button" id="jzsa-clear-cache-btn" class="button button-secondary" data-jzsa-clear-cache-scope="all" data-jzsa-idle-label="<?php echo esc_attr__( 'Clear All Cache', 'janzeman-shared-albums-for-google-photos' ); ?>">
										<?php esc_html_e( 'Clear All Cache', 'janzeman-shared-albums-for-google-photos' ); ?>
									</button>
								<button type="button" class="button-link" data-jzsa-clear-cache-scope="album" data-jzsa-idle-label="<?php echo esc_attr__( 'Clear Album Cache', 'janzeman-shared-albums-for-google-photos' ); ?>">
									<?php esc_html_e( 'Clear Album Cache', 'janzeman-shared-albums-for-google-photos' ); ?>
								</button>
									<button type="button" class="button-link" data-jzsa-clear-cache-scope="photo_meta" data-jzsa-idle-label="<?php echo esc_attr__( 'Clear Metadata Cache', 'janzeman-shared-albums-for-google-photos' ); ?>">
										<?php esc_html_e( 'Clear Metadata Cache', 'janzeman-shared-albums-for-google-photos' ); ?>
									</button>
									<span id="jzsa-clear-cache-result" class="jzsa-cache-result" aria-live="polite"></span>
								</div>	
							</div>
						</div>

					<p class="jzsa-help-text" style="margin: 16px 0 0 0;"><em><?php esc_html_e( 'Tip: Lowering album-cache-refresh makes newly added photos appear sooner, without re-fetching metadata for photos already in the metadata cache.', 'janzeman-shared-albums-for-google-photos' ); ?></em></p>

					<details class="jzsa-cache-explainer">
						<summary><?php esc_html_e( 'How the cache works', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
						<div class="jzsa-cache-explainer__body">
							<p>
								<strong><?php esc_html_e( 'Album cache', 'janzeman-shared-albums-for-google-photos' ); ?></strong> - <?php esc_html_e( 'Stores the album\'s photo list and title. Lifetime is controlled by the album-cache-refresh shortcode attribute (default: 7 days) and refreshes automatically when it expires.', 'janzeman-shared-albums-for-google-photos' ); ?><br>
								<strong><?php esc_html_e( 'If cleared:', 'janzeman-shared-albums-for-google-photos' ); ?></strong> <?php esc_html_e( 'The plugin re-fetches the full photo list from Google Photos on the next page load, picking up any added or removed photos. Per-photo metadata is not affected.', 'janzeman-shared-albums-for-google-photos' ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Metadata cache', 'janzeman-shared-albums-for-google-photos' ); ?></strong> - <?php esc_html_e( 'Stores per-photo data, filename, description, camera, and EXIF (aperture, shutter, ISO, focal length). Fixed 30-day lifetime, fully independent of the album cache. Populated lazily - metadata is fetched the first time a photo is viewed, then served from cache on all later visits.', 'janzeman-shared-albums-for-google-photos' ); ?><br>
								<strong><?php esc_html_e( 'If cleared:', 'janzeman-shared-albums-for-google-photos' ); ?></strong> <?php esc_html_e( 'Each photo\'s metadata is re-fetched from Google Photos on the next visit. Use this when you have updated photo descriptions in Google Photos and want the changes reflected in the gallery. Avoid clearing this routinely as re-fetching metadata for large albums is heavier.', 'janzeman-shared-albums-for-google-photos' ); ?>
							</p>
							<p>
								<?php esc_html_e( 'This section describes the server-side', 'janzeman-shared-albums-for-google-photos' ); ?> <strong><?php esc_html_e( 'plugin cache.', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
								<ul style="margin: 6px 0 0 16px; list-style: disc;">
									<li><?php esc_html_e( 'It is not about WP caching plugins (e.g. WP Fastest Cache, WP Super Cache) - clear those separately via their own recommendations if needed.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
									<li><?php esc_html_e( 'Neither is it about your local browser cache - that is something you manage through your browser settings.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								</ul>
							</p>
						</div>
					</details>
			</div>

				<!-- Samples Section -->
				<div class="jzsa-section jzsa-samples-section">
					<h2><?php esc_html_e( 'Samples', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p class="jzsa-intro"><?php esc_html_e( 'The samples below are grouped by topic. Each subgroup is open by default, and you can collapse or expand them all with the button below.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<p style="margin: 0 0 28px 0;">
						<button type="button" class="jzsa-action-btn" id="jzsa-toggle-sample-groups" data-collapse-label="<?php echo esc_attr__( 'Collapse sample groups', 'janzeman-shared-albums-for-google-photos' ); ?>" data-expand-label="<?php echo esc_attr__( 'Expand sample groups', 'janzeman-shared-albums-for-google-photos' ); ?>"><?php echo esc_html__( 'Collapse sample groups', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</p>
					<script>
					(function () {
						var toggle = document.getElementById( 'jzsa-toggle-sample-groups' );
						var groups = document.querySelectorAll( '.jzsa-sample-group.jzsa-collapsible-section' );
						if ( ! toggle || ! groups.length ) {
							return;
						}
						var setLabel = function () {
							var allOpen = true;
							groups.forEach( function ( group ) {
								if ( ! group.open ) {
									allOpen = false;
								}
							} );
							toggle.textContent = allOpen ? toggle.getAttribute( 'data-collapse-label' ) : toggle.getAttribute( 'data-expand-label' );
						};
						toggle.addEventListener( 'click', function () {
							var allOpen = true;
							groups.forEach( function ( group ) {
								if ( ! group.open ) {
									allOpen = false;
								}
							} );
							groups.forEach( function ( group ) {
								group.open = ! allOpen;
							} );
							setLabel();
						} );
						groups.forEach( function ( group ) {
							group.addEventListener( 'toggle', setLabel );
						} );
						setLabel();
					}() );
					</script>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Gallery Samples (1-6)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'Start here for the core gallery layouts, pagination, and the first slider examples.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 1: ' . esc_html__( 'Gallery - Limited Count, No Pagination', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses the default "gallery" mode to display album entries as a thumbnail gallery. Every cell has the same size. Tiles stay clean by default, and opening a thumbnail in fullscreen still shows the current item counter. Pagination is not required - all thumbnails are shown at once, limited only by limit.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" limit="6" gallery-gap="8"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-grid" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

							<div class="jzsa-sample-card">
									<h3><?php echo 'Sample 2: ' . esc_html__( 'Gallery - Paged (Default Page Counter)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
									<p><?php esc_html_e( 'Use gallery-rows to split the gallery into pages. By default, paginated galleries keep the tiles clean and show the page counter in the navigation bar. Use gallery-sizing="ratio" (default) to keep fixed tile aspect ratio, or gallery-sizing="fill" to stretch row heights and fill explicit control height.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<?php
								$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" gallery-rows="2" limit="18" gallery-gap="8" info-font-size="18"]';
							?>
							<div class="jzsa-code-block">
								<code><?php echo esc_html( $sample_shortcode ); ?></code>
								<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-gallery-paged" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
								<h3><?php echo 'Sample 3: ' . esc_html__( 'Gallery - Paged (Custom Page Counter)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
									<p><?php esc_html_e( 'Same as above but with a custom page counter format in the navigation bar. Use this when you want to include album context such as the album title together with the page count.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
								<?php
									$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" gallery-rows="2" limit="18" gallery-gap="8" gallery-info-bottom="{album-title}: {page} / {pages}" info-font-size="18"]';
								?>
								<div class="jzsa-code-block">
										<code><?php echo esc_html( $sample_shortcode ); ?></code>
									<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
								</div>
								<div class="jzsa-preview-container jzsa-preview-container-gallery-paged-page-pagination" style="height:auto;">
									<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											echo do_shortcode( $sample_shortcode );
									?>
							</div>
						</div>

							<div class="jzsa-sample-card">
									<h3><?php echo 'Sample 4: ' . esc_html__( 'Gallery - Paged (Tile Counter Override)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
									<p><?php esc_html_e( 'Same paged gallery layout, but with per-tile item numbers instead of the page counter in the navigation bar. Use this when you want to keep pagination while labeling each tile with its item position in the album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
								<?php
									$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" gallery-rows="2" limit="18" gallery-gap="8" info-bottom="{item} / {items}" gallery-info-bottom=""]';
								?>
								<div class="jzsa-code-block">
									<code><?php echo esc_html( $sample_shortcode ); ?></code>
									<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
								</div>
								<div class="jzsa-preview-container jzsa-preview-container-gallery-paged-page-pagination" style="height:auto;">
									<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo do_shortcode( $sample_shortcode );
									?>
								</div>
							</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 5: ' . esc_html__( 'Gallery - Scrollable Instead Of Paged', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use gallery-scrollable="true" with gallery-rows to show a fixed-height, vertically scrollable gallery instead of page controls.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<div class="jzsa-warning-box">
								<strong><?php esc_html_e( 'Mobile note: nested scrolling', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
								<p><?php esc_html_e( 'This mode creates two nested scroll areas (the gallery and the page). On touch screens, visitors may accidentally scroll the page instead of the gallery, making it appear as if all photos are shown at once.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
								<p><?php esc_html_e( 'If that is a concern, consider Sample 4 (paged gallery) instead. If you do use this mode on mobile, place the gallery in a two-column WordPress layout with the second column left empty and "Stack on mobile" turned off. The visible gap beside the gallery signals to visitors that the gallery is a self-contained scroll area.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							</div>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" gallery-rows="2" gallery-scrollable="true" gallery-gap="8" limit="18"]';
						?>
						<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-scrollable" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 6: ' . esc_html__( 'Gallery - Justified Layout', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses gallery-layout="justified" so photos keep their natural aspect ratios and fill each row edge-to-edge, similar to Google Photos. Use the fullscreen button on any thumbnail to open it in a fullscreen viewer.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" corner-radius="16" gallery-layout="justified" gallery-row-height="180" limit="7" gallery-gap="8"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-justified" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Slider basics and playback (Samples 7-20)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'Use these samples to understand the slider, playback, cropping, source size, and slideshow behavior before you reach the viewer block.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 7: ' . esc_html__( 'Slider - Basic Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'A basic slider example using mode="slider" with rounded corners.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-basic">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 8: ' . esc_html__( 'Custom Size Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Set the preview width and height so they fit your page layout.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="800" height="600" image-fit="contain"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-custom-size">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 9: ' . esc_html__( 'Hide Navigation Arrows', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Hides previous/next arrows. Useful for headless slideshows such as digital signage. Swipe and keyboard navigation still work.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" slideshow="auto" show-navigation="false" info-bottom="" viewer-toggle="disabled"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
						<div class="jzsa-preview-container jzsa-preview-container-hide-navigation">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 10: ' . esc_html__( 'Interaction Lock (Controls and Navigation Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Uses interaction-lock="true" as a <strong>hard override</strong> for interactions: swipe/drag, keyboard navigation, click/tap navigation, and fullscreen entry are disabled. Notice that all navigation buttons are hidden despite the shortcode explicitly enabling them (show-link-button, show-download-button, viewer-toggle). Counter and slideshow countdown stay visible.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" viewer-toggle="fullscreen-click" show-link-button="true" show-download-button="true" slideshow="auto" slideshow-delay="2" interaction-lock="true"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-interaction-lock">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

							<div class="jzsa-sample-card">
								<h3><?php echo 'Sample 11: ' . esc_html__( 'Limit Number of Entries Per Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<p><?php esc_html_e( 'Load only a limited number of album entries from a large mixed album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" limit="5"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-limit-photos">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 12: ' . esc_html__( 'Custom Slideshow Speed', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slideshow here is set to one second. You can easily see the difference in speed compared to the sample above :)', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" slideshow="auto" slideshow-delay="1"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-slower-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 13: ' . esc_html__( 'Random Start without Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Starts at a random photo with slideshow disabled. Each page load shows a different photo, but the viewer stays on it until the user navigates manually.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" start-at="random" slideshow="disabled"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 14: ' . esc_html__( 'Disable Cropping and Set Custom Background', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows photos fully without cropping by using image-fit="contain". This exposes the background color. Here we set it to yellow to make it very visible.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" image-fit="contain" background-color="#FFE50D"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-crop">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php echo 'Sample 15: ' . esc_html__( 'Custom Background Color for Fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Same as above but with fullscreen-background-color="#0000FF" to override the fullscreen background to blue, while the inline background is transparent.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" image-fit="contain" background-color="transparent" fullscreen-background-color="#0000FF"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-bg-color">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 16: ' . esc_html__( 'High-Resolution Inline Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Compare default resolution (left) with high-resolution source (right). Both use the same container size and image-fit="cover".', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$low_res_shortcode  = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="400" height="480" corner-radius="16" image-fit="cover" slideshow="auto" slideshow-delay="5" source-width="400" source-height="300"]';
						$high_res_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="400" height="480" corner-radius="16" image-fit="cover" slideshow="auto" slideshow-delay="5" source-width="1920" source-height="1440"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $low_res_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $high_res_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-hires-inline" style="display: flex; gap: 20px; justify-content: center;">
						<div>
							<p><strong><?php esc_html_e( 'Low (400×300)', 'janzeman-shared-albums-for-google-photos' ); ?></strong></p>
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $low_res_shortcode );
							?>
						</div>
						<div>
							<p><strong><?php esc_html_e( 'High-Res (1920×1440)', 'janzeman-shared-albums-for-google-photos' ); ?></strong></p>
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $high_res_shortcode );
							?>
						</div>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php echo 'Sample 17: ' . esc_html__( 'High-Resolution Fullscreen Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Request extra-high-resolution photos for fullscreen mode. The default fullscreen resolution is 1920x1440. Increase for 4K displays.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="300" corner-radius="16" fullscreen-source-width="2560" fullscreen-source-height="1700"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-hires-fullscreen">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php echo 'Sample 18: ' . esc_html__( 'Low-Resolution Fullscreen Photos with Limited Display Size', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Combine a smaller fullscreen source image with a smaller centered fullscreen display box. Useful when you want fullscreen mode, but do not want the photo to expand wall-to-wall across the screen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="300" corner-radius="16" fullscreen-source-width="512" fullscreen-source-height="340" fullscreen-max-width="640" fullscreen-max-height="425"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-lowres-fullscreen-limited">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 19: ' . esc_html__( 'Manual Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'The play/pause button is shown but the slideshow does not start automatically. The user must press play to begin auto-advancing.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" slideshow="manual" slideshow-delay="10" fullscreen-slideshow="manual" fullscreen-slideshow-delay="10"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-manual-slideshow">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 20: ' . esc_html__( 'Slideshow with Autostart and Autoresume', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'When the slideshow is running and you swipe or click to navigate manually, the slideshow is interrupted and pauses. After 20 seconds of inactivity it resumes automatically. Try it: let the slideshow advance, then swipe manually and wait. Note: if you stop the slideshow via the pause button, it stays stopped - autoresume only applies to interruptions by manual navigation.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" slideshow="auto" fullscreen-slideshow="auto" slideshow-autoresume="20"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-autoplay-timeout">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Viewer Samples (21-37)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'These samples are grouped by Viewer navigation, Viewer styling, and Viewer slideshow behavior.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Viewer Navigation Samples (21-29)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 21: ' . esc_html__( 'Viewer - Default Behaviour', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							</div>
							<p><?php esc_html_e( 'Viewer is not opened from the photo. The user must tap the dedicated button to enter it. It is in the default mode, so it opens as Lightbox. This default is the same as viewer-toggle="lightbox-button". Inside Lightbox, one button returns to the inline view and another button enters fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-gallery-grid" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 22: ' . esc_html__( 'Viewer Toggle - Disabled', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use viewer-toggle="disabled" when the inline slider should stay inline only. No Viewer button appears and clicking the photo does not open Lightbox or Fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="disabled"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-disabled">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 23: ' . esc_html__( 'Viewer Toggle - Lightbox from Photo Click', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php echo $s_lightbox_hint; ?></span>
							</div>
							<p><?php esc_html_e( 'Use lightbox-click when the photo itself should open Lightbox. This is direct and easy to understand, but click is now used for opening the overlay.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-click"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-gallery-grid" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 24: ' . esc_html__( 'Viewer Toggle - Lightbox from Double-Click', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Use lightbox-double-click when <strong>mouse gestures should have a clear split</strong>: single click stays available for previous and next navigation, while double-click is dedicated to opening or closing the Viewer. <strong>This avoids accidental opening and keeps browsing natural.</strong> To improve discoverability, the plugin shows a short first-time Lightbox hint after the visitor opens it this way.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-double-click"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-double">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 25: ' . esc_html__( 'Viewer Toggle - Fullscreen Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use fullscreen-button when you want native browser fullscreen instead of Lightbox. Lightbox is disabled, and Fullscreen opens only through the dedicated fullscreen button. This keeps normal clicks available for browsing.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-button"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-button">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 26: ' . esc_html__( 'Viewer Toggle - Fullscreen from Photo Click', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use fullscreen-click when clicking the photo should enter native fullscreen immediately. This is the most direct fullscreen entry, but it changes what a normal click does.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-click"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-single">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 27: ' . esc_html__( 'Viewer Toggle - Fullscreen from Double-Click', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
							</div>
							<p><?php esc_html_e( 'Use fullscreen-double-click when you want a gesture shortcut but do not want single click to enter fullscreen. This is usually safer than fullscreen-click. After the visitor enters fullscreen, the plugin also shows a short first-time hint with fullscreen navigation and exit guidance.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-double-click"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-double">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 28: ' . esc_html__( 'Viewer Toggle - Lightbox and Fullscreen Side-by-Side', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Left button opens lightbox, right button enters fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'This is the simplest Lightbox and Fullscreen combination: both modes are exposed as explicit buttons, and mouse gestures are disabled. This is powerful, but it asks visitors to understand the difference between Lightbox and Fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 29: ' . esc_html__( 'Viewer Toggle - Mixed Button and Gesture', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Lightbox uses double-click, fullscreen uses a button', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'A combined value can use one mode as a gesture and the other as a button. Here double-click opens Lightbox, while the button enters native Fullscreen. This can be useful, but it may be too complex for some visitors, so the admin should decide whether the extra choice is worth it.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-double-click, fullscreen-button"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-double">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Viewer Shared Settings and Overrides Samples (30-35)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'These samples show the three-tier viewer model: viewer-* sets the shared baseline, while lightbox-* and fullscreen-* override one mode only.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 30: ' . esc_html__( 'Viewer Size - Control Display Width and Height', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Compare Lightbox and Fullscreen with buttons', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'After choosing how Viewer opens, you can size it. The shared viewer-max-width and viewer-max-height settings apply to both Lightbox and Fullscreen, so the photo size in both modes is effectively identical. Enter both modes and check for yourself.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="600" viewer-max-height="400"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 31: ' . esc_html__( 'Viewer Size - Fullscreen Override', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Lightbox stays small, Fullscreen gets a larger limit', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'The shared viewer-max-width and viewer-max-height values set the baseline. The fullscreen-max-width and fullscreen-max-height values override that baseline for Fullscreen only, so Lightbox keeps the smaller shared size.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="600" viewer-max-height="400" fullscreen-max-width="1200" fullscreen-max-height="800"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 32: ' . esc_html__( 'Viewer Fit - Make Photos Fill Their Frame', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Both modes use the same cover fit', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'The shared viewer-image-fit setting applies to both Lightbox and Fullscreen. Its default value is contain, but in this sample we set it to cover. Cover fit fills the available box more aggressively, which may crop photo edges in both modes. What matters here is that Lightbox and Fullscreen behave the same way for this setting.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-image-fit="cover"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 33: ' . esc_html__( 'Viewer Fit - Fullscreen Override', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Lightbox contains, Fullscreen covers', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'The shared/default fit stays contain for Lightbox. The parameter fullscreen-image-fit changes Fullscreen only, so Fullscreen uses cover while Lightbox remains contained.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" fullscreen-image-fit="cover"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 34: ' . esc_html__( 'Viewer Colors - Background and Backdrop', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Compare viewer background with Lightbox backdrop', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Viewer colors split into two surfaces. The shared viewer-background-color setting controls the box behind the photo in both Lightbox and Fullscreen. Open both modes to compare it, then open Lightbox to see the separate lightbox-backdrop-color behind the box.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" image-fit="contain" background-color="rgba(128,0,64,0.7)" viewer-toggle="lightbox-button, fullscreen-button" lightbox-corner-radius="16" viewer-max-width="600" viewer-max-height="400" viewer-background-color="rgba(128,0,64,0.7)" lightbox-backdrop-color="rgba(0,128,64,0.7)"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-switch-button">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<div class="jzsa-sample-card-header">
								<h3><?php echo 'Sample 35: ' . esc_html__( 'Viewer Controls - Lightbox Override', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Lightbox controls use a different color', 'janzeman-shared-albums-for-google-photos' ); ?></span>
							</div>
							<p><?php esc_html_e( 'The shared viewer-controls-color value sets the baseline for both modes. The lightbox-controls-color value overrides that baseline for Lightbox only, so Fullscreen keeps the shared color.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-controls-color="#E63946" lightbox-controls-color="#00A878"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Viewer Slideshow and Start Behavior Samples (36-37)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 36: ' . esc_html__( 'Fullscreen Slideshow Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'The inline slider stays static. When Fullscreen opens, the Fullscreen slideshow starts automatically.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="fullscreen-button" fullscreen-slideshow="auto"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fullscreen-only">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 37: ' . esc_html__( 'Viewer Slideshow - Different Delays Per Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'The shared viewer-slideshow value starts slideshows automatically in both viewer modes. Lightbox advances every 1 second, while Fullscreen waits a random 7 to 9 seconds between photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="600" corner-radius="16" viewer-toggle="lightbox-button, fullscreen-button" viewer-slideshow="auto" lightbox-slideshow-delay="1" fullscreen-slideshow-delay="7-9"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-dual-expand">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						</details>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Buttons, colors, and carousel (Samples 38-43)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'Use these samples for button visibility, color accents, and the carousel layout.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 38: ' . esc_html__( 'Show "Open in Google Photos" Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-link-button parameter to display an external link button to the original album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" show-link-button="true"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-link-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 39: ' . esc_html__( 'Show Download Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-download-button parameter to add a download button for the current media item (photo or video).', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" show-download-button="true"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-download-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 40: ' . esc_html__( 'Show Link and Download Buttons - Gallery Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Gallery mode with link and download buttons on each thumbnail. Hover over a thumbnail to see the download and link buttons (top-left) appear alongside the fullscreen button (top-right).', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="gallery" width="800" corner-radius="16" show-download-button="true" show-link-button="true" limit="6"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-download-gallery">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 41: ' . esc_html__( 'Gallery Mode: Link Button in Inline and Fullscreen, Download Button in Fullscreen Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Gallery mode where the link button is enabled in both inline and fullscreen views, while the download button is shown only in fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="gallery" width="800" corner-radius="16" show-link-button="true" show-download-button="false" fullscreen-show-link-button="true" fullscreen-show-download-button="true" limit="6"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-download-gallery-fullscreen-only">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 42: ' . esc_html__( 'Custom Colors', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Example with a bright yellow controls-color and a separate yellow info-font-color, plus top info text to make the difference clearly visible.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" corner-radius="16" slideshow="auto" show-link-button="true" show-download-button="true" controls-color="#FFD400" info-font-color="#FFFF00" info-top="Info box font with a color..." info-top-secondary="... that is different from the controls"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-controls-color-custom">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 43: ' . esc_html__( 'Carousel Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses mode="carousel" to show multiple photos side by side. On mobile and tablets it shows 2 photos at a time, and on desktop it shows 3 photos. Use the fullscreen button on a photo to open it in a single-photo fullscreen viewer.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel" corner-radius="16"]';
						?>
						<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-carousel">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
						</div>
						</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Video albums (Samples 44-49)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'These samples cover video playback, video controls, and the gallery behavior around mixed media.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 44: ' . esc_html__( 'Video (Blue Accent)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Baseline video sample in slider mode with videos enabled and blue accent color.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="slider" corner-radius="16" show-videos="true" limit="8" video-controls-color="#00B2FF"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-slider">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 45: ' . esc_html__( 'Video in Carousel (Auto-Hide Controls)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Demonstrates carousel mode with video controls auto-hiding after inactivity.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="carousel" corner-radius="16" show-videos="true" limit="8" video-controls-color="#FF6B35" video-controls-autohide="true"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-carousel">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 46: ' . esc_html__( 'Video in Gallery (Button-only to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Gallery mode with videos included. Fullscreen opens via the fullscreen button only. Once in fullscreen, click left or right to navigate between items.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" width="800" corner-radius="16" show-videos="true" limit="6" gallery-layout="grid" video-controls-color="#00A878" gallery-gap="8"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-gallery">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 47: ' . esc_html__( 'Video in Gallery (Single-click to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Single-click on any thumbnail opens fullscreen. Trade-off: click can no longer navigate between items in fullscreen - use the arrow buttons instead. <strong>Consider double-click instead</strong> to keep click navigation available.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" width="800" corner-radius="16" show-videos="true" limit="6" gallery-layout="grid" viewer-toggle="fullscreen-click" video-controls-color="#E0527E" gallery-gap="8"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-gallery-click">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 48: ' . esc_html__( 'Video in Gallery (Double-click to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Double-click (or double-tap) on any thumbnail opens fullscreen; double-click again to exit. <strong>Recommended over single-click</strong>: click still navigates between items in fullscreen, and the gesture is less likely to be triggered accidentally.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" width="800" show-videos="true" limit="6" gallery-layout="grid" viewer-toggle="fullscreen-double-click" video-controls-color="#7A5CFF" gallery-gap="8"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-gallery-dblclick">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 49: ' . esc_html__( 'Photos-Only Sample (Videos Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses show-videos="false" to filter out videos from the same mixed album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" width="800" corner-radius="16" show-videos="false" limit="6" viewer-toggle="fullscreen-double-click" video-controls-color="#7A5CFF" gallery-gap="8"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-disabled">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						</details>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Mosaic layouts (Samples 50-56)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'This block focuses on the mosaic strip in slider, carousel, and fullscreen modes.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 50: ' . esc_html__( 'Slider - Mosaic Strip at the Bottom', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slider with a horizontal thumbnail strip below the main photo. Click any thumbnail to jump to that photo. By default, the thumbnails apply the same corner radius as the main photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="800" height="600" corner-radius="16" mosaic="true" mosaic-position="bottom" mosaic-count="12"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-bottom">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 51: ' . esc_html__( 'Slider - Mosaic Strip With Explicit Rounded Corners', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Same as above, but with square slider corners via corner-radius="0" and rounded corners only on the thumbnail strip via mosaic-corner-radius="16".', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="800" height="600" corner-radius="0" mosaic="true" mosaic-position="bottom" mosaic-count="12" mosaic-corner-radius="16"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-rounded">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 52: ' . esc_html__( 'Slider - Mosaic Strip on the Right', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slider with a vertical thumbnail strip on the right side. Great for landscape photos where the strip can use the full height.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="800" height="600" corner-radius="16" mosaic="true" mosaic-position="right"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-right">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 53: ' . esc_html__( 'Mosaic Strip with Carousel', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Carousel mode with a thumbnail strip at the bottom. The carousel shows multiple photos at once; the mosaic strip provides an overview of the full album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="carousel" width="800" height="600" corner-radius="24" mosaic="true" mosaic-position="bottom" mosaic-count="18"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-carousel" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 54: ' . esc_html__( 'Slider - Mosaic Strip with Custom Gap and Opacity', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Demonstrates mosaic-gap and mosaic-opacity together. A tighter gap between thumbnails and a lower inactive opacity create a stronger visual contrast between the active and inactive thumbnails.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $album_sample_link . '" mode="slider" width="800" height="600" corner-radius="16" mosaic="true" mosaic-position="bottom" mosaic-count="12" mosaic-gap="16" mosaic-opacity="0.7"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-gap-opacity" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php echo 'Sample 55: ' . esc_html__( 'Fullscreen Mosaic Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Combines the inline mosaic strip with fullscreen-mosaic="true". In fullscreen, the default layout now reserves a dedicated rail for the thumbnail strip so it no longer sits on top of the photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" mosaic="true" fullscreen-mosaic="true" mosaic-count="8" fullscreen-mosaic-count="16" fullscreen-slideshow="auto" limit="24"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fullscreen-mosaic">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php echo 'Sample 56: ' . esc_html__( 'Fullscreen Mosaic - Overlay Layout', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Adds fullscreen-mosaic-layout="overlay" and fullscreen-image-fit="cover". The thumbnail strip floats on top of the photo in fullscreen instead of occupying a separate rail, and the photo fills the full screen behind it for a cinema-style experience.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" mosaic="true" fullscreen-mosaic="true" mosaic-count="8" fullscreen-mosaic-count="16" fullscreen-slideshow="auto" limit="24" fullscreen-image-fit="cover" fullscreen-mosaic-layout="overlay"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fullscreen-mosaic-overlay" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

					</details>

				</div>

<!-- Start Tutorial -->
				<div class="jzsa-section jzsa-tutorial-section">
					<h2><?php esc_html_e( 'Now Use Your Own Albums', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p class="jzsa-intro"><?php esc_html_e( 'After experimenting with the sample album above, follow these simple steps to embed your own Google Photos albums in your posts or pages:', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<!-- Step 1 -->
					<div class="jzsa-step">
						<div class="jzsa-step-number">1</div>
						<div class="jzsa-step-content">
						<h3><?php esc_html_e( 'Open Your Google Photos Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p>
								<?php
								printf(
									/* translators: %s: Google Photos albums URL. */
									esc_html__( 'Go to %s to see the collection of your albums.', 'janzeman-shared-albums-for-google-photos' ),
									'<a href="https://photos.google.com/albums" target="_blank" rel="noopener">' . esc_html__( 'Google Photos', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
								);
								?>
							</p>
						</div>
					</div>

					<!-- Step 2 -->
					<div class="jzsa-step">
						<div class="jzsa-step-number">2</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Get the Share Link', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<ol class="jzsa-substeps">
								<li><?php esc_html_e( 'Select the album you want to share', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								<li><?php esc_html_e( 'Click the Share button (or three-dot menu → Share)', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								<li><?php esc_html_e( 'Click "Create link" and confirm in the dialog', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								<li><?php esc_html_e( 'Close the dialog - no need to copy the link; we will use its longer form below', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								<li><?php esc_html_e( 'Verify link sharing is on: the chain icon is visible below the album title', 'janzeman-shared-albums-for-google-photos' ); ?></li>
								<li><strong><?php esc_html_e( 'Important:', 'janzeman-shared-albums-for-google-photos' ); ?></strong> <?php esc_html_e( 'Click in the browser address bar and copy the FULL ALBUM LINK', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							</ol>
							<div class="jzsa-warning-box">
								<strong><?php esc_html_e( 'Use Full Links Only', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
								<p><?php esc_html_e( 'Make sure your link looks like this:', 'janzeman-shared-albums-for-google-photos' ); ?></p>
								<code class="jzsa-code-good">https://photos.google.com/share/AF1QipN...</code>
								<p style="margin-top: 8px;">
									<?php esc_html_e( 'Short photos.app.goo.gl links are deprecated; for best reliability always use the full https://photos.google.com/share/... link from your browser\'s address bar.', 'janzeman-shared-albums-for-google-photos' ); ?>
								</p>
							</div>
						</div>
					</div>

					<!-- Step 3 -->
					<div class="jzsa-step">
						<div class="jzsa-step-number">3</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Add the Shortcode to Your Post', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'In your WordPress post or page editor, add the shortcode:', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="YOUR_LINK_HERE"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
							<p class="jzsa-help-text"><?php esc_html_e( 'Replace YOUR_LINK_HERE with the full link you copied from Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<!-- Step 4 -->
					<div class="jzsa-step">
						<div class="jzsa-step-number">4</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Preview and Publish', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( "Preview your post to see the plugin in action, then publish when you're ready.", 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>
				</div>

				<?php $this->render_shortcode_parameters_section( true ); ?>

				<!-- Photo Info Overlay -->
				<div class="jzsa-section jzsa-photo-info-section">
					<h2><?php esc_html_e( 'Photo Info Overlay', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<h3><?php esc_html_e( 'Info Boxes', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php esc_html_e( 'Each photo slide (or gallery thumbnail) can display up to three inline info boxes at fixed positions. Each of those inline boxes also has a fullscreen variant. You control what goes in each box by assigning a text string - either plain text or a format string with placeholders like {date} and {item} that resolve to per-photo metadata. Boxes with empty strings are hidden.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<p><?php esc_html_e( 'The live preview below shows all three inline info boxes with descriptive labels so you can see where each box appears:', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<?php
						$info_boxes_demo_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="300" corner-radius="16" limit="7" start-at="1" slideshow="auto" slideshow-delay="10" show-link-button="true" show-download-button="true" info-bottom="Bottom" info-top="Top" info-top-secondary="Top secondary"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $info_boxes_demo_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container" style="max-width: 400px; margin-left: auto; margin-right: auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $info_boxes_demo_shortcode );
						?>
					</div>

					<p style="margin-top: 16px;"><?php esc_html_e( 'Each box has an inline variant and a fullscreen variant. When the fullscreen variant is omitted, it inherits the inline value. You can show different content in fullscreen - for example, a short date inline and a full EXIF line in fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<?php $this->render_placeholders_reference( true ); ?>

					<h3 style="margin-top: 28px;"><?php esc_html_e( 'Example Shortcodes', 'janzeman-shared-albums-for-google-photos' ); ?></h3>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Photo info and text formatting (Samples 60-69)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'Use these samples to compare info overlays, EXIF values, wrapping, and per-box alignment.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 60: ' . esc_html__( 'Slider with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows per-photo info overlays in slider mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" info-bottom="{item} / {items}" info-top="{album-title}" info-top-secondary="{filename} ({dimensions})"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-info-slider">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 61: ' . esc_html__( 'Carousel with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows per-photo info overlays in carousel mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel" corner-radius="16" info-bottom="{item} / {items}" info-top="{filename}" info-top-secondary="{dimensions}"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-info-carousel">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 62: ' . esc_html__( 'Gallery with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows per-photo info overlays on gallery thumbnails.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="gallery" width="800" corner-radius="16" limit="6" info-font-size="10" info-top="{filename}" info-top-secondary="{dimensions}"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-info-gallery" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 63: ' . esc_html__( 'Description & EXIF Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Demonstrates a Google Photos description together with EXIF-derived photo information in slider mode with a larger custom monospace font. These values may appear with a brief delay the first time and then load immediately from cache.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<p style="margin: 8px 0 0 0; display: flex; align-items: flex-start; gap: 8px;">
							<span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px; line-height: 20px; color: #dba617; flex-shrink: 0;"></span>
							<span><?php esc_html_e( 'EXIF output depends heavily on how complete and clean the metadata is across the photos in your shared album.', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						</p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="512" corner-radius="16" show-link-button="true" show-download-button="true" info-font-size="18" info-font-family="ui-monospace, SFMono-Regular, Consolas, monospace" info-top="{description}" info-top-secondary="{camera}" info-bottom="{aperture} ⸱ {shutter} ⸱ {focal} ⸱ {iso}" start-at="1" fullscreen-mosaic="true"]';
						?>
						<div class="jzsa-code-block">
								<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-info-exif">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 64: ' . esc_html__( 'EXIF Camera Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows the raw EXIF camera make and model separately, with {camera} underneath as the plugin\'s best-guess combined display value. Use the raw placeholders when you need exact source values and {camera} only when the combined output looks right for your album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="512" corner-radius="16" show-link-button="true" show-download-button="true" info-font-size="18" info-font-family="ui-monospace, SFMono-Regular, Consolas, monospace" info-top="{camera-make}" info-top-secondary="{camera-model}" info-bottom="{camera}" start-at="2"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-info-exif">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 65: ' . esc_html__( 'Long Text: Truncated (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'When text is too long it is cut off with "..." by default. Notice that info-top is intentionally narrower than info-top-secondary: it shares the top corners with action buttons (such as the fullscreen toggle), so space is reserved on both sides to avoid overlap. info-top-secondary and info-bottom have no such constraint and can use the full width.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="384" corner-radius="16" info-font-size="14" info-top="This is a sample of a very long text placed at the top of the photo" info-top-secondary="This is the secondary top text which is also very long and gets cut off with dots" info-bottom="And this is a sample of a very long text placed at the bottom" start-at="3"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 66: ' . esc_html__( 'Long Text: Wrapped (info-wrap="true")', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'The same shortcode with info-wrap="true" added. Long text breaks to a new line instead of being cut off; the pill expands vertically to fit all content. Consider reducing info-font-size slightly if the result feels too large. Note that {description} is capped at 100 characters by Google - see the placeholder reference.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="384" corner-radius="16" info-font-size="14" info-top="This is a sample of a very long text placed at the top of the photo" info-top-secondary="This is the secondary top text which is also very long and gets cut off with dots" info-bottom="And this is a sample of a very long text placed at the bottom" info-wrap="true" start-at="3"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 67: ' . esc_html__( 'Long Text: Wrapped with Link and Download Buttons', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'The same wrapped shortcode, but with show-link-button="true" and show-download-button="true" added. Both buttons appear in the top-left corner, so the plugin now reserves two slots on each side, making info-top noticeably shorter than in the previous example. This is intentional: without the extra reservation, info-top would overlap the buttons. Note that {description} is capped at 100 characters by Google - see the placeholder reference.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="384" corner-radius="16" info-font-size="14" info-top="This is a sample of a very long text placed at the top of the photo" info-top-secondary="This is the secondary top text which is also very long and gets cut off with dots" info-bottom="And this is a sample of a very long text placed at the bottom" info-wrap="true" show-link-button="true" show-download-button="true" start-at="3"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php echo 'Sample 68: ' . esc_html__( 'Text Halo Effect Per-Box Override', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'This sample keeps the global halo enabled, then disables it only for info-top-secondary with info-top-secondary-halo-effect="false". That lets you compare both treatments on the same photo while the bottom counter stays at its normal halo-enabled default.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="384" corner-radius="16" info-font-size="14" info-wrap="true" info-top="This is a text with the halo effect. It usually delivers better readability." info-top-secondary="And this is the text without that effect. Compare the differences." info-top-secondary-halo-effect="false" start-at="4"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

				  <div class="jzsa-sample-card">
						<h3><?php echo 'Sample 69: ' . esc_html__( 'Per-Box Text Alignment', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Each info box can have its own alignment using info-top-text-align, info-top-secondary-text-align, and info-bottom-text-align. These override the global info-text-align for that box only. Here info-top is left-aligned, info-top-secondary is centered, and info-bottom is right-aligned.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $info_sample_link . '" mode="slider" width="384" corner-radius="16" info-font-size="14" info-top="This text is left-aligned. This text is left-aligned." info-top-secondary="This text is centered. This text is centered." info-bottom="This text is right-aligned. This text is right-aligned." info-wrap="true" info-top-text-align="left" info-top-secondary-text-align="center" info-bottom-text-align="right" start-at="5"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
					</div>

					</div>

				</details>

			</div>

<!-- Troubleshooting -->
				<div class="jzsa-section">
					<h2><?php esc_html_e( 'Troubleshooting', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<div class="jzsa-faq">
						<h3><?php esc_html_e( 'Plugin Shows "Unable to Load Album"', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Use straight quotes in shortcode attributes: [jzsa-album link="..."] - not [jzsa-album link="…"]. The block editor (Gutenberg) may auto-convert quotes, which breaks shortcode parsing.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Make sure the album is shared publicly via link in Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Verify you are using the full link format (starts with https://photos.google.com/share/).', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Check that the album contains at least one photo.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>

						<details class="jzsa-sample-group jzsa-collapsible-section" open>
							<summary class="jzsa-collapsible-summary"><?php esc_html_e( 'Troubleshooting examples (Samples 70-71)', 'janzeman-shared-albums-for-google-photos' ); ?></summary>
							<p class="jzsa-sample-group__description"><?php esc_html_e( 'These examples show the two warning states mentioned above. Open them only when you want to see the exact admin-facing messages.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 70: ' . esc_html__( 'Sample "Unable to Load Album" Error', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'This example intentionally uses an invalid link to demonstrate the red error message visitors will see when the album cannot be loaded.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<?php
								$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/INVALID-EXAMPLE-LINK"]';
							?>
							<div class="jzsa-code-block">
								<code><?php echo esc_html( $sample_shortcode ); ?></code>
								<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
							</div>
							<div class="jzsa-preview-container jzsa-preview-container-error-sample">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo do_shortcode( $sample_shortcode );
								?>
							</div>
						</div>
					</div>

					<div class="jzsa-faq">
						<h3><?php esc_html_e( 'I See a Yellow Warning Banner', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'You are using a short link format (photos.app.goo.gl), which is deprecated by Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'This format works as of today, but it may stop working in the future.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Only logged-in administrators see this warning. For best reliability, update the shortcode to use the full https://photos.google.com/share/... link from your browser\'s address bar.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>

						<div class="jzsa-sample-card">
							<h3><?php echo 'Sample 71: ' . esc_html__( 'Basic Album with Deprecated Link Format (Admin-Only Warning)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Same as above, but using the older short link format. Visitors will NOT see this warning, but you as an administrator should update the link to the new format.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<?php
								$sample_shortcode = '[jzsa-album link="https://photos.app.goo.gl/6qmxgmqdouBFKH3i8" width="600" limit="6"]';
							?>
							<div class="jzsa-code-block">
									<code><?php echo esc_html( $sample_shortcode ); ?></code>
								<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
							</div>
							<div class="jzsa-preview-container jzsa-preview-container-basic-deprecated">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo do_shortcode( $sample_shortcode );
								?>
							</div>
						</div>
					</div>

					</details>

					<div class="jzsa-faq">
						<h3><?php esc_html_e( 'Changes Not Showing Up?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Save or update your post - the cache clears automatically.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'If issues persist, try clearing your browser cache.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>
					</div>
				</div>

				<?php $this->render_unhappy_section(); ?>
				<?php $this->render_happy_section(); ?>
			</div>
		</div>
		<?php
		$this->lazy_sample_previews = false;
	}

	public function render_dashboard_announcement() {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		if ( ! $this->should_show_viewer_migration_notice( self::DASHBOARD_ANNOUNCEMENT_META ) ) {
			return;
		}
		$dismiss_nonce  = wp_create_nonce( 'jzsa_dismiss_announcement' );
		$guide_url      = esc_url( self::get_guide_page_url() );
		$logo_url       = esc_url( JZSA_PLUGIN_URL . 'assets/icon-256x256.gif' );
		?>
		<style>
		#jzsa-announcement {
			background: #eef9f4;
			border: 1px solid #b2dfca;
			border-left: 4px solid #46b450;
			border-radius: 4px;
			margin: 5px 0 20px;
			padding: 0;
			position: relative;
			box-shadow: 0 1px 3px rgba( 0, 0, 0, 0.06 );
		}
		.jzsa-dash-promo-inner {
			display: flex;
			align-items: flex-start;
			gap: 20px;
			padding: 20px 56px 20px 20px;
		}
		.jzsa-dash-promo-logo {
			flex-shrink: 0;
			width: 56px;
			height: 56px;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 1px 4px rgba( 0, 0, 0, 0.12 );
		}
		.jzsa-dash-promo-logo img {
			width: 100%;
			height: 100%;
			display: block;
		}
		.jzsa-dash-promo-body {
			flex: 1;
			min-width: 0;
			padding-top: 2px;
		}
		.jzsa-dash-promo-body h3 {
			color: #1d2327;
			font-size: 16px;
			font-weight: 700;
			margin: 0 0 4px;
			padding: 0;
			line-height: 1.3;
		}
		.jzsa-dash-promo-body p {
			color: #50575e;
			font-size: 14px;
			margin: 0 0 12px;
			line-height: 1.6;
		}
		.jzsa-dash-promo-dismiss {
			position: absolute;
			top: 6px;
			right: 8px;
			background: none;
			border: none;
			color: #8c8f94;
			font-size: 20px;
			line-height: 1;
			cursor: pointer;
			padding: 2px 6px;
		}
		.jzsa-dash-promo-dismiss:hover {
			color: #1d2327;
		}
		@media (max-width: 782px) {
			.jzsa-dash-promo-inner {
				flex-direction: column;
				padding-right: 20px;
			}
			.jzsa-dash-promo-dismiss {
				top: 4px;
				right: 4px;
			}
		}
		</style>

		<div id="jzsa-announcement">
			<div class="jzsa-dash-promo-inner">
				<div class="jzsa-dash-promo-logo" aria-hidden="true">
					<img src="<?php echo $logo_url; ?>" alt="" width="56" height="56">
				</div>
				<div class="jzsa-dash-promo-body">
					<h3><?php esc_html_e( 'Shared Albums now opens photos in Lightbox by default', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php esc_html_e( 'Sorry for the disruption but this is a breaking change. If your site needs the old Fullscreen behavior, open the Guide page for the short migration tutorial and exact shortcode parameters.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<a href="<?php echo $guide_url; ?>" class="button"><?php esc_html_e( 'Open Migration Guide', 'janzeman-shared-albums-for-google-photos' ); ?></a>
				</div>
			</div>
			<button type="button" class="jzsa-dash-promo-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'janzeman-shared-albums-for-google-photos' ); ?>">&times;</button>
		</div>

		<script>
		( function() {
			var notice = document.getElementById( 'jzsa-announcement' );
			var btn    = notice ? notice.querySelector( '.jzsa-dash-promo-dismiss' ) : null;
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', function() {
				notice.style.transition = 'opacity 0.3s';
				notice.style.opacity    = '0';
				setTimeout( function() { notice.style.display = 'none'; }, 320 );
				var data = new FormData();
				data.append( 'action', 'jzsa_dismiss_announcement' );
				data.append( 'nonce',  '<?php echo esc_js( $dismiss_nonce ); ?>' );
				fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data } );
			} );
		} )();
		</script>
		<?php
	}

	public function handle_dismiss_announcement() {
		check_ajax_referer( 'jzsa_dismiss_announcement', 'nonce' );
		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		update_user_meta( get_current_user_id(), self::DASHBOARD_ANNOUNCEMENT_META, self::ANNOUNCEMENT_VERSION );
		wp_send_json_success();
	}

	public function handle_dismiss_guide_migration() {
		check_ajax_referer( 'jzsa_dismiss_guide_migration', 'nonce' );
		if ( ! current_user_can( jzsa_get_admin_capability() ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		update_user_meta( get_current_user_id(), self::GUIDE_ANNOUNCEMENT_META, self::ANNOUNCEMENT_VERSION );
		wp_send_json_success();
	}
}
