<?php
namespace GfMapBoundary;

if (!class_exists('GFAddOn')) {
    // Gravity Forms not loaded yet; class will be extended only when available via hook in Plugin.
}

class AddOn extends \GFAddOn
{
    protected $_version = GFMB_VERSION;
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'gf-map-boundary';
    protected $_path = 'gf-map-boundary/gf-map-boundary.php';
    protected $_full_path = GFMB_PLUGIN_FILE;
    protected $_title = 'GF Map Boundary';
    protected $_short_title = 'Map Boundary';

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function plugin_settings_fields()
    {
        return [
            [
                'title'  => esc_html__('Map Provider Settings', 'gf-map-boundary'),
                'fields' => [
                    [
                        'label'       => esc_html__('Google Maps API Key', 'gf-map-boundary'),
                        'type'        => 'text',
                        'name'        => 'google_api_key',
                        'class'       => 'medium',
                        'required'    => true,
                        'description' => esc_html__('Enter your Google Maps JavaScript API key. This is used to render the map and generate static images.', 'gf-map-boundary'),
                    ],
                ],
            ],
        ];
    }

    public function getPluginSetting(string $key, $default = '')
    {
        return $this->get_plugin_setting($key) ?: $default;
    }
}
