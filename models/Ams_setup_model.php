<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Generic CRUD for the master-data entities defined in ams_setup_entities().
 */
class Ams_setup_model extends App_Model
{
    public function entity($entity)
    {
        $entities = ams_setup_entities();

        return $entities[$entity] ?? null;
    }

    public function get($entity, $id)
    {
        $cfg = $this->entity($entity);

        return $cfg ? $this->db->where('id', (int) $id)->get(db_prefix() . $cfg['table'])->row_array() : null;
    }

    /**
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public function save($entity, $input, $id = null)
    {
        $cfg = $this->entity($entity);
        if (! $cfg) {
            return ['success' => false, 'message' => _l('ams_invalid_request')];
        }

        $data = [];
        foreach ($cfg['fields'] as $field => $def) {
            if ($def['type'] === 'checkbox') {
                $data[$field] = ! empty($input[$field]) ? 1 : 0;
                continue;
            }

            $value = isset($input[$field]) ? trim((string) $input[$field]) : '';

            if (! empty($def['required']) && $value === '') {
                return ['success' => false, 'message' => _l('ams_field_required', _l($def['label']))];
            }

            if (in_array($def['type'], ['select', 'staff', 'number'])) {
                $data[$field] = $value === '' ? (in_array($field, ['parent_id', 'sort_order']) ? 0 : null) : $value;
            } else {
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if ($error = $this->validate($entity, $cfg, $data, $id)) {
            return ['success' => false, 'message' => $error];
        }

        if (isset($data['parent_id']) && $id && (int) $data['parent_id'] === (int) $id) {
            return ['success' => false, 'message' => _l('ams_parent_self')];
        }

        // Only one level of nesting: a parent must itself be top-level.
        if (! empty($data['parent_id'])) {
            $parent = $this->get($entity, $data['parent_id']);
            if (! $parent || (int) $parent['parent_id'] !== 0) {
                return ['success' => false, 'message' => _l('ams_parent_must_be_top')];
            }
            if ($id && total_rows(db_prefix() . $cfg['table'], ['parent_id' => $id]) > 0) {
                return ['success' => false, 'message' => _l('ams_has_children_cannot_nest')];
            }
        }

        if ($entity === 'categories' && isset($data['code'])) {
            $data['code'] = $data['code'] ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code'])) : null;
        }

        if ($entity === 'categories') {
            if (! in_array($data['depreciation_method'], ['straight_line', 'declining_balance'], true)) {
                $data['depreciation_method'] = null;
            }
            $life = $data['useful_life_months'];
            if ($data['depreciation_method'] && (! is_numeric($life) || (int) $life < 1)) {
                return ['success' => false, 'message' => _l('ams_dep_life_required')];
            }
            $data['useful_life_months'] = is_numeric($life) && (int) $life > 0 ? (int) $life : null;
            $salvage = $data['salvage_percent'];
            if ($salvage !== null && (! is_numeric($salvage) || (float) $salvage < 0 || (float) $salvage >= 100)) {
                return ['success' => false, 'message' => _l('ams_dep_salvage_invalid')];
            }
            $data['salvage_percent'] = $salvage !== null ? round((float) $salvage, 2) : null;
        }

        $table = db_prefix() . $cfg['table'];

        if ($entity === 'statuses' && $id) {
            $current = $this->get('statuses', $id);
            // System statuses keep their type so check-out/check-in keep working.
            if ($current && $current['system_key']) {
                $data['type']   = $current['type'];
                $data['active'] = 1;
            }
        }

        if ($id) {
            $old = $this->get($entity, $id);
            if (! $old) {
                return ['success' => false, 'message' => _l('ams_not_found')];
            }
            $this->db->where('id', (int) $id)->update($table, $data);
            $this->log_changes($cfg['table'], $id, $old, $data);

            hooks()->do_action('ams_setup_saved', ['entity' => $entity, 'id' => (int) $id]);

            return ['success' => true, 'message' => _l('updated_successfully', _l($cfg['singular'])), 'id' => (int) $id];
        }

        $data['created_by']   = get_staff_user_id();
        $data['date_created'] = date('Y-m-d H:i:s');
        $this->db->insert($table, $data);
        $newId = (int) $this->db->insert_id();

        $this->audit($cfg['table'], $newId, 'create', null);
        log_activity('AMS ' . _l($cfg['singular']) . ' created [ID: ' . $newId . ', ' . ($data['name'] ?? '') . ']');

        hooks()->do_action('ams_setup_saved', ['entity' => $entity, 'id' => $newId]);

        return ['success' => true, 'message' => _l('added_successfully', _l($cfg['singular'])), 'id' => $newId];
    }

    /**
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete($entity, $id)
    {
        $cfg = $this->entity($entity);
        $row = $cfg ? $this->get($entity, $id) : null;

        if (! $row) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        if ($entity === 'statuses' && $row['system_key']) {
            return ['success' => false, 'message' => _l('ams_status_system_cannot_delete')];
        }

        // Where the record is still used, named in the refusal message.
        $labels = [
            'ams_assets' => 'ams_assets', 'ams_categories' => 'ams_categories', 'ams_models' => 'ams_models',
            'ams_licenses' => 'ams_licenses', 'ams_po_lines' => 'ams_purchase_orders',
            'ams_purchase_orders' => 'ams_purchase_orders', 'ams_goods_receipts' => 'ams_purchase_orders',
            'ams_requests' => 'ams_requests', 'ams_audits' => 'ams_audits', 'ams_audit_lines' => 'ams_audits',
            'ams_maintenance' => 'ams_maintenance', 'ams_maintenance_schedules' => 'ams_maintenance',
            'ams_disposals' => 'ams_disposal_register',
            'ams_locations' => 'ams_locations',
        ];
        $usedIn = [];
        foreach ($cfg['in_use'] as [$table, $column]) {
            $where = [$column => (int) $id];
            // Deleted assets keep their history, so statuses and categories they point to must stay.
            if ($table === 'ams_assets' && ! in_array($entity, ['statuses', 'categories'], true)) {
                $where['is_deleted'] = 0;
            }
            if (total_rows(db_prefix() . $table, $where) > 0) {
                $usedIn[] = _l($labels[$table] ?? $table);
            }
        }
        if ($usedIn) {
            return ['success' => false, 'message' => _l('ams_in_use_by', [_l($cfg['singular']), implode(', ', array_unique($usedIn))])];
        }

        $this->db->where('id', (int) $id)->delete(db_prefix() . $cfg['table']);
        $this->audit($cfg['table'], $id, 'delete', ['name' => [$row['name'] ?? '', null]]);
        log_activity('AMS ' . _l($cfg['singular']) . ' deleted [ID: ' . $id . ', ' . ($row['name'] ?? '') . ']');

        return ['success' => true, 'message' => _l('deleted', _l($cfg['singular']))];
    }

    /**
     * Server-side checks the form alone can't guarantee: select values must be real
     * options, colours real colours, names / codes unique, lengths within the columns.
     * Returns an error message or null.
     */
    private function validate($entity, $cfg, &$data, $id)
    {
        foreach ($cfg['fields'] as $field => $def) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '' || $field === 'parent_id') {
                continue;
            }
            if ($def['type'] === 'select' && ! empty($def['options']) && is_callable($def['options']) && $field !== 'depreciation_method') {
                $allowed = array_map('strval', array_column(call_user_func($def['options']), 'id'));
                if (! in_array((string) $value, $allowed, true)) {
                    return _l('ams_invalid_value', _l($def['label']));
                }
            }
            if ($def['type'] === 'staff' && total_rows(db_prefix() . 'staff', ['staffid' => (int) $value, 'active' => 1]) === 0) {
                return _l('ams_invalid_value', _l($def['label']));
            }
            if ($def['type'] === 'number' && ! is_numeric($value)) {
                return _l('ams_invalid_value', _l($def['label']));
            }
            if ($def['type'] === 'icon' && ! ams_valid_icon($value)) {
                return _l('ams_invalid_value', _l($def['label']));
            }
            if ($def['type'] === 'color' && ! preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', (string) $value)) {
                return _l('ams_invalid_value', _l($def['label']));
            }
            if (in_array($def['type'], ['text'], true) && mb_strlen((string) $value) > ($field === 'code' ? 20 : 191)) {
                return _l('ams_value_too_long', _l($def['label']));
            }
        }

        if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return _l('ams_invalid_value', _l('ams_email'));
        }

        $table = db_prefix() . $cfg['table'];

        // Names are unique (within the same parent for categories / locations).
        if (! empty($data['name'])) {
            $this->db->where('name', $data['name'])->where('id !=', (int) $id);
            if (array_key_exists('parent_id', $cfg['fields'])) {
                $this->db->where('parent_id', (int) ($data['parent_id'] ?? 0));
            }
            if ($entity === 'models') {
                $this->db->where('brand_id', $data['brand_id'] ?? null);
            }
            if ($this->db->count_all_results($table) > 0) {
                return _l('ams_name_exists', e($data['name']));
            }
        }

        // Category codes feed asset tags (one sequence per code), so they must be unique.
        if ($entity === 'categories' && ! empty($data['code'])) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code']));
            if ($code !== '' && total_rows($table, ['code' => $code, 'id !=' => (int) $id]) > 0) {
                return _l('ams_category_code_exists', e($code));
            }
        }

        // A status's type drives check-out / check-in: don't change it under assets using it.
        if ($entity === 'statuses' && $id && isset($data['type'])) {
            $current = $this->get('statuses', $id);
            if ($current && ! $current['system_key'] && $current['type'] !== $data['type']
                && total_rows(db_prefix() . 'ams_assets', ['status_id' => (int) $id, 'is_deleted' => 0]) > 0) {
                return _l('ams_status_type_in_use');
            }
        }

        return null;
    }

    private function log_changes($relType, $id, $old, $new)
    {
        $changes = [];
        foreach ($new as $field => $value) {
            if ((string) ($old[$field] ?? '') !== (string) ($value ?? '')) {
                $changes[$field] = [$old[$field] ?? null, $value];
            }
        }
        if ($changes) {
            $this->audit($relType, $id, 'update', $changes);
        }
    }

    public function audit($relType, $relId, $action, $changes)
    {
        $this->db->insert(db_prefix() . 'ams_audit_log', [
            'rel_type'     => $relType,
            'rel_id'       => (int) $relId,
            'action'       => $action,
            'changes'      => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
    }
}
