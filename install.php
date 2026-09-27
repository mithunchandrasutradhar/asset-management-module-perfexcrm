<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Idempotent installer: every table/column/row is created only if missing, so
// it is safe to re-run on every schema version bump (see ams_ensure_schema()).

$CI = &get_instance();
$p  = db_prefix();
$cs = $CI->db->char_set;
$co = $CI->db->dbcollat;
$engine = 'ENGINE=InnoDB DEFAULT CHARSET=' . $cs . ' COLLATE=' . $co;

// ─── Master data ──────────────────────────────────────────────────────────

if (! $CI->db->table_exists($p . 'ams_categories')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_categories` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `parent_id` INT(11) NOT NULL DEFAULT 0,
        `name` VARCHAR(191) NOT NULL,
        `code` VARCHAR(20) NULL,
        `icon` VARCHAR(60) NULL,
        `description` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `parent_id` (`parent_id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_brands')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_brands` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `website` VARCHAR(191) NULL,
        `notes` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_models')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_models` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `model_no` VARCHAR(100) NULL,
        `brand_id` INT(11) NULL,
        `category_id` INT(11) NULL,
        `specs` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `brand_id` (`brand_id`),
        KEY `category_id` (`category_id`)
    ) " . $engine);
}

// type: deployable | deployed | pending | undeployable | archived
// system_key marks the rows the code relies on, so no status ID is hard-coded.
if (! $CI->db->table_exists($p . 'ams_statuses')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_statuses` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `color` VARCHAR(20) NULL,
        `type` VARCHAR(20) NOT NULL DEFAULT 'deployable',
        `system_key` VARCHAR(30) NULL,
        `requires_location` TINYINT(1) NOT NULL DEFAULT 0,
        `requires_note` TINYINT(1) NOT NULL DEFAULT 0,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `system_key` (`system_key`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_locations')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_locations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `parent_id` INT(11) NOT NULL DEFAULT 0,
        `name` VARCHAR(191) NOT NULL,
        `type` VARCHAR(20) NOT NULL DEFAULT 'storage',
        `address` TEXT NULL,
        `manager_staff_id` INT(11) NULL,
        `notes` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `parent_id` (`parent_id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_suppliers')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_suppliers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `contact_person` VARCHAR(191) NULL,
        `phone` VARCHAR(50) NULL,
        `email` VARCHAR(191) NULL,
        `address` TEXT NULL,
        `website` VARCHAR(191) NULL,
        `notes` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_tag_sequences')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_tag_sequences` (
        `code` VARCHAR(60) NOT NULL,
        `next_number` INT(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (`code`)
    ) " . $engine);
}

// ─── Assets ───────────────────────────────────────────────────────────────
// The current state (status, assignee, location) lives on the row itself, so
// lists never need the legacy "MAX(id) per asset" history subquery.
// assigned_type = staff (tblstaff) | department (tbldepartments) | location.

if (! $CI->db->table_exists($p . 'ams_assets')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_assets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_tag` VARCHAR(60) NOT NULL,
        `serial_no` VARCHAR(100) NULL,
        `name` VARCHAR(191) NOT NULL,
        `category_id` INT(11) NULL,
        `brand_id` INT(11) NULL,
        `model_id` INT(11) NULL,
        `supplier_id` INT(11) NULL,
        `status_id` INT(11) NOT NULL,
        `asset_condition` VARCHAR(20) NULL,
        `location_id` INT(11) NULL,
        `source` VARCHAR(20) NOT NULL DEFAULT 'purchase',
        `gifted_by_type` VARCHAR(20) NULL,
        `gifted_by_id` INT(11) NULL,
        `gifted_by_name` VARCHAR(191) NULL,
        `purchased_by` INT(11) NULL,
        `purchase_date` DATE NULL,
        `purchase_cost` DECIMAL(15,2) NULL,
        `currency` INT(11) NULL,
        `invoice_no` VARCHAR(100) NULL,
        `order_no` VARCHAR(100) NULL,
        `lease_end_date` DATE NULL,
        `warranty_start` DATE NULL,
        `warranty_end` DATE NULL,
        `warranty_provider` VARCHAR(191) NULL,
        `warranty_notes` TEXT NULL,
        `assigned_type` VARCHAR(20) NULL,
        `assigned_id` INT(11) NULL,
        `department_id` INT(11) NULL,
        `assigned_at` DATETIME NULL,
        `expected_checkin` DATE NULL,
        `cover_file_id` INT(11) NULL,
        `notes` TEXT NULL,
        `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
        `deleted_reason` VARCHAR(255) NULL,
        `deleted_by` INT(11) NULL,
        `date_deleted` DATETIME NULL,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        `updated_by` INT(11) NULL,
        `date_updated` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `asset_tag` (`asset_tag`),
        KEY `serial_no` (`serial_no`),
        KEY `category_id` (`category_id`),
        KEY `brand_id` (`brand_id`),
        KEY `model_id` (`model_id`),
        KEY `supplier_id` (`supplier_id`),
        KEY `status_id` (`status_id`),
        KEY `location_id` (`location_id`),
        KEY `assigned` (`assigned_type`, `assigned_id`),
        KEY `department_id` (`department_id`),
        KEY `source` (`source`),
        KEY `purchase_date` (`purchase_date`),
        KEY `warranty_end` (`warranty_end`),
        KEY `is_deleted` (`is_deleted`)
    ) " . $engine);
}

// action: create | checkout | checkin | status | edit | delete
if (! $CI->db->table_exists($p . 'ams_asset_history')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_asset_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_id` INT(11) NOT NULL,
        `action` VARCHAR(30) NOT NULL,
        `status_from` INT(11) NULL,
        `status_to` INT(11) NULL,
        `assigned_type_from` VARCHAR(20) NULL,
        `assigned_id_from` INT(11) NULL,
        `assigned_type_to` VARCHAR(20) NULL,
        `assigned_id_to` INT(11) NULL,
        `location_from` INT(11) NULL,
        `location_to` INT(11) NULL,
        `department_id` INT(11) NULL,
        `asset_condition` VARCHAR(20) NULL,
        `note` TEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `asset_id` (`asset_id`),
        KEY `action` (`action`),
        KEY `date_created` (`date_created`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_asset_files')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_asset_files` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_id` INT(11) NOT NULL,
        `file_name` VARCHAR(191) NOT NULL,
        `original_name` VARCHAR(191) NOT NULL,
        `filetype` VARCHAR(100) NULL,
        `filesize` INT(11) NULL,
        `is_image` TINYINT(1) NOT NULL DEFAULT 0,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `asset_id` (`asset_id`)
    ) " . $engine);
}

// Field-level change log (legacy update_log parity). changes = JSON {field: [old, new]}
if (! $CI->db->table_exists($p . 'ams_audit_log')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_audit_log` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `rel_type` VARCHAR(30) NOT NULL,
        `rel_id` INT(11) NOT NULL,
        `action` VARCHAR(20) NOT NULL,
        `changes` LONGTEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `rel` (`rel_type`, `rel_id`)
    ) " . $engine);
}

// ─── Inventory (schema v2) ────────────────────────────────────────────────
// Quantity-tracked items. kind = accessory (checked out & returned),
// consumable (issued, not returned) or stock (goods, incl. sellable HostBill items).

if (! $CI->db->table_exists($p . 'ams_items')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `sku` VARCHAR(60) NOT NULL,
        `name` VARCHAR(191) NOT NULL,
        `kind` VARCHAR(20) NOT NULL,
        `category_id` INT(11) NULL,
        `brand_id` INT(11) NULL,
        `model_no` VARCHAR(100) NULL,
        `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
        `cost` DECIMAL(15,2) NULL,
        `sale_price` DECIMAL(15,2) NULL,
        `reorder_level` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `reorder_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `default_location_id` INT(11) NULL,
        `is_sellable` TINYINT(1) NOT NULL DEFAULT 0,
        `description` TEXT NULL,
        `notes` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `stock_alert` TINYINT(1) NOT NULL DEFAULT 0,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NULL,
        `updated_by` INT(11) NULL,
        `date_updated` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sku` (`sku`),
        KEY `kind` (`kind`),
        KEY `category_id` (`category_id`),
        KEY `brand_id` (`brand_id`),
        KEY `active` (`active`)
    ) " . $engine);
}

// Cached projection of the movement ledger (rebuildable at any time).
if (! $CI->db->table_exists($p . 'ams_stock_levels')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_stock_levels` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `location_id` INT(11) NOT NULL,
        `on_hand` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `reserved` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `item_location` (`item_id`, `location_id`),
        KEY `location_id` (`location_id`)
    ) " . $engine);
}

// Append-only stock ledger. type: receive | issue | checkout | return |
// transfer_in | transfer_out | adjust (sale | reserve | release come with HostBill).
if (! $CI->db->table_exists($p . 'ams_stock_movements')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_stock_movements` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `location_id` INT(11) NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `type` VARCHAR(20) NOT NULL,
        `ref_type` VARCHAR(30) NULL,
        `ref_id` INT(11) NULL,
        `unit_cost` DECIMAL(15,2) NULL,
        `assigned_type` VARCHAR(20) NULL,
        `assigned_id` INT(11) NULL,
        `department_id` INT(11) NULL,
        `supplier_id` INT(11) NULL,
        `reference` VARCHAR(100) NULL,
        `reason` VARCHAR(30) NULL,
        `note` TEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `item_id` (`item_id`),
        KEY `location_id` (`location_id`),
        KEY `type` (`type`),
        KEY `ref` (`ref_type`, `ref_id`),
        KEY `date_created` (`date_created`)
    ) " . $engine);
}

// Accessories checked out to Perfex staff / departments (partial returns allowed).
if (! $CI->db->table_exists($p . 'ams_item_checkouts')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_item_checkouts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `location_id` INT(11) NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `returned_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `assigned_type` VARCHAR(20) NOT NULL,
        `assigned_id` INT(11) NOT NULL,
        `department_id` INT(11) NULL,
        `expected_return` DATE NULL,
        `status` VARCHAR(10) NOT NULL DEFAULT 'open',
        `note` TEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        `date_closed` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `item_id` (`item_id`),
        KEY `assigned` (`assigned_type`, `assigned_id`),
        KEY `status` (`status`)
    ) " . $engine);
}

// ─── HostBill integration (schema v3) ─────────────────────────────────────

// Local cache of HostBill products (for the mapping screen and reconciliation).
if (! $CI->db->table_exists($p . 'ams_hb_products')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_hb_products` (
        `hb_product_id` INT(11) NOT NULL,
        `name` VARCHAR(191) NOT NULL,
        `orderpage_id` INT(11) NULL,
        `orderpage_name` VARCHAR(191) NULL,
        `stock_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `qty` DECIMAL(15,2) NULL,
        `visible` TINYINT(1) NOT NULL DEFAULT 1,
        `synced_at` DATETIME NULL,
        PRIMARY KEY (`hb_product_id`)
    ) " . $engine);
}

// HostBill product -> Perfex stock item. units per HostBill unit = qty_multiplier.
if (! $CI->db->table_exists($p . 'ams_hb_product_map')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_hb_product_map` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `hb_product_id` INT(11) NOT NULL,
        `qty_multiplier` DECIMAL(15,2) NOT NULL DEFAULT 1,
        `location_id` INT(11) NULL,
        `push_stock` TINYINT(1) NOT NULL DEFAULT 1,
        `push_pending` TINYINT(1) NOT NULL DEFAULT 1,
        `last_pushed_qty` DECIMAL(15,2) NULL,
        `last_pushed_at` DATETIME NULL,
        `last_push_error` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `hb_product_id` (`hb_product_id`),
        KEY `item_id` (`item_id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_hb_orders')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_hb_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `hb_order_id` INT(11) NOT NULL,
        `order_number` VARCHAR(60) NULL,
        `hb_client_id` INT(11) NULL,
        `client_name` VARCHAR(191) NULL,
        `client_email` VARCHAR(191) NULL,
        `perfex_client_id` INT(11) NULL,
        `status` VARCHAR(30) NULL,
        `invoice_status` VARCHAR(30) NULL,
        `balance` VARCHAR(30) NULL,
        `total` DECIMAL(15,2) NULL,
        `currency_id` INT(11) NULL,
        `order_date` DATETIME NULL,
        `has_mapped` TINYINT(1) NOT NULL DEFAULT 0,
        `needs_attention` TINYINT(1) NOT NULL DEFAULT 0,
        `fulfilment` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `fulfilment_note` TEXT NULL,
        `fulfilled_by` INT(11) NULL,
        `fulfilled_at` DATETIME NULL,
        `raw_json` LONGTEXT NULL,
        `last_synced_at` DATETIME NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `hb_order_id` (`hb_order_id`),
        KEY `status` (`status`),
        KEY `perfex_client_id` (`perfex_client_id`),
        KEY `order_date` (`order_date`)
    ) " . $engine);
}

// One row per HostBill order line. state = what Perfex currently holds for it:
// none | reserved | deducted | released | returned | short | unmapped | ignored
if (! $CI->db->table_exists($p . 'ams_hb_order_lines')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_hb_order_lines` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `hb_order_id` INT(11) NOT NULL,
        `line_key` VARCHAR(60) NOT NULL,
        `hb_product_id` INT(11) NULL,
        `product_name` VARCHAR(191) NULL,
        `hb_qty` DECIMAL(15,2) NOT NULL DEFAULT 1,
        `item_id` INT(11) NULL,
        `units` DECIMAL(15,2) NULL,
        `location_id` INT(11) NULL,
        `state` VARCHAR(20) NOT NULL DEFAULT 'none',
        `message` VARCHAR(255) NULL,
        `date_updated` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `order_line` (`hb_order_id`, `line_key`),
        KEY `item_id` (`item_id`),
        KEY `state` (`state`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_hb_sync_log')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_hb_sync_log` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `direction` VARCHAR(20) NOT NULL,
        `api_call` VARCHAR(60) NULL,
        `request` TEXT NULL,
        `response` MEDIUMTEXT NULL,
        `http_code` INT(11) NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `error` TEXT NULL,
        `duration_ms` INT(11) NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `direction` (`direction`),
        KEY `success` (`success`),
        KEY `date_created` (`date_created`)
    ) " . $engine);
}

// ─── People workflows (schema v4) ─────────────────────────────────────────

// Category-level acceptance terms (used when acceptance mode = per category).
if (! $CI->db->field_exists('require_acceptance', $p . 'ams_categories')) {
    $CI->db->query('ALTER TABLE `' . $p . 'ams_categories` ADD `require_acceptance` TINYINT(1) NOT NULL DEFAULT 0, ADD `eula_text` TEXT NULL');
}
if (! $CI->db->field_exists('overdue_notified_at', $p . 'ams_assets')) {
    $CI->db->query('ALTER TABLE `' . $p . 'ams_assets` ADD `overdue_notified_at` DATETIME NULL');
}
if (! $CI->db->field_exists('overdue_notified_at', $p . 'ams_item_checkouts')) {
    $CI->db->query('ALTER TABLE `' . $p . 'ams_item_checkouts` ADD `overdue_notified_at` DATETIME NULL');
}

// Staff acknowledgement of assets / accessories checked out to them.
// rel_type: asset | accessory ; status: pending | accepted | declined | cancelled
if (! $CI->db->table_exists($p . 'ams_acceptances')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_acceptances` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `rel_type` VARCHAR(20) NOT NULL,
        `rel_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `terms` TEXT NULL,
        `signature_file` VARCHAR(191) NULL,
        `signed_name` VARCHAR(191) NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` VARCHAR(255) NULL,
        `note` TEXT NULL,
        `requested_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        `date_responded` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `rel` (`rel_type`, `rel_id`),
        KEY `staff_status` (`staff_id`, `status`)
    ) " . $engine);
}

// Asset requests and issue reports. type: asset | accessory | consumable | issue
// status: pending_dept | pending_manager | approved | rejected | fulfilled | cancelled
if (! $CI->db->table_exists($p . 'ams_requests')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `request_no` VARCHAR(30) NULL,
        `staff_id` INT(11) NOT NULL,
        `department_id` INT(11) NULL,
        `type` VARCHAR(20) NOT NULL,
        `category_id` INT(11) NULL,
        `item_id` INT(11) NULL,
        `asset_id` INT(11) NULL,
        `qty` DECIMAL(15,2) NOT NULL DEFAULT 1,
        `subject` VARCHAR(191) NOT NULL,
        `description` TEXT NULL,
        `priority` VARCHAR(10) NOT NULL DEFAULT 'normal',
        `needed_by` DATE NULL,
        `status` VARCHAR(20) NOT NULL,
        `dept_approver_id` INT(11) NULL,
        `dept_decision_at` DATETIME NULL,
        `dept_note` TEXT NULL,
        `approver_id` INT(11) NULL,
        `decision_at` DATETIME NULL,
        `decision_note` TEXT NULL,
        `fulfilled_by` INT(11) NULL,
        `fulfilled_at` DATETIME NULL,
        `fulfilment_note` TEXT NULL,
        `fulfilled_asset_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `staff_id` (`staff_id`),
        KEY `department_id` (`department_id`),
        KEY `status` (`status`),
        KEY `type` (`type`)
    ) " . $engine);
}

// Perfex department -> staff who approve requests from that department.
if (! $CI->db->table_exists($p . 'ams_department_approvers')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_department_approvers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `department_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `dept_staff` (`department_id`, `staff_id`),
        KEY `staff_id` (`staff_id`)
    ) " . $engine);
}

// ─── Maintenance, licences, procurement (schema v5) ───────────────────────

// type: repair | upgrade | preventive | inspection | software | other
// status: scheduled | in_progress | completed | cancelled
if (! $CI->db->table_exists($p . 'ams_maintenance')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_maintenance` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_id` INT(11) NOT NULL,
        `schedule_id` INT(11) NULL,
        `request_id` INT(11) NULL,
        `type` VARCHAR(20) NOT NULL DEFAULT 'repair',
        `title` VARCHAR(191) NOT NULL,
        `supplier_id` INT(11) NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'scheduled',
        `due_date` DATE NULL,
        `start_date` DATE NULL,
        `end_date` DATE NULL,
        `cost` DECIMAL(15,2) NULL,
        `downtime_hours` DECIMAL(10,2) NULL,
        `status_before` INT(11) NULL,
        `notes` TEXT NULL,
        `resolution` TEXT NULL,
        `created_by` INT(11) NULL,
        `completed_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        `date_completed` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `asset_id` (`asset_id`),
        KEY `status` (`status`),
        KEY `schedule_id` (`schedule_id`)
    ) " . $engine);
}

// Preventive maintenance: every N day/week/month/year per asset.
if (! $CI->db->table_exists($p . 'ams_maintenance_schedules')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_maintenance_schedules` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_id` INT(11) NOT NULL,
        `title` VARCHAR(191) NOT NULL,
        `type` VARCHAR(20) NOT NULL DEFAULT 'preventive',
        `interval_value` INT(11) NOT NULL DEFAULT 1,
        `interval_unit` VARCHAR(10) NOT NULL DEFAULT 'month',
        `next_due` DATE NOT NULL,
        `supplier_id` INT(11) NULL,
        `last_done` DATE NULL,
        `reminded_for` DATE NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `asset_id` (`asset_id`),
        KEY `next_due` (`next_due`)
    ) " . $engine);
}

// Software licences; the key is stored encrypted.
if (! $CI->db->table_exists($p . 'ams_licenses')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_licenses` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `brand_id` INT(11) NULL,
        `category_id` INT(11) NULL,
        `license_type` VARCHAR(20) NOT NULL DEFAULT 'subscription',
        `license_key` TEXT NULL,
        `seats` INT(11) NOT NULL DEFAULT 1,
        `licensed_to` VARCHAR(191) NULL,
        `supplier_id` INT(11) NULL,
        `order_no` VARCHAR(100) NULL,
        `purchase_date` DATE NULL,
        `purchase_cost` DECIMAL(15,2) NULL,
        `expiry_date` DATE NULL,
        `renewal_cost` DECIMAL(15,2) NULL,
        `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
        `reminded_for` DATE NULL,
        `notes` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `expiry_date` (`expiry_date`)
    ) " . $engine);
}

// A seat is active while released_at IS NULL. assigned_type: staff | asset
if (! $CI->db->table_exists($p . 'ams_license_seats')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_license_seats` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `license_id` INT(11) NOT NULL,
        `assigned_type` VARCHAR(20) NOT NULL,
        `assigned_id` INT(11) NOT NULL,
        `note` VARCHAR(255) NULL,
        `assigned_by` INT(11) NULL,
        `assigned_at` DATETIME NOT NULL,
        `released_by` INT(11) NULL,
        `released_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `license_id` (`license_id`),
        KEY `assigned` (`assigned_type`, `assigned_id`)
    ) " . $engine);
}

// status: draft | pending_approval | approved | rejected | sent | partially_received | received | cancelled
if (! $CI->db->table_exists($p . 'ams_purchase_orders')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_purchase_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `po_number` VARCHAR(30) NULL,
        `supplier_id` INT(11) NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `order_date` DATE NULL,
        `expected_date` DATE NULL,
        `delivery_location_id` INT(11) NULL,
        `request_id` INT(11) NULL,
        `notes` TEXT NULL,
        `terms` TEXT NULL,
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `created_by` INT(11) NULL,
        `submitted_by` INT(11) NULL,
        `submitted_at` DATETIME NULL,
        `approved_by` INT(11) NULL,
        `approved_at` DATETIME NULL,
        `decision_note` TEXT NULL,
        `sent_at` DATETIME NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `supplier_id` (`supplier_id`),
        KEY `status` (`status`)
    ) " . $engine);
}

// line_type: asset (serialized, one asset per unit) | item (stock quantity)
if (! $CI->db->table_exists($p . 'ams_po_lines')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_po_lines` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `po_id` INT(11) NOT NULL,
        `line_type` VARCHAR(10) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `category_id` INT(11) NULL,
        `brand_id` INT(11) NULL,
        `model_id` INT(11) NULL,
        `item_id` INT(11) NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `unit_cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `po_id` (`po_id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_goods_receipts')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_goods_receipts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `po_id` INT(11) NOT NULL,
        `receipt_date` DATE NOT NULL,
        `location_id` INT(11) NOT NULL,
        `invoice_no` VARCHAR(100) NULL,
        `note` TEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `po_id` (`po_id`)
    ) " . $engine);
}

if (! $CI->db->table_exists($p . 'ams_goods_receipt_lines')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_goods_receipt_lines` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `receipt_id` INT(11) NOT NULL,
        `po_line_id` INT(11) NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `asset_ids` TEXT NULL,
        PRIMARY KEY (`id`),
        KEY `receipt_id` (`receipt_id`)
    ) " . $engine);
}

// ─── Finance, audits, disposal (schema v6) ────────────────────────────────

// Depreciation defaults per category (sub-categories without a method inherit the parent's).
// depreciation_method: NULL/none | straight_line | declining_balance
if (! $CI->db->field_exists('depreciation_method', $p . 'ams_categories')) {
    $CI->db->query('ALTER TABLE `' . $p . 'ams_categories` ADD `depreciation_method` VARCHAR(20) NULL, ADD `useful_life_months` INT(11) NULL, ADD `salvage_percent` DECIMAL(5,2) NULL');
}
// Per-asset override (NULL = category default) + stored book values for sortable/filterable reports.
if (! $CI->db->field_exists('depreciation_method', $p . 'ams_assets')) {
    $CI->db->query('ALTER TABLE `' . $p . 'ams_assets`
        ADD `depreciation_method` VARCHAR(20) NULL,
        ADD `useful_life_months` INT(11) NULL,
        ADD `salvage_value` DECIMAL(15,2) NULL,
        ADD `dep_accumulated` DECIMAL(15,2) NULL,
        ADD `dep_book_value` DECIMAL(15,2) NULL,
        ADD `dep_calculated_at` DATE NULL,
        ADD `last_audit_date` DATE NULL');
}

// method: sold | donated | scrapped | recycled | lost | stolen | returned
if (! $CI->db->table_exists($p . 'ams_disposals')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_disposals` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `asset_id` INT(11) NOT NULL,
        `method` VARCHAR(20) NOT NULL,
        `disposal_date` DATE NOT NULL,
        `status_id` INT(11) NULL,
        `status_before` INT(11) NULL,
        `proceeds` DECIMAL(15,2) NULL,
        `currency` INT(11) NULL,
        `book_value` DECIMAL(15,2) NULL,
        `gain_loss` DECIMAL(15,2) NULL,
        `recipient` VARCHAR(191) NULL,
        `reference` VARCHAR(100) NULL,
        `reason` TEXT NULL,
        `staff_id` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `asset_id` (`asset_id`),
        KEY `method` (`method`),
        KEY `disposal_date` (`disposal_date`)
    ) " . $engine);
}

// Physical audit campaigns. status: draft | in_progress | completed | cancelled
if (! $CI->db->table_exists($p . 'ams_audits')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_audits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `audit_no` VARCHAR(30) NULL,
        `title` VARCHAR(191) NOT NULL,
        `location_id` INT(11) NULL,
        `department_id` INT(11) NULL,
        `category_id` INT(11) NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `due_date` DATE NULL,
        `next_audit_date` DATE NULL,
        `notes` TEXT NULL,
        `started_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        `created_by` INT(11) NULL,
        `completed_by` INT(11) NULL,
        `date_created` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `status` (`status`)
    ) " . $engine);
}

// result: pending | found | misplaced | unexpected | missing
if (! $CI->db->table_exists($p . 'ams_audit_lines')) {
    $CI->db->query('CREATE TABLE `' . $p . "ams_audit_lines` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `audit_id` INT(11) NOT NULL,
        `asset_id` INT(11) NOT NULL,
        `expected_location_id` INT(11) NULL,
        `found_location_id` INT(11) NULL,
        `result` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `asset_condition` VARCHAR(20) NULL,
        `note` VARCHAR(255) NULL,
        `scanned_by` INT(11) NULL,
        `scanned_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `audit_asset` (`audit_id`, `asset_id`),
        KEY `result` (`result`)
    ) " . $engine);
}

// Email templates (Setup → Email Templates → Asset Management). Idempotent by slug.
$amsTemplates = [
    ['ams-asset-assigned', 'Asset assigned to staff', 'Asset assigned to you: {ams_item}',
        '<p>Hi {staff_firstname},</p><p><strong>{ams_item}</strong> has been assigned to you by {ams_by}.</p><p>{ams_details}</p><p>{ams_action_text}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-acceptance-declined', 'Asset acceptance declined (to managers)', '{ams_staff_name} declined {ams_item}',
        '<p>{ams_staff_name} declined <strong>{ams_item}</strong>.</p><p>Reason: {ams_details}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-request-submitted', 'Asset request waiting for approval', 'Asset request {ams_request_no} needs your approval',
        '<p>{ams_staff_name} submitted request <strong>{ams_request_no}</strong>: {ams_item}</p><p>{ams_details}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-request-updated', 'Asset request updated (to requester)', 'Your request {ams_request_no} is {ams_status}',
        '<p>Hi {staff_firstname},</p><p>Your request <strong>{ams_request_no}</strong> ({ams_item}) is now <strong>{ams_status}</strong>.</p><p>{ams_details}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-overdue-return', 'Overdue asset return reminder', 'Please return {ams_item}',
        '<p>Hi {staff_firstname},</p><p><strong>{ams_item}</strong> was due back on {ams_due_date}. Please return it or contact the asset manager.</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-low-stock', 'Low / out-of-stock alert', 'Stock alert: {ams_item}',
        '<p>{ams_item} is <strong>{ams_status}</strong>: {ams_details} available.</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-system-alert', 'HostBill sync / push failure alert', 'Asset Management alert: {ams_status}',
        '<p>{ams_details}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-purchase-order', 'Purchase order (to supplier)', 'Purchase Order {ams_po_number} from {ams_company}',
        '<p>Dear {ams_supplier},</p><p>Please find attached our purchase order <strong>{ams_po_number}</strong>.</p><p>{ams_details}</p><p>Kind regards,<br>{ams_company}</p>'],
    ['ams-maintenance-due', 'Maintenance due (to asset managers)', 'Maintenance due: {ams_item}',
        '<p>{ams_details} is due on {ams_due_date} for <strong>{ams_item}</strong>.</p><p><a href="{ams_link}">{ams_link}</a></p>'],
    ['ams-license-expiring', 'Licence expiring (to asset managers)', 'Licence expiring: {ams_item}',
        '<p>The licence <strong>{ams_item}</strong> expires on {ams_due_date}. {ams_details}</p><p><a href="{ams_link}">{ams_link}</a></p>'],
];
foreach ($amsTemplates as $tpl) {
    create_email_template($tpl[2], $tpl[3], 'ams', $tpl[1], $tpl[0]);
}

// ─── Seed statuses (legacy set + retired) ─────────────────────────────────

if ($CI->db->count_all($p . 'ams_statuses') == 0) {
    $now      = date('Y-m-d H:i:s');
    $statuses = [
        ['In Store',          '#22c55e', 'deployable',   'in_store', 1, 0],
        ['Assigned',          '#3b82f6', 'deployed',     'assigned', 0, 0],
        ['Maintenance',       '#f59e0b', 'pending',      null,       0, 1],
        ['Damaged',           '#ef4444', 'undeployable', null,       0, 1],
        ['Stolen / Lost',     '#7f1d1d', 'archived',     null,       0, 1],
        ['Sold',              '#64748b', 'archived',     null,       0, 1],
        ['Donated',           '#8b5cf6', 'archived',     null,       0, 1],
        ['Retired / Disposed', '#475569', 'archived',    null,       0, 1],
    ];

    foreach ($statuses as $i => $s) {
        $CI->db->insert($p . 'ams_statuses', [
            'name'              => $s[0],
            'color'             => $s[1],
            'type'              => $s[2],
            'system_key'        => $s[3],
            'requires_location' => $s[4],
            'requires_note'     => $s[5],
            'active'            => 1,
            'sort_order'        => $i + 1,
            'date_created'      => $now,
        ]);
    }
}

// ─── Options ──────────────────────────────────────────────────────────────

add_option('ams_asset_tag_prefix', 'ANB');
add_option('ams_asset_tag_digits', '5');
add_option('ams_asset_tag_separator', '-');
add_option('ams_default_category_code', 'GEN');
add_option('ams_warranty_expiring_days', '30');
add_option('ams_max_upload_mb', '10');
add_option('ams_item_sku_prefix', 'ITM');
add_option('ams_block_negative_stock', '1');
add_option('ams_low_stock_notify_staff', '[]');

// HostBill (all editable in Setup → Settings → Asset Management → HostBill)
add_option('ams_hb_enabled', '0');
add_option('ams_hb_url', '');
add_option('ams_hb_api_id', '');
add_option('ams_hb_api_key', '');
add_option('ams_hb_verify_ssl', '1');
add_option('ams_hb_timeout', '20');
add_option('ams_hb_sync_enabled', '1');
add_option('ams_hb_sync_interval', '10');
add_option('ams_hb_lookback_days', '14');
add_option('ams_hb_max_pages', '10');
add_option('ams_hb_reserve_on_pending', '1');
add_option('ams_hb_deduct_on', 'paid');
add_option('ams_hb_release_on_refund', '1');
add_option('ams_hb_restock_on_cancel', '1');
add_option('ams_hb_sales_location_id', '');
add_option('ams_hb_push_enabled', '1');
add_option('ams_hb_push_immediately', '1');
add_option('ams_hb_stock_buffer', '0');
add_option('ams_hb_webhook_enabled', '0');
add_option('ams_hb_webhook_secret', bin2hex(random_bytes(20)));
add_option('ams_hb_webhook_ips', '');
add_option('ams_hb_alert_staff', '[]');
add_option('ams_hb_log_retention_days', '30');
add_option('ams_hb_last_sync', '');
add_option('ams_hb_last_sync_status', '');

// People workflows
add_option('ams_acceptance_mode', 'always');
add_option('ams_acceptance_terms', "I confirm that I have received the item(s) listed above in the stated condition. I will use them for company work only, take reasonable care of them, report any damage, loss or theft immediately, and return them when asked or when I leave the company.");
add_option('ams_email_notifications', '1');
add_option('ams_overdue_reminder_days', '3');
add_option('ams_manager_notify_staff', '[]');
add_option('ams_block_staff_deactivation', '0');
add_option('ams_request_prefix', 'REQ');

// Maintenance, licences, procurement
add_option('ams_maintenance_lead_days', '7');
add_option('ams_license_reminder_days', '30');
add_option('ams_po_prefix', 'PO');
add_option('ams_po_require_approval', '1');
add_option('ams_po_terms', 'Please deliver the goods to the address above and quote the purchase order number on your invoice and delivery challan.');

// Finance, labels, audits
add_option('ams_declining_factor', '2');
add_option('ams_label_width', '50');
add_option('ams_label_height', '25');
add_option('ams_label_layout', 'single');
add_option('ams_label_code', 'qr');
add_option('ams_label_qr_content', 'url');
add_option('ams_label_show_company', '1');
add_option('ams_label_show_name', '1');
add_option('ams_label_show_serial', '0');
add_option('ams_label_show_logo', '0');
add_option('ams_label_company_text', '');
add_option('ams_audit_prefix', 'AUD');
add_option('ams_last_dep_run', '0');

// ─── Protected upload folder ──────────────────────────────────────────────

if (! is_dir(AMS_UPLOAD_PATH)) {
    mkdir(AMS_UPLOAD_PATH, 0755, true);
}
if (! file_exists(AMS_UPLOAD_PATH . '.htaccess')) {
    file_put_contents(AMS_UPLOAD_PATH . '.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n");
}
if (! file_exists(AMS_UPLOAD_PATH . 'index.html')) {
    file_put_contents(AMS_UPLOAD_PATH . 'index.html', '');
}
