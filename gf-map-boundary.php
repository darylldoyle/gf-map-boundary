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
// Constants & Helpers
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

/**
 * Retrieve the stored Google Maps API key from the GF Add-On settings or (fallback) wp_option.
 */
function gfmb_get_api_key() {
	$api_key = '';
	if (
		class_exists( 'GFAddOn' ) &&
		class_exists( 'GFMB_AddOn' ) &&
		method_exists( 'GFMB_AddOn', 'get_instance' )
	) {
		$api_key = GFMB_AddOn::get_instance()->get_plugin_setting( 'google_api_key' );
	}
	if ( ! $api_key ) {
		$api_key = get_option( 'gfmb_google_api_key', '' );
	}
	return is_string( $api_key ) ? trim( $api_key ) : '';
}

/**
 * Does the provided form include our custom field type?
 */
function gfmb_form_has_field( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return false;
	}
	foreach ( $form['fields'] as $field ) {
		if ( isset( $field->type ) && $field->type === 'map_boundary' ) {
			return true;
		}
	}
	return false;
}

// -----------------------------------------------------------------------------
// Gravity Forms Add-On for Settings (Google Maps API Key)
// -----------------------------------------------------------------------------

/**
 * Decode a Google encoded polyline string to an array of [lat, lng] points.
 * Minimal implementation suitable for small paths; returns empty array on failure.
 */
function gfmb_decode_polyline( $encoded ) {
	$len = strlen( $encoded );
	$index = 0; $lat = 0; $lng = 0; $points = [];
	while ( $index < $len ) {
		$shift = 0; $result = 0;
		do {
			$b = ord( $encoded[$index++] ) - 63;
			$result |= ( $b & 0x1f ) << $shift;
			$shift += 5;
		} while ( $index < $len && $b >= 0x20 );
		$dlat = ( ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 ) );
		$lat += $dlat;

		$shift = 0; $result = 0;
		do {
			$b = ord( $encoded[$index++] ) - 63;
			$result |= ( $b & 0x1f ) << $shift;
			$shift += 5;
		} while ( $index < $len && $b >= 0x20 );
		$dlng = ( ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 ) );
		$lng += $dlng;

		$points[] = [ $lat * 1e-5, $lng * 1e-5 ];
	}
	return $points;
}

/**
 * Encode an array of [lat, lng] points into a Google encoded polyline string.
 */
function gfmb_encode_polyline( $points ) {
	$lastLat = 0; $lastLng = 0; $result = '';
	foreach ( $points as $p ) {
		$lat = (int) round( (float) $p[0] * 1e5 );
		$lng = (int) round( (float) $p[1] * 1e5 );
		$dlat = $lat - $lastLat;
		$dlng = $lng - $lastLng;
		$lastLat = $lat; $lastLng = $lng;
		$dlat = ($dlat < 0) ? ~( $dlat << 1 ) : ( $dlat << 1 );
		$dlng = ($dlng < 0) ? ~( $dlng << 1 ) : ( $dlng << 1 );
		foreach ( [ $dlat, $dlng ] as $v ) {
			while ( $v >= 0x20 ) {
				$result .= chr( (0x20 | ($v & 0x1f)) + 63 );
				$v >>= 5;
			}
			$result .= chr( $v + 63 );
		}
	}
	return $result;
}

/**
 * Calculate simple bounds from an array of [lat, lng] points.
 */
function gfmb_bounds_from_points( $points ) {
	$minLat =  90.0; $maxLat = -90.0; $minLng =  180.0; $maxLng = -180.0;
	foreach ( $points as $p ) {
		$lat = (float) $p[0]; $lng = (float) $p[1];
		$minLat = min( $minLat, $lat );
		$maxLat = max( $maxLat, $lat );
		$minLng = min( $minLng, $lng );
		$maxLng = max( $maxLng, $lng );
	}
	return [ $minLat, $minLng, $maxLat, $maxLng ]; // [south, west, north, east]
}

/**
 * Convert longitude to world pixel X at a given zoom (Web Mercator).
 */
function gfmb_lng_to_pixel_x( $lng, $zoom ) {
	$lng = max( -180.0, min( 180.0, (float) $lng ) );
	$scale = 256 * pow( 2, (int) $zoom );
	return ($lng + 180.0) / 360.0 * $scale;
}

/**
 * Convert latitude to world pixel Y at a given zoom (Web Mercator).
 */
function gfmb_lat_to_pixel_y( $lat, $zoom ) {
	$lat = max( -85.05112878, min( 85.05112878, (float) $lat ) );
	$sin = sin( deg2rad( $lat ) );
	$scale = 256 * pow( 2, (int) $zoom );
	$y = 0.5 - ( log( (1 + $sin) / (1 - $sin) ) / (4 * M_PI) );
	return $y * $scale;
}

/**
 * Inverse: convert world pixel X to longitude at a given zoom.
 */
function gfmb_pixel_x_to_lng( $x, $zoom ) {
	$scale = 256 * pow( 2, (int) $zoom );
	$lng = ($x / $scale) * 360.0 - 180.0;
	return max( -180.0, min( 180.0, $lng ) );
}

/**
 * Inverse: convert world pixel Y to latitude at a given zoom.
 */
function gfmb_pixel_y_to_lat( $y, $zoom ) {
	$scale = 256 * pow( 2, (int) $zoom );
	$yy = 0.5 - ($y / $scale);
	$lat_rad = 2.0 * atan( exp( $yy * 2.0 * M_PI ) ) - M_PI / 2.0;
	$lat = rad2deg( $lat_rad );
	return max( -85.05112878, min( 85.05112878, $lat ) );
}

/**
 * Compute the maximum zoom such that the bounds fit within (width - 2*pad) x (height - 2*pad) pixels.
 * Returns an integer zoom (0..21). Assumes bounds do not cross the anti-meridian.
 */
function gfmb_compute_zoom_for_bounds( $bounds, $width_px, $height_px, $pad_px ) {
	list( $south, $west, $north, $east ) = $bounds;
	$usable_w = max( 1, (int) $width_px - 2 * (int) $pad_px );
	$usable_h = max( 1, (int) $height_px - 2 * (int) $pad_px );
	$max_zoom = 20; // cap to 20 to avoid black tiles on hybrid in some areas
	$best = 0;
	for ( $z = $max_zoom; $z >= 0; $z-- ) {
		$x1 = gfmb_lng_to_pixel_x( $west, $z );
		$x2 = gfmb_lng_to_pixel_x( $east, $z );
		$y1 = gfmb_lat_to_pixel_y( $north, $z ); // note: north has smaller Y
		$y2 = gfmb_lat_to_pixel_y( $south, $z );
		$span_x = abs( $x2 - $x1 );
		$span_y = abs( $y2 - $y1 );
		if ( $span_x <= $usable_w && $span_y <= $usable_h ) { $best = $z; break; }
	}
	return $best;
}


add_action( 'gform_loaded', function () {
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}

	// Ensure the Add-On framework is available.
	if ( method_exists( 'GFForms', 'include_addon_framework' ) ) {
		GFForms::include_addon_framework();
	}

	if ( class_exists( 'GFAddOn' ) && ! class_exists( 'GFMB_AddOn' ) ) {
		class GFMB_AddOn extends GFAddOn {
			protected $_version = GFMB_VERSION;
			protected $_min_gravityforms_version = '2.5';
			protected $_slug = 'gf-map-boundary';
			protected $_path = 'gf-map-boundary/gf-map-boundary.php';
			protected $_full_path = __FILE__;
			protected $_title = 'GF Map Boundary';
			protected $_short_title = 'Map Boundary';

			private static $_instance = null;

			public static function get_instance() {
				if ( self::$_instance === null ) {
					self::$_instance = new self();
				}
				return self::$_instance;
			}

			public function plugin_settings_fields() {
				return [
					[
						'title'  => esc_html__( 'Map Provider Settings', 'gf-map-boundary' ),
						'fields' => [
							[
								'label'       => esc_html__( 'Google Maps API Key', 'gf-map-boundary' ),
								'type'        => 'text',
								'name'        => 'google_api_key',
								'class'       => 'medium',
								'required'    => true,
								'description' => esc_html__( 'Enter your Google Maps JavaScript API key. This is used to render the map and generate static images.', 'gf-map-boundary' ),
							],
						],
					],
				];
			}
		}

		GFMB_AddOn::get_instance();
	}
}, 5 );

// -----------------------------------------------------------------------------
// Custom Field Registration
// -----------------------------------------------------------------------------

add_action( 'gform_loaded', function () {
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}

	if ( ! class_exists( 'GF_Field_Map_Boundary' ) ) {
		class GF_Field_Map_Boundary extends GF_Field {
			public $type = 'map_boundary';

			public function get_form_editor_field_title() {
				return esc_html__( 'Map Drawing', 'gf-map-boundary' );
			}

			public function get_form_editor_field_settings() {
				return [ 'label_setting', 'description_setting', 'error_message_setting', 'rules_setting', 'visibility_setting' ];
			}

			public function get_form_editor_button() {
				return [
					'group' => 'advanced_fields',
					'text'  => $this->get_form_editor_field_title(),
				];
			}

			public function get_form_editor_icon() {
				return 'gform-icon--map-marker';
			}

			public function get_field_input( $form, $value = '', $entry = null ) {
				$form_id  = absint( rgar( $form, 'id' ) );
				$field_id = intval( $this->id );

				$input_id   = $form_id . '_' . $field_id;
				$input_name = 'input_' . $field_id;

				// Hidden input to carry the encoded polygon path.
				$required_attr = $this->isRequired ? " aria-required='true' data-required='true'" : " aria-required='false'";
				$hidden_input = sprintf(
					"<input type='hidden' name='%s' id='input_%s' value='%s' class='gfmb-encoded-path'%s />",
					esc_attr( $input_name ),
					esc_attr( $input_id ),
					esc_attr( $value ),
					$required_attr
				);

				// Postcode input and controls.
				$placeholder = esc_attr__( 'Enter postcode', 'gf-map-boundary' );
				$locate      = esc_html__( 'Locate', 'gf-map-boundary' );
				$undo        = esc_html__( 'Undo last point', 'gf-map-boundary' );
				$clear       = esc_html__( 'Clear', 'gf-map-boundary' );

				$api_missing = gfmb_get_api_key() ? '' : sprintf( '<div class="gfmb-warning">%s</div>', esc_html__( 'Google Maps API key is not configured. Map will not load.', 'gf-map-boundary' ) );

    // Restore map view values from POST if present (so we keep the view on validation errors)
				$center_lat = rgpost( 'input_' . $field_id . '_center_lat' );
				$center_lng = rgpost( 'input_' . $field_id . '_center_lng' );
				$zoom_level = rgpost( 'input_' . $field_id . '_zoom' );
				$center_lat = is_scalar( $center_lat ) ? (string) $center_lat : '';
				$center_lng = is_scalar( $center_lng ) ? (string) $center_lng : '';
				$zoom_level = is_scalar( $zoom_level ) ? (string) $zoom_level : '';

				$markup  = "<div class='gfmb-field' data-form-id='" . esc_attr( $form_id ) . "' data-field-id='" . esc_attr( $field_id ) . "' data-center-lat='" . esc_attr( $center_lat ) . "' data-center-lng='" . esc_attr( $center_lng ) . "' data-zoom='" . esc_attr( $zoom_level ) . "'>";
				$markup .= $api_missing;
				$markup .= "<div class='gfmb-controls'>";
				$markup .= "<input type='text' class='gfmb-postcode' placeholder='{$placeholder}' aria-label='{$placeholder}' />";
				$markup .= "<button type='button' class='button gfmb-locate'>{$locate}</button>";
				$markup .= '</div>';
				$markup .= "<div class='gfmb-drawing-controls'>";
				$markup .= "<button type='button' class='button gfmb-undo' disabled>{$undo}</button>";
				$markup .= "<button type='button' class='button gfmb-clear' disabled>{$clear}</button>";
				$markup .= '</div>';
				$markup .= "<div class='gfmb-map' style='height:420px' aria-label='" . esc_attr__( 'Map drawing area', 'gf-map-boundary' ) . "'></div>";
				$markup .= $hidden_input;
				// Hidden inputs to persist center/zoom between submissions
				$markup .= sprintf( "<input type='hidden' name='input_%s_center_lat' class='gfmb-center-lat' value='%s' />", esc_attr( $field_id ), esc_attr( $center_lat ) );
				$markup .= sprintf( "<input type='hidden' name='input_%s_center_lng' class='gfmb-center-lng' value='%s' />", esc_attr( $field_id ), esc_attr( $center_lng ) );
				$markup .= sprintf( "<input type='hidden' name='input_%s_zoom' class='gfmb-zoom' value='%s' />", esc_attr( $field_id ), esc_attr( $zoom_level ) );
				$markup .= '</div>';

				return $markup;
			}

			public function validate( $value, $form ) {
				parent::validate( $value, $form );
				// Use Gravity Forms built-in required handling for consistency with other fields.
				if ( $this->isRequired && $this->is_value_submission_empty( rgar( $form, 'id' ) ) ) {
					$this->set_required_error( $value );
				}
			}

			public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
				// Export the image URL (if generated) or empty string.
				$value = rgar( $entry, (string) $this->id );
				return is_string( $value ) ? $value : '';
			}

			public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
				$url = is_string( $value ) ? trim( $value ) : '';
				if ( $format === 'html' && $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return sprintf( '<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:1px solid #e5e5e5;" />', esc_url( $url ), esc_attr( $this->label ) );
				}
				return esc_html( $url );
			}
		}
	}

	if ( class_exists( 'GF_Fields' ) && class_exists( 'GF_Field_Map_Boundary' ) ) {
		GF_Fields::register( new GF_Field_Map_Boundary() );
	}
}, 6 );

// Add button to the form editor (Advanced Fields group).
add_filter( 'gform_add_field_buttons', function ( $field_groups ) {
	foreach ( $field_groups as &$group ) {
		if ( $group['name'] === 'advanced_fields' ) {
			$group['fields'][] = [
				'class' => 'button',
				'value' => esc_html__( 'Map Drawing', 'gf-map-boundary' ),
				'data-type' => 'map_boundary',
			];
			break;
		}
	}
	return $field_groups;
} );

// -----------------------------------------------------------------------------
// Enqueue Frontend Assets (only on forms that include the field)
// -----------------------------------------------------------------------------

add_action( 'gform_enqueue_scripts', function ( $form, $is_ajax ) {
	if ( ! gfmb_form_has_field( $form ) ) {
		return;
	}

	$api_key = gfmb_get_api_key();

	// Front-end CSS.
	wp_register_style( 'gfmb-frontend', GFMB_PLUGIN_URL . 'assets/css/gfmb.css', [], GFMB_VERSION );
	wp_enqueue_style( 'gfmb-frontend' );

	// Google Maps JS API.
	if ( $api_key ) {
		$google_url = add_query_arg(
			[
				'key'       => $api_key,
				'libraries' => 'drawing,geometry',
			],
			'https://maps.googleapis.com/maps/api/js'
		);
		wp_register_script( 'gfmb-google-maps', $google_url, [], GFMB_VERSION, true );
	}

	// Our front-end logic, depends on Google if available.
	$deps = $api_key ? [ 'gfmb-google-maps' ] : [];
	wp_register_script( 'gfmb-frontend', GFMB_PLUGIN_URL . 'assets/js/gfmb-frontend.js', $deps, GFMB_VERSION, true );
	wp_localize_script( 'gfmb-frontend', 'GFMB_Config', [
		'apiKeyPresent' => (bool) $api_key,
		'i18n'         => [
			'geocodeFailed' => esc_html__( 'Could not find that postcode. Try again.', 'gf-map-boundary' ),
			'noApiKey'      => esc_html__( 'Google Maps API key is not configured. Contact site admin.', 'gf-map-boundary' ),
		],
	] );
	wp_enqueue_script( 'gfmb-frontend' );
}, 10, 2 );

// -----------------------------------------------------------------------------
// Submission Handling: Generate and save static map image
// -----------------------------------------------------------------------------

add_filter( 'gform_entry_post_save', function ( $entry, $form ) {
	// If there are no fields, return early.
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $entry;
	}

	$api_key = gfmb_get_api_key();
	if ( ! $api_key ) {
		return $entry; // Can't generate image without API key.
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	foreach ( $form['fields'] as $field ) {
		if ( ! isset( $field->type ) || $field->type !== 'map_boundary' ) {
			continue;
		}

		$field_id    = (string) $field->id;
		$input_name  = 'input_' . $field_id;
		$encodedPath = isset( $_POST[ $input_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) ) : '';

		// Always clear the saved value to avoid persisting coordinates.
		GFAPI::update_entry_field( (int) $entry['id'], (int) $field_id, '' );
		$entry[ $field_id ] = '';

		if ( $encodedPath === '' ) {
			continue; // Nothing drawn.
		}

		// Build Static Maps URL.
			$size   = '640x640'; // Combined with scale=2 => 1280x1280
			$scale  = 2;
			// Ensure the polygon is closed server-side to guarantee all sides are rendered in the static image.
			$points = gfmb_decode_polyline( $encodedPath );
			$encoded_for_path = $encodedPath;
			if ( is_array( $points ) && count( $points ) >= 3 ) {
				$first = $points[0];
				$last  = $points[count($points)-1];
				$eps = 1e-6;
				if ( abs($first[0]-$last[0]) > $eps || abs($first[1]-$last[1]) > $eps ) {
					$points[] = $first; // append first point to close ring
				}
				$encoded_for_path = gfmb_encode_polyline( $points );
			}
  $path   = 'fillcolor:0x44FF0000|color:0xFF0000|weight:2|enc:' . $encoded_for_path;

  // Compute explicit center and zoom to guarantee ~100px padding in the final image (pixel-precise framing).
			$center = '';
			$zoom   = null;
		if ( is_array( $points ) && count( $points ) >= 2 ) {
			$bounds = gfmb_bounds_from_points( $points ); // [S, W, N, E]
			list( $south, $west, $north, $east ) = $bounds;
			$lngSpan = $east - $west;
			if ( $lngSpan > 0 && $lngSpan < 180 ) {
				// Use effective pixel size (size * scale) when computing zoom.
				list($w_str, $h_str) = explode('x', $size);
				$w = max(1, (int) $w_str) * (int) $scale;
				$h = max(1, (int) $h_str) * (int) $scale;
				$pad_px = 100; // desired pixel margin (~100px) around polygon in the saved image
				$zoom = gfmb_compute_zoom_for_bounds( $bounds, $w, $h, $pad_px );
				// Clamp zoom to <= 20 to avoid black tiles on hybrid imagery
				$zoom = min(20, max(0, (int) $zoom));
				// Compute center in Web Mercator pixel space at the chosen zoom, then convert back
				$xw = gfmb_lng_to_pixel_x( $west, $zoom );
				$xe = gfmb_lng_to_pixel_x( $east, $zoom );
				$yn = gfmb_lat_to_pixel_y( $north, $zoom );
				$ys = gfmb_lat_to_pixel_y( $south, $zoom );
				$cx = ($xw + $xe) / 2.0;
				$cy = ($yn + $ys) / 2.0;
				// inverse projection helpers
				$center_lng = gfmb_pixel_x_to_lng( $cx, $zoom );
				$center_lat = gfmb_pixel_y_to_lat( $cy, $zoom );
				$center = $center_lat . ',' . $center_lng;
			}
		}

		$query  = [
			'size'    => $size,
			'scale'   => (string) $scale,
            'maptype' => 'hybrid',
			'format'  => 'png',
			'path'    => $path,
			'key'     => $api_key,
		];
		if ( $center !== '' && $zoom !== null ) {
			$query['center'] = $center;
			$query['zoom']   = (string) $zoom;
		}
		$static_url = 'https://maps.googleapis.com/maps/api/staticmap?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );

		// Retrieve image.
		$response = wp_remote_get( $static_url, [ 'timeout' => 20 ] );
		if ( is_wp_error( $response ) ) {
			continue; // Fail silently; entry keeps value empty.
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( (int) $code !== 200 || empty( $body ) ) {
			continue;
		}

		// Save to uploads.
		$upload_dir = wp_upload_dir();
		$subdir     = trailingslashit( $upload_dir['basedir'] ) . 'gf-map-boundary';
		wp_mkdir_p( $subdir );

		$filename    = sprintf( 'gfmb-form%s-entry%s-field%s-%s.png', $form['id'], $entry['id'], $field_id, wp_generate_password( 6, false ) );
		$filepath    = trailingslashit( $subdir ) . $filename;
		$file_saved  = (bool) file_put_contents( $filepath, $body );
		if ( ! $file_saved ) {
			continue;
		}

		$fileurl = trailingslashit( $upload_dir['baseurl'] ) . 'gf-map-boundary/' . rawurlencode( $filename );

		// Store the image URL as the field value.
		GFAPI::update_entry_field( (int) $entry['id'], (int) $field_id, $fileurl );
		$entry[ $field_id ] = $fileurl;

		// Save center lat/lng and zoom as entry meta and provide a Google Maps link (interactive).
		$posted_center_lat = isset( $_POST[ 'input_' . $field_id . '_center_lat' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'input_' . $field_id . '_center_lat' ] ) ) : '';
		$posted_center_lng = isset( $_POST[ 'input_' . $field_id . '_center_lng' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'input_' . $field_id . '_center_lng' ] ) ) : '';
		$posted_zoom       = isset( $_POST[ 'input_' . $field_id . '_zoom' ] )       ? sanitize_text_field( wp_unslash( $_POST[ 'input_' . $field_id . '_zoom' ] ) )       : '';

		$save_center_lat = isset( $center_lat ) ? $center_lat : ( $posted_center_lat !== '' ? (float) $posted_center_lat : '' );
		$save_center_lng = isset( $center_lng ) ? $center_lng : ( $posted_center_lng !== '' ? (float) $posted_center_lng : '' );
		$save_zoom       = isset( $zoom ) && $zoom !== null ? (int) $zoom : ( $posted_zoom !== '' ? (int) $posted_zoom : '' );

		$entry_id = (int) $entry['id'];
		if ( $save_center_lat !== '' && $save_center_lng !== '' ) {
			gform_update_meta( $entry_id, 'gfmb_' . $field_id . '_center_lat', (string) $save_center_lat );
			gform_update_meta( $entry_id, 'gfmb_' . $field_id . '_center_lng', (string) $save_center_lng );
		}
		if ( $save_zoom !== '' ) {
			gform_update_meta( $entry_id, 'gfmb_' . $field_id . '_zoom', (string) $save_zoom );
		}
		// Build an interactive Google Maps link if we have center and zoom.
		if ( $save_center_lat !== '' && $save_center_lng !== '' ) {
			$z = $save_zoom !== '' ? (int) $save_zoom : 15;
			$gmaps_link = sprintf( 'https://www.google.com/maps/@%s,%s,%sz', rawurlencode( (string) $save_center_lat ), rawurlencode( (string) $save_center_lng ), rawurlencode( (string) $z ) );
			gform_update_meta( $entry_id, 'gfmb_' . $field_id . '_gmaps_link', $gmaps_link );
		}
	}

	return $entry;
}, 10, 2 );

// -----------------------------------------------------------------------------
// Register entry meta keys for center/zoom and Google Maps link (for display/export)
// -----------------------------------------------------------------------------

add_filter( 'gform_entry_meta', function( $entry_meta, $form_id ) {
	$form = GFAPI::get_form( $form_id );
	if ( ! $form || empty( $form['fields'] ) ) {
		return $entry_meta;
	}
	foreach ( $form['fields'] as $field ) {
		if ( ! isset( $field->type ) || $field->type !== 'map_boundary' ) {
			continue;
		}
		$field_label = is_string( $field->label ) && $field->label !== '' ? $field->label : sprintf( __( 'Field %d', 'gf-map-boundary' ), (int) $field->id );
		$fid = (string) $field->id;
		$entry_meta[ 'gfmb_' . $fid . '_center_lat' ] = [
			'label'             => sprintf( __( '%s – Center Latitude', 'gf-map-boundary' ), $field_label ),
			'is_numeric'        => true,
			'is_default_column' => false,
		];
		$entry_meta[ 'gfmb_' . $fid . '_center_lng' ] = [
			'label'             => sprintf( __( '%s – Center Longitude', 'gf-map-boundary' ), $field_label ),
			'is_numeric'        => true,
			'is_default_column' => false,
		];
		$entry_meta[ 'gfmb_' . $fid . '_zoom' ] = [
			'label'             => sprintf( __( '%s – Map Zoom', 'gf-map-boundary' ), $field_label ),
			'is_numeric'        => true,
			'is_default_column' => false,
		];
		$entry_meta[ 'gfmb_' . $fid . '_gmaps_link' ] = [
			'label'             => sprintf( __( '%s – Google Maps Link', 'gf-map-boundary' ), $field_label ),
			'is_numeric'        => false,
			'is_default_column' => false,
		];
	}
	return $entry_meta;
}, 10, 2 );

// -----------------------------------------------------------------------------
// Render image & details in emails (merge tags) and admin entry detail
// -----------------------------------------------------------------------------

add_filter( 'gform_merge_tag_filter', function ( ...$args ) {
	// Gravity Forms has had different signatures for this filter across versions.
	// Map incoming args safely to avoid fatal ArgumentCountError.
	$value      = $args[0] ?? '';
	$merge_tag  = $args[1] ?? '';
	$modifiers  = $args[2] ?? '';
	$field      = $args[3] ?? null;
	$raw_value  = $args[4] ?? null;
	$format     = $args[5] ?? 'html';
	// Newer versions may pass these additional parameters:
	$form       = $args[6] ?? null;
	$entry      = $args[7] ?? null;
	$url_encode = $args[8] ?? null;
	$esc_html   = $args[9] ?? null;
	$nl2br      = $args[10] ?? null;

 if ( $field instanceof GF_Field && $field->type === 'map_boundary' ) {
		$url = is_string( $raw_value ) ? trim( $raw_value ) : '';
		$entry_id = ( is_array( $entry ) && isset( $entry['id'] ) ) ? (int) $entry['id'] : 0;
		$center_lat = $entry_id ? gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_center_lat' ) : '';
		$center_lng = $entry_id ? gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_center_lng' ) : '';
		$zoom       = $entry_id ? gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_zoom' )       : '';
		$gmaps_link = $entry_id ? gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_gmaps_link' ) : '';

		if ( $format === 'html' && $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$img_html = sprintf( '<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:1px solid #e5e5e5;" />', esc_url( $url ), esc_attr( $field->label ) );
			$details  = '';
			if ( $center_lat !== '' && $center_lng !== '' ) {
				$lat_disp = is_numeric( $center_lat ) ? number_format( (float) $center_lat, 6, '.', '' ) : esc_html( (string) $center_lat );
				$lng_disp = is_numeric( $center_lng ) ? number_format( (float) $center_lng, 6, '.', '' ) : esc_html( (string) $center_lng );
				$zoom_disp = $zoom !== '' ? sprintf( ' (z%s)', esc_html( (string) $zoom ) ) : '';
				$details .= sprintf( '<div style="margin-top:6px;">%s %s, %s%s</div>', esc_html__( 'Center:', 'gf-map-boundary' ), esc_html( $lat_disp ), esc_html( $lng_disp ), $zoom_disp );
			}
			if ( is_string( $gmaps_link ) && $gmaps_link !== '' ) {
				$details .= sprintf( '<div style="margin-top:4px;"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>', esc_url( $gmaps_link ), esc_html__( 'Open in Google Maps', 'gf-map-boundary' ) );
			}
			return $img_html . $details;
		}

		// For non-HTML formats (e.g., plain text emails), append details inline.
		if ( $format !== 'html' ) {
			$parts = array();
			if ( $value ) { $parts[] = (string) $value; }
			$center_txt = '';
			if ( $center_lat !== '' && $center_lng !== '' ) {
				$center_txt = sprintf( 'Center: %s,%s', (string) $center_lat, (string) $center_lng );
				if ( $zoom !== '' ) { $center_txt .= sprintf( ' (z%s)', (string) $zoom ); }
				$parts[] = $center_txt;
			}
			if ( is_string( $gmaps_link ) && $gmaps_link !== '' ) {
				$parts[] = 'Google Maps: ' . $gmaps_link;
			}
			if ( ! empty( $parts ) ) {
				return implode( ' | ', $parts );
			}
		}
	}
	return $value;
}, 10, 11 );

// Enhance admin entry detail: append center lat/lng and link below the field value
add_filter( 'gform_entry_field_value', function( ...$args ) {
	$value = $args[0] ?? '';
	$field = $args[1] ?? null;
	$entry = $args[2] ?? null;
	$form  = $args[3] ?? null;

	if ( ! ( $field instanceof GF_Field ) || $field->type !== 'map_boundary' ) {
		return $value;
	}

	$entry_id = ( is_array( $entry ) && isset( $entry['id'] ) ) ? (int) $entry['id'] : 0;
	if ( ! $entry_id ) {
		return $value;
	}

	$center_lat = gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_center_lat' );
	$center_lng = gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_center_lng' );
	$zoom       = gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_zoom' );
	$gmaps_link = gform_get_meta( $entry_id, 'gfmb_' . $field->id . '_gmaps_link' );

	$details = '';
	if ( $center_lat !== '' && $center_lng !== '' ) {
		$lat_disp = is_numeric( $center_lat ) ? number_format( (float) $center_lat, 6, '.', '' ) : esc_html( (string) $center_lat );
		$lng_disp = is_numeric( $center_lng ) ? number_format( (float) $center_lng, 6, '.', '' ) : esc_html( (string) $center_lng );
		$details .= sprintf( '<div style="margin-top:6px;">%s %s, %s</div>', esc_html__( 'Center:', 'gf-map-boundary' ), esc_html( $lat_disp ), esc_html( $lng_disp ) );
	}
	if ( is_string( $gmaps_link ) && $gmaps_link !== '' ) {
		$details .= sprintf( '<div style="margin-top:4px;"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>', esc_url( $gmaps_link ), esc_html__( 'Open in Google Maps', 'gf-map-boundary' ) );
	}

	return $value . $details;
}, 10, 4 );
