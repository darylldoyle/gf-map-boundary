<?php
namespace GfMapBoundary\Field;

use GF_Field;

class MapBoundary extends GF_Field
{
    public $type = 'map_boundary';

    public function get_form_editor_field_title()
    {
        return esc_html__('Map Drawing', 'gf-map-boundary');
    }

    public function get_form_editor_field_settings()
    {
        return ['label_setting', 'description_setting', 'error_message_setting', 'rules_setting', 'visibility_setting'];
    }

    public function get_form_editor_button()
    {
        return [
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    public function get_form_editor_icon()
    {
        return 'gform-icon--map-marker';
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        $form_id  = absint(rgar($form, 'id'));
        $field_id = intval($this->id);

        $input_id   = $form_id . '_' . $field_id;
        $input_name = 'input_' . $field_id;

        $required_attr = $this->isRequired ? " aria-required='true' data-required='true'" : " aria-required='false'";
        $hidden_input = sprintf(
            "<input type='hidden' name='%s' id='input_%s' value='%s' class='gfmb-encoded-path'%s />",
            esc_attr($input_name),
            esc_attr($input_id),
            esc_attr($value),
            $required_attr
        );

        $placeholder = esc_attr__('Enter postcode', 'gf-map-boundary');
        $locate      = esc_html__('Locate', 'gf-map-boundary');
        $undo        = esc_html__('Undo last point', 'gf-map-boundary');
        $clear       = esc_html__('Clear', 'gf-map-boundary');

        $api_missing = function_exists('gfmb_get_api_key') && gfmb_get_api_key() ? '' : sprintf('<div class="gfmb-warning">%s</div>', esc_html__('Google Maps API key is not configured. Map will not load.', 'gf-map-boundary'));

        $center_lat = rgpost('input_' . $field_id . '_center_lat');
        $center_lng = rgpost('input_' . $field_id . '_center_lng');
        $zoom_level = rgpost('input_' . $field_id . '_zoom');
        $center_lat = is_scalar($center_lat) ? (string) $center_lat : '';
        $center_lng = is_scalar($center_lng) ? (string) $center_lng : '';
        $zoom_level = is_scalar($zoom_level) ? (string) $zoom_level : '';

        $markup  = "<div class='gfmb-field' data-form-id='" . esc_attr($form_id) . "' data-field-id='" . esc_attr($field_id) . "' data-center-lat='" . esc_attr($center_lat) . "' data-center-lng='" . esc_attr($center_lng) . "' data-zoom='" . esc_attr($zoom_level) . "'>";
        $markup .= $api_missing;
        $markup .= "<div class='gfmb-controls'>";
        $markup .= "<input type='text' class='gfmb-postcode' placeholder='{$placeholder}' aria-label='{$placeholder}' />";
        $markup .= "<button type='button' class='button gfmb-locate'>{$locate}</button>";
        $markup .= '</div>';
        $markup .= "<div class='gfmb-drawing-controls'>";
        $markup .= "<button type='button' class='button gfmb-undo' disabled>{$undo}</button>";
        $markup .= "<button type='button' class='button gfmb-clear' disabled>{$clear}</button>";
        $markup .= '</div>';
        $markup .= "<div class='gfmb-map' style='height:420px' aria-label='" . esc_attr__('Map drawing area', 'gf-map-boundary') . "'></div>";
        $markup .= $hidden_input;
        $markup .= sprintf("<input type='hidden' name='input_%s_center_lat' class='gfmb-center-lat' value='%s' />", esc_attr($field_id), esc_attr($center_lat));
        $markup .= sprintf("<input type='hidden' name='input_%s_center_lng' class='gfmb-center-lng' value='%s' />", esc_attr($field_id), esc_attr($center_lng));
        $markup .= sprintf("<input type='hidden' name='input_%s_zoom' class='gfmb-zoom' value='%s' />", esc_attr($field_id), esc_attr($zoom_level));
        $markup .= '</div>';

        return $markup;
    }

    public function validate($value, $form)
    {
        parent::validate($value, $form);
        if ($this->isRequired && $this->is_value_submission_empty(rgar($form, 'id'))) {
            $this->set_required_error($value);
        }
    }

    public function get_value_export($entry, $input_id = '', $use_text = false, $is_csv = false)
    {
        $value = rgar($entry, (string) $this->id);
        return is_string($value) ? $value : '';
    }

    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
    {
        $url = is_string($value) ? trim($value) : '';
        if ($format === 'html' && $url && filter_var($url, FILTER_VALIDATE_URL)) {
            return sprintf('<img src="%s" alt="%s" style="max-width:100%%;height:auto;border:1px solid #e5e5e5;" />', esc_url($url), esc_attr($this->label));
        }
        return esc_html($url);
    }
}
