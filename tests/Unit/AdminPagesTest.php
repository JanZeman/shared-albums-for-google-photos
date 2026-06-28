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
	}

	public function test_add_admin_pages_registers_main_menu_and_four_submenus(): void {
		$this->admin_pages->add_admin_pages();

		$this->assertCount( 1, $GLOBALS['jzsa_test_admin_menu_pages'] );
		$this->assertSame( JZSA_Admin_Pages::MENU_SLUG, $GLOBALS['jzsa_test_admin_menu_pages'][0]['menu_slug'] );
		$this->assertSame( 'edit_pages', $GLOBALS['jzsa_test_admin_menu_pages'][0]['capability'] );
		$this->assertCount( 4, $GLOBALS['jzsa_test_admin_submenu_pages'] );
		$this->assertSame(
			array(
				JZSA_Admin_Pages::MENU_SLUG,
				JZSA_Admin_Pages::SHORTCODE_PARAMETERS_SLUG,
				JZSA_Admin_Pages::PLACEHOLDERS_SLUG,
				JZSA_Admin_Pages::COMMUNITY_SLUG,
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
		$_GET['page'] = JZSA_Admin_Pages::SHORTCODE_PARAMETERS_SLUG;

		$this->admin_pages->enqueue_admin_styles( 'ignored-hook' );

		$this->assertSame( 'jzsa-admin-styles', $GLOBALS['jzsa_test_enqueued_styles'][0]['handle'] );
		$this->assertSame( 'jzsa-admin-settings', $GLOBALS['jzsa_test_enqueued_scripts'][0]['handle'] );
		$this->assertSame( 'jzsaAdminAjax', $GLOBALS['jzsa_test_localized_scripts'][0]['object_name'] );
		$this->assertSame( 'https://site.example/wp-admin/admin-ajax.php', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['ajaxUrl'] );
		$this->assertSame( 'test-nonce', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['clearCacheNonce'] );
		$this->assertSame( 'test-nonce', $GLOBALS['jzsa_test_localized_scripts'][0]['l10n']['previewNonce'] );
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

	public function test_dashboard_announcement_explains_viewer_breaking_change_and_fullscreen_fix(): void {
		$GLOBALS['jzsa_test_current_screen_id'] = 'dashboard';
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );

		ob_start();
		$this->admin_pages->render_dashboard_announcement();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sorry for the disruption.', $output );
		$this->assertStringContainsString( 'Open Migration Guide', $output );
		$this->assertStringNotContainsString( 'Shared Albums now has a Community', $output );
		$this->assertStringNotContainsString( 'Keep Fullscreen as default', $output );
	}

	public function test_guide_migration_tutorial_explains_fullscreen_fix(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Migration Guide: Lightbox Is Now the Default Viewer', $output );
		$this->assertStringContainsString( '<strong>Viewer</strong> means either Lightbox or Fullscreen.', $output );
		$this->assertStringContainsString( 'go through the Viewer samples', $output );
		$this->assertStringContainsString( 'viewer-toggle="fullscreen-button"', $output );
		$this->assertStringContainsString( 'viewer-toggle="lightbox-button, fullscreen-button"', $output );
		$this->assertStringContainsString( 'roughly a 75:25 ratio', $output );
		$this->assertStringContainsString( 'Do You Prefer Fullscreen?', $output );
		$this->assertStringContainsString( '[jzsa-album link=&quot;...&quot;] -&gt; [jzsa-album link=&quot;...&quot; viewer-toggle=&quot;fullscreen-button&quot;]', $output );
		$this->assertStringContainsString( 'Do You Prefer Lightbox?', $output );
		$this->assertStringContainsString( '[jzsa-album link=&quot;...&quot;] -&gt; [jzsa-album link=&quot;...&quot;]', $output );
		$this->assertStringContainsString( 'Do You Want Both for Your Visitors?', $output );
		$this->assertStringContainsString( 'Are you sure you do not need this migration guide anymore?', $output );
		$this->assertStringContainsString( 'Really dismiss it?', $output );
	}

	public function test_guide_migration_dismissal_is_independent_from_dashboard_dismissal(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		update_user_meta( 1, JZSA_Admin_Pages::DASHBOARD_ANNOUNCEMENT_META, JZSA_Admin_Pages::ANNOUNCEMENT_VERSION );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Migration Guide: Lightbox Is Now the Default Viewer', $output );
	}

	public function test_guide_migration_tutorial_stays_visible_but_collapsed_after_guide_dismissal(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		update_user_meta( 1, JZSA_Admin_Pages::GUIDE_ANNOUNCEMENT_META, JZSA_Admin_Pages::ANNOUNCEMENT_VERSION );
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Migration Guide: Lightbox Is Now the Default Viewer', $output );
		$this->assertStringNotContainsString( '<details id="jzsa-guide-migration" class="jzsa-section jzsa-viewer-migration-guide" open>', $output );
		$this->assertStringNotContainsString( 'Collapse this migration guide', $output );
	}
}
