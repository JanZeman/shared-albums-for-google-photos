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
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
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
								<strong class="jzsa-purpose-heading" style="display:flex; align-items:center; font-size:18px; margin-bottom:6px; gap:10px;">
									<img src="<?php echo esc_url( JZSA_PLUGIN_URL . 'assets/icon-256x256.gif' ); ?>" alt="<?php esc_attr_e( 'Shared Albums for Google Photos logo', 'janzeman-shared-albums-for-google-photos' ); ?>" class="jzsa-purpose-heading-icon" style="width:48px; height:48px; border-radius:8px;" />
									<span>
										<?php esc_html_e( 'What This Plugin Does – and What You Must Do Yourself', 'janzeman-shared-albums-for-google-photos' ); ?>
									</span>
								</strong>
						<p style="margin: 16px 0 0 0;">
							<?php esc_html_e( 'This plugin renders one album per shortcode. It does not provide any layout mechanism for multiple albums. One [jzsa-album] shortcode will always render only one given album. To display many albums together you have to build your own layout and place one shortcode per album where you want it to appear.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
						<p style="margin: 8px 0 0 0;">
							<?php esc_html_e( 'For grid layouts or pagination across many albums (for example year-based archives), use your theme or block editor layout tools such as the Query Loop block, and insert one [jzsa-album] shortcode in each grid item.', 'janzeman-shared-albums-for-google-photos' ); ?>
						</p>
						<div class="jzsa-purpose-diagram-wrapper">
							<svg class="jzsa-purpose-diagram" viewBox="0 0 360 95" role="img" aria-labelledby="jzsa-purpose-diagram-title jzsa-purpose-diagram-desc">
								<title id="jzsa-purpose-diagram-title"><?php esc_html_e( 'One album versus multi-album page layout', 'janzeman-shared-albums-for-google-photos' ); ?></title>
								<desc id="jzsa-purpose-diagram-desc"><?php esc_html_e( 'Left: we render your individual albums, one per shortcode. Right: you create the overall layout of multiple albums on your page.', 'janzeman-shared-albums-for-google-photos' ); ?></desc>
								
								<!-- Left: one album gallery rendered by this plugin -->
								<rect x="1" y="10" width="140" height="73" rx="6" class="jzsa-purpose-panel jzsa-purpose-panel-single" />
								<text x="72" y="50" text-anchor="middle" class="jzsa-purpose-label">
									<tspan x="72" dy="-7"><?php esc_html_e( 'We render your', 'janzeman-shared-albums-for-google-photos' ); ?></tspan>
									<tspan x="72" dy="14"><?php esc_html_e( 'individual albums.', 'janzeman-shared-albums-for-google-photos' ); ?></tspan>
								</text>

								<!-- Right: multi-album page layout built outside this plugin -->
								<rect x="169" y="10" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-1" />
								<rect x="250" y="10" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-2" />
								<rect x="169" y="36" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-3" />
								<rect x="250" y="36" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-4" />
								<rect x="169" y="62" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-5" />
								<rect x="250" y="62" width="75" height="21" rx="4" class="jzsa-purpose-panel jzsa-purpose-panel-negative jzsa-purpose-panel-neg-6" />

								<text x="207" y="21" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-1">
									<?php esc_html_e( 'You', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="287" y="21" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-2">
									<?php esc_html_e( 'take', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="207" y="47" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-3">
									<?php esc_html_e( 'care', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="287" y="47" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-4">
									<?php esc_html_e( 'about', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="207" y="73" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-5">
									<?php esc_html_e( 'the', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
								<text x="287" y="73" text-anchor="middle" dominant-baseline="middle" class="jzsa-purpose-label-negative jzsa-purpose-label-neg-6">
									<?php esc_html_e( 'layout.', 'janzeman-shared-albums-for-google-photos' ); ?>
								</text>
							</svg>
						</div>
					</div>
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
							<p><?php esc_html_e( 'Use the Shortcode Playground below to explore the sample album: try the main controls, enter and exit fullscreen, understand what your visitors will experience.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">2</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Browse the Shortcode Samples Further Below', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Scroll down to the Shortcode Samples section to see different shortcode configurations with descriptions, ready to copy and adapt.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						</div>
					</div>

					<div class="jzsa-step">
						<div class="jzsa-step-number">3</div>
						<div class="jzsa-step-content">
							<h3><?php esc_html_e( 'Try the Samples in the Playground', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php esc_html_e( 'Copy some of the sample shortcodes, paste them into the Shortcode Playground textarea on this page end experiment with shortcode modifications.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
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

				<!-- Shortcode Playground Section -->
				<div class="jzsa-section jzsa-playground-section">
					<h2><?php esc_html_e( 'Shortcode Playground', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
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
					><?php echo esc_textarea( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]' ); ?></textarea>

					<div class="jzsa-preview-container jzsa-playground-preview">
						<?php
							// Step 1: static preview using the same sample album as above.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]' );
						?>
					</div>
				</div>

				<!-- Shortcode Samples Section -->
				<div class="jzsa-section jzsa-samples-section">
					<h2><?php esc_html_e( 'Shortcode Samples', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Basic Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'The default viewer experience when no parameters are set.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]</code>
							<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-basic">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R"]' );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Custom Size Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Set the preview width and height so they fit your page layout.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<div class="jzsa-code-block">
							<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" height="600"]</code>
							<button class="jzsa-copy-btn" onclick="jzsaCopyToClipboard(this, '[jzsa-album link=&quot;https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R&quot; width=&quot;800&quot; height=&quot;600&quot;]')"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
						</div>
						<div class="jzsa-preview-container jzsa-preview-container-custom-size">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" width="800" height="600"]' );
							?>
						</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Album with Title and Photo Counter', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Display the album title followed by the photo counter.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-title="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-title">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-title="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Album with Title Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Display the album title without a photo counter.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-title="true" show-counter="false"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-title">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-title="true" show-counter="false"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Limit Number of Photos Per Album', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Load only a limited number of photos from a large album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" max-photos-per-album="5"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-limit-photos">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" max-photos-per-album="5"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Custom Autoplay Speed', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Autoplay here is set to one second. You can easily see the difference in speed compared to the sample above :)', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay-delay="1"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-slower-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay-delay="1"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Start at First Photo with Disabled Autoplay', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Start the gallery at the first photo (start-at="1") and disable autoplay (manual navigation only).', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" start-at="1" autoplay="false"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-autoplay">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" start-at="1" autoplay="false"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Disable Cropping and Set Custom Background', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows photos fully without cropping by using image-fit="contain". This exposes the background color. Here we set it to green to make it very visible.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" start-at="1" autoplay="false" image-fit="contain" background-color="#008000"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-no-crop">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" start-at="1" autoplay="false" image-fit="contain" background-color="#008000"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Stretched Images', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses image-fit="stretch" to stretch photos and fill the entire frame, which may distort photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" image-fit="stretch"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-stretch">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" image-fit="stretch"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'High-Resolution Photos', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'This example shows how to request higher-resolution photos from Google Photos. Please switch to fullscreen mode to see the difference in image quality.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" image-width="2560" image-height="1700"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-hires">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" image-width="2560" image-height="1700"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Delayed Autoplay Resume', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Switch to fullscreen mode, stop autoplay, and wait for the timeout to expire. You will see autoplay resume automatically.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay-inactivity-timeout="20"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-autoplay-timeout">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay-inactivity-timeout="20"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Fullscreen Autoplay Only', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Disables autoplay in the page preview while keeping it enabled in fullscreen mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay="false" full-screen-autoplay="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fullscreen-only">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" autoplay="false" full-screen-autoplay="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Faster Fullscreen Autoplay', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses full-screen-autoplay-delay to advance photos more quickly in fullscreen mode.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-autoplay-delay="2"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fast-fullscreen">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-autoplay-delay="2"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Click Anywhere to Enter Fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Shows full-screen-switch="single-click" so a single click on the album enters or exits fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-switch="single-click"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-switch-single">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-switch="single-click"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Double-Click Navigation in Fullscreen', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'This parameter changes how double-clicking works. It no longer toggles between preview and fullscreen. Instead, in fullscreen mode, double-clicking on the left or right area navigates between photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-navigation="double-click"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-fs-nav-double">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" full-screen-navigation="double-click"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Show "Open in Google Photos" Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-link-button parameter to display an external link button to the original album.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-link-button="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-link-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-link-button="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Show Download Button', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Enables the show-download-button parameter to add a download button for the current photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-download-button="true"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-download-button">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" show-download-button="true"]' );
						?>
					</div>
					</div>

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Carousel Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses mode="carousel" to show multiple photos side by side. On mobile and tablets it shows 2 photos at a time, and on desktop it shows 3 photos.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
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

					<div class="jzsa-example">
						<h3><?php esc_html_e( 'Carousel to Single Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php esc_html_e( 'Uses mode="carousel-to-single" to combine the best of both modes: displays photos as a carousel, but clicking any photo opens it as a single photo viewer in fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<div class="jzsa-code-block">
						<code>[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel-to-single"]</code>
						<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
					</div>
					<div class="jzsa-preview-container jzsa-preview-container-carousel">
						<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo do_shortcode( '[jzsa-album link="https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R" mode="carousel-to-single"]' );
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
					<h2><?php esc_html_e( 'List of All Shortcode Parameters', 'janzeman-shared-albums-for-google-photos' ); ?></h2>

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
								<td>#FFFFFF</td>
							</tr>
							<tr>
								<td><code>image-fit</code></td>
								<td>How photos fit the frame: "cover" (fill and crop edges), "contain" (letterbox, no cropping), or "stretch" (fill and distort).</td>
								<td>cover</td>
							</tr>
							<tr>
								<td><code>width</code></td>
								<td>Width in pixels or "auto"</td>
								<td>267</td>
							</tr>
							<tr>
								<td><code>height</code></td>
								<td>Height in pixels or "auto"</td>
								<td>200</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Image Quality', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
								<td><code>image-width</code></td>
								<td>Full-resolution photo width to fetch from Google</td>
								<td>1920</td>
							</tr>
							<tr>
								<td><code>image-height</code></td>
								<td>Full-resolution photo height to fetch from Google</td>
								<td>1440</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Autoplay Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
								<td><code>autoplay</code></td>
								<td>Enable autoplay in normal mode: "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>autoplay-delay</code></td>
								<td>Autoplay delay in normal mode, in seconds, supports ranges like "4-12"</td>
								<td>"4-12"</td>
							</tr>
							<tr>
								<td><code>autoplay-inactivity-timeout</code></td>
								<td>Time in seconds after which autoplay resumes following user interaction</td>
								<td>30</td>
							</tr>
							<tr>
								<td><code>start-at</code></td>
								<td>Starting photo: "random" (default) or a 1-based photo index like "1" or "12". Values out of range fall back to 1.</td>
								<td>random</td>
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
								<td><code>full-screen-autoplay</code></td>
								<td>Enable autoplay in fullscreen mode: "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>full-screen-autoplay-delay</code></td>
								<td>Autoplay delay in fullscreen mode, in seconds, supports ranges like "3-5" or single values</td>
								<td>3</td>
							</tr>
							<tr>
								<td><code>full-screen-switch</code></td>
								<td>Full screen switch mode: "double-click" (default), "button-only" (button only), or "single-click" (single-click). Works both in and out of full screen mode.</td>
								<td>double-click</td>
							</tr>
							<tr>
								<td><code>full-screen-navigation</code></td>
								<td>Full screen navigation mode: "single-click" (click left/right areas to navigate), "buttons-only" (navigation buttons only), or "double-click" (double-click left/right areas to navigate). Only works when in full screen mode.</td>
								<td>single-click</td>
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
								<td><code>show-title</code></td>
								<td>Display album title: "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>show-counter</code></td>
								<td>Show the photo counter (e.g., "4 / 50" or "Trip to Bali: 4 / 50"): "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>show-link-button</code></td>
								<td>Show external link button to open album in Google Photos: "true" or "false"</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>show-download-button</code></td>
								<td>Show download button to save current photo: "true" or "false"</td>
								<td>false</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Gallery Mode', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
									• "single": Single photo viewer with zoom support (pinch/double-click to zoom)<br>
									• "carousel": Multiple photos visible at once (2 on mobile/tablet, 3 on desktop)<br>
									• "carousel-to-single": Carousel preview (2 or 3 photos visible) that switches to a single photo viewer in fullscreen</td>
								<td>single</td>
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
								<code>[jzsa-album link="https://photos.app.goo.gl/6qmxgmqdouBFKH3i8"]</code>
								<button class="jzsa-copy-btn" type="button"><?php esc_html_e( 'Copy', 'janzeman-shared-albums-for-google-photos' ); ?></button>
							</div>
							<div class="jzsa-preview-container jzsa-preview-container-basic-deprecated">
								<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo do_shortcode( '[jzsa-album link="https://photos.app.goo.gl/6qmxgmqdouBFKH3i8"]' );
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

				<!-- Help & Support -->
				<div class="jzsa-section jzsa-help-section">
					<h2><?php esc_html_e( 'Need More Help?', 'janzeman-shared-albums-for-google-photos' ); ?></h2>
					<p>
						<?php
					printf(
						/* translators: %s: link to plugin page on WordPress.org. */
						esc_html__( 'For detailed documentation on all available settings, visit the %s.', 'janzeman-shared-albums-for-google-photos' ),
						'<a href="https://wordpress.org/plugins/janzeman-shared-albums-for-google-photos/" target="_blank" rel="noopener">' . esc_html__( 'plugin page', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
					);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: link to GitHub issues. */
							esc_html__( 'Found a bug or have a feature request? %s.', 'janzeman-shared-albums-for-google-photos' ),
							'<a href="https://github.com/JanZeman/shared-albums-for-google-photos/issues" target="_blank" rel="noopener">' . esc_html__( 'Report it on GitHub', 'janzeman-shared-albums-for-google-photos' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
