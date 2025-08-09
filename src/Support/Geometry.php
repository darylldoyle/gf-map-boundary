<?php

namespace GfMapBoundary\Support;

/**
 * Geometry helpers for encoding/decoding polylines and computing viewport data.
 *
 * Why: We need deterministic transformations that match Google's algorithms so
 * client-rendered drawings can be converted into static map requests reliably.
 */
class Geometry {
	/**
	 * Decode a Google-encoded polyline into a list of [lat, lng] pairs.
	 *
	 * @param string $encoded Encoded polyline string.
	 *
	 * @return list<array{0: float, 1: float}> Ordered list of latitude/longitude pairs.
	 */
	public static function decodePolyline( string $encoded ): array {
		$len    = strlen( $encoded );
		$index  = 0;
		$lat    = 0;
		$lng    = 0;
		$points = [];
		while ( $index < $len ) {
			$shift  = 0;
			$result = 0;
			do {
				$b      = ord( $encoded[ $index ++ ] ) - 63;
				$result |= ( $b & 0x1f ) << $shift;
				$shift  += 5;
			} while ( $index < $len && $b >= 0x20 );
			$dlat = ( ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 ) );
			$lat  += $dlat;

			$shift  = 0;
			$result = 0;
			do {
				$b      = ord( $encoded[ $index ++ ] ) - 63;
				$result |= ( $b & 0x1f ) << $shift;
				$shift  += 5;
			} while ( $index < $len && $b >= 0x20 );
			$dlng = ( ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 ) );
			$lng  += $dlng;

			$points[] = [ $lat * 1e-5, $lng * 1e-5 ];
		}

		return $points;
	}

	/**
	 * Encode a list of [lat, lng] pairs into a Google polyline string.
	 *
	 * @param iterable<array{0: float|int, 1: float|int}> $points List of points.
	 *
	 * @return string Encoded polyline.
	 */
	public static function encodePolyline( array $points ): string {
		$lastLat = 0;
		$lastLng = 0;
		$result  = '';
		foreach ( $points as $p ) {
			$lat     = (int) round( (float) $p[0] * 1e5 );
			$lng     = (int) round( (float) $p[1] * 1e5 );
			$dlat    = $lat - $lastLat;
			$dlng    = $lng - $lastLng;
			$lastLat = $lat;
			$lastLng = $lng;
			$dlat    = ( $dlat < 0 ) ? ~( $dlat << 1 ) : ( $dlat << 1 );
			$dlng    = ( $dlng < 0 ) ? ~( $dlng << 1 ) : ( $dlng << 1 );
			foreach ( [ $dlat, $dlng ] as $v ) {
				while ( $v >= 0x20 ) {
					$result .= chr( ( 0x20 | ( $v & 0x1f ) ) + 63 );
					$v      >>= 5;
				}
				$result .= chr( $v + 63 );
			}
		}

		return $result;
	}

	/**
	 * Compute bounding box of points as [south, west, north, east].
	 *
	 * @param iterable<array{0: float|int, 1: float|int}> $points Points to bound.
	 *
	 * @return array{0: float, 1: float, 2: float, 3: float}
	 */
	public static function boundsFromPoints( array $points ): array {
		$minLat = 90.0;
		$maxLat = - 90.0;
		$minLng = 180.0;
		$maxLng = - 180.0;
		foreach ( $points as $p ) {
			$lat    = (float) $p[0];
			$lng    = (float) $p[1];
			$minLat = min( $minLat, $lat );
			$maxLat = max( $maxLat, $lat );
			$minLng = min( $minLng, $lng );
			$maxLng = max( $maxLng, $lng );
		}

		return [ $minLat, $minLng, $maxLat, $maxLng ];
	}

	/**
	 * Convert world pixel X to longitude at a given zoom level.
	 */
	public static function pixelXToLng( float $x, int $zoom ): float {
		$scale = 256 * pow( 2, $zoom );
		$lng   = ( $x / $scale ) * 360.0 - 180.0;

		return max( - 180.0, min( 180.0, $lng ) );
	}

	/**
	 * Convert world pixel Y to latitude at a given zoom level (inverse Mercator).
	 */
	public static function pixelYToLat( float $y, int $zoom ): float {
		$scale   = 256 * pow( 2, $zoom );
		$yy      = 0.5 - ( $y / $scale );
		$lat_rad = 2.0 * atan( exp( $yy * 2.0 * M_PI ) ) - M_PI / 2.0;
		$lat     = rad2deg( $lat_rad );

		return max( - 85.05112878, min( 85.05112878, $lat ) );
	}

	/**
	 * Choose the highest zoom where the bounds fit inside the given pixel viewport.
	 *
	 * Why: Produces a reasonable center/zoom for the static map when we can't rely
	 * on JS to supply those. Padding ensures visual breathing room around polygons.
	 *
	 * @param array{0: float, 1: float, 2: float, 3: float} $bounds   [south, west, north, east].
	 * @param int                                           $widthPx  Total image width in pixels.
	 * @param int                                           $heightPx Total image height in pixels.
	 * @param int                                           $padPx    Padding in pixels applied to both sides.
	 *
	 * @return int Zoom level (0..20).
	 */
	public static function computeZoomForBounds( array $bounds, int $widthPx, int $heightPx, int $padPx ): int {
		[ $south, $west, $north, $east ] = $bounds;
		$usableW = max( 1, $widthPx - 2 * $padPx );
		$usableH = max( 1, $heightPx - 2 * $padPx );
		$maxZoom = 20; // keep <= 20
		$best    = 0;
		for ( $z = $maxZoom; $z >= 0; $z -- ) {
			$x1    = self::lngToPixelX( $west, $z );
			$x2    = self::lngToPixelX( $east, $z );
			$y1    = self::latToPixelY( $north, $z );
			$y2    = self::latToPixelY( $south, $z );
			$spanX = abs( $x2 - $x1 );
			$spanY = abs( $y2 - $y1 );
			if ( $spanX <= $usableW && $spanY <= $usableH ) {
				$best = $z;
				break;
			}
		}

		return $best;
	}

	/**
	 * Convert longitude to world pixel X at a given zoom level (Web Mercator).
	 */
	public static function lngToPixelX( float $lng, int $zoom ): float {
		$lng   = max( - 180.0, min( 180.0, $lng ) );
		$scale = 256 * pow( 2, $zoom );

		return ( $lng + 180.0 ) / 360.0 * $scale;
	}

	/**
	 * Convert latitude to world pixel Y at a given zoom level (Web Mercator).
	 * Latitude is clamped to Mercator limits to avoid infinity.
	 */
	public static function latToPixelY( float $lat, int $zoom ): float {
		$lat   = max( - 85.05112878, min( 85.05112878, $lat ) );
		$sin   = sin( deg2rad( $lat ) );
		$scale = 256 * pow( 2, $zoom );
		$y     = 0.5 - ( log( ( 1 + $sin ) / ( 1 - $sin ) ) / ( 4 * M_PI ) );

		return $y * $scale;
	}
}
