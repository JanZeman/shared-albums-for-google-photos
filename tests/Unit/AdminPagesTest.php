<?php
/**
 * Tests for admin-page integration helpers.
 */

use PHPUnit\Framework\TestCase;

class AdminPagesTest extends TestCase {

	private JZSA_Admin_Pages $admin_pages;
	private ReflectionClass $reflection;

	protected function setUp(): void {
		$this->admin_pages = new JZSA_Admin_Pages();
		$this->reflection  = new ReflectionClass( $this->admin_pages );

		$_GET = array();
		$GLOBALS['jzsa_test_admin_menu_pages']      = array();
		$GLOBALS['jzsa_test_admin_submenu_pages']   = array();
		$GLOBALS['jzsa_test_enqueued_styles']       = array();
		$GLOBALS['jzsa_test_enqueued_scripts']      = array();
		$GLOBALS['jzsa_test_localized_scripts']     = array();
		$GLOBALS['jzsa_test_admin_page_title']      = 'Shared Albums';
		$GLOBALS['jzsa_test_current_screen_id']     = null;
		$GLOBALS['jzsa_test_options']               = array();
		$GLOBALS['jzsa_test_user_meta']             = array();
	}

	protected function tearDown(): void {
		$_GET = array();
	}

	private function setLazySamplePreviews( bool $enabled ): void {
		$property = $this->reflection->getProperty( 'lazy_sample_previews' );
		$property->setValue( $this->admin_pages, $enabled );
	}

	public function test_admin_page_urls_point_to_canonical_top_level_pages(): void {
		$this->assertSame(
			'https://site.example/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos',
			JZSA_Admin_Pages::get_guide_page_url()
		);
		$this->assertSame(
			'https://site.example/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-shortcode-parameters',
			JZSA_Admin_Pages::get_shortcode_parameters_page_url()
		);
		$this->assertSame(
			'https://site.example/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-placeholders',
			JZSA_Admin_Pages::get_placeholders_page_url()
		);
		$this->assertSame(
			'https://site.example/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-community',
			JZSA_Admin_Pages::get_community_page_url()
		);
		$this->assertSame(
			'https://site.example/wp-admin/admin.php?page=janzeman-shared-albums-for-google-photos-settings',
			JZSA_Admin_Pages::get_settings_page_url()
		);
	}

	public function test_add_admin_pages_registers_main_menu_and_five_submenus(): void {
		$this->admin_pages->add_admin_pages();

		$this->assertCount( 1, $GLOBALS['jzsa_test_admin_menu_pages'] );
		$this->assertSame( JZSA_Admin_Pages::MENU_SLUG, $GLOBALS['jzsa_test_admin_menu_pages'][0]['menu_slug'] );
		$this->assertSame( 'edit_pages', $GLOBALS['jzsa_test_admin_menu_pages'][0]['capability'] );
		$this->assertCount( 5, $GLOBALS['jzsa_test_admin_submenu_pages'] );
		$this->assertSame(
			array(
				JZSA_Admin_Pages::MENU_SLUG,
				JZSA_Admin_Pages::SHORTCODE_PARAMETERS_SLUG,
				JZSA_Admin_Pages::PLACEHOLDERS_SLUG,
				JZSA_Admin_Pages::COMMUNITY_SLUG,
				JZSA_Admin_Pages::SETTINGS_SLUG,
			),
			array_column( $GLOBALS['jzsa_test_admin_submenu_pages'], 'menu_slug' )
		);
	}

	public function test_menu_icon_stylesheet_is_enqueued_on_all_admin_pages(): void {
		$this->admin_pages->enqueue_admin_menu_icon_style();

		$this->assertCount( 1, $GLOBALS['jzsa_test_enqueued_styles'] );
		$this->assertSame( 'jzsa-admin-menu-icon', $GLOBALS['jzsa_test_enqueued_styles'][0]['handle'] );
		$this->assertStringContainsString( 'assets/css/admin-menu-icon.css', $GLOBALS['jzsa_test_enqueued_styles'][0]['src'] );
	}

	public function test_admin_assets_are_enqueued_only_for_plugin_pages(): void {
		$_GET['page'] = JZSA_Admin_Pages::SETTINGS_SLUG;

		$this->admin_pages->enqueue_admin_styles( 'ignored-hook' );

		$this->assertSame( 'jzsa-admin-styles', $GLOBALS['jzsa_test_enqueued_styles'][0]['handle'] );
		$this->assertSame( 'jzsa-admin-settings', $GLOBALS['jzsa_test_enqueued_scripts'][0]['handle'] );
		$this->assertSame( 'jzsaAdminAjax', $GLOBALS['jzsa_test_localized_scripts'][0]['object_name'] );
		$this->assertSame( 'https://site.example/wp-admin/admin-ajax.php', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['ajaxUrl'] );
		$this->assertSame( 'test-nonce', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['clearCacheNonce'] );
		$this->assertSame( 'test-nonce', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['previewNonce'] );
	}

	public function test_settings_page_shows_only_the_single_site_default_control(): void {
		$method = $this->reflection->getMethod( 'render_settings_page' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Settings are intentionally limited', $output );
		$this->assertStringContainsString( 'Most of the plugin is driven by shortcodes, so the global settings stay intentionally small.', $output );
		$this->assertStringContainsString( 'This setting determines which viewer is used only when a shortcode does not explicitly contain the <code>viewer</code> parameter.', $output );
		$this->assertStringContainsString( 'Shortcodes that explicitly set the <code>viewer</code> parameter are not affected by this setting.', $output );
		$this->assertStringNotContainsString( 'Changing it never rewrites your posts or pages.', $output );
		$this->assertStringContainsString( 'Default Viewer for Shortcodes Without an Explicit Viewer', $output );
		$this->assertStringContainsString( 'Lightbox, recommended', $output );
		$this->assertStringContainsString( 'Fullscreen', $output );
		$this->assertStringNotContainsString( 'One Setting', $output );
		$this->assertStringNotContainsString( 'Recommended Update: Try Lightbox', $output );
	}

	public function test_settings_notice_can_be_dismissed_per_user(): void {
		update_user_meta( 1, JZSA_Admin_Pages::SETTINGS_ANNOUNCEMENT_META, '' );
		$GLOBALS['jzsa_test_current_user_can'] = true;
		$method = $this->reflection->getMethod( 'render_settings_page' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$first_output = ob_get_clean();

		$this->assertStringContainsString( 'jzsa-settings-notice', $first_output );
		$this->assertStringContainsString( 'Settings are intentionally limited', $first_output );
		$this->assertStringNotContainsString( 'Viewer Default', $first_output );

		$_POST = array(
			'nonce' => wp_create_nonce( 'jzsa_dismiss_settings_notice' ),
		);
		try {
			$this->admin_pages->handle_dismiss_settings_notice();
		} catch ( JZSA_Test_JSON_Response $response ) {
			$this->assertTrue( $response->success );
		}

		ob_start();
		$method->invoke( $this->admin_pages );
		$second_output = ob_get_clean();

		$this->assertStringNotContainsString( 'jzsa-settings-notice', $second_output );
	}

	public function test_shortcode_parameters_page_mentions_the_site_default_for_missing_viewer(): void {
		$method = $this->reflection->getMethod( 'render_shortcode_parameters_page' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'If <code>viewer</code> is not set, the site default from <strong>Settings</strong> decides which viewer is used.', $output );
	}

	public function test_admin_assets_are_not_enqueued_for_unrelated_admin_page(): void {
		$_GET['page'] = 'plugins';

		$this->admin_pages->enqueue_admin_styles( 'ignored-hook' );

		$this->assertSame( array(), $GLOBALS['jzsa_test_enqueued_styles'] );
		$this->assertSame( array(), $GLOBALS['jzsa_test_enqueued_scripts'] );
		$this->assertSame( array(), $GLOBALS['jzsa_test_localized_scripts'] );
	}

	public function test_lazy_sample_placeholder_is_inactive_until_guide_rendering_enables_it(): void {
		$output = $this->admin_pages->maybe_render_lazy_sample_placeholder(
			false,
			'jzsa-album',
			array(),
			array( '[jzsa-album link="https://photos.google.com/share/AF1QipTest"]' )
		);

		$this->assertFalse( $output );
	}

	public function test_lazy_sample_placeholder_escapes_shortcode_attribute(): void {
		$this->setLazySamplePreviews( true );
		$shortcode = '[jzsa-album link="https://photos.google.com/share/AF1QipTest" title="<script>alert(1)</script>"]';

		$output = $this->admin_pages->maybe_render_lazy_sample_placeholder(
			false,
			'jzsa-album',
			array(),
			array( $shortcode )
		);

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'class="jzsa-lazy-preview"', $output );
		$this->assertStringContainsString( 'data-lazy-state="pending"', $output );
		$this->assertStringContainsString( esc_attr( $shortcode ), $output );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
		$this->assertStringContainsString( 'role="status"', $output );
		$this->assertStringContainsString( 'aria-live="polite"', $output );
	}

	public function test_lazy_sample_placeholder_ignores_other_shortcodes_and_empty_matches(): void {
		$this->setLazySamplePreviews( true );

		$this->assertSame(
			'already-rendered',
			$this->admin_pages->maybe_render_lazy_sample_placeholder( 'already-rendered', 'gallery', array(), array( '[gallery]' ) )
		);
		$this->assertSame(
			'already-rendered',
			$this->admin_pages->maybe_render_lazy_sample_placeholder( 'already-rendered', 'jzsa-album', array(), array( '' ) )
		);
	}

	public function test_dashboard_announcement_is_hidden_without_viewer_migration_flag(): void {
		$GLOBALS['jzsa_test_current_screen_id'] = 'dashboard';

		ob_start();
		$this->admin_pages->render_dashboard_announcement();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_dashboard_announcement_recommends_lightbox_without_changing_existing_galleries(): void {
		$GLOBALS['jzsa_test_current_screen_id'] = 'dashboard';
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );

		ob_start();
		$this->admin_pages->render_dashboard_announcement();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Lightbox is the recommended default viewer', $output );
		$this->assertStringContainsString( 'No worries, your existing galleries keep their current behavior.', $output );
		$this->assertStringContainsString( 'Open Viewer Guide', $output );
		$this->assertStringNotContainsString( 'Shared Albums now has a Community', $output );
		$this->assertStringNotContainsString( 'Keep Fullscreen as default', $output );
	}

	public function test_guide_migration_tutorial_explains_safe_viewer_update(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Recommended Update: Try Lightbox', $output );
		$this->assertStringContainsString( 'This is not a breaking change. It is only a recommendation.', $output );
		$this->assertStringContainsString( 'Your existing galleries keep their current behavior.', $output );
		$this->assertStringContainsString( 'Why Lightbox?', $output );
		$this->assertStringContainsString( '<li>Lightbox is easier to exit and keeps visitors inside the page.</li>', $output );
		$this->assertStringContainsString( '<li>Based on broad internet research, roughly 75% of online galleries use Lightbox as the default.</li>', $output );
		$this->assertStringContainsString( '<li>That still leaves the final choice to you as the admin, and you can keep Fullscreen if it fits your site better.</li>', $output );
		$this->assertStringContainsString( 'Set the Viewer Explicitly', $output );
		$this->assertStringContainsString( 'For predictable behavior, we recommend always setting the <code>viewer</code> parameter explicitly in each shortcode.', $output );
		$this->assertStringContainsString( 'If the <code>viewer</code> parameter is omitted, the site default is used: <strong>Fullscreen</strong>.', $output );
		$this->assertStringContainsString( '>Change it in Settings</a>', $output );
		$this->assertStringNotContainsString( 'Current site default:', $output );
		$this->assertGreaterThan( strpos( $output, 'Manual Migration Reference' ), strpos( $output, 'Set the Viewer Explicitly' ) );
		$this->assertStringNotContainsString( 'Default Viewer for Shortcodes Without an Explicit Viewer', $output );
		$this->assertStringContainsString( '<strong>Viewer Samples (21-38)</strong>', $output );
		$this->assertStringContainsString( 'Shortcode Migration Tool', $output );
		$this->assertStringContainsString( 'Preserve its current behavior', $output );
		$this->assertStringContainsString( 'Manual Migration Reference', $output );
		$this->assertStringContainsString( 'viewer=&quot;both&quot; lightbox-trigger=&quot;double-click&quot; fullscreen-trigger=&quot;button&quot;', $output );
		$this->assertStringContainsString( '<strong>Playground</strong>', $output );
		$this->assertStringContainsString( '<strong>validation will help you catch unknown or obsolete parameters</strong>', $output );
		$this->assertStringContainsString( 'The section will be collapsed. You can expand it anytime or check the Parameters page for the details.', $output );
	}

	public function test_guide_migration_dismissal_is_independent_from_dashboard_dismissal(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		update_user_meta( 1, JZSA_Admin_Pages::DASHBOARD_ANNOUNCEMENT_META, JZSA_Admin_Pages::ANNOUNCEMENT_VERSION );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Recommended Update: Try Lightbox', $output );
	}

	public function test_guide_migration_tutorial_stays_visible_but_collapsed_after_guide_dismissal(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		update_user_meta( 1, JZSA_Admin_Pages::GUIDE_ANNOUNCEMENT_META, JZSA_Admin_Pages::ANNOUNCEMENT_VERSION );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Recommended Update: Try Lightbox', $output );
		$this->assertStringNotContainsString( '<details id="jzsa-guide-migration-details" open>', $output );
		$this->assertStringNotContainsString( 'Collapse this migration guide', $output );
	}
}
