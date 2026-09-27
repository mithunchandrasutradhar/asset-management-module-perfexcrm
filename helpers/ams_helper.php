<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Master-data ("Setup") entities: DB table, language keys and modal fields.
 * Field types: text | textarea | select | staff | color | number | checkbox.
 * A select's 'options' is a callable returning [['id'=>..,'name'=>..], ...].
 */
function ams_setup_entities()
{
    static $entities = null;

    if ($entities !== null) {
        return $entities;
    }

    $entities = [
        'categories' => [
            'table'    => 'ams_categories',
            'singular' => 'ams_category',
            'plural'   => 'ams_categories',
            'fields'   => [
                'name'        => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'parent_id'   => ['type' => 'select', 'label' => 'ams_parent_category', 'options' => 'ams_parent_category_options', 'help' => 'ams_parent_category_help'],
                'code'        => ['type' => 'text', 'label' => 'ams_category_code', 'help' => 'ams_category_code_help'],
                'icon'        => ['type' => 'text', 'label' => 'ams_icon', 'help' => 'ams_icon_help'],
                'description'        => ['type' => 'textarea', 'label' => 'ams_description'],
                'require_acceptance' => ['type' => 'checkbox', 'label' => 'ams_require_acceptance', 'help' => 'ams_require_acceptance_help'],
                'eula_text'          => ['type' => 'textarea', 'label' => 'ams_category_terms', 'help' => 'ams_category_terms_help'],
                'depreciation_method' => ['type' => 'select', 'label' => 'ams_dep_method', 'options' => 'ams_category_depreciation_method_options', 'help' => 'ams_dep_category_help'],
                'useful_life_months'  => ['type' => 'number', 'label' => 'ams_dep_life_months'],
                'salvage_percent'     => ['type' => 'number', 'label' => 'ams_dep_salvage_percent', 'step' => '0.01'],
                'active'             => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'category_id'], ['ams_categories', 'parent_id'], ['ams_models', 'category_id'], ['ams_items', 'category_id']],
        ],
        'brands' => [
            'table'    => 'ams_brands',
            'singular' => 'ams_brand',
            'plural'   => 'ams_brands',
            'fields'   => [
                'name'    => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'website' => ['type' => 'text', 'label' => 'ams_website'],
                'notes'   => ['type' => 'textarea', 'label' => 'ams_notes'],
                'active'  => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'brand_id'], ['ams_models', 'brand_id'], ['ams_items', 'brand_id']],
        ],
        'models' => [
            'table'    => 'ams_models',
            'singular' => 'ams_model',
            'plural'   => 'ams_models',
            'fields'   => [
                'name'        => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'model_no'    => ['type' => 'text', 'label' => 'ams_model_no'],
                'brand_id'    => ['type' => 'select', 'label' => 'ams_brand', 'options' => 'ams_brand_options'],
                'category_id' => ['type' => 'select', 'label' => 'ams_category', 'options' => 'ams_category_options'],
                'specs'       => ['type' => 'textarea', 'label' => 'ams_specs'],
                'active'      => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'model_id']],
        ],
        'statuses' => [
            'table'    => 'ams_statuses',
            'singular' => 'ams_status',
            'plural'   => 'ams_statuses',
            'fields'   => [
                'name'              => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'color'             => ['type' => 'color', 'label' => 'ams_color'],
                'type'              => ['type' => 'select', 'label' => 'ams_status_type', 'options' => 'ams_status_type_options', 'required' => true, 'help' => 'ams_status_type_help'],
                'requires_location' => ['type' => 'checkbox', 'label' => 'ams_requires_location'],
                'requires_note'     => ['type' => 'checkbox', 'label' => 'ams_requires_note'],
                'sort_order'        => ['type' => 'number', 'label' => 'ams_sort_order'],
                'active'            => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'status_id']],
        ],
        'locations' => [
            'table'    => 'ams_locations',
            'singular' => 'ams_location',
            'plural'   => 'ams_locations',
            'fields'   => [
                'name'             => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'parent_id'        => ['type' => 'select', 'label' => 'ams_parent_location', 'options' => 'ams_parent_location_options', 'help' => 'ams_parent_location_help'],
                'type'             => ['type' => 'select', 'label' => 'ams_location_type', 'options' => 'ams_location_type_options', 'required' => true],
                'manager_staff_id' => ['type' => 'staff', 'label' => 'ams_location_manager'],
                'address'          => ['type' => 'textarea', 'label' => 'ams_address'],
                'notes'            => ['type' => 'textarea', 'label' => 'ams_notes'],
                'active'           => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'location_id'], ['ams_locations', 'parent_id'], ['ams_stock_movements', 'location_id'], ['ams_items', 'default_location_id']],
        ],
        'suppliers' => [
            'table'    => 'ams_suppliers',
            'singular' => 'ams_supplier',
            'plural'   => 'ams_suppliers',
            'fields'   => [
                'name'           => ['type' => 'text', 'label' => 'ams_name', 'required' => true],
                'contact_person' => ['type' => 'text', 'label' => 'ams_contact_person'],
                'phone'          => ['type' => 'text', 'label' => 'ams_phone'],
                'email'          => ['type' => 'text', 'label' => 'ams_email'],
                'website'        => ['type' => 'text', 'label' => 'ams_website'],
                'address'        => ['type' => 'textarea', 'label' => 'ams_address'],
                'notes'          => ['type' => 'textarea', 'label' => 'ams_notes'],
                'active'         => ['type' => 'checkbox', 'label' => 'ams_active', 'default' => 1],
            ],
            'in_use' => [['ams_assets', 'supplier_id'], ['ams_stock_movements', 'supplier_id']],
        ],
    ];

    return $entities;
}

// ─── Option lists (all read from DB / Perfex core) ────────────────────────

function ams_rows($table, $where = [], $order = 'name')
{
    $CI = &get_instance();
    $CI->db->where($where);
    $CI->db->order_by($order, 'asc');

    return $CI->db->get(db_prefix() . $table)->result_array();
}

function ams_parent_category_options()
{
    return ams_rows('ams_categories', ['parent_id' => 0]);
}

/** Categories as "Parent › Child" for selects. */
function ams_category_options($activeOnly = false)
{
    $CI = &get_instance();
    $p  = db_prefix();
    $CI->db->select('c.id, c.parent_id, c.code, IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as name', false);
    $CI->db->from($p . 'ams_categories c');
    $CI->db->join($p . 'ams_categories pc', 'pc.id = c.parent_id', 'left');
    if ($activeOnly) {
        $CI->db->where('c.active', 1);
    }
    $CI->db->order_by('name', 'asc');

    return $CI->db->get()->result_array();
}

function ams_brand_options($activeOnly = false)
{
    return ams_rows('ams_brands', $activeOnly ? ['active' => 1] : []);
}

function ams_model_options($activeOnly = false)
{
    return ams_rows('ams_models', $activeOnly ? ['active' => 1] : []);
}

function ams_supplier_options($activeOnly = false)
{
    return ams_rows('ams_suppliers', $activeOnly ? ['active' => 1] : []);
}

function ams_parent_location_options()
{
    return ams_rows('ams_locations', ['parent_id' => 0]);
}

/** Locations as "Parent › Child" for selects. */
function ams_location_options($activeOnly = false)
{
    $CI = &get_instance();
    $p  = db_prefix();
    $CI->db->select('l.id, IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as name', false);
    $CI->db->from($p . 'ams_locations l');
    $CI->db->join($p . 'ams_locations pl', 'pl.id = l.parent_id', 'left');
    if ($activeOnly) {
        $CI->db->where('l.active', 1);
    }
    $CI->db->order_by('name', 'asc');

    return $CI->db->get()->result_array();
}

function ams_get_statuses($activeOnly = false)
{
    static $cache = [];
    $key = $activeOnly ? 'active' : 'all';

    if (! isset($cache[$key])) {
        $cache[$key] = ams_rows('ams_statuses', $activeOnly ? ['active' => 1] : [], 'sort_order');
    }

    return $cache[$key];
}

function ams_get_status($id)
{
    foreach (ams_get_statuses() as $status) {
        if ((int) $status['id'] === (int) $id) {
            return $status;
        }
    }

    return null;
}

function ams_get_status_by_key($key)
{
    foreach (ams_get_statuses() as $status) {
        if ($status['system_key'] === $key) {
            return $status;
        }
    }

    return null;
}

function ams_status_type_options()
{
    return [
        ['id' => 'deployable', 'name' => _l('ams_status_type_deployable')],
        ['id' => 'deployed', 'name' => _l('ams_status_type_deployed')],
        ['id' => 'pending', 'name' => _l('ams_status_type_pending')],
        ['id' => 'undeployable', 'name' => _l('ams_status_type_undeployable')],
        ['id' => 'archived', 'name' => _l('ams_status_type_archived')],
    ];
}

function ams_location_type_options()
{
    return [
        ['id' => 'office', 'name' => _l('ams_location_type_office')],
        ['id' => 'building', 'name' => _l('ams_location_type_building')],
        ['id' => 'room', 'name' => _l('ams_location_type_room')],
        ['id' => 'storage', 'name' => _l('ams_location_type_storage')],
        ['id' => 'cabinet', 'name' => _l('ams_location_type_cabinet')],
    ];
}

function ams_condition_options()
{
    return [
        ['id' => 'new', 'name' => _l('ams_condition_new')],
        ['id' => 'good', 'name' => _l('ams_condition_good')],
        ['id' => 'fair', 'name' => _l('ams_condition_fair')],
        ['id' => 'poor', 'name' => _l('ams_condition_poor')],
        ['id' => 'broken', 'name' => _l('ams_condition_broken')],
    ];
}

function ams_source_options()
{
    return [
        ['id' => 'purchase', 'name' => _l('ams_source_purchase')],
        ['id' => 'gift', 'name' => _l('ams_source_gift')],
        ['id' => 'lease', 'name' => _l('ams_source_lease')],
        ['id' => 'transfer', 'name' => _l('ams_source_transfer')],
    ];
}

function ams_assignee_type_options()
{
    return [
        ['id' => 'staff', 'name' => _l('ams_assign_type_staff')],
        ['id' => 'department', 'name' => _l('ams_assign_type_department')],
        ['id' => 'location', 'name' => _l('ams_assign_type_location')],
    ];
}

function ams_option_label($options, $id)
{
    foreach ($options as $option) {
        if ((string) $option['id'] === (string) $id) {
            return $option['name'];
        }
    }

    return $id;
}

/** Active Perfex staff (tblstaff) for pickers. */
function ams_staff_options()
{
    $CI = &get_instance();
    $CI->load->model('staff_model');

    return $CI->staff_model->get('', ['active' => 1]);
}

/** Perfex departments (tbldepartments). */
function ams_department_options()
{
    $CI = &get_instance();
    $CI->load->model('departments_model');

    return $CI->departments_model->get();
}

/** First Perfex department of a staff member (tblstaff_departments), or null. */
function ams_staff_primary_department($staffId)
{
    $CI  = &get_instance();
    $row = $CI->db->select('departmentid')
        ->where('staffid', (int) $staffId)
        ->order_by('staffdepartmentid', 'asc')
        ->limit(1)
        ->get(db_prefix() . 'staff_departments')
        ->row();

    return $row ? (int) $row->departmentid : null;
}

function ams_department_name($id)
{
    if (! $id) {
        return '';
    }
    $CI  = &get_instance();
    $row = $CI->db->select('name')->where('departmentid', (int) $id)->get(db_prefix() . 'departments')->row();

    return $row ? $row->name : '';
}

function ams_location_name($id)
{
    if (! $id) {
        return '';
    }
    foreach (ams_location_options() as $location) {
        if ((int) $location['id'] === (int) $id) {
            return $location['name'];
        }
    }

    return '';
}

// ─── Display helpers ──────────────────────────────────────────────────────

function ams_status_badge($name, $color = null)
{
    if ($name === null || $name === '') {
        return '';
    }
    $color = $color ?: '#64748b';

    return '<span class="label" style="color:' . e($color) . ';border:1px solid ' . e($color) . ';background:transparent">' . e($name) . '</span>';
}

/** Assignee display (staff / department / location). $name is optional pre-joined label. */
function ams_assignee_html($type, $id, $name = null)
{
    if (! $type || ! $id) {
        return '';
    }

    if ($type === 'staff') {
        $label = $name ?: get_staff_full_name($id);

        return '<a href="' . admin_url('staff/member/' . (int) $id) . '">' . staff_profile_image($id, ['staff-profile-image-small', 'tw-mr-1']) . e($label) . '</a>';
    }

    if ($type === 'department') {
        return '<i class="fa-solid fa-users tw-text-neutral-400 tw-mr-1"></i>' . e($name ?: ams_department_name($id));
    }

    if ($type === 'location') {
        return '<i class="fa-solid fa-location-dot tw-text-neutral-400 tw-mr-1"></i>' . e($name ?: ams_location_name($id));
    }

    return '';
}

function ams_assignee_text($type, $id)
{
    if (! $type || ! $id) {
        return '';
    }
    if ($type === 'staff') {
        return get_staff_full_name($id);
    }
    if ($type === 'department') {
        return ams_department_name($id);
    }

    return ams_location_name($id);
}

function ams_warranty_html($warrantyEnd)
{
    if (empty($warrantyEnd)) {
        return '<span class="text-muted">' . _l('ams_warranty_none') . '</span>';
    }

    $days = (int) floor((strtotime($warrantyEnd) - strtotime(date('Y-m-d'))) / 86400);

    if ($days < 0) {
        return '<span class="text-danger"><i class="fa-solid fa-circle-xmark tw-mr-1"></i>' . e(_d($warrantyEnd)) . '</span>';
    }

    $class = $days <= (int) get_option('ams_warranty_expiring_days') ? 'text-warning' : 'text-success';

    return '<span class="' . $class . '"><i class="fa-solid fa-circle-check tw-mr-1"></i>' . e(_d($warrantyEnd)) . '</span>';
}

function ams_format_money($amount, $currencyId = null)
{
    if ($amount === null || $amount === '') {
        return '';
    }

    $currency = null;
    if ($currencyId) {
        $CI = &get_instance();
        $CI->load->model('currencies_model');
        $currency = $CI->currencies_model->get($currencyId);
    }

    return app_format_money($amount, $currency ?: get_base_currency());
}

function ams_currency_options()
{
    $CI = &get_instance();
    $CI->load->model('currencies_model');

    return $CI->currencies_model->get();
}

// ─── Access ───────────────────────────────────────────────────────────────

function ams_can_view_assets()
{
    return staff_can('view', 'ams_assets') || staff_can('view_own', 'ams_assets');
}

/** "View own" = assets currently checked out to the logged-in staff member. */
function ams_can_view_asset($asset)
{
    if (staff_can('view', 'ams_assets')) {
        return true;
    }

    if (! staff_can('view_own', 'ams_assets') || ! $asset) {
        return false;
    }

    $asset = (object) $asset;

    return $asset->assigned_type === 'staff' && (int) $asset->assigned_id === (int) get_staff_user_id();
}

/** SQL fragment restricting an ams_assets query to what the current user may see. */
function ams_assets_scope_where($alias)
{
    if (staff_can('view', 'ams_assets')) {
        return '';
    }

    return 'AND ' . $alias . '.assigned_type = "staff" AND ' . $alias . '.assigned_id = ' . (int) get_staff_user_id();
}

// ─── Inventory (quantity-tracked items) ───────────────────────────────────

/**
 * Item kinds and the Perfex permission group that governs each one.
 * Capabilities per group: view / create / edit / delete, "adjust" (receive,
 * transfer, stock adjustments), plus "checkout" (accessories) or "issue".
 */
function ams_item_kinds()
{
    return [
        'accessory'  => ['perm' => 'ams_accessories', 'plural' => 'ams_accessories', 'singular' => 'ams_accessory', 'icon' => 'fa-solid fa-computer-mouse'],
        'consumable' => ['perm' => 'ams_consumables', 'plural' => 'ams_consumables', 'singular' => 'ams_consumable', 'icon' => 'fa-solid fa-box-open'],
        'stock'      => ['perm' => 'ams_stock', 'plural' => 'ams_stock_items', 'singular' => 'ams_stock_item', 'icon' => 'fa-solid fa-boxes-stacked'],
    ];
}

function ams_item_kind_options()
{
    return array_map(fn ($k, $cfg) => ['id' => $k, 'name' => _l($cfg['singular'])], array_keys(ams_item_kinds()), ams_item_kinds());
}

function ams_item_can($capability, $kind)
{
    $kinds = ams_item_kinds();

    return isset($kinds[$kind]) && staff_can($capability, $kinds[$kind]['perm']);
}

/** Kinds the current user may see globally ("view"). */
function ams_item_viewable_kinds()
{
    return array_values(array_filter(array_keys(ams_item_kinds()), fn ($k) => ams_item_can('view', $k)));
}

function ams_item_can_view_kind_page($kind)
{
    return ams_item_can('view', $kind) || ($kind === 'accessory' && staff_can('view_own', 'ams_accessories'));
}

/** out | low | ok, from the available quantity and the item's reorder level. */
function ams_stock_state($available, $reorderLevel)
{
    if ((float) $available <= 0) {
        return 'out';
    }
    if ((float) $reorderLevel > 0 && (float) $available <= (float) $reorderLevel) {
        return 'low';
    }

    return 'ok';
}

function ams_stock_state_badge($state)
{
    $map = ['out' => 'danger', 'low' => 'warning', 'ok' => 'success'];

    return '<span class="label label-' . ($map[$state] ?? 'default') . '">' . _l('ams_stock_state_' . $state) . '</span>';
}

/** Quantities: no trailing ".00" for whole numbers. */
function ams_qty($qty)
{
    $qty = (float) $qty;

    return floor($qty) == $qty ? number_format($qty, 0, '.', '') : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
}

function ams_movement_type_options()
{
    return array_map(fn ($t) => ['id' => $t, 'name' => _l('ams_mv_' . $t)], ['receive', 'issue', 'checkout', 'return', 'transfer_in', 'transfer_out', 'adjust', 'sale', 'sale_return', 'reserve', 'release']);
}

function ams_adjust_reason_options()
{
    return array_map(fn ($r) => ['id' => $r, 'name' => _l('ams_reason_' . $r)], ['correction', 'found', 'damaged', 'lost', 'expired', 'other']);
}

/** Staff selected in settings to receive low / out-of-stock notifications. */
function ams_low_stock_recipients()
{
    $ids = json_decode((string) get_option('ams_low_stock_notify_staff'), true);

    return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
}

// ─── Notifications & email ────────────────────────────────────────────────

/**
 * Perfex in-app notification to staff members.
 * $langKey is rendered by Perfex with $data (sprintf), $link is relative to admin_url().
 */
function ams_notify($staffIds, $langKey, $data, $link)
{
    $staffIds = array_values(array_unique(array_filter(array_map('intval', (array) $staffIds))));
    $sent     = [];
    foreach ($staffIds as $id) {
        if ($id === (int) get_staff_user_id()) {
            continue; // never notify yourself about your own action
        }
        if (add_notification([
            'description'     => $langKey,
            'touserid'        => $id,
            'fromcompany'     => true,
            'link'            => $link,
            'additional_data' => serialize(array_values((array) $data)),
        ])) {
            $sent[] = $id;
        }
    }
    if ($sent) {
        pusher_trigger_notification($sent);
    }
}

/**
 * Sends an Asset Management email template (Setup → Email Templates → Asset Management)
 * to a Perfex staff member, if module emails are enabled and the template is active.
 */
function ams_send_email($slug, $staffId, $fields = [])
{
    if (get_option('ams_email_notifications') != '1' || ! $staffId) {
        return false;
    }

    $CI    = &get_instance();
    $staff = $CI->db->select('email, firstname, lastname, active')->where('staffid', (int) $staffId)->get(db_prefix() . 'staff')->row();
    if (! $staff || ! $staff->active || ! $staff->email) {
        return false;
    }

    $fields = array_merge([
        '{staff_firstname}' => $staff->firstname,
        '{staff_lastname}'  => $staff->lastname,
        '{ams_item}'        => '',
        '{ams_details}'     => '',
        '{ams_link}'        => admin_url('asset_management'),
        '{ams_status}'      => '',
        '{ams_by}'          => '',
        '{ams_action_text}' => '',
        '{ams_staff_name}'  => '',
        '{ams_request_no}'  => '',
        '{ams_due_date}'    => '',
    ], $fields);

    // Values are plain text; escape them because the templates are HTML.
    foreach ($fields as $key => $value) {
        if ($key !== '{ams_link}') {
            $fields[$key] = nl2br(e((string) $value));
        }
    }

    return send_mail_template('Ams_mail', AMS_MODULE_NAME, $slug, $staff->email, (int) $staffId, $fields);
}

/**
 * Email to a non-staff address (e.g. a supplier) using a module template,
 * regardless of the staff-notification switch. Returns true when queued/sent.
 */
function ams_send_email_to($slug, $email, $fields = [], $files = [])
{
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    foreach ($fields as $key => $value) {
        if ($key !== '{ams_link}') {
            $fields[$key] = nl2br(e((string) $value));
        }
    }
    $fields = array_merge(['{staff_firstname}' => '', '{staff_lastname}' => '', '{ams_company}' => e(get_option('companyname'))], $fields);

    return send_mail_template('Ams_mail', AMS_MODULE_NAME, $slug, $email, 0, $fields, $files);
}

/** Active staff holding a module capability (Perfex staff permissions); admins if nobody has it. */
function ams_staff_with_capability($feature, $capability, $fallbackToAdmins = true)
{
    $CI  = &get_instance();
    $p   = db_prefix();
    $ids = array_map('intval', array_column($CI->db->query('SELECT DISTINCT sp.staff_id FROM ' . $p . 'staff_permissions sp
        JOIN ' . $p . 'staff s ON s.staffid = sp.staff_id AND s.active = 1
        WHERE sp.feature = ? AND sp.capability = ?', [$feature, $capability])->result_array(), 'staff_id'));

    if (! $ids && $fallbackToAdmins) {
        $ids = array_map('intval', array_column($CI->db->query('SELECT staffid FROM ' . $p . 'staff WHERE admin = 1 AND active = 1')->result_array(), 'staffid'));
    }

    return $ids;
}

/** Staff chosen in settings for asset-manager notifications (overdue, declined acceptances). */
function ams_manager_recipients()
{
    $ids = json_decode((string) get_option('ams_manager_notify_staff'), true);

    return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
}

function ams_request_status_badge($status)
{
    $map = ['pending_dept' => 'warning', 'pending_manager' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'fulfilled' => 'success', 'cancelled' => 'default'];

    return '<span class="label label-' . ($map[$status] ?? 'default') . '">' . _l('ams_req_status_' . $status) . '</span>';
}

function ams_po_status_badge($status)
{
    $map = ['draft' => 'default', 'pending_approval' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'sent' => 'primary', 'partially_received' => 'warning', 'received' => 'success', 'cancelled' => 'default'];

    return '<span class="label label-' . ($map[$status] ?? 'default') . '">' . _l('ams_po_status_' . $status) . '</span>';
}

function ams_acceptance_status_badge($status)
{
    $map = ['pending' => 'warning', 'accepted' => 'success', 'declined' => 'danger', 'cancelled' => 'default'];

    return '<span class="label label-' . ($map[$status] ?? 'default') . '">' . _l('ams_acc_status_' . $status) . '</span>';
}

/** Can the current user open the "My Assets" page? */
function ams_can_use_my_assets()
{
    return staff_can('view', 'ams_assets') || staff_can('view_own', 'ams_assets')
        || staff_can('view', 'ams_accessories') || staff_can('view_own', 'ams_accessories')
        || staff_can('view_own', 'ams_licenses')
        || staff_can('create', 'ams_requests') || staff_can('view_own', 'ams_requests') || staff_can('view', 'ams_requests');
}

/** Departments the current user approves requests for. */
function ams_my_approver_departments()
{
    static $cache = null;
    if ($cache === null) {
        $CI    = &get_instance();
        $cache = array_map('intval', array_column($CI->db->where('staff_id', (int) get_staff_user_id())
            ->get(db_prefix() . 'ams_department_approvers')->result_array(), 'department_id'));
    }

    return $cache;
}

function ams_can_see_requests_page()
{
    return staff_can('view', 'ams_requests') || staff_can('approve', 'ams_requests') || (bool) ams_my_approver_departments();
}

// ─── HostBill ─────────────────────────────────────────────────────────────

/** HostBill order / invoice status as a Perfex label. */
function ams_hb_status_badge($status)
{
    if ($status === null || $status === '') {
        return '';
    }
    $map = ['active' => 'success', 'paid' => 'success', 'pending' => 'warning', 'unpaid' => 'warning', 'cancelled' => 'default', 'fraud' => 'danger', 'refunded' => 'info'];

    return '<span class="label label-' . ($map[strtolower($status)] ?? 'default') . '">' . e($status) . '</span>';
}

/** Stock state of a HostBill order line. */
function ams_hb_line_state_badge($state)
{
    $map = [
        'reserved' => 'info', 'deducted' => 'success', 'released' => 'default', 'returned' => 'default',
        'short'    => 'danger', 'unmapped' => 'default', 'ignored' => 'default', 'none' => 'default',
    ];

    return '<span class="label label-' . ($map[$state] ?? 'default') . '">' . _l('ams_hb_line_' . $state) . '</span>';
}

function ams_hb_fulfilment_badge($status)
{
    $map = ['pending' => 'warning', 'picked' => 'info', 'delivered' => 'success'];

    return '<span class="label label-' . ($map[$status] ?? 'default') . '">' . _l('ams_hb_fulfilment_' . $status) . '</span>';
}

function ams_hb_webhook_url()
{
    return site_url('asset_management/hostbill_webhook') . '?token=' . urlencode((string) get_option('ams_hb_webhook_secret'));
}

// ─── Files ────────────────────────────────────────────────────────────────

function ams_asset_upload_dir($assetId)
{
    return AMS_UPLOAD_PATH . 'assets/' . (int) $assetId . '/';
}

function ams_file_url($fileId, $download = false)
{
    return admin_url('asset_management/assets/file/' . (int) $fileId . ($download ? '/1' : ''));
}

// ─── Finance, audits, disposal (Phase 7) ──────────────────────────────────

function ams_depreciation_methods()
{
    return ['none', 'straight_line', 'declining_balance'];
}

/** For selects. $withInherit adds "use category default" (asset form). */
function ams_depreciation_method_options($withInherit = false)
{
    $options = [];
    if ($withInherit) {
        $options[] = ['id' => '', 'name' => _l('ams_dep_inherit')];
    }
    foreach (ams_depreciation_methods() as $m) {
        $options[] = ['id' => $m, 'name' => _l('ams_dep_method_' . $m)];
    }

    return $options;
}

function ams_category_depreciation_method_options()
{
    return array_values(array_filter(ams_depreciation_method_options(), fn ($o) => $o['id'] !== 'none'));
}

function ams_disposal_methods()
{
    return ['sold', 'donated', 'scrapped', 'recycled', 'lost', 'stolen', 'returned'];
}

function ams_disposal_method_options()
{
    return array_map(fn ($m) => ['id' => $m, 'name' => _l('ams_disp_method_' . $m)], ams_disposal_methods());
}

/** Suggested archived status for a disposal method (matched on the seeded status names). */
function ams_disposal_default_status($method)
{
    $words = [
        'sold'     => ['sold'],
        'donated'  => ['donat'],
        'scrapped' => ['retired', 'dispos', 'scrap'],
        'recycled' => ['retired', 'dispos', 'recycl'],
        'lost'     => ['lost'],
        'stolen'   => ['stolen'],
        'returned' => ['retired', 'dispos', 'return'],
    ];
    $archived = array_values(array_filter(ams_get_statuses(true), fn ($s) => $s['type'] === 'archived'));
    foreach ($words[$method] ?? [] as $w) {
        foreach ($archived as $s) {
            if (stripos($s['name'], $w) !== false) {
                return (int) $s['id'];
            }
        }
    }

    return $archived ? (int) $archived[0]['id'] : 0;
}

function ams_audit_status_badge($status)
{
    $map = ['draft' => 'default', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'default'];

    return '<span class="label label-' . ($map[$status] ?? 'default') . '">' . _l('ams_audit_status_' . $status) . '</span>';
}

function ams_audit_result_badge($result)
{
    $map = ['pending' => 'default', 'found' => 'success', 'misplaced' => 'warning', 'unexpected' => 'info', 'missing' => 'danger'];

    return '<span class="label label-' . ($map[$result] ?? 'default') . '">' . _l('ams_audit_result_' . $result) . '</span>';
}

/** Location plus all its sub-locations (locations nest one level). */
function ams_location_with_children($locationId)
{
    if (! $locationId) {
        return [];
    }
    $CI  = &get_instance();
    $ids = array_map('intval', array_column($CI->db->select('id')->where('parent_id', (int) $locationId)->get(db_prefix() . 'ams_locations')->result_array(), 'id'));

    return array_merge([(int) $locationId], $ids);
}

function ams_category_with_children($categoryId)
{
    if (! $categoryId) {
        return [];
    }
    $CI  = &get_instance();
    $ids = array_map('intval', array_column($CI->db->select('id')->where('parent_id', (int) $categoryId)->get(db_prefix() . 'ams_categories')->result_array(), 'id'));

    return array_merge([(int) $categoryId], $ids);
}

/** URL printed in the asset label QR code: opens the asset (or records it in an active audit scan). */
function ams_scan_url($assetTag)
{
    return admin_url('asset_management/scan/tag/' . rawurlencode($assetTag));
}

function ams_label_layout_options()
{
    return [['id' => 'single', 'name' => _l('ams_label_layout_single')], ['id' => 'sheet', 'name' => _l('ams_label_layout_sheet')]];
}

/**
 * State-changing controller methods only answer POST: Perfex checks the CSRF
 * token on POST only, so a GET link (e.g. an <img> in a note) must not be able
 * to delete or change anything. Call from a controller constructor.
 */
function ams_post_only(array $methods)
{
    $CI = &get_instance();
    if (! in_array($CI->router->fetch_method(), $methods, true) || $CI->input->method() === 'post') {
        return;
    }
    if ($CI->input->is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => _l('ams_invalid_request')]);
        exit;
    }
    set_alert('warning', _l('ams_invalid_request'));
    redirect($_SERVER['HTTP_REFERER'] ?? admin_url('asset_management'));
}

/**
 * Filter values posted to the module's tables must never be passed on as SQL
 * expressions: Perfex core (App_table::wrapValueInQuotes) leaves a value that
 * contains "WORD(" unquoted. Real filter values never contain that (date
 * presets are resolved on the server), so such rules are dropped.
 * Hooked on table_ams_*_output_params, i.e. before the filters are read.
 */
function ams_sanitize_table_filters($params)
{
    if (empty($_POST['filters']['rules']) || ! is_array($_POST['filters']['rules'])) {
        return $params;
    }
    foreach ($_POST['filters']['rules'] as $i => $rule) {
        $values = (array) ($rule['value'] ?? '');
        array_walk_recursive($values, function ($v) use (&$bad) {
            if (is_string($v) && preg_match('/[A-Z]+\s*\(/', $v)) {
                $bad = true;
            }
        });
        if (! empty($bad) || ! is_array($rule)) {
            unset($_POST['filters']['rules'][$i]);
            $bad = false;
        }
    }

    return $params;
}

/** Staff who may use the import page for at least one type (assets, stock items, suppliers). */
function ams_can_import()
{
    if (staff_can('create', 'ams_assets') || staff_can('edit', 'ams_assets') || staff_can('create', 'ams_setup') || staff_can('edit', 'ams_setup')) {
        return true;
    }
    foreach (array_keys(ams_item_kinds()) as $kind) {
        if (ams_item_can('create', $kind) || ams_item_can('edit', $kind)) {
            return true;
        }
    }

    return false;
}

function ams_label_code_options()
{
    return [['id' => 'qr', 'name' => _l('ams_label_code_qr')], ['id' => 'barcode', 'name' => _l('ams_label_code_barcode')]];
}
