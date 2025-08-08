<?php
namespace GfMapBoundary;

use GfMapBoundary\Field\MapBoundary as MapBoundaryField;
use GfMapBoundary\Support\Geometry;

class Plugin
{
    public static function init(): void
    {
        // Load GF Add-On and Field classes when GF is loaded
        add_action('gform_loaded', [self::class, 'onGFormLoaded_AddOn'], 5);
        add_action('gform_loaded', [self::class, 'onGFormLoaded_Field'], 6);

        // Form editor button
        add_filter('gform_add_field_buttons', [self::class, 'addFieldEditorButton']);

        // Enqueue assets where needed
        add_action('gform_enqueue_scripts', [self::class, 'enqueueFrontend'], 10, 2);

        // Post-save generation of static image and meta
        add_filter('gform_entry_post_save', [self::class, 'handleEntryPostSave'], 10, 2);

        // Register entry meta keys
        add_filter('gform_entry_meta', [self::class, 'registerEntryMeta'], 10, 2);

        // Output filters for emails/admin
        add_filter('gform_merge_tag_filter', [self::class, 'mergeTagFilter'], 10, 11);
        add_filter('gform_entry_field_value', [self::class, 'adminEntryFieldValue'], 10, 4);

        // Provide BC class aliases for original class names (if needed elsewhere)
        if (!class_exists('GFMB_AddOn')) {
            class_alias(AddOn::class, 'GFMB_AddOn');
        }
        if (!class_exists('GF_Field_Map_Boundary')) {
            class_alias(MapBoundaryField::class, 'GF_Field_Map_Boundary');
        }
    }

    public static function onGFormLoaded_AddOn(): void
    {
        if (!class_exists('GFForms')) {
            return;
        }
        if (method_exists('GFForms', 'include_addon_framework')) {
            \GFForms::include_addon_framework();
        }
        if (class_exists('GFAddOn')) {
            AddOn::get_instance();
        }
    }

    public static function onGFormLoaded_Field(): void
    {
        if (!class_exists('GFForms')) {
            return;
        }
        if (class_exists('GF_Fields') && class_exists(MapBoundaryField::class)) {
            \GF_Fields::register(new MapBoundaryField());
        }
    }

    public static function addFieldEditorButton(array $groups): array
    {
        foreach ($groups as &$group) {
            if (($group['name'] ?? '') === 'advanced_fields') {
                $group['fields'][] = [
                    'class' => 'button',
                    'value' => esc_html__('Map Drawing', 'gf-map-boundary'),
                    'data-type' => 'map_boundary',
                ];
                break;
            }
        }
        return $groups;
    }

    public static function formHasField($form): bool
    {
        if (empty($form['fields']) || !is_array($form['fields'])) {
            return false;
        }
        foreach ($form['fields'] as $field) {
            if (isset($field->type) && $field->type === 'map_boundary') {
                return true;
            }
        }
        return false;
    }

    public static function getApiKey(): string
    {
        $api_key = '';
        if (class_exists('GFAddOn') && class_exists(AddOn::class) && method_exists(AddOn::class, 'get_instance')) {
            $api_key = AddOn::get_instance()->get_plugin_setting('google_api_key');
        }
        if (!$api_key) {
            $api_key = get_option('gfmb_google_api_key', '');
        }
        return is_string($api_key) ? trim($api_key) : '';
    }

    public static function enqueueFrontend($form, $is_ajax): void
    {
        if (!self::formHasField($form)) {
            return;
        }

        $api_key = self::getApiKey();

        wp_register_style('gfmb-frontend', GFMB_PLUGIN_URL . 'assets/css/gfmb.css', [], GFMB_VERSION);
        wp_enqueue_style('gfmb-frontend');

        if ($api_key) {
            $google_url = add_query_arg([
                'key'       => $api_key,
                'libraries' => 'drawing,geometry',
            ], 'https://maps.googleapis.com/maps/api/js');
            wp_register_script('gfmb-google-maps', $google_url, [], GFMB_VERSION, true);
        }

        $deps = $api_key ? ['gfmb-google-maps'] : [];
        wp_register_script('gfmb-frontend', GFMB_PLUGIN_URL . 'assets/js/gfmb-frontend.js', $deps, GFMB_VERSION, true);
        wp_localize_script('gfmb-frontend', 'GFMB_Config', [
            'apiKeyPresent' => (bool) $api_key,
            'i18n' => [
                'geocodeFailed' => esc_html__('Could not find that postcode. Try again.', 'gf-map-boundary'),
                'noApiKey'      => esc_html__('Google Maps API key is not configured. Contact site admin.', 'gf-map-boundary'),
            ],
        ]);
        wp_enqueue_script('gfmb-frontend');
    }

    public static function handleEntryPostSave(array $entry, $form): array
    {
        if (empty($form['fields']) || !is_array($form['fields'])) {
            return $entry;
        }
        $api_key = self::getApiKey();
        if (!$api_key) {
            return $entry;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';

        foreach ($form['fields'] as $field) {
            if (!isset($field->type) || $field->type !== 'map_boundary') {
                continue;
            }
            $field_id    = (string) $field->id;
            $input_name  = 'input_' . $field_id;
            $encodedPath = isset($_POST[$input_name]) ? sanitize_text_field(wp_unslash($_POST[$input_name])) : '';

            \GFAPI::update_entry_field((int) $entry['id'], (int) $field_id, '');
            $entry[$field_id] = '';

            if ($encodedPath === '') {
                continue;
            }

            $size   = '640x640';
            $scale  = 2;
            $points = Geometry::decodePolyline($encodedPath);
            $encoded_for_path = $encodedPath;
            if (is_array($points) && count($points) >= 3) {
                $first = $points[0];
                $last  = $points[count($points)-1];
                $eps = 1e-6;
                if (abs($first[0]-$last[0]) > $eps || abs($first[1]-$last[1]) > $eps) {
                    $points[] = $first;
                }
                $encoded_for_path = Geometry::encodePolyline($points);
            }
            $path   = 'fillcolor:0x44FF0000|color:0xFF0000|weight:2|enc:' . $encoded_for_path;

            $center = '';
            $zoom   = null;
            if (is_array($points) && count($points) >= 2) {
                $bounds = Geometry::boundsFromPoints($points);
                list($south, $west, $north, $east) = $bounds;
                $lngSpan = $east - $west;
                if ($lngSpan > 0 && $lngSpan < 180) {
                    list($w_str, $h_str) = explode('x', $size);
                    $w = max(1, (int) $w_str) * (int) $scale;
                    $h = max(1, (int) $h_str) * (int) $scale;
                    $pad_px = 100;
                    $zoom = Geometry::computeZoomForBounds($bounds, $w, $h, $pad_px);
                    $zoom = min(20, max(0, (int) $zoom));
                    $xw = Geometry::lngToPixelX($west, $zoom);
                    $xe = Geometry::lngToPixelX($east, $zoom);
                    $yn = Geometry::latToPixelY($north, $zoom);
                    $ys = Geometry::latToPixelY($south, $zoom);
                    $cx = ($xw + $xe) / 2.0;
                    $cy = ($yn + $ys) / 2.0;
                    $center_lng = Geometry::pixelXToLng($cx, $zoom);
                    $center_lat = Geometry::pixelYToLat($cy, $zoom);
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
            if ($center !== '' && $zoom !== null) {
                $query['center'] = $center;
                $query['zoom']   = (string) $zoom;
            }
            $static_url = 'https://maps.googleapis.com/maps/api/staticmap?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

            $response = wp_remote_get($static_url, ['timeout' => 20]);
            if (is_wp_error($response)) {
                continue;
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ((int) $code !== 200 || empty($body)) {
                continue;
            }

            $upload_dir = wp_upload_dir();
            $subdir     = trailingslashit($upload_dir['basedir']) . 'gf-map-boundary';
            wp_mkdir_p($subdir);

            $filename    = sprintf('gfmb-form%s-entry%s-field%s-%s.png', $form['id'], $entry['id'], $field_id, wp_generate_password(6, false));
            $filepath    = trailingslashit($subdir) . $filename;
            $file_saved  = (bool) file_put_contents($filepath, $body);
            if (!$file_saved) {
                continue;
            }

            $fileurl = trailingslashit($upload_dir['baseurl']) . 'gf-map-boundary/' . rawurlencode($filename);

            \GFAPI::update_entry_field((int) $entry['id'], (int) $field_id, $fileurl);
            $entry[$field_id] = $fileurl;

            $posted_center_lat = isset($_POST['input_' . $field_id . '_center_lat']) ? sanitize_text_field(wp_unslash($_POST['input_' . $field_id . '_center_lat'])) : '';
            $posted_center_lng = isset($_POST['input_' . $field_id . '_center_lng']) ? sanitize_text_field(wp_unslash($_POST['input_' . $field_id . '_center_lng'])) : '';
            $posted_zoom       = isset($_POST['input_' . $field_id . '_zoom'])       ? sanitize_text_field(wp_unslash($_POST['input_' . $field_id . '_zoom']))       : '';

            $save_center_lat = isset($center_lat) ? $center_lat : ($posted_center_lat !== '' ? (float) $posted_center_lat : '');
            $save_center_lng = isset($center_lng) ? $center_lng : ($posted_center_lng !== '' ? (float) $posted_center_lng : '');
            $save_zoom       = isset($zoom) && $zoom !== null ? (int) $zoom : ($posted_zoom !== '' ? (int) $posted_zoom : '');

            $entry_id = (int) $entry['id'];
            if ($save_center_lat !== '' && $save_center_lng !== '') {
                gform_update_meta($entry_id, 'gfmb_' . $field_id . '_center_lat', (string) $save_center_lat);
                gform_update_meta($entry_id, 'gfmb_' . $field_id . '_center_lng', (string) $save_center_lng);
            }
            if ($save_zoom !== '') {
                gform_update_meta($entry_id, 'gfmb_' . $field_id . '_zoom', (string) $save_zoom);
            }
            if ($save_center_lat !== '' && $save_center_lng !== '') {
                $z = $save_zoom !== '' ? (int) $save_zoom : 15;
                $gmaps_link = sprintf('https://www.google.com/maps/@%s,%s,%sz', rawurlencode((string) $save_center_lat), rawurlencode((string) $save_center_lng), rawurlencode((string) $z));
                gform_update_meta($entry_id, 'gfmb_' . $field_id . '_gmaps_link', $gmaps_link);
            }
        }

        return $entry;
    }

    public static function registerEntryMeta(array $entry_meta, $form_id): array
    {
        $form = \GFAPI::get_form($form_id);
        if (!$form || empty($form['fields'])) {
            return $entry_meta;
        }
        foreach ($form['fields'] as $field) {
            if (!isset($field->type) || $field->type !== 'map_boundary') {
                continue;
            }
            $field_label = is_string($field->label) && $field->label !== '' ? $field->label : sprintf( __('Field %d', 'gf-map-boundary'), (int) $field->id );
            $fid = (string) $field->id;
            $entry_meta['gfmb_' . $fid . '_center_lat'] = [
                'label'             => sprintf( __('%s – Center Latitude', 'gf-map-boundary'), $field_label ),
                'is_numeric'        => true,
                'is_default_column' => false,
            ];
            $entry_meta['gfmb_' . $fid . '_center_lng'] = [
                'label'             => sprintf( __('%s – Center Longitude', 'gf-map-boundary'), $field_label ),
                'is_numeric'        => true,
                'is_default_column' => false,
            ];
            $entry_meta['gfmb_' . $fid . '_zoom'] = [
                'label'             => sprintf( __('%s – Map Zoom', 'gf-map-boundary'), $field_label ),
                'is_numeric'        => true,
                'is_default_column' => false,
            ];
            $entry_meta['gfmb_' . $fid . '_gmaps_link'] = [
                'label'             => sprintf( __('%s – Google Maps Link', 'gf-map-boundary'), $field_label ),
                'is_numeric'        => false,
                'is_default_column' => false,
            ];
        }
        return $entry_meta;
    }

    public static function mergeTagFilter(...$args)
    {
        $value      = $args[0] ?? '';
        $merge_tag  = $args[1] ?? '';
        $modifiers  = $args[2] ?? '';
        $field      = $args[3] ?? null;
        $raw_value  = $args[4] ?? null;
        $format     = $args[5] ?? 'html';
        $form       = $args[6] ?? null;
        $entry      = $args[7] ?? null;
        $url_encode = $args[8] ?? null;
        $esc_html   = $args[9] ?? null;
        $nl2br      = $args[10] ?? null;

        if ($field instanceof \GF_Field && $field->type === 'map_boundary') {
            $url = is_string($raw_value) ? trim($raw_value) : '';
            $entry_id = (is_array($entry) && isset($entry['id'])) ? (int) $entry['id'] : 0;
            $center_lat = $entry_id ? gform_get_meta($entry_id, 'gfmb_' . $field->id . '_center_lat') : '';
            $center_lng = $entry_id ? gform_get_meta($entry_id, 'gfmb_' . $field->id . '_center_lng') : '';
            $zoom       = $entry_id ? gform_get_meta($entry_id, 'gfmb_' . $field->id . '_zoom')       : '';
            $gmaps_link = $entry_id ? gform_get_meta($entry_id, 'gfmb_' . $field->id . '_gmaps_link') : '';

            if ($format === 'html' && $url && filter_var($url, FILTER_VALIDATE_URL)) {
                $img_html = sprintf('<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:1px solid #e5e5e5;" />', esc_url($url), esc_attr($field->label));
                $details  = '';
                if ($center_lat !== '' && $center_lng !== '') {
                    $lat_disp = is_numeric($center_lat) ? number_format((float) $center_lat, 6, '.', '') : esc_html((string) $center_lat);
                    $lng_disp = is_numeric($center_lng) ? number_format((float) $center_lng, 6, '.', '') : esc_html((string) $center_lng);
                    $zoom_disp = $zoom !== '' ? sprintf(' (z%s)', esc_html((string) $zoom)) : '';
                    $details .= sprintf('<div style="margin-top:6px;">%s %s, %s%s</div>', esc_html__('Center:', 'gf-map-boundary'), esc_html($lat_disp), esc_html($lng_disp), $zoom_disp);
                }
                if (is_string($gmaps_link) && $gmaps_link !== '') {
                    $details .= sprintf('<div style="margin-top:4px;"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>', esc_url($gmaps_link), esc_html__('Open in Google Maps', 'gf-map-boundary'));
                }
                return $img_html . $details;
            }

            if ($format !== 'html') {
                $parts = [];
                if ($value) { $parts[] = (string) $value; }
                $center_txt = '';
                if ($center_lat !== '' && $center_lng !== '') {
                    $center_txt = sprintf('Center: %s,%s', (string) $center_lat, (string) $center_lng);
                    if ($zoom !== '') { $center_txt .= sprintf(' (z%s)', (string) $zoom); }
                    $parts[] = $center_txt;
                }
                if (is_string($gmaps_link) && $gmaps_link !== '') {
                    $parts[] = 'Google Maps: ' . $gmaps_link;
                }
                if (!empty($parts)) {
                    return implode(' | ', $parts);
                }
            }
        }
        return $value;
    }

    public static function adminEntryFieldValue(...$args)
    {
        $value = $args[0] ?? '';
        $field = $args[1] ?? null;
        $entry = $args[2] ?? null;
        $form  = $args[3] ?? null;

        if (!($field instanceof \GF_Field) || $field->type !== 'map_boundary') {
            return $value;
        }

        $entry_id = (is_array($entry) && isset($entry['id'])) ? (int) $entry['id'] : 0;
        if (!$entry_id) {
            return $value;
        }

        $center_lat = gform_get_meta($entry_id, 'gfmb_' . $field->id . '_center_lat');
        $center_lng = gform_get_meta($entry_id, 'gfmb_' . $field->id . '_center_lng');
        $zoom       = gform_get_meta($entry_id, 'gfmb_' . $field->id . '_zoom');
        $gmaps_link = gform_get_meta($entry_id, 'gfmb_' . $field->id . '_gmaps_link');

        $details = '';
        if ($center_lat !== '' && $center_lng !== '') {
            $lat_disp = is_numeric($center_lat) ? number_format((float) $center_lat, 6, '.', '') : esc_html((string) $center_lat);
            $lng_disp = is_numeric($center_lng) ? number_format((float) $center_lng, 6, '.', '') : esc_html((string) $center_lng);
            $details .= sprintf('<div style="margin-top:6px;">%s %s, %s</div>', esc_html__('Center:', 'gf-map-boundary'), esc_html($lat_disp), esc_html($lng_disp));
        }
        if (is_string($gmaps_link) && $gmaps_link !== '') {
            $details .= sprintf('<div style="margin-top:4px;"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>', esc_url($gmaps_link), esc_html__('Open in Google Maps', 'gf-map-boundary'));
        }

        return $value . $details;
    }
}
