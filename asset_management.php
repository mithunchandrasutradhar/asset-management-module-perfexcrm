<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Asset Management
Description: IT asset & inventory management - asset register, check-out/check-in, lifecycle, history, purchases (HostBill stock sync in a later phase)
Version: 1.0.0
Requires at least: 3.3.*
Author: Alpha Net BD
*/

define('AMS_MODULE_NAME', 'asset_management');

// Bump whenever install.php gains a table/column/option. ams_ensure_schema()
// re-runs the (idempotent) installer on the next admin page load, so updated
// module files never run against a stale schema and no manual
// deactivate/reactivate is needed.
define('AMS_SCHEMA_VERSION', 6);

define('AMS_UPLOAD_PATH', FCPATH . 'uploads/asset_management/');

// ─── Hooks ────────────────────────────────────────────────────────────────

hooks()->add_action('admin_init', 'ams_ensure_schema');
hooks()->add_action('admin_init', 'ams_register_permissions');
hooks()->add_action('admin_init', 'ams_init_menu_items');
hooks()->add_action('admin_init', 'ams_register_settings_section');
hooks()->add_action('admin_init', 'ams_register_tables');
hooks()->add_action('after_custom_fields_select_options', 'ams_custom_fields_select_option');
hooks()->add_filter('before_single_setting_updated_in_loop', 'ams_encode_array_settings');
hooks()->add_action('after_cron_run', 'ams_cron_verify_stock_levels');
hooks()->add_action('after_cron_run', 'ams_cron_hostbill_sync');
hooks()->add_action('ams_stock_committed', 'ams_hostbill_after_stock_change');
hooks()->add_action('admin_init', 'ams_register_customer_tab');

// People workflows (acceptances, requests, overdue, staff lifecycle, email)
hooks()->add_action('ams_after_asset_checkout', 'ams_people_on_asset_checkout');
hooks()->add_action('ams_after_asset_checkin', 'ams_people_on_asset_checkin');
hooks()->add_action('ams_after_asset_status_changed', 'ams_people_on_asset_status_changed');
hooks()->add_action('ams_after_item_checkout', 'ams_people_on_item_checkout');
hooks()->add_action('ams_after_item_checkout_closed', 'ams_people_on_item_checkout_closed');
hooks()->add_filter('before_staff_status_change', 'ams_people_on_staff_status_change', 10, 2);
hooks()->add_action('before_delete_staff_member', 'ams_people_on_staff_delete');
hooks()->add_action('after_cron_run', 'ams_cron_overdue_reminders');
hooks()->add_action('after_cron_run', 'ams_cron_maintenance_and_licenses');
hooks()->add_action('after_email_templates', 'ams_email_templates_section');

// Finance: keep stored book values current
hooks()->add_action('ams_after_asset_created', 'ams_finance_recalculate_asset');
hooks()->add_action('ams_after_asset_updated', 'ams_finance_recalculate_asset');
hooks()->add_action('ams_setup_saved', 'ams_finance_on_setup_saved');
hooks()->add_action('after_cron_run', 'ams_cron_depreciation');
hooks()->add_action('app_admin_footer', 'ams_staff_profile_shortcut');
hooks()->add_action('app_admin_footer', 'ams_post_links_script');

register_merge_fields(AMS_MODULE_NAME . '/merge_fields/ams_merge_fields');
hooks()->add_filter('get_dashboard_widgets', 'ams_register_dashboard_widgets');
hooks()->add_filter('module_' . AMS_MODULE_NAME . '_action_links', 'ams_module_action_links');

register_activation_hook(AMS_MODULE_NAME, 'ams_activation_hook');
register_uninstall_hook(AMS_MODULE_NAME, 'ams_uninstall_hook');

register_language_files(AMS_MODULE_NAME, [AMS_MODULE_NAME]);

$CI = &get_instance();
$CI->load->helper(AMS_MODULE_NAME . '/ams');

// ─── Install / schema ─────────────────────────────────────────────────────

function ams_activation_hook()
{
    require_once __DIR__ . '/install.php';
    update_option('ams_schema_version', AMS_SCHEMA_VERSION);
}

function ams_ensure_schema()
{
    if ((int) get_option('ams_schema_version') >= AMS_SCHEMA_VERSION) {
        return;
    }

    require_once __DIR__ . '/install.php';
    update_option('ams_schema_version', AMS_SCHEMA_VERSION);
}

function ams_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function ams_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings?group=ams') . '">' . _l('settings') . '</a>';

    return $actions;
}

// ─── Permissions (Setup → Staff → Roles) ──────────────────────────────────
// Every key starts with "ams_" and every label with "AMS - " (see plan §5).
// Only the capabilities used by the phases built so far are registered, so the
// Roles screen never shows a permission that does nothing yet.

function ams_register_permissions()
{
    $view_own = _l('permission_view_own');
    $view     = _l('permission_view') . ' (' . _l('permission_global') . ')';
    $create   = _l('permission_create');
    $edit     = _l('permission_edit');
    $delete   = _l('permission_delete');

    register_staff_capabilities('ams_assets', [
        'capabilities' => [
            'view_own' => $view_own,
            'view'     => $view,
            'create'   => $create,
            'edit'     => $edit,
            'delete'   => $delete,
            'checkout' => _l('ams_perm_checkout'),
            'checkin'  => _l('ams_perm_checkin'),
            'dispose'  => _l('ams_perm_dispose'),
        ],
    ], _l('ams_perm_assets'));

    $adjust = _l('ams_perm_adjust');

    register_staff_capabilities('ams_accessories', [
        'capabilities' => [
            'view_own' => $view_own,
            'view'     => $view,
            'create'   => $create,
            'edit'     => $edit,
            'delete'   => $delete,
            'checkout' => _l('ams_perm_checkout_checkin'),
            'adjust'   => $adjust,
        ],
    ], _l('ams_perm_accessories'));

    register_staff_capabilities('ams_consumables', [
        'capabilities' => [
            'view'   => $view,
            'create' => $create,
            'edit'   => $edit,
            'delete' => $delete,
            'issue'  => _l('ams_perm_issue'),
            'adjust' => $adjust,
        ],
    ], _l('ams_perm_consumables'));

    register_staff_capabilities('ams_stock', [
        'capabilities' => [
            'view'   => $view,
            'create' => $create,
            'edit'   => $edit,
            'delete' => $delete,
            'issue'  => _l('ams_perm_issue'),
            'adjust' => $adjust,
        ],
    ], _l('ams_perm_stock'));

    register_staff_capabilities('ams_maintenance', [
        'capabilities' => ['view' => $view, 'create' => $create, 'edit' => $edit, 'delete' => $delete],
    ], _l('ams_perm_maintenance'));

    register_staff_capabilities('ams_licenses', [
        'capabilities' => [
            'view_own'  => $view_own,
            'view'      => $view,
            'create'    => $create,
            'edit'      => $edit,
            'delete'    => $delete,
            'view_keys' => _l('ams_perm_view_keys'),
        ],
    ], _l('ams_perm_licenses'));

    register_staff_capabilities('ams_procurement', [
        'capabilities' => [
            'view'       => $view,
            'create'     => $create,
            'edit'       => $edit,
            'delete'     => $delete,
            'approve_po' => _l('ams_perm_approve_po'),
        ],
    ], _l('ams_perm_procurement'));

    register_staff_capabilities('ams_audits', [
        'capabilities' => ['view' => $view, 'create' => $create, 'edit' => _l('ams_perm_audit_edit'), 'delete' => $delete],
    ], _l('ams_perm_audits'));

    register_staff_capabilities('ams_reports', [
        'capabilities' => ['view' => $view],
    ], _l('ams_perm_reports'));

    register_staff_capabilities('ams_requests', [
        'capabilities' => [
            'view_own' => $view_own,
            'view'     => $view,
            'create'   => $create,
            'delete'   => $delete,
            'approve'  => _l('ams_perm_req_approve'),
        ],
    ], _l('ams_perm_requests'));

    register_staff_capabilities('ams_hostbill', [
        'capabilities' => [
            'view' => $view,
            'edit' => _l('ams_perm_hb_edit'),
            'sync' => _l('ams_perm_hb_sync'),
        ],
    ], _l('ams_perm_hostbill'));

    register_staff_capabilities('ams_setup', [
        'capabilities' => [
            'view'   => $view,
            'create' => $create,
            'edit'   => $edit,
            'delete' => $delete,
        ],
    ], _l('ams_perm_setup'));

    register_staff_capabilities('ams_settings', [
        'capabilities' => [
            'view' => $view,
            'edit' => $edit,
        ],
    ], _l('ams_perm_settings'));
}

// ─── Menu ─────────────────────────────────────────────────────────────────

function ams_init_menu_items()
{
    $CI = &get_instance();

    $canAssets    = staff_can('view', 'ams_assets') || staff_can('view_own', 'ams_assets');
    $itemKinds    = array_filter(array_keys(ams_item_kinds()), 'ams_item_can_view_kind_page');
    $canInventory = (bool) $itemKinds;
    $canHostbill  = staff_can('view', 'ams_hostbill');
    $canMine      = ams_can_use_my_assets();
    $canRequests  = ams_can_see_requests_page();
    $canMt        = staff_can('view', 'ams_maintenance');
    $canLic       = staff_can('view', 'ams_licenses');
    $canPo        = staff_can('view', 'ams_procurement');
    $canAudits    = staff_can('view', 'ams_audits');
    $canReports   = staff_can('view', 'ams_reports');

    if ($canAssets || $canInventory || $canHostbill || $canMine || $canRequests || $canMt || $canLic || $canPo || $canAudits || $canReports) {
        $home = $canAssets ? 'asset_management' : ($canInventory ? 'asset_management/inventory/index/' . reset($itemKinds) : ($canHostbill ? 'asset_management/hostbill/orders' : ($canMine ? 'asset_management/my_assets' : ($canRequests ? 'asset_management/requests' : ($canMt ? 'asset_management/maintenance' : ($canLic ? 'asset_management/licenses' : ($canPo ? 'asset_management/procurement' : ($canAudits ? 'asset_management/audits' : 'asset_management/reports'))))))));
        $CI->app_menu->add_sidebar_menu_item('ams', [
            'name'     => _l('ams_menu_assets'),
            'icon'     => 'fa-solid fa-laptop',
            'href'     => admin_url($home),
            'position' => 16,
        ]);
    }

    if ($canMine) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-my-assets',
            'name'     => _l('ams_my_assets'),
            'href'     => admin_url('asset_management/my_assets'),
            'position' => 0,
        ]);
    }
    if ($canRequests) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-requests',
            'name'     => _l('ams_requests'),
            'href'     => admin_url('asset_management/requests'),
            'position' => 4,
        ]);
    }
    if (staff_can('view', 'ams_assets')) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-people',
            'name'     => _l('ams_assets_by_staff'),
            'href'     => admin_url('asset_management/people'),
            'position' => 5,
        ]);
    }

    foreach ([
        ['ok' => $canMt, 'slug' => 'ams-maintenance', 'name' => 'ams_maintenance', 'href' => 'asset_management/maintenance', 'pos' => 6],
        ['ok' => $canLic, 'slug' => 'ams-licenses', 'name' => 'ams_licenses', 'href' => 'asset_management/licenses', 'pos' => 7],
        ['ok' => $canPo, 'slug' => 'ams-procurement', 'name' => 'ams_purchase_orders', 'href' => 'asset_management/procurement', 'pos' => 8],
        ['ok' => $canAudits, 'slug' => 'ams-audits', 'name' => 'ams_audits', 'href' => 'asset_management/audits', 'pos' => 9],
        ['ok' => $canReports, 'slug' => 'ams-reports', 'name' => 'ams_reports', 'href' => 'asset_management/reports', 'pos' => 24],
        ['ok' => ams_can_import(), 'slug' => 'ams-import', 'name' => 'ams_import', 'href' => 'asset_management/import', 'pos' => 26],
    ] as $m) {
        if ($m['ok']) {
            $CI->app_menu->add_sidebar_children_item('ams', [
                'slug'     => $m['slug'],
                'name'     => _l($m['name']),
                'href'     => admin_url($m['href']),
                'position' => $m['pos'],
            ]);
        }
    }

    if ($canHostbill) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-hb-orders',
            'name'     => _l('ams_hb_menu_orders'),
            'href'     => admin_url('asset_management/hostbill/orders'),
            'position' => 30,
        ]);
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-hb-products',
            'name'     => _l('ams_hb_menu_products'),
            'href'     => admin_url('asset_management/hostbill/products'),
            'position' => 31,
        ]);
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-hb-log',
            'name'     => _l('ams_hb_menu_log'),
            'href'     => admin_url('asset_management/hostbill/log'),
            'position' => 32,
        ]);
    }

    $position = 10;
    foreach ($itemKinds as $kind) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-inventory-' . $kind,
            'name'     => _l(ams_item_kinds()[$kind]['plural']),
            'href'     => admin_url('asset_management/inventory/index/' . $kind),
            'position' => $position++,
        ]);
    }
    if (ams_item_viewable_kinds()) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-stock-levels',
            'name'     => _l('ams_stock_levels'),
            'href'     => admin_url('asset_management/inventory/levels'),
            'position' => 20,
        ]);
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-stock-movements',
            'name'     => _l('ams_stock_movements'),
            'href'     => admin_url('asset_management/inventory/movements'),
            'position' => 21,
        ]);
    }

    if ($canAssets) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-dashboard',
            'name'     => _l('ams_menu_dashboard'),
            'href'     => admin_url('asset_management'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => 'ams-assets',
            'name'     => _l('ams_menu_asset_list'),
            'href'     => admin_url('asset_management/assets'),
            'position' => 2,
        ]);

        if (staff_can('view', 'ams_assets')) {
            $CI->app_menu->add_sidebar_children_item('ams', [
                'slug'     => 'ams-purchases',
                'name'     => _l('ams_menu_purchases'),
                'href'     => admin_url('asset_management/purchases'),
                'position' => 3,
            ]);
        }
    }

    // Master data lives in the standard Perfex "Setup" menu, like core masters.
    if (staff_can('view', 'ams_setup')) {
        $CI->app_menu->add_setup_menu_item('ams-setup', [
            'name'     => _l('ams_setup_menu'),
            'collapse' => true,
            'position' => 60,
        ]);

        $position = 1;
        foreach (ams_setup_entities() as $entity => $cfg) {
            $CI->app_menu->add_setup_children_item('ams-setup', [
                'slug'     => 'ams-setup-' . $entity,
                'name'     => _l($cfg['plural']),
                'href'     => admin_url('asset_management/setup/index/' . $entity),
                'position' => $position++,
            ]);
        }
        $CI->app_menu->add_setup_children_item('ams-setup', [
            'slug'     => 'ams-setup-approvers',
            'name'     => _l('ams_department_approvers'),
            'href'     => admin_url('asset_management/approvers'),
            'position' => $position,
        ]);
    }
}

// ─── Settings (Setup → Settings → Asset Management) ───────────────────────

function ams_register_settings_section()
{
    if (! is_admin() && staff_cant('edit', 'ams_settings')) {
        return;
    }

    $CI = &get_instance();
    $CI->app->add_settings_section('ams', [
        'title'    => _l('ams_settings_section'),
        'position' => 45,
        'children' => [
            [
                'id'       => 'ams',
                'name'     => _l('ams_settings_general'),
                'view'     => AMS_MODULE_NAME . '/settings',
                'position' => 1,
                'icon'     => 'fa-solid fa-laptop',
            ],
            [
                'id'       => 'ams_hostbill',
                'name'     => _l('ams_hb_settings_tab'),
                'view'     => AMS_MODULE_NAME . '/settings_hostbill',
                'position' => 2,
                'icon'     => 'fa-solid fa-cart-shopping',
            ],
        ],
    ]);
}

// ─── Custom fields (Setup → Custom Fields → "Belongs to") ─────────────────

function ams_custom_fields_select_option($custom_field)
{
    foreach (['ams_assets' => 'ams_custom_field_assets', 'ams_items' => 'ams_custom_field_items'] as $value => $label) {
        $selected = isset($custom_field) && $custom_field->fieldto == $value ? ' selected' : '';
        echo '<option value="' . $value . '"' . $selected . '>' . _l($label) . '</option>';
    }
}

/**
 * Settings that need special storage:
 * - multi-selects are stored as JSON (update_option() only takes scalars);
 * - the HostBill API key is stored encrypted; an empty field keeps the saved key.
 */
function ams_encode_array_settings($hookData)
{
    if (in_array($hookData['name'], ['ams_low_stock_notify_staff', 'ams_hb_alert_staff', 'ams_manager_notify_staff'])) {
        $values             = array_values(array_filter(array_map('intval', (array) $hookData['value'])));
        $hookData['value'] = json_encode($values);
    }

    if ($hookData['name'] === 'ams_hb_api_key') {
        $plain = trim((string) $hookData['value']);
        if ($plain === '') {
            $hookData['value'] = get_option('ams_hb_api_key');
        } else {
            $CI = &get_instance();
            $CI->load->library('encryption');
            $hookData['value'] = $CI->encryption->encrypt($plain);
        }
    }

    if ($hookData['name'] === 'ams_hb_url') {
        $hookData['value'] = rtrim(trim((string) $hookData['value']), '/');
    }

    return $hookData;
}

/** Scheduled HostBill order sync (runs on Perfex cron, every "sync interval" minutes). */
function ams_cron_hostbill_sync()
{
    if (get_option('ams_hb_enabled') != '1' || get_option('ams_hb_sync_enabled') != '1') {
        return;
    }

    $interval = max(1, (int) get_option('ams_hb_sync_interval')) * 60;
    $last     = strtotime((string) get_option('ams_hb_last_sync')) ?: 0;
    if (time() - $last < $interval - 30) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');
    $CI->ams_hostbill_model->sync();
}

/** After any committed stock change: flag mapped HostBill products and optionally push now. */
function ams_hostbill_after_stock_change($itemIds)
{
    if (! $itemIds || get_option('ams_hb_enabled') != '1') {
        return;
    }

    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');
    $CI->ams_hostbill_model->mark_push_pending($itemIds);

    if (get_option('ams_hb_push_immediately') == '1') {
        $CI->ams_hostbill_model->push_pending($itemIds);
    }
}

/** Customer profile → "Hardware Orders" (HostBill orders matched by contact email). */
function ams_register_customer_tab()
{
    if (! staff_can('view', 'ams_hostbill')) {
        return;
    }

    $CI = &get_instance();
    $CI->app_tabs->add_customer_profile_tab('ams_hostbill_orders', [
        'name'     => _l('ams_hb_customer_tab'),
        'icon'     => 'fa-solid fa-box',
        'view'     => AMS_MODULE_NAME . '/hostbill/customer_tab',
        'position' => 95,
        'badge'    => [],
    ]);
}

/**
 * Daily-ish integrity check: the cached stock levels must equal the ledger sum.
 * Mismatches are logged (not silently repaired) so a bug never hides itself.
 */
function ams_cron_verify_stock_levels()
{
    $last = (int) get_option('ams_last_stock_verify');
    if (time() - $last < 86400) {
        return;
    }
    update_option('ams_last_stock_verify', time());

    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_inventory_model');
    $mismatches = $CI->ams_inventory_model->verify_levels(false);

    if ($mismatches) {
        log_activity('AMS stock level check: ' . count($mismatches) . ' item/location level(s) differ from the ledger, e.g. item #'
            . $mismatches[0]['item_id'] . ' at location #' . $mismatches[0]['location_id']
            . ' (ledger ' . $mismatches[0]['total'] . ', cached ' . $mismatches[0]['cached'] . ')');
    }
}

// ─── Dashboard widgets ────────────────────────────────────────────────────

function ams_register_dashboard_widgets($widgets)
{
    if (staff_can('view', 'ams_assets') || ams_item_viewable_kinds()) {
        $widgets[] = [
            'path'      => AMS_MODULE_NAME . '/widgets/ams_overview',
            'container' => 'right-4',
        ];
    }

    return $widgets;
}

// ─── Data tables (Perfex App_table + App_table_filter) ────────────────────

function ams_register_tables()
{
    $tables = [
        'ams_assets'      => ['db' => 'ams_assets', 'view' => 'tables/assets', 'cf' => 'ams_assets'],
        'ams_purchases'   => ['db' => 'ams_assets', 'view' => 'tables/purchases', 'cf' => null],
        'ams_history'     => ['db' => 'ams_asset_history', 'view' => 'tables/history', 'cf' => null],
        'ams_audit_log'   => ['db' => 'ams_audit_log', 'view' => 'tables/audit_log', 'cf' => null],
        'ams_items'       => ['db' => 'ams_items', 'view' => 'tables/items', 'cf' => 'ams_items'],
        'ams_movements'   => ['db' => 'ams_stock_movements', 'view' => 'tables/movements', 'cf' => null],
        'ams_levels'      => ['db' => 'ams_stock_levels', 'view' => 'tables/levels', 'cf' => null],
        'ams_checkouts'   => ['db' => 'ams_item_checkouts', 'view' => 'tables/checkouts', 'cf' => null],
        'ams_hb_orders'   => ['db' => 'ams_hb_orders', 'view' => 'tables/hb_orders', 'cf' => null],
        'ams_hb_mappings' => ['db' => 'ams_hb_product_map', 'view' => 'tables/hb_mappings', 'cf' => null],
        'ams_hb_log'      => ['db' => 'ams_hb_sync_log', 'view' => 'tables/hb_log', 'cf' => null],
        'ams_acceptances' => ['db' => 'ams_acceptances', 'view' => 'tables/acceptances', 'cf' => null],
        'ams_requests'    => ['db' => 'ams_requests', 'view' => 'tables/requests', 'cf' => null],
        'ams_people'      => ['db' => 'staff', 'view' => 'tables/people', 'cf' => null, 'pk' => 'staffid'],
        'ams_approvers'   => ['db' => 'departments', 'view' => 'tables/approvers', 'cf' => null, 'pk' => 'departmentid'],
        'ams_maintenance' => ['db' => 'ams_maintenance', 'view' => 'tables/maintenance', 'cf' => null],
        'ams_schedules'   => ['db' => 'ams_maintenance_schedules', 'view' => 'tables/schedules', 'cf' => null],
        'ams_licenses'    => ['db' => 'ams_licenses', 'view' => 'tables/licenses', 'cf' => null],
        'ams_license_seats' => ['db' => 'ams_license_seats', 'view' => 'tables/license_seats', 'cf' => null],
        'ams_pos'         => ['db' => 'ams_purchase_orders', 'view' => 'tables/pos', 'cf' => null],
        'ams_valuation'   => ['db' => 'ams_assets', 'view' => 'tables/valuation', 'cf' => null],
        'ams_disposals'   => ['db' => 'ams_disposals', 'view' => 'tables/disposals', 'cf' => null],
        'ams_audits'      => ['db' => 'ams_audits', 'view' => 'tables/audits', 'cf' => null],
        'ams_audit_lines' => ['db' => 'ams_audit_lines', 'view' => 'tables/audit_lines', 'cf' => null],
        'ams_history_all' => ['db' => 'ams_asset_history', 'view' => 'tables/history_all', 'cf' => null],
        'ams_warranty'    => ['db' => 'ams_assets', 'view' => 'tables/warranty', 'cf' => null],
    ];

    foreach (ams_setup_entities() as $entity => $cfg) {
        $tables['ams_' . $entity] = ['db' => $cfg['table'], 'view' => 'tables/setup_' . $entity, 'cf' => null];
    }

    foreach ($tables as $id => $t) {
        $table = App_table::new($id, module_views_path(AMS_MODULE_NAME, $t['view']))
            ->setDbTableName($t['db']);

        if ($t['cf']) {
            $table->customfieldable($t['cf']);
        }
        if (! empty($t['pk'])) {
            $table->setPrimaryKeyName($t['pk']);
        }

        App_table::register($table);
        hooks()->add_filter('table_' . $id . '_output_params', 'ams_sanitize_table_filters');
    }
}

// ─── People workflows ─────────────────────────────────────────────────────

function ams_people_model()
{
    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_people_model');

    return $CI->ams_people_model;
}

function ams_people_on_asset_checkout($data)
{
    // Bulk import without "notify staff": no acceptance request, notification or email.
    if (! empty($GLOBALS['ams_import_silent'])) {
        return;
    }
    ams_people_model()->on_asset_checkout($data);
}

function ams_people_on_asset_checkin($assetId)
{
    ams_people_model()->cancel_pending('asset', $assetId);
}

/** A status that ends the assignment (e.g. Sold, In Store) also ends a pending acceptance. */
function ams_people_on_asset_status_changed($data)
{
    $CI    = &get_instance();
    $asset = $CI->db->select('assigned_type')->where('id', (int) $data['asset_id'])->get(db_prefix() . 'ams_assets')->row();
    if ($asset && ! $asset->assigned_type) {
        ams_people_model()->cancel_pending('asset', (int) $data['asset_id']);
    }
}

function ams_people_on_item_checkout($data)
{
    ams_people_model()->on_item_checkout($data);
}

function ams_people_on_item_checkout_closed($checkoutId)
{
    ams_people_model()->cancel_pending('accessory', $checkoutId);
}

function ams_people_on_staff_status_change($status, $staffId)
{
    return ams_people_model()->on_staff_status_change($status, $staffId);
}

function ams_people_on_staff_delete($data)
{
    ams_people_model()->on_staff_delete($data);
}

/** Overdue return reminders, at most every 6 hours (each item is re-reminded every N days). */
function ams_cron_overdue_reminders()
{
    $last = (int) get_option('ams_last_overdue_run');
    if (time() - $last < 6 * 3600) {
        return;
    }
    update_option('ams_last_overdue_run', time());
    ams_people_model()->send_overdue_reminders();
}

/** Due maintenance schedules → scheduled jobs; licence expiry reminders (at most every 6 hours). */
function ams_cron_maintenance_and_licenses()
{
    $last = (int) get_option('ams_last_mt_lic_run');
    if (time() - $last < 6 * 3600) {
        return;
    }
    update_option('ams_last_mt_lic_run', time());

    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
    $CI->load->model(AMS_MODULE_NAME . '/ams_license_model');
    $CI->ams_maintenance_model->process_due_schedules();
    $CI->ams_license_model->send_expiry_reminders();
}

// ─── Finance (depreciation) ───────────────────────────────────────────────

function ams_finance_model()
{
    $CI = &get_instance();
    $CI->load->model(AMS_MODULE_NAME . '/ams_finance_model');

    return $CI->ams_finance_model;
}

function ams_finance_recalculate_asset($assetId)
{
    ams_finance_model()->recalculate([(int) $assetId]);
}

function ams_finance_on_setup_saved($data)
{
    if (($data['entity'] ?? '') === 'categories') {
        ams_finance_model()->recalculate_category((int) $data['id']);
    }
}

/** Book values move every month: refresh them once a day. */
function ams_cron_depreciation()
{
    $last = (int) get_option('ams_last_dep_run');
    if (time() - $last < 86400) {
        return;
    }
    update_option('ams_last_dep_run', time());
    ams_finance_model()->recalculate();
}

/** Setup → Email Templates: an "Asset Management" group listing the module's templates. */
function ams_email_templates_section()
{
    $CI        = &get_instance();
    $templates = $CI->db->where('type', 'ams')->where('language', 'english')->order_by('name')->get(db_prefix() . 'emailtemplates')->result_array();
    if (! $templates) {
        return;
    }
    $canEdit = staff_can('edit', 'email_templates'); ?>
<div class="col-md-12">
    <h4 class="bold email-template-heading">
        <?= _l('ams_email_templates_group'); ?>
        <?php if ($canEdit) { ?>
        <a href="<?= admin_url('emails/disable_by_type/ams'); ?>" class="pull-right mleft5 mright25"><small><?= _l('disable_all'); ?></small></a>
        <a href="<?= admin_url('emails/enable_by_type/ams'); ?>" class="pull-right"><small><?= _l('enable_all'); ?></small></a>
        <?php } ?>
    </h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th><span class="tw-font-semibold"><?= _l('email_templates_table_heading_name'); ?></span></th></tr></thead>
            <tbody>
                <?php foreach ($templates as $tpl) { ?>
                <tr>
                    <td class="<?= $tpl['active'] == 0 ? 'tw-line-through' : ''; ?>">
                        <a href="<?= admin_url('emails/email_template/' . $tpl['emailtemplateid']); ?>"><?= e($tpl['name']); ?></a>
                        <?php if ($canEdit) { ?>
                        <a href="<?= admin_url('emails/' . ($tpl['active'] == '1' ? 'disable/' : 'enable/') . $tpl['emailtemplateid']); ?>" class="pull-right"><small><?= _l($tpl['active'] == 1 ? 'disable' : 'enable'); ?></small></a>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php
}

/**
 * Module pages: delete links (Perfex "_delete" class, after its confirmation)
 * and links marked "ams-post" are sent as POST with the CSRF token, because
 * the controllers only accept POST for state changes (see ams_post_only()).
 */
function ams_post_links_script()
{
    $CI = &get_instance();
    $settings = $CI->uri->segment(2) === 'settings' && strpos((string) $CI->input->get('group'), 'ams') === 0;
    if ($CI->uri->segment(2) !== AMS_MODULE_NAME && ! $settings) {
        return;
    } ?>
<script>
    $(document).on('click', 'a._delete[href*="/asset_management/"], a.ams-post', function(e) {
        e.preventDefault();
        var form = $('<form method="post" class="hide"></form>').attr('action', $(this).attr('href'));
        if (typeof csrfData !== 'undefined') {
            form.append($('<input type="hidden">').attr('name', csrfData.token_name).val(csrfData.hash));
        }
        form.appendTo('body').trigger('submit');
    });
</script>
<?php
}

/** Perfex staff profile (admin/staff/member/ID): add an "Assets" button (no core file edits). */
function ams_staff_profile_shortcut()
{
    $CI = &get_instance();
    if ($CI->uri->segment(2) !== 'staff' || $CI->uri->segment(3) !== 'member' || ! is_numeric($CI->uri->segment(4)) || staff_cant('view', 'ams_assets')) {
        return;
    }

    $staffId = (int) $CI->uri->segment(4);
    $h       = ams_people_model()->holdings($staffId);
    $label   = _l('ams_staff_assets_button', [$h['assets'], ams_qty($h['accessories'])]); ?>
<script>
    $(function() {
        var btn = $('<a class="btn btn-default tw-mb-3"><i class="fa-solid fa-laptop tw-mr-1"></i></a>')
            .attr('href', <?= json_encode(admin_url('asset_management/people/staff/' . $staffId), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>)
            .append(document.createTextNode(<?= json_encode($label, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>));
        $('#wrapper .content').first().prepend($('<div>').append(btn));
    });
</script>
<?php
}
