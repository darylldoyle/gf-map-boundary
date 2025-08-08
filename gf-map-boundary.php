<?php
/**
 * Plugin Name:       GF Map Boundary
 * Description:       Adds a Gravity Forms field to draw a polygon on Google Maps and saves a static image with the entry.
 * Version:           0.1.0
 * Author:            darylldoyle
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gf-map-boundary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// Constants & Bootstrap (PSR-4 Autoload + OOP initialization)
// -----------------------------------------------------------------------------

define( 'GFMB_VERSION', '0.1.0' );
if ( ! defined( 'GFMB_PLUGIN_FILE' ) ) {
	define( 'GFMB_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'GFMB_PLUGIN_DIR' ) ) {
	define( 'GFMB_PLUGIN_DIR', plugin_dir_path( GFMB_PLUGIN_FILE ) );
}
if ( ! defined( 'GFMB_PLUGIN_URL' ) ) {
	define( 'GFMB_PLUGIN_URL', plugin_dir_url( GFMB_PLUGIN_FILE ) );
}

// Load PSR-4 autoloader for src/
require_once GFMB_PLUGIN_DIR . 'src/Autoloader.php';
(new \GfMapBoundary\Autoloader( GFMB_PLUGIN_DIR . 'src' ))->register();

// Initialize plugin (register hooks, classes, filters)
\GfMapBoundary\Plugin::init();

// -----------------------------------------------------------------------------
// Back-compat procedural wrappers (do not change functionality)
// -----------------------------------------------------------------------------

if ( ! function_exists( 'gfmb_get_api_key' ) ) {
	function gfmb_get_api_key() {
		return \GfMapBoundary\Plugin::getApiKey();
	}
}

if ( ! function_exists( 'gfmb_form_has_field' ) ) {
	function gfmb_form_has_field( $form ) {
		return \GfMapBoundary\Plugin::formHasField( $form );
	}
}

if ( ! function_exists( 'gfmb_decode_polyline' ) ) {
	function gfmb_decode_polyline( $encoded ) {
		$encoded = is_string( $encoded ) ? $encoded : '';
		return \GfMapBoundary\Support\Geometry::decodePolyline( $encoded );
	}
}

if ( ! function_exists( 'gfmb_encode_polyline' ) ) {
	function gfmb_encode_polyline( $points ) {
		$points = is_array( $points ) ? $points : [];
		return \GfMapBoundary\Support\Geometry::encodePolyline( $points );
	}
}

if ( ! function_exists( 'gfmb_bounds_from_points' ) ) {
	function gfmb_bounds_from_points( $points ) {
		$points = is_array( $points ) ? $points : [];
		return \GfMapBoundary\Support\Geometry::boundsFromPoints( $points );
	}
}

if ( ! function_exists( 'gfmb_lng_to_pixel_x' ) ) {
	function gfmb_lng_to_pixel_x( $lng, $zoom ) {
		return \GfMapBoundary\Support\Geometry::lngToPixelX( (float) $lng, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_lat_to_pixel_y' ) ) {
	function gfmb_lat_to_pixel_y( $lat, $zoom ) {
		return \GfMapBoundary\Support\Geometry::latToPixelY( (float) $lat, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_pixel_x_to_lng' ) ) {
	function gfmb_pixel_x_to_lng( $x, $zoom ) {
		return \GfMapBoundary\Support\Geometry::pixelXToLng( (float) $x, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_pixel_y_to_lat' ) ) {
	function gfmb_pixel_y_to_lat( $y, $zoom ) {
		return \GfMapBoundary\Support\Geometry::pixelYToLat( (float) $y, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_compute_zoom_for_bounds' ) ) {
	function gfmb_compute_zoom_for_bounds( $bounds, $width_px, $height_px, $pad_px ) {
		$bounds = is_array( $bounds ) ? $bounds : [0,0,0,0];
		return \GfMapBoundary\Support\Geometry::computeZoomForBounds( $bounds, (int) $width_px, (int) $height_px, (int) $pad_px );
	}
}
