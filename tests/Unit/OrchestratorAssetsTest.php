<?php

declare( strict_types=1 );

namespace JZSA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JZSA_Admin_Pages;
use JZSA_Shared_Albums;

/**
 * Tests front-end and admin-preview asset enqueue behavior.
 */
class OrchestratorAssetsTest extends TestCase {

	private JZSA_Shared_Albums $orchestrator;

	protected function setUp(): void {
		$this->orchestrator = new JZSA_Shared_Albums( JZSA_PLUGIN_FILE );

		$_GET = array();
		$GLOBALS['jzsa_test_enqueued_styles']   = array();
		$GLOBALS['jzsa_test_enqueued_scripts']  = array();
		$GLOBALS['jzsa_test_localized_scripts'] = array();
	}

	protected function tearDown(): void {
		$_GET = array();
	}

	public function test_enqueue_assets_registers_vendor_and_plugin_handles(): void {
		$this->orchestrator->enqueue_assets();

		$this->assertSame(
			array( 'swiper-css', 'plyr-css', 'jzsa-style' ),
			array_column( $GLOBALS['jzsa_test_enqueued_styles'], 'handle' )
		);
		$this->assertSame(
			array( 'swiper-js', 'plyr-js', 'jzsa-init' ),
			array_column( $GLOBALS['jzsa_test_enqueued_scripts'], 'handle' )
		);
		$this->assertSame( array( 'swiper-css', 'plyr-css' ), $GLOBALS['jzsa_test_enqueued_styles'][2]['deps'] );
		$this->assertSame( array( 'jquery', 'swiper-js', 'plyr-js' ), $GLOBALS['jzsa_test_enqueued_scripts'][2]['deps'] );
		$this->assertStringStartsWith( JZSA_VERSION . '.', (string) $GLOBALS['jzsa_test_enqueued_styles'][2]['ver'] );
		$this->assertStringStartsWith( JZSA_VERSION . '.', (string) $GLOBALS['jzsa_test_enqueued_scripts'][2]['ver'] );
	}

	public function test_enqueue_assets_localizes_frontend_ajax_config(): void {
		$this->orchestrator->enqueue_assets();

		$this->assertCount( 1, $GLOBALS['jzsa_test_localized_scripts'] );
		$localized = $GLOBALS['jzsa_test_localized_scripts'][0];

		$this->assertSame( 'jzsa-init', $localized['handle'] );
		$this->assertSame( 'jzsaAjax', $localized['object_name'] );
		$this->assertSame( 'https://site.example/wp-admin/admin-ajax.php', $localized['l10n']['ajaxUrl'] );
		$this->assertSame( 'test-nonce', $localized['l10n']['downloadNonce'] );
		$this->assertSame( 'test-nonce', $localized['l10n']['previewNonce'] );
		$this->assertSame( 'test-nonce', $localized['l10n']['refreshNonce'] );
		$this->assertSame( 'test-nonce', $localized['l10n']['chunkNonce'] );
		$this->assertSame( 'test-nonce', $localized['l10n']['photoMetaNonce'] );
		$this->assertStringContainsString( 'assets/vendor/plyr/plyr.svg', $localized['l10n']['plyrSvgUrl'] );
		$this->assertArrayHasKey( 'openLightbox', $localized['l10n']['i18n'] );
		$this->assertArrayHasKey( 'largeDownloadWarning', $localized['l10n']['i18n'] );
	}

	public function test_enqueue_admin_assets_runs_only_on_preview_pages(): void {
		$_GET['page'] = JZSA_Admin_Pages::MENU_SLUG;
		$this->orchestrator->enqueue_admin_assets( 'ignored-hook' );
		$this->assertSame( array( 'swiper-css', 'plyr-css', 'jzsa-style' ), array_column( $GLOBALS['jzsa_test_enqueued_styles'], 'handle' ) );

		$GLOBALS['jzsa_test_enqueued_styles']   = array();
		$GLOBALS['jzsa_test_enqueued_scripts']  = array();
		$GLOBALS['jzsa_test_localized_scripts'] = array();
		$_GET['page'] = JZSA_Admin_Pages::SHORTCODE_PARAMETERS_SLUG;
		$this->orchestrator->enqueue_admin_assets( 'ignored-hook' );

		$this->assertSame( array(), $GLOBALS['jzsa_test_enqueued_styles'] );
		$this->assertSame( array(), $GLOBALS['jzsa_test_enqueued_scripts'] );
		$this->assertSame( array(), $GLOBALS['jzsa_test_localized_scripts'] );
	}
}
