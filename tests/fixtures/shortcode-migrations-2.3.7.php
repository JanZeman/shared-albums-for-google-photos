<?php

declare( strict_types=1 );

$link = 'https://photos.google.com/share/AF1QipOg3EA51ATc_YWHyfcffDCzNZFsVTU_uBqSEKFix7LY80DIgH3lMkLwt4QDTHd8EQ?key=RGwySFNhbmhqMFBDbnZNUUtwY0stNy1XV1JRbE9R';

return array(
	'basic-slider-implicit-fullscreen' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16"]',
		'source_model'   => 'implicit',
		'current_viewer' => 'fullscreen',
		'contains'       => array( 'mode="slider"', 'viewer="fullscreen"', 'corner-radius="16"' ),
		'absent'         => array( 'viewer-trigger=' ),
	),
	'fullscreen-click' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" fullscreen-toggle="click"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'fullscreen',
		'contains'       => array( 'viewer="fullscreen"', 'viewer-trigger="click"' ),
		'absent'         => array( 'fullscreen-toggle=' ),
	),
	'fullscreen-double-click' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" fullscreen-toggle="double-click"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'fullscreen',
		'contains'       => array( 'viewer="fullscreen"', 'viewer-trigger="double-click"' ),
		'absent'         => array( 'fullscreen-toggle=' ),
	),
	'fullscreen-disabled' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" fullscreen-toggle="disabled"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'disabled',
		'contains'       => array( 'viewer="disabled"' ),
		'absent'         => array( 'viewer-trigger=', 'fullscreen-toggle=' ),
	),
	'lightbox-click-gallery' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" width="800" mode="gallery" limit="6" gallery-gap="8" corner-radius="16" lightbox-toggle="click" fullscreen-toggle="disabled"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'lightbox',
		'contains'       => array( 'mode="gallery"', 'viewer="lightbox"', 'viewer-trigger="click"' ),
		'absent'         => array( 'lightbox-toggle=', 'fullscreen-toggle=' ),
	),
	'lightbox-button-with-bounded-box' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" lightbox-toggle="button-only" fullscreen-toggle="disabled" lightbox-corner-radius="16" lightbox-max-width="1100" lightbox-max-height="750" lightbox-background-color="rgba(0,128,0,0.7)"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'lightbox',
		'contains'       => array( 'viewer="lightbox"', 'lightbox-max-width="1100"', 'lightbox-max-height="750"' ),
		'absent'         => array( 'viewer-trigger=', 'lightbox-toggle=', 'fullscreen-toggle=' ),
	),
	'both-viewers-button-only' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" lightbox-toggle="button-only" lightbox-max-width="1100" lightbox-max-height="750" fullscreen-toggle="button-only"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'both',
		'contains'       => array( 'viewer="both"', 'lightbox-max-width="1100"', 'lightbox-max-height="750"' ),
		'absent'         => array( 'viewer-trigger=', 'lightbox-trigger=', 'fullscreen-trigger=' ),
	),
	'lightbox-double-click' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" lightbox-toggle="double-click" fullscreen-toggle="disabled" lightbox-max-width="1100" lightbox-max-height="750"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'lightbox',
		'contains'       => array( 'viewer="lightbox"', 'viewer-trigger="double-click"' ),
		'absent'         => array( 'lightbox-toggle=', 'fullscreen-toggle=' ),
	),
	'fullscreen-display-caps' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" width="400" height="300" corner-radius="16" fullscreen-source-width="512" fullscreen-source-height="340" fullscreen-display-max-width="640" fullscreen-display-max-height="425"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'fullscreen',
		'contains'       => array( 'viewer="fullscreen"', 'fullscreen-max-width="640"', 'fullscreen-max-height="425"' ),
		'absent'         => array( 'fullscreen-display-max-width=', 'fullscreen-display-max-height=', 'lightbox-max-width=', 'lightbox-max-height=' ),
	),
	'both-viewers-sideways-controls' => array(
		'shortcode'      => '[jzsa-album link="' . $link . '" mode="slider" corner-radius="16" lightbox-toggle="button-only" fullscreen-toggle="button-only" lightbox-max-width="1100" lightbox-max-height="750" fullscreen-controls-color="#E63946"]',
		'source_model'   => 'legacy',
		'current_viewer' => 'both',
		'contains'       => array( 'viewer="both"', 'lightbox-controls-color="#E63946"', 'fullscreen-controls-color="#E63946"' ),
		'absent'         => array( 'lightbox-toggle=', 'fullscreen-toggle=' ),
	),
);
