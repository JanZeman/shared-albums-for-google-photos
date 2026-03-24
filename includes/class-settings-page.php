<?php
/**
 * Settings Page Class
 *
 * Provides admin settings page with tutorial and examples
 *
 * @package JZSA_Shared_Albums
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page Class
 */
class JZSA_Settings_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Add settings page to WordPress admin menu
	 */
	public function add_settings_page() {
		add_options_page(
			'Shared Albums for Google Photos (by JanZeman)',           // Page title
			'Shared Albums for Google Photos (by JanZeman)',           // Menu title
			'manage_options',                 // Capability
			'janzeman-shared-albums-for-google-photos',           // Menu slug
			array( $this, 'render_settings_page' ) // Callback
		);
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_styles( $hook ) {
		// Only load on our settings page
		if ( 'settings_page_janzeman-shared-albums-for-google-photos' !== $hook ) {
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
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$video_sample_link = 'https://photos.google.com/share/AF1QipM-v19vtjd5NEiD6w40U7XqZoqwMUX4FyPr6p9U-9Ixjw2jy7oYFs7m7vgvvpm3PA?key=ZjhXZDNkc1ZrNmFvZ2tIOW16QXlGal94Y2g2cGJB';

		$video_slider_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="slider" show-videos="true" limit="8" video-controls-color="#00B2FF"]';
		$video_carousel_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="carousel" show-videos="true" limit="8" video-controls-color="#FF6B35" video-controls-autohide="true"]';
		$video_gallery_shortcode = '[jzsa-album link="' . $video_sample_link . '" mode="gallery" show-videos="true" limit="12" gallery-layout="justified" gallery-row-height="180" video-controls-color="#00A878"]';
		$video_photos_only_shortcode = '[jzsa-album link="' . $video_sample_link . '" show-videos="false" limit="6" video-controls-color="#7A5CFF"]';
		$controls_custom_shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" show-link-button="true" show-download-button="true" controls-color="#FFD400"]';
		$mosaic_sample_link        = 'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';
		$mosaic_bottom_shortcode   = '[jzsa-album link="' . $mosaic_sample_link . '" mode="slider" width="800" height="600" mosaic="true" mosaic-position="bottom" mosaic-count="12"]';
		$mosaic_right_shortcode    = '[jzsa-album link="' . $mosaic_sample_link . '" mode="slider" width="800" height="600" mosaic="true" mosaic-position="right"]';
		$mosaic_rounded_shortcode  = '[jzsa-album link="' . $mosaic_sample_link . '" mode="slider" width="800" height="600" mosaic="true" mosaic-position="bottom" mosaic-count="12" corner-radius="16"]';
		$mosaic_carousel_shortcode = '[jzsa-album link="' . $mosaic_sample_link . '" mode="carousel" width="800" height="600" mosaic="true" mosaic-position="bottom" mosaic-count="18"]';
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
									<?php esc_html_e( 'What This Plugin Does – and What It Doesn\'t', 'janzeman-shared-albums-for-google-photos' ); ?>
								</strong>
						<p style="margin: 16px 0 0 0;">
							<?php esc_html_e( 'This plugin renders one Google Photos album per shortcode. It does not provide any layout mechanism for multiple albums. One [jzsa-album] shortcode will always render only one given album. To display many albums together, build your own layout with one shortcode per album — for example using columns, the Query Loop block, or any page builder of your choice.', 'janzeman-shared-albums-for-google-photos' ); ?>
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

				<div class="jzsa-section jzsa-help-section">
					<h2><?php esc_html_e( 'This Plugin Has Potential, But...', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-warning" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #d63638; flex-shrink: 0;"></span>
						<span><?php esc_html_e( 'Found a bug or something not working right?', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://github.com/JanZeman/shared-albums-for-google-photos/issues/new" target="_blank" rel="noopener"><?php esc_html_e( 'Report it on GitHub', 'janzeman-shared-albums-for-google-photos' ); ?></a></span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-lightbulb" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #dba617; flex-shrink: 0;"></span>
						<span><?php esc_html_e( 'Missing a feature or have an idea for improvement?', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/" target="_blank" rel="noopener"><?php esc_html_e( 'Request it on the support forum', 'janzeman-shared-albums-for-google-photos' ); ?></a></span>
					</p>
				</div>

				<div class="jzsa-section jzsa-happy-section">
					<h2><?php esc_html_e( 'Enjoying This Plugin?', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #f0ad4e; flex-shrink: 0;"></span>
						<span>
							<?php
							printf(
								/* translators: %s: link to WordPress.org reviews. */
								esc_html__( 'If this plugin saved you time or made your site better, a quick %s would truly make my day. It helps others find the plugin too!', 'janzeman-shared-albums-for-google-photos' ),
								'<a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post" target="_blank" rel="noopener">' . esc_html__( '5-star review', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
							);
							?>
						</span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<img src="<?php echo esc_url( plugins_url( 'assets/BuyMeACoffee_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
						<span><?php esc_html_e( 'You can also', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://www.buymeacoffee.com/janzeman" target="_blank" rel="noopener"><?php esc_html_e( 'buy me a coffee.', 'janzeman-shared-albums-for-google-photos' ); ?></a> <?php esc_html_e( "I'll take my family to a local café and finally explain why all those late-night coding sessions were somehow worth it ;)", 'janzeman-shared-albums-for-google-photos' ); ?></span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<img src="<?php echo esc_url( plugins_url( 'assets/Photographer_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
						<span><?php esc_html_e( 'Made by a hobbyist WordPress developer and occasional photographer. Thank you for using this plugin!', 'janzeman-shared-albums-for-google-photos' ); ?></span>
					</p>
				</div>

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

					<label for="jzsa-playground-shortcode" class="screen-reader-text">
						<?php esc_html_e( 'Shortcode to test', 'janzeman-shared-albums-for-google-photos' ); ?>
					</label>
					<textarea
						id="jzsa-playground-shortcode"
						class="large-text code"
						rows="3"
					><?php echo esc_textarea( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider"]' ); ?></textarea>

					<div class="jzsa-preview-container jzsa-playground-preview">
						<?php
							// Step 1: static preview using the same sample album as above.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider"]' );
						?>
					</div>
				</div>

			<!-- Tools Section -->
			<div class="jzsa-section jzsa-tools-section">
				<h2><?php esc_html_e( 'Tools', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

				<div class="jzsa-tool-row">
					<div class="jzsa-tool-info">
						<strong><?php esc_html_e( 'Album Cache', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<p class="jzsa-help-text"><?php esc_html_e( 'Album data is cached for 24 hours on your web server to reduce load. If you have modified any of your Google Photos albums and need to see the changes immediately, simply click the button below. For albums that are updated frequently (e.g. live events), use the cache-refresh shortcode parameter to automatically reduce the cache duration.', 'janzeman-shared-albums-for-google-photos' ); ?><br><span style="display:block;margin-top:6px"></span><em><?php esc_html_e( 'This clears the cached album data - not your browser or page cache. If you use a page caching plugin (WP Fastest Cache, WP Super Cache, etc.), consider excluding the pages with albums from page caching, or clear that cache separately.', 'janzeman-shared-albums-for-google-photos' ); ?></em></p>
					</div>
					<div class="jzsa-tool-action">
						<button type="button" id="jzsa-clear-cache-btn" class="button button-secondary">
							<?php esc_html_e( 'Clear Cache', 'janzeman-shared-albums-for-google-photos' ); ?>
						</button>
						<span id="jzsa-clear-cache-result" class="jzsa-tool-result" aria-live="polite"></span>
					</div>
				</div>
			</div>

				<!-- Samples Section -->
				<div class="jzsa-section jzsa-samples-section">
					<h2><?php esc_html_e( 'Samples', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Gallery Mode with Limited Entry Count (Without Pagination)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses the default "gallery" mode to display album entries as a thumbnail gallery. Every cell has the same size. Click any thumbnail to open it in a fullscreen viewer. Pagination is not required - all thumbnails are shown at once, limited only by limit.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" limit="12"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-grid" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" limit="12"]' );
						?>
					</div>
					</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Gallery Mode – Paged Thumbnails', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Use gallery-rows to split the gallery into pages. The same previous/next and pagination controls are reused for gallery page navigation. Use gallery-sizing="ratio" (default) to keep fixed tile aspect ratio, or gallery-sizing="fill" to stretch row heights and fill explicit control height.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" gallery-rows="2" limit="18"]</code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-gallery-paged" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" gallery-rows="2" limit="18"]' );
							?>
						</div>
						</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Gallery Mode – Scrolling Instead of Paging', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Use gallery-scrollable="true" with gallery-rows to show a fixed-height, vertically scrollable gallery instead of page controls.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" gallery-rows="2" gallery-scrollable="true" corner-radius="12" gallery-gap="12" limit="18"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-scrollable" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" gallery-rows="2" gallery-scrollable="true" corner-radius="12" gallery-gap="12" limit="18"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Gallery Mode – Justified Layout', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses gallery-layout="justified" so photos keep their natural aspect ratios and fill each row edge-to-edge, similar to Google Photos. Click any thumbnail to open it in a fullscreen viewer.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" gallery-layout="justified" gallery-row-height="180" limit="7"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-gallery-justified" style="height:auto;">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" gallery-layout="justified" gallery-row-height="180" limit="7"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Basic Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'The default viewer experience when no parameters are set.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider"]</code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-basic">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider"]' );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Custom Size Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Set the preview width and height so they fit your page layout.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="800" height="600" image-fit="contain"]</code>
							<button class="jzsa-copy-btn" onclick="jzsaCopyToClipboard(this, '[jzsa-album link=&quot;https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R&quot; mode=&quot;slider&quot; width=&quot;800&quot; height=&quot;600&quot; image-fit=&quot;contain&quot;]')"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-custom-size">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="800" height="600" image-fit="contain"]' );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Album with Title and Photo Counter', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Display the album title followed by the photo counter.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-title="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-title">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-title="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Album with Title Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Display the album title without a photo counter.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-title="true" show-counter="false"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-title">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-title="true" show-counter="false"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Hide Navigation Arrows', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Hides previous/next arrows. Useful for headless slideshows such as digital signage. Swipe and keyboard navigation still work.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" show-navigation="false" show-counter="false" fullscreen-toggle="disabled"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
						<div class="jzsa-preview-container jzsa-preview-container-hide-navigation">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" show-navigation="false" show-counter="false" fullscreen-toggle="disabled"]' );
							?>
						</div>
						</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Interaction Lock (Controls and Navigation Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses interaction-lock="true" as a hard override for interactions: swipe/drag, keyboard navigation, click/tap navigation, and fullscreen entry are disabled. Counter and slideshow countdown stay visible.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="click" show-link-button="true" show-download-button="true" slideshow="true" slideshow-delay="2" interaction-lock="true"]</code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-interaction-lock">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="click" show-link-button="true" show-download-button="true" slideshow="true" slideshow-delay="2" interaction-lock="true"]' );
							?>
						</div>
						</div>

							<div class="jzsa-example">
								<h3><?php esc_html_e( 'Limit Number of Entries Per Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
								<p><?php esc_html_e( 'Load only a limited number of album entries from a large mixed album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" limit="5"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-limit-photos">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" limit="5"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Custom Slideshow Speed', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slideshow here is set to one second. You can easily see the difference in speed compared to the sample above :)', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" slideshow-delay="1"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-slower-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" slideshow-delay="1"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Random Start without Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Starts at a random photo with slideshow disabled. Each page load shows a different photo, but the viewer stays on it until the user navigates manually.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" start-at="random" slideshow="false"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" start-at="random" slideshow="false"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Disable Cropping and Set Custom Background', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows photos fully without cropping by using image-fit="contain". This exposes the background color. Here we set it to yellow to make it very visible.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" image-fit="contain" background-color="#FFE50D"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-crop">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" image-fit="contain" background-color="#FFE50D"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'High-Resolution Inline Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Compare default resolution (left) with high-resolution source (right). Both use the same container size and image-fit="cover".', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="480" image-fit="cover" slideshow="true" slideshow-delay="5" source-width="400" source-height="300"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="480" image-fit="cover" slideshow="true" slideshow-delay="5" source-width="1920" source-height="1440"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-hires-inline" style="display: flex; gap: 20px; justify-content: center;">
						<div>
							<p><strong><?php esc_html_e( 'Low (400×300)', 'janzeman-shared-albums-for-google-photos' ); ?></strong></p>
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="480" image-fit="cover" slideshow="true" slideshow-delay="5" source-width="400" source-height="300"]' );
							?>
						</div>
						<div>
							<p><strong><?php esc_html_e( 'High-Res (1920×1440)', 'janzeman-shared-albums-for-google-photos' ); ?></strong></p>
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="400" height="480" image-fit="cover" slideshow="true" slideshow-delay="5" source-width="1920" source-height="1440"]' );
							?>
						</div>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'High-Resolution Fullscreen Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Request extra-high-resolution photos for fullscreen mode. The default fullscreen resolution is 1920x1440. Increase for 4K displays.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="800" height="600" fullscreen-source-width="2560" fullscreen-source-height="1700"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-hires-fullscreen">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" width="800" height="600" fullscreen-source-width="2560" fullscreen-source-height="1700"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Delayed Slideshow Resume', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Switch to fullscreen mode, stop slideshow, and wait for the timeout to expire. You will see slideshow resume automatically.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" fullscreen-slideshow="true" slideshow-inactivity-timeout="20"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-autoplay-timeout">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" slideshow="true" fullscreen-slideshow="true" slideshow-inactivity-timeout="20"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Fullscreen Slideshow Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables slideshow only in fullscreen mode, keeping inline mode static.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-slideshow="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fullscreen-only">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-slideshow="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Faster Fullscreen Slideshow', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-slideshow-delay to advance photos more quickly in fullscreen slideshow mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-slideshow="true" fullscreen-slideshow-delay="5"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fast-fullscreen">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-slideshow="true" fullscreen-slideshow-delay="5"]' );
						?>
						</div>
						</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Fullscreen Fit (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-image-fit="contain" to preserve the entire photo in fullscreen while scaling it up to fill at least one axis. This is the default fullscreen image-fit mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-image-fit="contain"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-fit">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-image-fit="contain"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Button-Only Fullscreen (Default)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="button-only" (the default) so fullscreen can only be entered via the fullscreen button. Once in fullscreen, click to navigate between photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="button-only"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-switch-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="button-only"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Single-Click Fullscreen Toggle', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="click" so clicking anywhere on the slider enters fullscreen. Once in fullscreen, click to navigate between photos. Exit via the Escape key or the fullscreen button.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="click"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-switch-single">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="click"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Double-Click Fullscreen Toggle', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="double-click" so double-click (or double-tap) toggles fullscreen on and off. In fullscreen, click still navigates between photos, but double-click is reserved for toggling fullscreen only. Use the Escape key or the fullscreen button as alternatives.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="double-click"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-switch-double">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="double-click"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Fullscreen Disabled', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses fullscreen-toggle="disabled" to completely prevent fullscreen mode. No fullscreen button is shown and clicks do not enter fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="disabled"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-disabled">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" fullscreen-toggle="disabled"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Show "Open in Google Photos" Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-link-button parameter to display an external link button to the original album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-link-button="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-link-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-link-button="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Show Download Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-download-button parameter to add a download button for the current photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-download-button="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-download-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="slider" show-download-button="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Custom Controls Color', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Example with a bright classic yellow controls-color for better visibility on mixed photo backgrounds.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code><?php echo esc_html( $controls_custom_shortcode ); ?></code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-controls-color-custom">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( $controls_custom_shortcode );
						?>
					</div>
					</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Carousel Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses mode="carousel" to show multiple photos side by side. On mobile and tablets it shows 2 photos at a time, and on desktop it shows 3 photos. Clicking a photo opens it in a single-photo fullscreen viewer.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-carousel">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel"]' );
						?>
						</div>
						</div>

						<h3><?php esc_html_e( 'Video Samples (Temporary Album)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'These examples use a temporary mixed album that contains videos. They demonstrate layout modes with different accent colors and key video parameters.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Video in Slider Mode (Blue Accent)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Baseline video sample in slider mode with videos enabled and blue accent color.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $video_slider_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-slider">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $video_slider_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Video in Carousel Mode (Orange Accent + Auto-Hide Controls)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Demonstrates carousel mode with video controls auto-hiding after inactivity.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $video_carousel_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-carousel">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $video_carousel_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Video in Gallery Mode (Green Accent)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Demonstrates gallery mode with justified thumbnails and videos included.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $video_gallery_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-gallery">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $video_gallery_shortcode );
							?>
						</div>
						</div>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Photos-Only Control Sample (Videos Disabled)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Uses show-videos="false" to filter out videos from the same mixed album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $video_photos_only_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-video-disabled">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $video_photos_only_shortcode );
							?>
						</div>
						</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Mosaic Strip at the Bottom', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slider with a horizontal thumbnail strip below the main photo. Click any thumbnail to jump to that photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $mosaic_bottom_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-bottom">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $mosaic_bottom_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Mosaic Strip With Rounded Corners', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Same as above with corner-radius="16" for rounded corners on the slider and thumbnail strip.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $mosaic_rounded_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-rounded">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $mosaic_rounded_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Mosaic Strip on the Right', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Slider with a vertical thumbnail strip on the right side. Great for landscape photos where the strip can use the full height.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $mosaic_right_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-right">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $mosaic_right_shortcode );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Mosaic Strip with Carousel', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Carousel mode with a thumbnail strip at the bottom. The carousel shows multiple photos at once; the mosaic strip provides an overview of the full album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code><?php echo esc_html( $mosaic_carousel_shortcode ); ?></code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-mosaic-carousel" style="height:auto;">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( $mosaic_carousel_shortcode );
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
								<li><?php esc_html_e( 'Close the dialog – no need to copy the link; we will use its longer form below', 'janzeman-shared-albums-for-google-photos' ); ?></li>
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
					<div class="jzsa-code-block">
						<code>[jzsa-album link="YOUR_LINK_HERE"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
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

				<!-- Shortcode Parameters -->
				<div class="jzsa-section">
					<h2><?php esc_html_e( 'All Shortcode Parameters', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<h3><?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>link</code></td>
								<td><?php esc_html_e( 'Google Photos share URL (supports both full and short link formats)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td><em><?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?></em></td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Core Parameters', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>mode</code></td>
								<td>Gallery mode:<br>
									• "gallery": Thumbnail gallery with optional paging or scrolling via <code>gallery-rows</code> and <code>gallery-scrollable</code>; click any thumbnail to open it in a fullscreen viewer<br>
									• "slider": Single photo viewer with zoom support (pinch on touch devices)<br>
									• "carousel": Multiple photos visible at once (2 on mobile/tablet, 3 on desktop). Clicking a photo opens it in a single-photo fullscreen viewer</td>
								<td>gallery</td>
							</tr>
								<tr>
									<td><code>limit</code></td>
									<td>Maximum number of album entries to display (photos and videos that remain after filters such as show-videos) from the album (1–300). Google Photos typically returns up to 300 entries per album.</td>
									<td>300</td>
								</tr>
							<tr>
								<td><code>cache-refresh</code></td>
								<td>How often the album data is re-fetched from Google Photos, in hours. Useful for albums that are updated frequently (e.g. live event albums). Example: cache-refresh="1" to refresh every hour.</td>
								<td>168 (7 days)</td>
							</tr>
							<tr>
								<td><code>source-width</code></td>
								<td>Photo width to fetch from Google for inline mode</td>
								<td>800</td>
							</tr>
							<tr>
								<td><code>source-height</code></td>
								<td>Photo height to fetch from Google for inline mode</td>
								<td>600</td>
							</tr>
							<tr>
								<td><code>fullscreen-source-width</code></td>
								<td>Photo width to fetch from Google for fullscreen mode</td>
								<td>1920</td>
							</tr>
							<tr>
								<td><code>fullscreen-source-height</code></td>
								<td>Photo height to fetch from Google for fullscreen mode</td>
								<td>1440</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Gallery Mode Options (those apply only for the default "gallery" mode)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
								<tr>
									<td><code>gallery-layout</code></td>
									<td><?php esc_html_e( 'Gallery layout algorithm: "grid" (equal-size cells) or "justified" (photos fill each row at their natural aspect ratio, like Google Photos)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
									<td>grid</td>
								</tr>
								<tr>
									<td><code>gallery-sizing</code></td>
									<td><?php esc_html_e( 'Grid gallery sizing: "ratio" keeps a fixed tile ratio (default), while "fill" stretches row heights to fill an explicit control height when width/height and gallery-rows are used.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
									<td>ratio</td>
								</tr>
								<tr>
									<td><code>gallery-columns</code></td>
									<td><?php esc_html_e( 'Number of columns on desktop (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
									<td>3</td>
								</tr>
							<tr>
								<td><code>gallery-columns-tablet</code></td>
								<td><?php esc_html_e( 'Number of columns on tablet screens ≤ 768 px (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>2</td>
							</tr>
							<tr>
								<td><code>gallery-columns-mobile</code></td>
								<td><?php esc_html_e( 'Number of columns on mobile screens ≤ 480 px (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>1</td>
							</tr>
							<tr>
								<td><code>gallery-row-height</code></td>
								<td><?php esc_html_e( 'Target row height in pixels for the justified layout (50–800)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>200</td>
							</tr>
							<tr>
								<td><code>gallery-rows</code></td>
								<td><?php esc_html_e( 'Number of visible gallery rows when row limiting is enabled. If more rows are available, gallery uses paging by default or scrolling when gallery-scrollable="true". Use 0 to show all rows.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0 (all rows)</td>
							</tr>
							<tr>
								<td><code>gallery-scrollable</code></td>
								<td><?php esc_html_e( 'When set to "true" (and gallery-rows > 0), uses a single vertically scrollable gallery instead of page controls.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>gallery-gap</code></td>
								<td><?php esc_html_e( 'Spacing between gallery thumbnails in pixels. Applies to both grid and justified layouts.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>4</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Appearance', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>background-color</code></td>
								<td>Background color hex code or "transparent"</td>
								<td>transparent</td>
							</tr>
							<tr>
								<td><code>controls-color</code></td>
								<td>Color for custom album controls (previous/next, fullscreen, link, download, play/pause). Any valid 6-digit hex color.</td>
								<td>#ffffff</td>
							</tr>
							<tr>
								<td><code>corner-radius</code></td>
								<td><?php esc_html_e( 'Rounded corner radius in pixels. Applies to slider, carousel, gallery thumbnails, and mosaic strips. Use 0 for square corners. Disabled in fullscreen mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0</td>
							</tr>
							<tr>
								<td><code>image-fit</code></td>
								<td>How photos fit the frame: "cover" (fill and crop edges) or "contain" (show whole image, no cropping, may letterbox).</td>
								<td>cover</td>
							</tr>
								<tr>
									<td><code>width</code></td>
									<td>Width in pixels or "auto". In <code>mode="gallery"</code>, prefer <code>gallery-columns</code>/<code>gallery-rows</code>.</td>
									<td>400</td>
								</tr>
								<tr>
									<td><code>height</code></td>
									<td>Height in pixels or "auto". In <code>mode="gallery"</code>, prefer <code>gallery-columns</code>/<code>gallery-rows</code>.</td>
									<td>300</td>
								</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Slideshow Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>slideshow</code></td>
								<td>Enable slideshow in normal mode: "true" or "false". In <code>mode="gallery"</code> with pagination (<code>gallery-rows &gt; 0</code> and <code>gallery-scrollable="false"</code>), this advances gallery pages automatically.</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>slideshow-delay</code></td>
								<td>Slideshow delay in normal mode, in seconds. Supports single values like "5" or ranges like "4-12". In paginated gallery mode this is the delay between page changes.</td>
								<td>5</td>
							</tr>
							<tr>
								<td><code>slideshow-inactivity-timeout</code></td>
								<td>Time in seconds after which slideshow resumes following user interaction</td>
								<td>30</td>
							</tr>
							<tr>
								<td><code>start-at</code></td>
								<td>Starting photo: a 1-based photo index like "1" or "12", or "random" for a random starting point. Values out of range fall back to 1.</td>
								<td>1</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Fullscreen Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>fullscreen-slideshow</code></td>
								<td>Enable slideshow in fullscreen mode: "true" or "false"</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>fullscreen-slideshow-delay</code></td>
								<td>Slideshow delay in fullscreen mode, in seconds, supports ranges like "3-5" or single values</td>
								<td>5</td>
							</tr>
							<tr>
								<td><code>fullscreen-toggle</code></td>
								<td>How fullscreen is toggled: "button-only" (default) requires the fullscreen button, "click" enters fullscreen on click, "double-click" toggles fullscreen on/off, or "disabled" to prevent fullscreen entirely. In fullscreen, click navigates between photos, while double-click mode reserves double-click for fullscreen toggle only.</td>
								<td>button-only</td>
							</tr>
							<tr>
								<td><code>fullscreen-image-fit</code></td>
								<td>How photos fit the frame in fullscreen: "contain" (default, show whole image, no cropping) or "cover" (fill and crop edges).</td>
								<td>contain</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Display Options', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>interaction-lock</code></td>
								<td>Master interaction lock: when "true", disables swipe/drag, keyboard navigation, click/tap photo navigation, gallery thumbnail fullscreen opening, and fullscreen entry gestures/buttons. Interactive controls are hidden; passive indicators like counter/progress can remain visible.</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>show-navigation</code></td>
								<td>Show previous/next navigation arrows: "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>show-title</code></td>
								<td>Display album title: "false" or "true"</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>show-counter</code></td>
								<td>Show the photo counter (e.g., "4 / 50" or "Trip to Bali: 4 / 50"): "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>show-link-button</code></td>
								<td>Show external link button to open album in Google Photos: "false" or "true"</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>show-download-button</code></td>
								<td>Show download button to save current photo: "false" or "true"</td>
								<td>false</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Video Support (Experimental)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php echo wp_kses( __( 'Albums containing videos will attempt to detect and play them using the native browser video player. Please notice: <strong>This is an experimental feature. The video playback experience might not be perfect under all conditions.</strong>', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>show-videos</code></td>
								<td><?php esc_html_e( 'Include videos from mixed albums: "true" or "false". Set to "false" to display only photos and filter out all video items.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>video-controls-autohide</code></td>
								<td><?php esc_html_e( 'Auto-hide the video control bar after a few seconds of inactivity: "true" or "false". When enabled, controls reappear on hover or tap.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>video-controls-color</code></td>
								<td><?php esc_html_e( 'Accent color for video play button and control bar. Any valid CSS hex color (e.g. "#00b2ff", "#FF69B4").', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>#00b2ff</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Mosaic Thumbnail Strip', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php esc_html_e( 'Display a strip of thumbnail previews alongside the main slider or carousel. Works with mode="slider" and mode="carousel". The strip is synchronized with the main swiper — clicking a thumbnail jumps to that photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>mosaic</code></td>
								<td><?php esc_html_e( 'Enable the mosaic thumbnail strip: "true" or "false".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>mosaic-position</code></td>
								<td><?php esc_html_e( 'Position of the thumbnail strip relative to the main viewer: "top", "bottom", "left", or "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>bottom</td>
							</tr>
							<tr>
								<td><code>mosaic-count</code></td>
								<td><?php esc_html_e( 'Number of thumbnails visible at once in the strip. Use an integer (e.g. "5") or "auto" to let the plugin calculate the best fit based on the available space.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>auto</td>
							</tr>
						</tbody>
					</table>

				</div>

				<!-- Troubleshooting -->
				<div class="jzsa-section">
					<h2><?php esc_html_e( 'Troubleshooting', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<div class="jzsa-faq">
						<h3><?php esc_html_e( 'Plugin Shows "Unable to Load Album"', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Use straight quotes in shortcode attributes: [jzsa-album link="..."] – not [jzsa-album link="…"]. The block editor (Gutenberg) may auto-convert quotes, which breaks shortcode parsing.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Make sure the album is shared publicly via link in Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Verify you are using the full link format (starts with https://photos.google.com/share/).', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Check that the album contains at least one photo.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Sample "Unable to Load Album" Error', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'This example intentionally uses an invalid link to demonstrate the red error message visitors will see when the album cannot be loaded.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<div class="jzsa-code-block">
								<code>[jzsa-album link="https://photos.google.com/share/INVALID-EXAMPLE-LINK"]</code>
								<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
							</div>
							<div class="jzsa-preview-container jzsa-preview-container-error-sample">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/INVALID-EXAMPLE-LINK"]' );
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

						<div class="jzsa-example">
							<h3><?php esc_html_e( 'Basic Album with Deprecated Link Format (Admin-Only Warning)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Same as above, but using the older short link format. Visitors will NOT see this warning, but you as an administrator should update the link to the new format.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
							<div class="jzsa-code-block">
									<code>[jzsa-album link="https://photos.app.goo.gl/6qmxgmqdouBFKH3i8" limit="6"]</code>
								<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
							</div>
							<div class="jzsa-preview-container jzsa-preview-container-basic-deprecated">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo do_shortcode( '[jzsa-album link="https://photos.app.goo.gl/6qmxgmqdouBFKH3i8" limit="6"]' );
								?>
							</div>
						</div>
					</div>

					<div class="jzsa-faq">
						<h3><?php esc_html_e( 'Changes Not Showing Up?', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Save or update your post – the cache clears automatically.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'If issues persist, try clearing your browser cache.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="jzsa-section jzsa-help-section">
					<h2><?php esc_html_e( 'This Plugin Has Potential, But...', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-warning" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #d63638; flex-shrink: 0;"></span>
						<span><?php esc_html_e( 'Found a bug or something not working right?', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://github.com/JanZeman/shared-albums-for-google-photos/issues/new" target="_blank" rel="noopener"><?php esc_html_e( 'Report it on GitHub', 'janzeman-shared-albums-for-google-photos' ); ?></a></span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-lightbulb" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #dba617; flex-shrink: 0;"></span>
						<span><?php esc_html_e( 'Missing a feature or have an idea for improvement?', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/" target="_blank" rel="noopener"><?php esc_html_e( 'Request it on the support forum', 'janzeman-shared-albums-for-google-photos' ); ?></a></span>
					</p>
				</div>

				<div class="jzsa-section jzsa-happy-section">
					<h2><?php esc_html_e( 'Enjoying This Plugin?', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p style="margin: 4px 0 6px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 36px; height: 36px; line-height: 36px; text-align: center; color: #f0ad4e; flex-shrink: 0;"></span>
						<span>
							<?php
							printf(
								/* translators: %s: link to WordPress.org reviews. */
								esc_html__( 'If this plugin saved you time or made your site better, a quick %s would truly make my day. It helps others find the plugin too!', 'janzeman-shared-albums-for-google-photos' ),
								'<a href="https://wordpress.org/support/plugin/janzeman-shared-albums-for-google-photos/reviews/#new-post" target="_blank" rel="noopener">' . esc_html__( '5-star review', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
							);
							?>
						</span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<img src="<?php echo esc_url( plugins_url( 'assets/BuyMeACoffee_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
						<span><?php esc_html_e( 'You can also', 'janzeman-shared-albums-for-google-photos' ); ?> <a href="https://www.buymeacoffee.com/janzeman" target="_blank" rel="noopener"><?php esc_html_e( 'buy me a coffee.', 'janzeman-shared-albums-for-google-photos' ); ?></a> <?php esc_html_e( "I'll take my family to a local café and finally explain why all those late-night coding sessions were somehow worth it ;)", 'janzeman-shared-albums-for-google-photos' ); ?></span>
					</p>
					<p style="margin: 6px 0 0; display: flex; align-items: center; gap: 8px;">
						<img src="<?php echo esc_url( plugins_url( 'assets/Photographer_128x128.png', dirname( __FILE__ ) ) ); ?>" alt="" style="width: 36px; height: 36px; flex-shrink: 0;">
						<span><?php esc_html_e( 'Made by a hobbyist WordPress developer and occasional photographer. Thank you for using this plugin!', 'janzeman-shared-albums-for-google-photos' ); ?></span>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
