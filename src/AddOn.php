<?php

namespace GfMapBoundary;

use GFAddOn;

if ( ! class_exists( 'GFAddOn' ) ) {
	// Gravity Forms not loaded yet; class will be extended only when available via hook in Plugin.
}

/**
 * Gravity Forms Add-On wrapper for plugin settings and integration points.
 *
 * Why: Centralizes plugin-level settings (API key) via the GF Add-On framework
 * to allow site owners to configure without touching code.
 */
class AddOn extends GFAddOn {
	/** @var self|null Singleton instance. */
	private static $_instance = null;
	/** @var string */
	protected $_version = GFMB_VERSION;
	/** @var string */
	protected $_min_gravityforms_version = '2.5';
	/** @var string */
	protected $_slug = 'gf-map-boundary';
	/** @var string */
	protected $_path = 'gf-map-boundary/gf-map-boundary.php';
	/** @var string */
	protected $_full_path = GFMB_PLUGIN_FILE;
	/** @var string */
	protected $_title = 'GF Map Boundary';
	/** @var string */
	protected $_short_title = 'Map Boundary';

	/**
	 * Get the singleton instance required by GFAddOn conventions.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Define plugin settings fields for the GF settings UI.
	 *
	 * @return array<int, array{title:string,fields:array<int, array<string, mixed>>}>
	 */
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
						'description' => esc_html__(
							'Enter your Google Maps JavaScript API key. This is used to render the map and generate static images.',
							'gf-map-boundary'
						),
					],
				],
			],
		];
	}

	/**
	 * Convenience wrapper to fetch a plugin setting with a default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 *
	 * @return mixed
	 */
	public function getPluginSetting( string $key, $default = '' ) {
		return $this->get_plugin_setting( $key ) ?: $default;
	}
}
