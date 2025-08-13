<?php
/**
 * Plugin Name: GF Map Boundary
 * Description: Adds a Gravity Forms field to draw a polygon on Google Maps and saves a static image with the entry.
 * Version: 0.1.0
 * Author: darylldoyle
 * License: GPL-2.0-or-later License URI:
 *  https://www.gnu.org/licenses/gpl-2.0.html Text Domain:       gf-map-boundary
 */

use GfMapBoundary\Autoloader;
use GfMapBoundary\Plugin;
use GfMapBoundary\Support\Geometry;

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
( new Autoloader( GFMB_PLUGIN_DIR . 'src' ) )->register();

// Initialize plugin (register hooks, classes, filters)
Plugin::init();

// -----------------------------------------------------------------------------
// Back-compat procedural wrappers (do not change functionality)
// -----------------------------------------------------------------------------

if ( ! function_exists( 'gfmb_get_api_key' ) ) {
	/**
	 * Retrieve the configured Google Maps API key.
	 *
	 * What: Thin wrapper around Plugin::getApiKey().
	 * Why: Maintains backward-compatibility for procedural calls in themes/snippets.
	 *
	 * @return string
	 */
	function gfmb_get_api_key() {
		return Plugin::getApiKey();
	}
}

if ( ! function_exists( 'gfmb_form_has_field' ) ) {
	/**
	 * Check if a Gravity Form contains the map boundary field.
	 *
	 * @param array $form Gravity Forms form array.
	 *
	 * @return bool
	 */
	function gfmb_form_has_field( $form ) {
		return Plugin::formHasField( $form );
	}
}

if ( ! function_exists( 'gfmb_decode_polyline' ) ) {
	/**
	 * Decode a Google polyline string into a list of [lat, lng] pairs.
	 *
	 * @param string $encoded
	 *
	 * @return list<array{0: float, 1: float}>
	 */
	function gfmb_decode_polyline( $encoded ) {
		$encoded = is_string( $encoded ) ? $encoded : '';

		return Geometry::decodePolyline( $encoded );
	}
}

if ( ! function_exists( 'gfmb_encode_polyline' ) ) {
	/**
	 * Encode a list of [lat, lng] pairs into a Google polyline string.
	 *
	 * @param iterable<array{0: float|int, 1: float|int}> $points
	 *
	 * @return string
	 */
	function gfmb_encode_polyline( $points ) {
		$points = is_array( $points ) ? $points : [];

		return Geometry::encodePolyline( $points );
	}
}

if ( ! function_exists( 'gfmb_bounds_from_points' ) ) {
	/**
	 * Compute bounds [south, west, north, east] from a list of [lat, lng] points.
	 *
	 * @param iterable<array{0: float|int, 1: float|int}> $points
	 *
	 * @return array{0: float,1: float,2: float,3: float}
	 */
	function gfmb_bounds_from_points( $points ) {
		$points = is_array( $points ) ? $points : [];

		return Geometry::boundsFromPoints( $points );
	}
}

if ( ! function_exists( 'gfmb_lng_to_pixel_x' ) ) {
	/**
	 * Convert longitude to world pixel X for a zoom level.
	 *
	 * @param float|int $lng
	 * @param int       $zoom
	 *
	 * @return float
	 */
	function gfmb_lng_to_pixel_x( $lng, $zoom ) {
		return Geometry::lngToPixelX( (float) $lng, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_lat_to_pixel_y' ) ) {
	/**
	 * Convert latitude to world pixel Y for a zoom level.
	 *
	 * @param float|int $lat
	 * @param int       $zoom
	 *
	 * @return float
	 */
	function gfmb_lat_to_pixel_y( $lat, $zoom ) {
		return Geometry::latToPixelY( (float) $lat, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_pixel_x_to_lng' ) ) {
	/**
	 * Convert world pixel X back to longitude for a zoom level.
	 *
	 * @param float|int $x
	 * @param int       $zoom
	 *
	 * @return float
	 */
	function gfmb_pixel_x_to_lng( $x, $zoom ) {
		return Geometry::pixelXToLng( (float) $x, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_pixel_y_to_lat' ) ) {
	/**
	 * Convert world pixel Y back to latitude for a zoom level.
	 *
	 * @param float|int $y
	 * @param int       $zoom
	 *
	 * @return float
	 */
	function gfmb_pixel_y_to_lat( $y, $zoom ) {
		return Geometry::pixelYToLat( (float) $y, (int) $zoom );
	}
}

if ( ! function_exists( 'gfmb_compute_zoom_for_bounds' ) ) {
	/**
	 * Compute the highest zoom that fits the given bounds in the given pixel viewport.
	 *
	 * @param array{0: float,1: float,2: float,3: float} $bounds
	 * @param int                                        $width_px
	 * @param int                                        $height_px
	 * @param int                                        $pad_px
	 *
	 * @return int
	 */
	function gfmb_compute_zoom_for_bounds( $bounds, $width_px, $height_px, $pad_px ) {
		$bounds = is_array( $bounds ) ? $bounds : [ 0, 0, 0, 0 ];

		return Geometry::computeZoomForBounds( $bounds, (int) $width_px, (int) $height_px,
			(int) $pad_px );
	}
}
