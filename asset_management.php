<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Asset Management
Description: IT asset & inventory management - asset register, check-out/check-in, requests, accessories/consumables/stock, maintenance, licences, purchase orders, depreciation, labels, audits, reports and a read-only HostBill inventory with low-stock alerts
Version: 1.0.0
Requires at least: 3.3.*
Author: Alpha Net BD
*/

define('AMS_MODULE_NAME', 'asset_management');

// Bump whenever install.php gains a table/column/option. ams_ensure_schema()
// re-runs the (idempotent) installer on the next admin page load, so updated
// module files never run against a stale schema and no manual
// deactivate/reactivate is needed.
define('AMS_SCHEMA_VERSION', 8);

define('AMS_UPLOAD_PATH', FCPATH . 'uploads/asset_management/');

// ─── Hooks ────────────────────────────────────────────────────────────────

hooks()->add_action('admin_init', 'ams_ensure_schema');
hooks()->add_action('admin_init', 'ams_register_permissions');
hooks()->add_action('admin_init', 'ams_init_menu_items');
hooks()->add_action('admin_init', 'ams_register_tables');
hooks()->add_action('after_custom_fields_select_options', 'ams_custom_fields_select_option');
hooks()->add_filter('before_single_setting_updated_in_loop', 'ams_encode_array_settings');
hooks()->add_filter('after_parse_email_template_message', 'ams_plain_mail_subject');
hooks()->add_action('after_cron_run', 'ams_ensure_schema', 1); // before the module's cron jobs
hooks()->add_action('after_cron_run', 'ams_cron_verify_stock_levels');
hooks()->add_action('after_cron_run', 'ams_cron_hostbill_sync');

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
hooks()->add_action('app_admin_footer', 'ams_keep_menu_open_script');

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

/**
 * Upgrades the database after a module update: on admin page loads and before the
 * module's cron jobs. A lock makes sure only one request runs install.php.
 */
function ams_ensure_schema()
{
    if ((int) get_option('ams_schema_version') >= AMS_SCHEMA_VERSION) {
        return;
    }

    $CI   = &get_instance();
    $lock = 'ams_schema_' . md5((string) $CI->db->database);
    if ((int) $CI->db->query('SELECT GET_LOCK(?, 30) l', [$lock])->row()->l !== 1) {
        return;
    }
    try {
        // Another request may have finished the upgrade while this one waited.
        $CI->db->where('name', 'ams_schema_version');
        $current = (int) ($CI->db->get(db_prefix() . 'options')->row()->value ?? 0);
        if ($current < AMS_SCHEMA_VERSION) {
            require_once __DIR__ . '/install.php';
            update_option('ams_schema_version', AMS_SCHEMA_VERSION);
        }
    } finally {
        $CI->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
    }
}

function ams_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

function ams_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('asset_management/configuration') . '">' . _l('settings') . '</a>';

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
// Everything lives under the "Assets" sidebar item, ordered in blocks (Perfex has one
// level of sub-items): self-service · assets · operations · inventory · HostBill · administration.

function ams_init_menu_items()
{
    $CI = &get_instance();

    $canAssetsAll = staff_can('view', 'ams_assets');
    $canAssets    = $canAssetsAll || staff_can('view_own', 'ams_assets');
    $itemKinds    = array_filter(array_keys(ams_item_kinds()), 'ams_item_can_view_kind_page');
    $canStockAll  = (bool) ams_item_viewable_kinds();
    $canHostbill  = staff_can('view', 'ams_hostbill');
    $canSetup     = staff_can('view', 'ams_setup');
    $canSettings  = ams_can_view_settings();

    $items = [
        // Self-service
        [ams_can_use_my_assets(), 'ams-my-assets', 'ams_my_assets', 'asset_management/my_assets'],
        // Assets
        [$canAssets, 'ams-dashboard', 'ams_menu_dashboard', 'asset_management'],
        [$canAssets, 'ams-assets', 'ams_menu_asset_list', 'asset_management/assets'],
        [$canAssetsAll, 'ams-people', 'ams_assets_by_staff', 'asset_management/people'],
        [ams_can_see_requests_page(), 'ams-requests', 'ams_requests', 'asset_management/requests'],
        [staff_can('view', 'ams_audits'), 'ams-audits', 'ams_audits', 'asset_management/audits'],
        // Operations
        [staff_can('view', 'ams_maintenance'), 'ams-maintenance', 'ams_maintenance', 'asset_management/maintenance'],
        [staff_can('view', 'ams_licenses'), 'ams-licenses', 'ams_licenses', 'asset_management/licenses'],
        [staff_can('view', 'ams_procurement'), 'ams-procurement', 'ams_purchase_orders', 'asset_management/procurement'],
        [$canAssetsAll, 'ams-purchases', 'ams_menu_purchases', 'asset_management/purchases'],
    ];
    // Inventory
    foreach ($itemKinds as $kind) {
        $items[] = [true, 'ams-inventory-' . $kind, ams_item_kinds()[$kind]['plural'], 'asset_management/inventory/index/' . $kind];
    }
    $items = array_merge($items, [
        [$canStockAll, 'ams-stock-levels', 'ams_stock_levels', 'asset_management/inventory/levels'],
        [$canStockAll, 'ams-stock-movements', 'ams_stock_movements', 'asset_management/inventory/movements'],
        // HostBill
        [$canHostbill, 'ams-hb-inventory', 'ams_hb_inventory', 'asset_management/hostbill'],
        // Administration
        [staff_can('view', 'ams_reports'), 'ams-reports', 'ams_reports', 'asset_management/reports'],
        [ams_can_import(), 'ams-import', 'ams_import', 'asset_management/import'],
        // Setup = master data + department approvers + module settings (tabs, each by permission).
        [$canSetup || $canSettings, 'ams-setup', 'ams_menu_setup', $canSetup ? 'asset_management/setup/index/categories' : 'asset_management/configuration'],
    ]);

    $items = array_values(array_filter($items, fn ($i) => $i[0]));
    if (! $items) {
        return;
    }

    // The parent opens the first page the staff member may use.
    $home = $canAssets ? 'asset_management' : $items[0][3];
    $CI->app_menu->add_sidebar_menu_item('ams', [
        'name'     => _l('ams_menu_assets'),
        'icon'     => 'fa-solid fa-laptop',
        'href'     => admin_url($home),
        'position' => 16,
    ]);

    foreach ($items as $position => $i) {
        $CI->app_menu->add_sidebar_children_item('ams', [
            'slug'     => $i[1],
            'name'     => _l($i[2]),
            'href'     => admin_url($i[3]),
            'position' => $position + 1,
        ]);
    }
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

/** Scheduled HostBill inventory refresh + low-stock check (Perfex cron, every "refresh interval" minutes). */
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
    $CI->ams_hostbill_model->refresh();
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
        'ams_hb_inventory' => ['db' => 'ams_hb_products', 'view' => 'tables/hb_inventory', 'cf' => null, 'pk' => 'hb_product_id'],
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
        'ams_assets_deleted' => ['db' => 'ams_assets', 'view' => 'tables/assets_deleted', 'cf' => null],
        'ams_setup_log'   => ['db' => 'ams_audit_log', 'view' => 'tables/setup_log', 'cf' => null],
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
    if ($CI->uri->segment(2) !== AMS_MODULE_NAME) {
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

    // Tables on tabs that were hidden at page load were measured at zero width:
    // re-measure them when their tab is shown (My Assets, asset page, item page, staff page).
    $(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function(e) {
        $($(e.target).attr('href')).find('table.dataTable').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().columns.adjust();
            }
        });
    });
</script>
<?php
}

/**
 * Perfex only opens a sidebar group when the URL equals one of its links. On every
 * module page (asset view, PO page, other Setup tabs...) keep "Assets" open and
 * highlight the closest sub-item: page aliases first, then the longest link prefix.
 */
function ams_keep_menu_open_script()
{
    $CI = &get_instance();
    if ($CI->uri->segment(2) !== AMS_MODULE_NAME) {
        return;
    }

    // Pages whose URL is not under their menu item's link.
    $aliases = [
        'setup'         => 'ams-setup',
        'approvers'     => 'ams-setup',
        'configuration' => 'ams-setup',
        'hostbill'      => 'ams-hb-inventory',
        'scan'          => 'ams-assets',
        'legacy_import' => 'ams-setup',
    ]; ?>
<script>
    $(function() {
        var li = $('#side-menu > li.menu-item-ams');
        if (!li.length) {
            return;
        }
        li.addClass('active');
        li.children('a').attr('aria-expanded', 'true');
        li.children('ul.nav-second-level').addClass('in').attr('aria-expanded', 'true').css('height', '');

        if (li.find('ul.nav-second-level > li.active').length) {
            return; // Perfex already matched the exact link
        }
        var base = <?= json_encode(admin_url(AMS_MODULE_NAME . '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
        var here = (location.origin + location.pathname).replace(/\/+$/, '');
        var path = here.indexOf(base) === 0 ? here.substring(base.length) : '';
        var aliases = <?= json_encode($aliases, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
        var target = path === '' ? li.find('li.sub-menu-item-ams-dashboard') : null;

        // Alias "x" matches x and x/...; alias "x/" matches anything starting with x/.
        $.each(aliases, function(prefix, slug) {
            var stem = prefix.replace(/\/$/, '');
            var hit = prefix !== stem ? path.indexOf(prefix) === 0 : (path === stem || path.indexOf(stem + '/') === 0);
            if (!target && hit) {
                target = li.find('li.sub-menu-item-' + slug);
            }
        });
        if (!target || !target.length) {
            var best = null, bestLen = 0;
            li.find('ul.nav-second-level > li > a').each(function() {
                var href = (this.href || '').replace(/\/+$/, '');
                // The dashboard link (module root) is a prefix of everything: exact match only.
                if (href.length > bestLen && href !== base.replace(/\/+$/, '') && (here === href || here.indexOf(href + '/') === 0)) {
                    best = $(this).parent();
                    bestLen = href.length;
                }
            });
            target = best;
        }
        if (target && target.length) {
            target.addClass('active');
        }
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

/**
 * Merge values are HTML-escaped for the email body; a subject is plain text, so
 * show "Dell & Co", not "Dell &amp; Co" (only this module's templates).
 */
function ams_plain_mail_subject($template)
{
    if (is_object($template) && ($template->type ?? '') === 'ams' && isset($template->subject)) {
        $template->subject = html_entity_decode(strip_tags(str_replace(['<br />', '<br>'], ' ', (string) $template->subject)), ENT_QUOTES, 'UTF-8');
    }

    return $template;
}
