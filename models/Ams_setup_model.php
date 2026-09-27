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

        foreach ($cfg['in_use'] as [$table, $column]) {
            $where = [$column => (int) $id];
            if ($table === 'ams_assets') {
                $where['is_deleted'] = 0;
            }
            if (total_rows(db_prefix() . $table, $where) > 0) {
                return ['success' => false, 'message' => _l('is_referenced', _l($cfg['singular']))];
            }
        }

        $this->db->where('id', (int) $id)->delete(db_prefix() . $cfg['table']);
        $this->audit($cfg['table'], $id, 'delete', ['name' => [$row['name'] ?? '', null]]);
        log_activity('AMS ' . _l($cfg['singular']) . ' deleted [ID: ' . $id . ', ' . ($row['name'] ?? '') . ']');

        return ['success' => true, 'message' => _l('deleted', _l($cfg['singular']))];
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
