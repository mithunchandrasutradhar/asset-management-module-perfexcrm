<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Removes only objects carrying the module prefix: tblams_* tables, ams_*
// options, ams_* staff permissions and ams_assets custom fields.
// Uploaded files in uploads/asset_management/ are left in place on purpose.

$CI = &get_instance();
$p  = db_prefix();

$tables = [
    'ams_audit_lines',
    'ams_audits',
    'ams_disposals',
    'ams_goods_receipt_lines',
    'ams_goods_receipts',
    'ams_po_lines',
    'ams_purchase_orders',
    'ams_license_seats',
    'ams_licenses',
    'ams_maintenance_schedules',
    'ams_maintenance',
    'ams_department_approvers',
    'ams_requests',
    'ams_acceptances',
    'ams_hb_sync_log',
    'ams_hb_order_lines',
    'ams_hb_orders',
    'ams_hb_product_map',
    'ams_hb_products',
    'ams_item_checkouts',
    'ams_stock_movements',
    'ams_stock_levels',
    'ams_items',
    'ams_audit_log',
    'ams_asset_files',
    'ams_asset_history',
    'ams_assets',
    'ams_tag_sequences',
    'ams_suppliers',
    'ams_locations',
    'ams_statuses',
    'ams_models',
    'ams_brands',
    'ams_categories',
];

foreach ($tables as $table) {
    if ($CI->db->table_exists($p . $table)) {
        $CI->db->query('DROP TABLE `' . $p . $table . '`');
    }
}

$CI->db->like('name', 'ams_', 'after');
$CI->db->delete($p . 'options');

$CI->db->like('feature', 'ams_', 'after');
$CI->db->delete($p . 'staff_permissions');

$CI->db->where('type', 'ams')->delete($p . 'emailtemplates');

$fields = $CI->db->where_in('fieldto', ['ams_assets', 'ams_items'])->get($p . 'customfields')->result_array();
foreach ($fields as $field) {
    $CI->db->where('fieldid', $field['id'])->delete($p . 'customfieldsvalues');
}
$CI->db->where_in('fieldto', ['ams_assets', 'ams_items'])->delete($p . 'customfields');
