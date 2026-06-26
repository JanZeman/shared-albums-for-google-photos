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

function jzsa_e2e_video_album_markup( string $id, string $lightbox_toggle, string $fullscreen_toggle ): string {
	$photos = array(
		array(
			'full'     => 'https://lh3.googleusercontent.com/jzsa-e2e-video-one=w1920-h1440-no',
			'thumb'    => 'https://lh3.googleusercontent.com/jzsa-e2e-video-one=w400-h400-c',
			'preview'  => 'https://lh3.googleusercontent.com/jzsa-e2e-video-one=w800-h600-no',
			'video'    => 'https://lh3.googleusercontent.com/jzsa-e2e-video-one=dv',
			'type'     => 'video',
			'id'       => 'JZSA_E2E_VIDEO_1',
			'width'    => 1280,
			'height'   => 720,
			'filename' => 'fixture-video-one.mp4',
		),
		array(
			'full'     => 'https://lh3.googleusercontent.com/jzsa-e2e-image-one=w1920-h1440',
			'thumb'    => 'https://lh3.googleusercontent.com/jzsa-e2e-image-one=w400-h400-c',
			'preview'  => 'https://lh3.googleusercontent.com/jzsa-e2e-image-one=w800-h600',
			'id'       => 'JZSA_E2E_IMAGE_1',
			'width'    => 1200,
			'height'   => 800,
			'filename' => 'fixture-image-one.jpg',
		),
		array(
			'full'     => 'https://lh3.googleusercontent.com/jzsa-e2e-video-two=w1920-h1440-no',
			'thumb'    => 'https://lh3.googleusercontent.com/jzsa-e2e-video-two=w400-h400-c',
			'preview'  => 'https://lh3.googleusercontent.com/jzsa-e2e-video-two=w800-h600-no',
			'video'    => 'https://lh3.googleusercontent.com/jzsa-e2e-video-two=dv',
			'type'     => 'video',
			'id'       => 'JZSA_E2E_VIDEO_2',
			'width'    => 1280,
			'height'   => 720,
			'filename' => 'fixture-video-two.mp4',
		),
	);

	$classes = array( 'jzsa-album', 'swiper', 'jzsa-loader-pending' );
	if ( 'disabled' !== $lightbox_toggle && 'disabled' !== $fullscreen_toggle ) {
		$classes[] = 'jzsa-has-dual-expand';
	}

	$html  = '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '"';
	$html .= ' data-all-photos="' . esc_attr( wp_json_encode( $photos ) ) . '"';
	$html .= ' data-total-count="3" data-slideshow="disabled" data-fullscreen-slideshow="disabled"';
	$html .= ' data-interaction-lock="false" data-show-navigation="true" data-fullscreen-show-navigation="true"';
	$html .= ' data-show-link-button="false" data-show-download-button="false"';
	$html .= ' data-fullscreen-show-link-button="false" data-fullscreen-show-download-button="false"';
	$html .= ' data-video-controls-autohide="false" data-fullscreen-video-controls-autohide="false"';
	$html .= ' data-info-halo-effect="true" data-mosaic="false" data-fullscreen-mosaic="false"';
	$html .= ' data-has-active-bottom-center="true" data-info-bottom="{item} / {items}" data-fullscreen-info-bottom="{item} / {items}"';
	$html .= ' data-mosaic-position="bottom" data-mosaic-count="0" data-mosaic-gap="8" data-mosaic-opacity="0.3"';
	$html .= ' data-fullscreen-mosaic-position="bottom" data-fullscreen-mosaic-layout="outer" data-fullscreen-mosaic-count="0" data-fullscreen-mosaic-gap="8" data-fullscreen-mosaic-opacity="0.3"';
	$html .= ' data-slideshow-delay="5" data-download-size-warning="128" data-image-fit="cover" data-fullscreen-image-fit="contain"';
	$html .= ' data-start-at="1" data-fullscreen-slideshow-delay="5" data-slideshow-autoresume="30" data-fullscreen-slideshow-autoresume="30"';
	$html .= ' data-mode="slider" data-background-color="transparent" data-controls-color="#ffffff" data-fullscreen-controls-color="#ffffff"';
	$html .= ' data-video-controls-color="#00b2ff" data-fullscreen-video-controls-color="#00b2ff"';
	$html .= ' data-album-title="JZSA E2E Video Album" data-fullscreen-toggle="' . esc_attr( $fullscreen_toggle ) . '"';
	$html .= ' data-album-url="https://photos.google.com/share/JZSA_E2E_VIDEO_ALBUM?key=fixture123"';
	$html .= ' data-info-font-size="12" data-fullscreen-info-font-size="12" data-lightbox-toggle="' . esc_attr( $lightbox_toggle ) . '"';
	$html .= ' style="width: 400px; max-width: 100%; height: 300px; --gallery-bg-color: transparent; --jzsa-controls-color: #ffffff; --jzsa-video-controls-color: #00b2ff; --jzsa-corner-radius: 0px; --jzsa-info-font-size: 12px">';
	$html .= '<div class="swiper-wrapper"></div><div class="swiper-button-prev"></div><div class="swiper-button-next"></div><div class="swiper-pagination"></div>';
	$html .= '<button class="swiper-button-play-pause" title="Play/Pause (Space)"></button><div class="swiper-slideshow-progress"><div class="swiper-slideshow-progress-bar"></div></div>';
	if ( 'disabled' !== $fullscreen_toggle ) {
		$html .= '<div class="swiper-button-fullscreen"></div>';
	}
	if ( 'disabled' !== $lightbox_toggle ) {
		$html .= '<button class="swiper-button-lightbox" type="button" title="Open in lightbox" aria-label="Open in lightbox"></button>';
	}
	$html .= '</div>';

	return $html;
}

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
				'[jzsa-album link="' . $album_link . '" mode="slider" fullscreen-toggle="button-only" fullscreen-max-width="320" fullscreen-max-height="240"]',
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
				'[jzsa-album link="' . $album_link . '" mode="slider" info-bottom="{item}" info-top="{filename}" info-top-secondary="{camera} {description}"]',
			)
		),
	),
	'viewer-fixture'    => array(
		'title'   => 'Viewer Fixture',
		'content' => implode(
			"\n\n",
			array(
				'[jzsa-album link="' . $album_link . '" mode="slider" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="640" viewer-max-height="480" viewer-source-width="1200" viewer-source-height="900" viewer-image-fit="contain" viewer-background-color="rgba(0,0,0,0.85)" viewer-corner-radius="12" viewer-controls-color="#123456" viewer-show-navigation="false" viewer-slideshow="manual" viewer-info-top="Shared {item}" viewer-info-bottom="{item} of {items}" viewer-info-font-size="18" viewer-mosaic="true" viewer-mosaic-position="bottom" viewer-mosaic-layout="overlay" viewer-mosaic-count="3" viewer-mosaic-gap="6" viewer-mosaic-opacity="0.4" viewer-mosaic-background="#111111" viewer-mosaic-corner-radius="8"]',
				'[jzsa-album link="' . $album_link . '" mode="slider" viewer-toggle="lightbox-button, fullscreen-button" viewer-max-width="900" lightbox-max-width="700" fullscreen-max-width="1100" viewer-info-top="Shared" lightbox-info-top="Lightbox only" fullscreen-info-top="Fullscreen only" viewer-mosaic="true" lightbox-mosaic="false" fullscreen-mosaic="true"]',
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
	'video-fixture'     => array(
		'title'   => 'Video Fixture',
		'content' => implode(
			"\n\n",
			array(
				jzsa_e2e_video_album_markup( 'jzsa-e2e-video-inline', 'disabled', 'button-only' ),
				jzsa_e2e_video_album_markup( 'jzsa-e2e-video-lightbox', 'button-only', 'disabled' ),
				'[jzsa-album link="' . $album_link . '" mode="slider" limit="1"]',
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
echo "Ensured disconnected user {$disconnected_user} (#{$disconnected_id})\n";

/**
 * Probe whether a community JWT is still accepted by the API for the
 * install identified by $install_hash. Used by the fixture to decide
 * whether an existing JWT can be reused (no API mutation needed) vs a
 * full sign-in flow is required.
 */
function jzsa_e2e_jwt_works( string $jwt, string $install_hash ): bool {
	if ( '' === $jwt ) {
		return false;
	}
	$probe = wp_remote_get(
		JZSA_COMMUNITY_API_URL . '/v1/me',
		array(
			'headers' => array(
				'Authorization'  => 'Bearer ' . $jwt,
				'X-JZSA-Install' => $install_hash,
				'Accept'         => 'application/json',
			),
			'timeout' => 5,
		)
	);
	return ! is_wp_error( $probe ) && 200 === wp_remote_retrieve_response_code( $probe );
}

/**
 * Make a WP user appear "signed in to community" without disrupting any
 * existing sign-in on this WP install. Preferred path: borrow a working
 * JWT from any other WP user on the same install. Fallback: drive the
 * live sign-in flow (only when nobody is connected).
 *
 * Why this is non-invasive: multiple WP users on the same WP install all
 * use the same `jzsa_install_secret` (it lives in `wp_options`, not
 * `user_meta`). On the API side a single `user_installs` row binds that
 * install hash to one community account. So once one WP user signs in,
 * any other WP user can share that JWT and the API treats their calls as
 * coming from the same install + account. Sufficient for tests, and the
 * original sign-in (e.g. your manual sign-in as jan) keeps working.
 *
 * Only when nothing on the install is bound do we fall through to the
 * full sign-in flow, which will create a fresh community account for
 * $email. That path no longer evicts existing accounts; if the install
 * is already bound, the borrow path always succeeds first.
 *
 * Local-dev only. Hardcodes the docker-compose API DB credentials for
 * the fallback path.
 *
 * @throws RuntimeException on any HTTP / DB / API failure in the
 *                           fallback (full sign-in) path.
 */
function jzsa_e2e_signin_user( int $wp_user_id, string $email, string $display_name ): void {
	// 1. Make sure the WP install has an install_secret to identify itself.
	$install_secret = get_option( 'jzsa_install_secret', '' );
	if ( '' === $install_secret ) {
		$install_secret = bin2hex( random_bytes( 32 ) );
		update_option( 'jzsa_install_secret', $install_secret, false );
	}
	$install_hash = hash( 'sha256', $install_secret );

	// 2. Already signed in? Idempotent no-op.
	$own_jwt = (string) get_user_meta( $wp_user_id, 'jzsa_community_jwt', true );
	if ( jzsa_e2e_jwt_works( $own_jwt, $install_hash ) ) {
		echo "  (existing JWT still valid; skipping live sign-in)\n";
		return;
	}

	// 3. Borrow a JWT from any other WP user on this install whose JWT
	// the API still accepts. No API mutation, no eviction. The other
	// user's community account view is shared with this user.
	global $wpdb;
	$candidates = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, meta_value
		   FROM {$wpdb->usermeta}
		  WHERE meta_key = 'jzsa_community_jwt'
		    AND meta_value != ''
		    AND user_id != %d",
		$wp_user_id
	) );
	foreach ( $candidates as $row ) {
		$other_jwt = (string) $row->meta_value;
		if ( ! jzsa_e2e_jwt_works( $other_jwt, $install_hash ) ) {
			continue;
		}
		update_user_meta( $wp_user_id, 'jzsa_community_jwt', $other_jwt );
		$other_name = (string) get_user_meta( (int) $row->user_id, 'jzsa_community_display_name', true );
			if ( '' !== $other_name ) {
				update_user_meta( $wp_user_id, 'jzsa_community_display_name', $other_name );
			}
			echo "  (borrowed JWT from WP user #{$row->user_id}; both WP users now share that community account)\n";
		return;
	}

	// 4. Nobody is connected on this install. Drive the live sign-in
	// flow with $email so the fixture sets up a brand-new community
	// account for the test user. Only clears pending_verifications for
	// $email (in case a prior /auth/start call burned through rate
	// limit budget); does NOT touch user_installs or users.
	$db = new mysqli( 'mariadb', 'jzsa_api', 'jzsa_api', 'jzsa_api', 3306 );
	if ( $db->connect_error ) {
		throw new RuntimeException( "Could not connect to jzsa_api DB: {$db->connect_error}" );
	}
	$stmt = $db->prepare( 'DELETE FROM pending_verifications WHERE email = ?' );
	$stmt->bind_param( 's', $email );
	$stmt->execute();
	$stmt->close();

	// 3. Generate the WP-side challenge transient (the API's site
	// verification will fetch /community-challenge with this value).
	$challenge       = wp_generate_password( 40, false, false );
	$challenge_key   = 'jzsa_community_auth_challenge_' . hash( 'sha256', $challenge );
	set_transient( $challenge_key, $challenge, 5 * MINUTE_IN_SECONDS );

	// 4. POST /v1/auth/start. Expect state=pending (fresh email, fresh install).
	$start = wp_remote_post(
		JZSA_COMMUNITY_API_URL . '/v1/auth/start',
		array(
			'headers' => array(
				'Content-Type'   => 'application/json',
				'X-JZSA-Install' => $install_hash,
			),
			'body'    => wp_json_encode( array(
				'email'               => $email,
				'display_name'        => $display_name,
				'site_url'            => home_url(),
				'install_secret_hash' => $install_hash,
				'verification_url'    => rest_url( 'jzsa/v1/community-challenge' ),
				'challenge'           => $challenge,
			) ),
			'timeout' => 15,
		)
	);
	if ( is_wp_error( $start ) ) {
		$db->close();
		throw new RuntimeException( '/auth/start request failed: ' . $start->get_error_message() );
	}
	$start_code = wp_remote_retrieve_response_code( $start );
	$start_body = json_decode( wp_remote_retrieve_body( $start ), true );
	if ( 200 !== $start_code ) {
		$db->close();
		throw new RuntimeException( "/auth/start returned {$start_code}: " . wp_remote_retrieve_body( $start ) );
	}
	if ( ( $start_body['state'] ?? '' ) !== 'pending' ) {
		// Unlikely after eviction; defensively use the connected response if it appears.
		if ( ( $start_body['state'] ?? '' ) === 'connected' && ! empty( $start_body['jwt'] ) ) {
			$db->close();
			update_user_meta( $wp_user_id, 'jzsa_community_jwt', $start_body['jwt'] );
			return;
		}
		$db->close();
		throw new RuntimeException( 'Unexpected /auth/start state: ' . wp_remote_retrieve_body( $start ) );
	}
	$pending_id = (int) $start_body['pending_id'];

	// 5. Read the one-time confirmation token straight from the DB (the API
	// only emits it via email; this fixture intentionally bypasses that).
	$stmt = $db->prepare( 'SELECT token FROM pending_verifications WHERE id = ?' );
	$stmt->bind_param( 'i', $pending_id );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$db->close();
	if ( empty( $row['token'] ) ) {
		throw new RuntimeException( "No token row for pending_id {$pending_id}" );
	}
	$token = $row['token'];

	// 6. Click the confirm link (server-to-server). 200 = pending row marked
	// confirmed and user_installs row created.
	$confirm = wp_remote_get(
		JZSA_COMMUNITY_API_URL . '/v1/auth/confirm?token=' . rawurlencode( $token ),
		array( 'timeout' => 10 )
	);
	if ( is_wp_error( $confirm ) ) {
		throw new RuntimeException( '/auth/confirm request failed: ' . $confirm->get_error_message() );
	}
	if ( 200 !== wp_remote_retrieve_response_code( $confirm ) ) {
		throw new RuntimeException( '/auth/confirm returned ' . wp_remote_retrieve_response_code( $confirm ) );
	}

	// 7. Poll for the JWT. Confirm flushes the pending → connected state
	// immediately, so one poll is enough; loop briefly for resilience.
	$jwt = null;
	for ( $i = 0; $i < 5; $i++ ) {
		$poll = wp_remote_get(
			JZSA_COMMUNITY_API_URL . '/v1/auth/poll?pending_id=' . $pending_id,
			array(
				'headers' => array( 'X-JZSA-Install' => $install_hash ),
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $poll ) ) {
			continue;
		}
		if ( 200 === wp_remote_retrieve_response_code( $poll ) ) {
			$poll_body = json_decode( wp_remote_retrieve_body( $poll ), true );
			$jwt = $poll_body['jwt'] ?? null;
			break;
		}
		// 202 means still pending; very unlikely right after confirm but possible.
		usleep( 500_000 );
	}
	if ( empty( $jwt ) ) {
		throw new RuntimeException( '/auth/poll never returned a JWT for pending ' . $pending_id );
	}

	update_user_meta( $wp_user_id, 'jzsa_community_jwt', $jwt );
	update_user_meta( $wp_user_id, 'jzsa_community_display_name', $display_name );
}

$connected_user = jzsa_e2e_env( 'JZSA_E2E_CONNECTED_USER', $admin_user );
$connected_id   = username_exists( $connected_user );
$connected_jwt  = getenv( 'JZSA_E2E_CONNECTED_JWT' );
if ( $connected_id && false !== $connected_jwt && '' !== $connected_jwt ) {
	update_user_meta( (int) $connected_id, 'jzsa_community_jwt', $connected_jwt );
	echo "Set connected JWT for {$connected_user} (#{$connected_id}) from JZSA_E2E_CONNECTED_JWT\n";
} elseif ( $connected_id ) {
	// No env JWT supplied. Drive the live sign-in flow against the local
	// API so the e2e suite has a working connected user without a manual
	// sign-in step. Evicts any prior community account owner of this
	// install - see jzsa_e2e_signin_user() for the trade-off.
	try {
		jzsa_e2e_signin_user(
			(int) $connected_id,
			"e2e-{$connected_user}@example.test",
			'E2E Test User'
		);
		echo "Programmatically signed in {$connected_user} (#{$connected_id}) via the live API\n";
	} catch ( Throwable $e ) {
		fwrite( STDERR, "WARNING: Auto sign-in for {$connected_user} failed: " . $e->getMessage() . "\n" );
		fwrite( STDERR, "  E2E tests requiring connected state will fail until {$connected_user} has a JWT.\n" );
	}
}

echo "E2E fixture setup complete.\n";
