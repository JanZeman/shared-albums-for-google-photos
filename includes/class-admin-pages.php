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

		// Reusable UI strings — define once, echo everywhere below.
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
								<button type="button" class="button button-link" data-jzsa-clear-cache-scope="album" data-jzsa-idle-label="<?php echo esc_attr__( 'Clear Album Cache', 'janzeman-shared-albums-for-google-photos' ); ?>">
									<?php esc_html_e( 'Clear Album Cache', 'janzeman-shared-albums-for-google-photos' ); ?>
								</button>
									<button type="button" class="button button-link" data-jzsa-clear-cache-scope="photo_meta" data-jzsa-idle-label="<?php echo esc_attr__( 'Clear Metadata Cache', 'janzeman-shared-albums-for-google-photos' ); ?>">
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

						<div class="jzsa-sample-card">
							<h3><?php esc_html_e( 'Gallery - Limited Count, No Pagination', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
									<h3><?php esc_html_e( 'Gallery - Paged (Default Page Counter)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
								<h3><?php esc_html_e( 'Gallery - Paged (Custom Page Counter)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
									<h3><?php esc_html_e( 'Gallery - Paged (Tile Counter Override)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Gallery - Scrollable Instead Of Paged', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use gallery-scrollable="true" with gallery-rows to show a fixed-height, vertically scrollable gallery instead of page controls.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
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
						<h3><?php esc_html_e( 'Gallery - Justified Layout', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Slider - Basic Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Custom Size Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Hide Navigation Arrows', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Hides previous/next arrows. Useful for headless slideshows such as digital signage. Swipe and keyboard navigation still work.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" slideshow="auto" show-navigation="false" info-bottom="" fullscreen-toggle="disabled"]';
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
							<h3><?php esc_html_e( 'Interaction Lock (Controls and Navigation Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Uses interaction-lock="true" as a <strong>hard override</strong> for interactions: swipe/drag, keyboard navigation, click/tap navigation, and fullscreen entry are disabled. Notice that all navigation buttons are hidden despite the shortcode explicitly enabling them (show-link-button, show-download-button, fullscreen-toggle). Counter and slideshow countdown stay visible.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-toggle="click" show-link-button="true" show-download-button="true" slideshow="auto" slideshow-delay="2" interaction-lock="true"]';
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
								<h3><?php esc_html_e( 'Limit Number of Entries Per Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Custom Slideshow Speed', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Random Start without Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Disable Cropping and Set Custom Background', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Custom Background Color for Fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'High-Resolution Inline Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'High-Resolution Fullscreen Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Low-Resolution Fullscreen Photos with Limited Display Size', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_fullscreen_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'Combine a smaller fullscreen source image with a smaller centered fullscreen display box. Useful when you want fullscreen mode, but do not want the photo to expand wall-to-wall across the screen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="300" corner-radius="16" fullscreen-source-width="512" fullscreen-source-height="340" fullscreen-display-max-width="640" fullscreen-display-max-height="425"]';
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
						<h3><?php esc_html_e( 'Manual Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Slideshow with Autostart and Autoresume', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Fullscreen Slideshow Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables slideshow only in fullscreen mode, keeping inline mode static.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-slideshow="auto"]';
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
						<h3><?php esc_html_e( 'Faster Fullscreen Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-slideshow-delay to advance photos more quickly in fullscreen slideshow mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-slideshow="auto" fullscreen-slideshow-delay="2"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fast-fullscreen">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php esc_html_e( 'Fullscreen Fit (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Shows the default fullscreen behavior with no fullscreen-image-fit parameter set. By default, fullscreen uses "contain" to preserve the entire photo while scaling it up to fill at least one axis.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-fit">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-sample-card">
							<h3><?php esc_html_e( 'Fullscreen Fit (Cover Override)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Overrides the default fullscreen behavior with fullscreen-image-fit="cover". This fills the entire fullscreen area, but parts of the photo may be cropped near the edges.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-image-fit="cover"]';
						?>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $sample_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-fs-fit">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $sample_shortcode );
							?>
						</div>
						</div>

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Button-Only Fullscreen (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="button-only" (the default) so fullscreen can only be entered via the fullscreen button. Once in fullscreen, click to navigate between photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-toggle="button-only"]';
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
						<h3><?php esc_html_e( 'Single-Click Fullscreen Toggle', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php echo wp_kses( __( 'Uses fullscreen-toggle="click" so clicking anywhere on the slider enters fullscreen. Trade-off: single-click can no longer navigate between photos in fullscreen - use the arrow buttons or keyboard instead. Exit via the Escape key or the fullscreen button. For a less accidental shortcut that preserves click navigation, <strong>consider double-click instead</strong>.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-toggle="click"]';
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
						<h3><?php esc_html_e( 'Double-Click Fullscreen Toggle (Recommended)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php echo wp_kses( __( 'Uses fullscreen-toggle="double-click" so double-click (or double-tap on touch) toggles fullscreen on and off. <strong>Recommended over single-click</strong>: single-click still navigates between photos in fullscreen, and the double-click gesture is less likely to be triggered accidentally. Exit via the Escape key or the fullscreen button as alternatives.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-toggle="double-click"]';
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
						<h3><?php esc_html_e( 'Fullscreen Disabled', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="disabled" to completely prevent fullscreen mode. No fullscreen button is shown and clicks do not enter fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" fullscreen-toggle="disabled"]';
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
							<h3><?php esc_html_e( 'Lightbox - Open Photos at a Predetermined Size', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_lightbox_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'The lightbox is an alternative to native fullscreen: clicking a photo opens it in a dimmed overlay on top of the page, not in the browser\'s fullscreen mode - the page stays underneath. With lightbox-max-width and lightbox-max-height the photo is centered inside a size-capped box, so it opens at a predetermined size instead of wall-to-wall.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" mode="gallery" gallery-layout="justified" limit="9" gallery-gap="8" lightbox-toggle="click" fullscreen-toggle="disabled" lightbox-max-width="1000" lightbox-max-height="700"]';
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

					<div class="jzsa-sample-card">
						<div class="jzsa-sample-card-header">
							<h3><?php esc_html_e( 'Lightbox - Basic (Click to Open)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_lightbox_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'The minimal setup: lightbox-toggle="click" on a thumbnail gallery. Clicking any photo opens it in the lightbox overlay with prev/next navigation. Without size caps the overlay fills most of the viewport (with a small margin) - add lightbox-max-width / lightbox-max-height to bound it.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" mode="gallery" limit="6" gallery-gap="8" corner-radius="16" lightbox-toggle="click" fullscreen-toggle="disabled"]';
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
						<div class="jzsa-sample-card-header">
							<h3><?php esc_html_e( 'Lightbox - Button Only, Rounded Box, Lighter Backdrop', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Use the corner button to open the lightbox', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						</div>
						<p><?php esc_html_e( 'lightbox-toggle="button-only" opens the lightbox only via the corner button (clicking the photo does nothing extra). lightbox-corner-radius rounds the floating box and lightbox-background-color="rgba(0,128,0,0.7)" dims the page more gently so it shows through.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" lightbox-toggle="button-only" fullscreen-toggle="disabled" lightbox-corner-radius="16" lightbox-max-width="1100" lightbox-max-height="750" lightbox-background-color="rgba(0,128,0,0.7)"]';
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
							<h3><?php esc_html_e( 'Lightbox - Cover Fit, Smaller Box', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php echo $s_lightbox_hint; ?></span>
						</div>
						<p><?php esc_html_e( 'lightbox-image-fit="cover" fills the box edge-to-edge (cropping the edges of the photo) instead of letterboxing it. Combined here with a smaller lightbox-max-width / lightbox-max-height for a compact, fully-filled lightbox.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" mode="gallery" limit="6" gallery-gap="8" corner-radius="16" lightbox-toggle="click" fullscreen-toggle="disabled" lightbox-max-width="800" lightbox-max-height="560" lightbox-image-fit="cover" lightbox-corner-radius="12"]';
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
						<div class="jzsa-sample-card-header">
							<h3><?php esc_html_e( 'Lightbox - Double-Click to Toggle (Slider)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Double-click the slider to open the lightbox', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						</div>
						<p><?php echo wp_kses( __( 'lightbox-toggle="double-click" opens (and closes) the lightbox on double-click, leaving single-click free to navigate. <strong>Recommended over <code>lightbox-toggle="click"</code></strong> when your visitors also click to browse - just like the fullscreen-toggle trade-off. Escape, the close button, or a click on the backdrop also close it.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array(), 'code' => array() ) ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" lightbox-toggle="double-click" fullscreen-toggle="disabled" lightbox-max-width="1100" lightbox-max-height="750"]';
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
							<h3><?php esc_html_e( 'Lightbox + Fullscreen - Both Buttons Side by Side', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Left button opens lightbox, right button enters fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						</div>
						<p><?php echo wp_kses( __( 'When both <code>lightbox-toggle</code> and <code>fullscreen-toggle</code> are set, two buttons appear side by side in the top-right corner. The left button (with the center-square icon) opens the CSS lightbox overlay; the right button enters native fullscreen. Your visitor chooses which experience they prefer. Inside the lightbox a fullscreen button is also available, so they can escalate from lightbox to fullscreen without closing first.', 'janzeman-shared-albums-for-google-photos' ), array( 'code' => array(), 'strong' => array() ) ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" corner-radius="16" lightbox-toggle="button-only" lightbox-max-width="1100" lightbox-max-height="750" fullscreen-toggle="button-only"]';
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
							<h3><?php esc_html_e( 'Lightbox + Fullscreen - Both Buttons in Gallery Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<span class="jzsa-sample-card-hint"><?php esc_html_e( 'Hover a photo to reveal both buttons in its top-right corner', 'janzeman-shared-albums-for-google-photos' ); ?></span>
						</div>
						<p><?php echo wp_kses( __( 'Gallery mode also supports having both a lightbox button and a fullscreen button at the same time. Set <code>lightbox-toggle="button-only"</code> and <code>fullscreen-toggle="button-only"</code> together: hovering any photo reveals two expand buttons in its top-right corner. The left button opens the lightbox overlay; the right button enters native fullscreen. Your visitor picks whichever experience they prefer, photo by photo.', 'janzeman-shared-albums-for-google-photos' ), array( 'code' => array() ) ); ?></p>
					<?php
						$sample_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" mode="gallery" gallery-layout="justified" limit="9" gallery-gap="8" lightbox-toggle="button-only" fullscreen-toggle="button-only" lightbox-max-width="1000" lightbox-max-height="700"]';
					?>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $sample_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php echo $s_copy; ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-dual-expand">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $sample_shortcode );
						?>
					</div>
					</div>

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Show "Open in Google Photos" Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Show Download Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Show Link and Download Buttons - Gallery Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Gallery Mode: Link Button in Inline and Fullscreen, Download Button in Fullscreen Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Custom Colors', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Carousel Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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

						<div class="jzsa-sample-card">
							<h3><?php esc_html_e( 'Video (Blue Accent)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Video in Carousel (Auto-Hide Controls)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Video in Gallery (Button-only to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Video in Gallery (Single-click to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Single-click on any thumbnail opens fullscreen. Trade-off: click can no longer navigate between items in fullscreen - use the arrow buttons instead. <strong>Consider double-click instead</strong> to keep click navigation available.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" width="800" corner-radius="16" show-videos="true" limit="6" gallery-layout="grid" fullscreen-toggle="click" video-controls-color="#E0527E" gallery-gap="8"]';
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
							<h3><?php esc_html_e( 'Video in Gallery (Double-click to Fullscreen)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses( __( 'Double-click (or double-tap) on any thumbnail opens fullscreen; double-click again to exit. <strong>Recommended over single-click</strong>: click still navigates between items in fullscreen, and the gesture is less likely to be triggered accidentally.', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" width="800" show-videos="true" limit="6" gallery-layout="grid" fullscreen-toggle="double-click" video-controls-color="#7A5CFF" gallery-gap="8"]';
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
							<h3><?php esc_html_e( 'Photos-Only Sample (Videos Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses show-videos="false" to filter out videos from the same mixed album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<?php
							$sample_shortcode = '[jzsa-album link="' . $video_sample_link . '" width="800" corner-radius="16" show-videos="false" limit="6" fullscreen-toggle="double-click" video-controls-color="#7A5CFF" gallery-gap="8"]';
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

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Slider - Mosaic Strip at the Bottom', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Slider - Mosaic Strip With Explicit Rounded Corners', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Slider - Mosaic Strip on the Right', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Mosaic Strip with Carousel', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Slider - Mosaic Strip with Custom Gap and Opacity', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Fullscreen Mosaic Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Fullscreen Mosaic - Overlay Layout', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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

					<div class="jzsa-sample-card">
						<h3><?php esc_html_e( 'Slider with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Carousel with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Gallery with Photo Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Description & EXIF Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'EXIF Camera Info', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Long Text: Truncated (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Long Text: Wrapped (info-wrap="true")', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Long Text: Wrapped with Link and Download Buttons', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Text Halo Effect Per-Box Override', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
						<h3><?php esc_html_e( 'Per-Box Text Alignment', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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

						<div class="jzsa-sample-card">
							<h3><?php esc_html_e( 'Sample "Unable to Load Album" Error', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
							<h3><?php esc_html_e( 'Basic Album with Deprecated Link Format (Admin-Only Warning)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
}
