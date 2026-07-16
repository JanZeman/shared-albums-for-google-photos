<?php
/**
 * Tests for admin-page integration helpers.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdminPagesTest extends TestCase {

	private JZSA_Admin_Pages $admin_pages;
	private ReflectionClass $reflection;

	protected function setUp(): void {
		$this->admin_pages = new JZSA_Admin_Pages();
		$this->reflection  = new ReflectionClass( $this->admin_pages );

		$_GET = array();
		$_POST = array();
		$GLOBALS['jzsa_test_admin_menu_pages']      = array();
		$GLOBALS['jzsa_test_admin_submenu_pages']   = array();
		$GLOBALS['jzsa_test_enqueued_styles']       = array();
		$GLOBALS['jzsa_test_enqueued_scripts']      = array();
		$GLOBALS['jzsa_test_localized_scripts']     = array();
		$GLOBALS['jzsa_test_do_shortcode_calls']    = array();
		$GLOBALS['jzsa_test_admin_page_title']      = 'Shared Albums';
		$GLOBALS['jzsa_test_current_screen_id']     = null;
		$GLOBALS['jzsa_test_current_user_can']      = false;
		$GLOBALS['jzsa_test_nonce_valid']           = true;
		$GLOBALS['jzsa_test_options']               = array();
		$GLOBALS['jzsa_test_user_meta']             = array();
	}

	protected function tearDown(): void {
		$_GET = array();
		$_POST = array();
		$GLOBALS['jzsa_test_current_user_can'] = false;
		$GLOBALS['jzsa_test_nonce_valid']      = true;
	}

	private function callAjax( string $method ): JZSA_Test_JSON_Response {
		try {
			$this->admin_pages->$method();
		} catch ( JZSA_Test_JSON_Response $response ) {
			return $response;
		}

		$this->fail( 'Expected ' . $method . ' to send a JSON response.' );
	}

	public static function shortcodeToolEndpointProvider(): array {
		return array(
			'validation' => array(
				'handle_validate_shortcode',
				array( 'nonce' => 'test-nonce', 'shortcode' => '[jzsa-album link="https://photos.google.com/share/test"]' ),
			),
			'migration' => array(
				'handle_migrate_shortcode',
				array( 'nonce' => 'test-nonce', 'shortcode' => '[jzsa-album link="https://photos.google.com/share/test"]', 'goal' => 'preserve' ),
			),
			'default viewer' => array(
				'handle_set_default_viewer',
				array( 'nonce' => 'test-nonce', 'viewer' => 'lightbox' ),
			),
		);
	}

	#[DataProvider( 'shortcodeToolEndpointProvider' )]
	public function test_shortcode_tool_endpoints_reject_users_without_capability( string $method, array $post ): void {
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
		$_POST = $post;

		$response = $this->callAjax( $method );

		$this->assertFalse( $response->success );
		$this->assertSame( 403, $response->status_code );
		$this->assertSame( 'Insufficient permissions', $response->data );
		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
	}

	#[DataProvider( 'shortcodeToolEndpointProvider' )]
	public function test_shortcode_tool_endpoints_reject_invalid_nonces( string $method, array $post ): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		$GLOBALS['jzsa_test_nonce_valid']      = false;
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
		$_POST = $post;

		$response = $this->callAjax( $method );

		$this->assertFalse( $response->success );
		$this->assertSame( 403, $response->status_code );
		$this->assertSame( 'Invalid nonce', $response->data );
		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
	}

	public function test_default_viewer_endpoint_rejects_invalid_value_without_mutation(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
		$_POST = array( 'nonce' => 'test-nonce', 'viewer' => 'both' );

		$response = $this->callAjax( 'handle_set_default_viewer' );

		$this->assertFalse( $response->success );
		$this->assertSame( 400, $response->status_code );
		$this->assertSame( 'Invalid default viewer.', $response->data );
		$this->assertSame( 'fullscreen', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
	}

	public function test_default_viewer_endpoint_saves_an_authorized_value(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
		$_POST = array( 'nonce' => 'test-nonce', 'viewer' => 'lightbox' );

		$response = $this->callAjax( 'handle_set_default_viewer' );

		$this->assertTrue( $response->success );
		$this->assertSame( array( 'viewer' => 'lightbox' ), $response->data );
		$this->assertSame( 'lightbox', get_option( JZSA_DEFAULT_VIEWER_OPTION ) );
	}

	public function test_migration_endpoint_returns_output_for_an_authorized_request(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
		$_POST = array(
			'nonce'     => 'test-nonce',
			'shortcode' => '[jzsa-album link="https://photos.google.com/share/test"]',
			'goal'      => 'preserve',
		);

		$response = $this->callAjax( 'handle_migrate_shortcode' );

		$this->assertTrue( $response->success );
		$this->assertTrue( $response->data['ok'] );
		$this->assertSame(
			'[jzsa-album link="https://photos.google.com/share/test" viewer="fullscreen"]',
			$response->data['shortcode']
		);
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
		$this->assertStringContainsString( 'Recommended: Set the Viewer Explicitly', $output );
		$this->assertStringContainsString( 'If all your shortcodes set it, you can safely ignore the default viewer setting below.', $output );
		$this->assertStringContainsString( 'The default viewer is only a fallback for shortcodes that omit the <code>viewer</code> parameter.', $output );
		$this->assertLessThan( strpos( $output, 'The default viewer is only a fallback' ), strpos( $output, 'Recommended: Set the Viewer Explicitly' ) );
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
		$this->assertStringContainsString( 'Viewer Examples', $output );
		$this->assertStringContainsString( 'viewer=&quot;lightbox&quot; viewer-trigger=&quot;double-click&quot;', $output );
		$this->assertStringContainsString( 'viewer=&quot;both&quot; lightbox-trigger=&quot;double-click&quot; fullscreen-trigger=&quot;button&quot;', $output );
	}

	public function test_guide_samples_use_the_complete_canonical_parameter_order(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-admin-pages.php' );
		preg_match_all( '/^.*\$[a-z_]*shortcode\s*=\s*\'\[jzsa-album link=.*$/m', $source, $matches );
		$priority = JZSA_Shortcode_Tools::canonical_attribute_order();

		$this->assertNotEmpty( $matches[0] );
		foreach ( $matches[0] as $declaration ) {
			preg_match_all( '/\s([\w-]+)=/', $declaration, $names );
			$expected_order = array_values(
				array_filter(
					$priority,
					function ( $name ) use ( $names ) {
						return in_array( $name, $names[1], true );
					}
				)
			);

			$this->assertContains( 'viewer', $names[1] );
			$this->assertSame( $expected_order, array_slice( $names[1], 0, count( $expected_order ) ) );
		}
	}

	public function test_shortcode_validation_offers_safe_legacy_migration(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		$_POST = array(
			'nonce'     => wp_create_nonce( 'jzsa_validate_shortcode' ),
			'shortcode' => '[jzsa-album link="https://photos.google.com/share/test" fullscreen-toggle="double-click"]',
		);

		try {
			$this->admin_pages->handle_validate_shortcode();
			$this->fail( 'Expected a JSON response.' );
		} catch ( JZSA_Test_JSON_Response $response ) {
			$this->assertTrue( $response->success );
			$this->assertSame( 'legacy', $response->data['migration']['sourceModel'] );
			$this->assertSame(
				'[jzsa-album link="https://photos.google.com/share/test" viewer="fullscreen" viewer-trigger="double-click"]',
				$response->data['migration']['shortcode']
			);
		}
	}

	public function test_shortcode_validation_does_not_offer_unsafe_legacy_migration(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		$_POST = array(
			'nonce'     => wp_create_nonce( 'jzsa_validate_shortcode' ),
			'shortcode' => '[jzsa-album link="https://photos.google.com/share/test" lightbox-toggle="click" fullscreen-toggle="double-click"]',
		);

		try {
			$this->admin_pages->handle_validate_shortcode();
			$this->fail( 'Expected a JSON response.' );
		} catch ( JZSA_Test_JSON_Response $response ) {
			$this->assertTrue( $response->success );
			$this->assertNull( $response->data['migration'] );
			$this->assertSame( 'legacy_gesture_conflict', $response->data['issues'][0]['code'] );
		}
	}

	public function test_shortcode_validation_returns_a_behavior_neutral_format_candidate(): void {
		$GLOBALS['jzsa_test_current_user_can'] = true;
		$_POST = array(
			'nonce'     => wp_create_nonce( 'jzsa_validate_shortcode' ),
			'shortcode' => "[jzsa-album viewer='lightbox' link='https://photos.google.com/share/test' mode='slider']",
		);

		try {
			$this->admin_pages->handle_validate_shortcode();
			$this->fail( 'Expected a JSON response.' );
		} catch ( JZSA_Test_JSON_Response $response ) {
			$this->assertTrue( $response->success );
			$this->assertTrue( $response->data['format']['changed'] );
			$this->assertSame(
				'[jzsa-album link="https://photos.google.com/share/test" mode="slider" viewer="lightbox"]',
				$response->data['format']['shortcode']
			);
			$this->assertNull( $response->data['migration'] );
		}
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

		$this->assertStringContainsString( 'Lightbox is now the recommended default viewer', $output );
		$this->assertStringContainsString( 'No worries, your existing galleries keep their current behavior.', $output );
		$this->assertStringContainsString( 'Open Viewer Guide', $output );
		$this->assertStringNotContainsString( 'Shared Albums now has a Community', $output );
		$this->assertStringNotContainsString( 'Keep Fullscreen as default', $output );
	}

	public function test_guide_migration_tutorial_is_hidden_without_viewer_migration_flag(): void {
		$method = $this->reflection->getMethod( 'render_guide_migration_tutorial' );

		ob_start();
		$method->invoke( $this->admin_pages );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_guide_migration_tutorial_explains_safe_viewer_update(): void {
		update_option( JZSA_VIEWER_MIGRATION_NOTICE_OPTION, '1' );
		update_option( JZSA_DEFAULT_VIEWER_OPTION, 'fullscreen' );
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
		$this->assertStringContainsString( 'For the sake of simplicity, we recommend always setting the <code>viewer</code> parameter explicitly in each shortcode.', $output );
		$this->assertStringContainsString( 'If it is omitted, the site default is used: <strong>Fullscreen</strong>.', $output );
		$this->assertStringContainsString( '>Change it in Settings</a>', $output );
		$this->assertStringNotContainsString( 'Current site default:', $output );
		$this->assertStringNotContainsString( 'Default Viewer for Shortcodes Without an Explicit Viewer', $output );
		$this->assertStringContainsString( '<strong>Viewer Samples (21-38)</strong>', $output );
		$this->assertStringContainsString( 'Shortcode Migration Tool', $output );
		$this->assertStringContainsString( 'Keep this gallery working exactly as it does now', $output );
		$this->assertStringContainsString( '(update shortcode syntax only)', $output );
		$this->assertStringContainsString( 'Use Lightbox', $output );
		$this->assertStringContainsString( '(recommended)', $output );
		$this->assertStringContainsString( 'Offer both Lightbox and Fullscreen', $output );
		$this->assertStringContainsString( '(Will visitors understand both options? Investigate samples 29 &amp; 30.)', $output );
		$this->assertMatchesRegularExpression( '/value="preserve" checked/', $output );
		$this->assertLessThan( strpos( $output, 'value="lightbox"' ), strpos( $output, 'value="preserve"' ) );
		$this->assertStringNotContainsString( 'Manual Migration Reference', $output );
		$this->assertStringContainsString( 'Recommended Migration Path', $output );
		$this->assertStringContainsString( '<strong>Shortcode Migration Tool</strong>', $output );
		$this->assertStringContainsString( '<strong>Playground</strong>', $output );
		$this->assertStringContainsString( 'It will guide you safely through the process.', $output );
		$this->assertStringContainsString( 'We recommend migrating even if you want to keep the gallery working exactly as it does now.', $output );
		$this->assertStringContainsString( 'to update only the shortcode syntax without changing the viewer experience.', $output );
		$this->assertStringContainsString( 'Never update a live page until you have verified its shortcode with this tool', $output );
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
