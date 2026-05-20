<?php
/**
 * Seed deterministic WordPress pages and users for the Playwright suite.
 *
 * Run inside the WordPress container:
 *
 * docker compose exec wordpress php \
 *   /var/www/html/wp-content/plugins/janzeman-shared-albums-for-google-photos/tests/e2e/setup-fixtures.php
 */

declare( strict_types=1 );

$wp_root = getenv( 'WP_ROOT' ) ?: dirname( __DIR__, 5 );
$wp_load = rtrim( $wp_root, '/\\' ) . '/wp-load.php';

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI'] ?? '/';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Could not find wp-load.php at {$wp_load}\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! function_exists( 'wp_insert_post' ) ) {
	fwrite( STDERR, "WordPress did not load correctly.\n" );
	exit( 1 );
}

function jzsa_e2e_env( string $key, string $default ): string {
	$value = getenv( $key );
	return false === $value || '' === $value ? $default : $value;
}

function jzsa_e2e_upsert_page( string $slug, string $title, string $content ): int {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$args = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $page ) {
		$args['ID'] = $page->ID;
		$result     = wp_update_post( $args, true );
	} else {
		$result = wp_insert_post( $args, true );
	}

	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( "Failed to upsert page {$slug}: " . $result->get_error_message() );
	}

	return (int) $result;
}

function jzsa_e2e_ensure_admin_user( string $username, string $password, string $email, string $display_name ): int {
	$user_id = username_exists( $username );

	if ( ! $user_id ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => $email,
				'display_name' => $display_name,
				'role'         => 'administrator',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			throw new RuntimeException( "Failed to create user {$username}: " . $user_id->get_error_message() );
		}
	} else {
		wp_update_user(
			array(
				'ID'           => $user_id,
				'user_email'   => $email,
				'display_name' => $display_name,
				'role'         => 'administrator',
			)
		);
		wp_set_password( $password, (int) $user_id );
	}

	$user = new WP_User( (int) $user_id );
	$user->set_role( 'administrator' );

	return (int) $user_id;
}

$album_link = jzsa_e2e_env(
	'JZSA_E2E_ALBUM_URL',
	'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R'
);

$pages = array(
	'lightbox-fixture'  => array(
		'title'   => 'JZSA Lightbox E2E Fixtures',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" lightbox-toggle="click" fullscreen-toggle="disabled"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" lightbox-toggle="button-only" fullscreen-toggle="disabled"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" lightbox-toggle="button-only" fullscreen-toggle="button-only"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="disabled"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" lightbox-toggle="button-only" fullscreen-toggle="button-only"]',
			)
		),
	),
	'slideshow-fixture' => array(
		'title'   => 'Slideshow Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" slideshow="auto" slideshow-delay="1"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" slideshow="manual"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" slideshow="disabled"]',
			)
		),
	),
	'gallery-fixture'   => array(
		'title'   => 'Gallery Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="gallery" gallery-columns="3" fullscreen-toggle="button-only"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" gallery-layout="justified" fullscreen-toggle="button-only"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" gallery-scrollable="true" fullscreen-toggle="button-only"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" gallery-rows="2" fullscreen-toggle="button-only"]',
				'[jzsa-album link="' . $album_link . '" mode="gallery" lightbox-toggle="click" fullscreen-toggle="disabled"]',
			)
		),
	),
	'mosaic-fixture'    => array(
		'title'   => 'Mosaic Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" mosaic="true"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" mosaic="true" mosaic-position="left"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" mosaic="true" mosaic-position="top"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" mosaic="true" mosaic-position="bottom"]',
			)
		),
	),
	'info-fixture'      => array(
		'title'   => 'Info Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" info-bottom="{item} / {items}"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" info-top="{album-title}"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" info-bottom="{item}" info-top="{album-title}"]',
			)
		),
	),
	'feature-fixture'   => array(
		'title'   => 'Feature Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" show-navigation="true"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" show-download-button="true" show-link-button="true"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" interaction-lock="true"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" show-navigation="false"]',
			)
		),
	),
);

foreach ( $pages as $slug => $page ) {
	$id = jzsa_e2e_upsert_page( $slug, $page['title'], $page['content'] );
	echo "Upserted page {$slug} (#{$id})\n";
}

$admin_user = jzsa_e2e_env( 'JZSA_E2E_ADMIN_USER', 'dev' );
$admin_pass = jzsa_e2e_env( 'JZSA_E2E_ADMIN_PASS', 'test123' );
$admin_id   = jzsa_e2e_ensure_admin_user( $admin_user, $admin_pass, 'dev@example.test', 'Dev User' );
echo "Ensured admin user {$admin_user} (#{$admin_id})\n";

$disconnected_user = jzsa_e2e_env( 'JZSA_E2E_DISCONNECTED_USER', 'testuser-noc' );
$disconnected_pass = jzsa_e2e_env( 'JZSA_E2E_DISCONNECTED_PASS', 'testpass123' );
$disconnected_id   = jzsa_e2e_ensure_admin_user( $disconnected_user, $disconnected_pass, 'testuser-noc@example.test', 'Disconnected User' );

delete_user_meta( $disconnected_id, 'jzsa_community_jwt' );
delete_user_meta( $disconnected_id, 'jzsa_community_display_name' );
delete_user_meta( $disconnected_id, 'jzsa_community_display_url' );
echo "Ensured disconnected user {$disconnected_user} (#{$disconnected_id})\n";

$connected_user = jzsa_e2e_env( 'JZSA_E2E_CONNECTED_USER', $admin_user );
$connected_id   = username_exists( $connected_user );
$connected_jwt  = getenv( 'JZSA_E2E_CONNECTED_JWT' );
if ( $connected_id && false !== $connected_jwt && '' !== $connected_jwt ) {
	update_user_meta( (int) $connected_id, 'jzsa_community_jwt', $connected_jwt );
	echo "Set connected JWT for {$connected_user} (#{$connected_id}) from JZSA_E2E_CONNECTED_JWT\n";
} elseif ( $connected_id ) {
	echo "Preserved existing connected state for {$connected_user} (#{$connected_id})\n";
}

echo "E2E fixture setup complete.\n";
