<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Assets → Setup → General / HostBill Settings: the module settings, as tabs of the Setup section.
 * Saving goes through Perfex's settings_model->update(), so the same option
 * hooks apply (JSON multi-selects, encrypted HostBill API key, trimmed URL).
 * Permission: "AMS - Settings" (view / edit) or Perfex admin.
 *
 * Only the fields of the tab being saved are accepted, each checked against its
 * rule (internal options such as the schema version can never be posted).
 */
class Configuration extends AdminController
{
    private const TABS = [
        'general'  => ['view' => 'settings'],
        'hostbill' => ['view' => 'settings_hostbill'],
    ];

    /** Rules: bool | int:min:max | float:min:max | str:maxLen | tag:maxLen | text | enum:a,b | staff | url */
    private const FIELDS = [
        'general' => [
            'ams_asset_tag_prefix'         => 'tag:10',
            'ams_asset_tag_separator'      => 'tag:3',
            'ams_asset_tag_digits'         => 'int:1:10',
            'ams_default_category_code'    => 'tag:10',
            'ams_warranty_expiring_days'   => 'int:1:3650',
            'ams_max_upload_mb'            => 'int:1:512',
            'ams_acceptance_mode'          => 'enum:always,category,never',
            'ams_overdue_reminder_days'    => 'int:1:365',
            'ams_request_prefix'           => 'str:10',
            'ams_acceptance_terms'         => 'text',
            'ams_manager_notify_staff'     => 'staff',
            'ams_email_notifications'      => 'bool',
            'ams_block_staff_deactivation' => 'bool',
            'ams_maintenance_lead_days'    => 'int:0:365',
            'ams_mt_overdue_days'          => 'int:1:365',
            'ams_mt_ack_days'              => 'int:0:365',
            'ams_mt_responsible_required'  => 'bool',
            'ams_mt_notify_managers'       => 'bool',
            'ams_license_reminder_days'    => 'int:1:365',
            'ams_po_prefix'                => 'str:10',
            'ams_po_require_approval'      => 'bool',
            'ams_po_terms'                 => 'text',
            'ams_declining_factor'         => 'float:0.5:4',
            'ams_audit_prefix'             => 'str:10',
            'ams_label_width'              => 'int:20:150',
            'ams_label_height'             => 'int:10:150',
            'ams_label_layout'             => 'enum:single,sheet',
            'ams_label_code'               => 'enum:qr,barcode',
            'ams_label_qr_content'         => 'enum:url,tag',
            'ams_label_company_text'       => 'str:100',
            'ams_label_show_company'       => 'bool',
            'ams_label_show_name'          => 'bool',
            'ams_label_show_serial'        => 'bool',
            'ams_label_show_logo'          => 'bool',
        ],
        'hostbill' => [
            'ams_hb_enabled'            => 'bool',
            'ams_hb_url'                => 'url',
            'ams_hb_api_id'             => 'str:191',
            'ams_hb_api_key'            => 'text',
            'ams_hb_timeout'            => 'int:5:120',
            'ams_hb_verify_ssl'         => 'bool',
            'ams_hb_sync_enabled'       => 'bool',
            'ams_hb_sync_interval'      => 'int:5:1440',
            'ams_hb_product_scope'      => 'enum:tracked,all',
            'ams_hb_include_hidden'     => 'bool',
            'ams_hb_default_low_level'  => 'float:0:1000000',
            'ams_hb_allow_override'     => 'bool',
            'ams_hb_alert_low'          => 'bool',
            'ams_hb_alert_out'          => 'bool',
            'ams_hb_alert_sync_fail'    => 'bool',
            'ams_hb_alert_email'        => 'bool',
            'ams_hb_alert_staff'        => 'staff',
            'ams_hb_log_retention_days' => 'int:1:365',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        if (! ams_can_view_settings()) {
            access_denied('ams_settings');
        }
    }

    public function index($tab = 'general')
    {
        $tab = isset(self::TABS[$tab]) ? $tab : 'general';

        if ($this->input->method() === 'post') {
            if (! is_admin() && staff_cant('edit', 'ams_settings')) {
                access_denied('ams_settings');
            }

            $posted   = (array) $this->input->post('settings');
            $settings = [];
            $errors   = [];
            foreach (self::FIELDS[$tab] as $name => $rule) {
                if (! array_key_exists($name, $posted)) {
                    continue;
                }
                $value = $this->clean($posted[$name], $rule);
                if ($value === null) {
                    $errors[] = _l('ams_setting_invalid', e($name));
                    continue;
                }
                $settings[$name] = $value;
            }

            // The HostBill key only ever goes to the saved URL: a new URL needs the key again.
            if ($tab === 'hostbill' && isset($settings['ams_hb_url'])
                && rtrim($settings['ams_hb_url'], '/') !== rtrim((string) get_option('ams_hb_url'), '/')
                && get_option('ams_hb_url') !== '' && trim((string) ($settings['ams_hb_api_key'] ?? '')) === '') {
                $errors[] = _l('ams_hb_url_changed_key_required');
                unset($settings['ams_hb_url']);
            }

            if ($errors) {
                set_alert('warning', implode('<br>', $errors));
            }
            if ($settings) {
                $this->load->model('settings_model');
                if ($this->settings_model->update(['settings' => $settings]) > 0 && ! $errors) {
                    set_alert('success', _l('settings_updated'));
                }
            }
            redirect(admin_url('asset_management/configuration/index/' . $tab));
        }

        $data['title']   = _l('ams_menu_setup');
        $data['tab']     = $tab;
        $data['tabs']    = self::TABS;
        $data['canEdit'] = is_admin() || staff_can('edit', 'ams_settings');
        $this->load->view(AMS_MODULE_NAME . '/configuration', $data);
    }

    /** Normalised value for a rule, or null when it is not acceptable. */
    private function clean($value, $rule)
    {
        $parts = explode(':', $rule);

        switch ($parts[0]) {
            case 'staff': // multi-select; the option hook stores it as JSON
                return array_values(array_filter(array_map('intval', (array) $value)));
            case 'bool':
                return in_array((string) $value, ['0', '1'], true) ? (string) $value : null;
            case 'int':
                return is_numeric($value) && (int) $value == $value && (int) $value >= (int) $parts[1] && (int) $value <= (int) $parts[2] ? (string) (int) $value : null;
            case 'float':
                return is_numeric($value) && (float) $value >= (float) $parts[1] && (float) $value <= (float) $parts[2] ? (string) (float) $value : null;
            case 'enum':
                return in_array((string) $value, explode(',', $parts[1]), true) ? (string) $value : null;
            case 'str':
                $value = trim((string) $value);

                return mb_strlen($value) <= (int) $parts[1] ? $value : null;
            case 'tag': // part of asset tags, which go into QR links and barcodes
                $value = trim((string) $value);

                return preg_match('/^[A-Za-z0-9._-]{0,' . (int) $parts[1] . '}$/', $value) ? $value : null;
            case 'url':
                $value = trim((string) $value);

                return $value === '' || (mb_strlen($value) <= 255 && preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)) ? $value : null;
            default: // text
                return is_array($value) ? null : (string) $value;
        }
    }
}
