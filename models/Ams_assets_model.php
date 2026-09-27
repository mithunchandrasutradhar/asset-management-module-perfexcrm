<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ams_assets_model extends App_Model
{
    /** Fields editable from the asset form (status / assignment / location change only through actions). */
    private $editable = [
        'asset_tag', 'serial_no', 'name', 'category_id', 'brand_id', 'model_id', 'supplier_id',
        'asset_condition', 'source', 'gifted_by_type', 'gifted_by_id', 'gifted_by_name',
        'purchased_by', 'purchase_date', 'purchase_cost', 'currency', 'invoice_no', 'order_no',
        'lease_end_date', 'warranty_start', 'warranty_end', 'warranty_provider', 'warranty_notes',
        'department_id', 'notes', 'depreciation_method', 'useful_life_months', 'salvage_value',
    ];

    private $dateFields = ['purchase_date', 'lease_end_date', 'warranty_start', 'warranty_end', 'expected_checkin'];

    private $intFields = ['category_id', 'brand_id', 'model_id', 'supplier_id', 'gifted_by_id', 'purchased_by', 'currency', 'department_id'];

    private function t($table)
    {
        return db_prefix() . $table;
    }

    // ─── Read ─────────────────────────────────────────────────────────────

    public function get($id, $includeDeleted = false)
    {
        $p = db_prefix();
        $this->db->select('a.*, c.name as category_name, pc.name as parent_category_name, b.name as brand_name,
            m.name as model_name, m.model_no, sp.name as supplier_name, st.name as status_name,
            st.color as status_color, st.type as status_type, st.system_key as status_key,
            d.name as department_name, cur.name as currency_name');
        $this->db->from($p . 'ams_assets a');
        $this->db->join($p . 'ams_categories c', 'c.id = a.category_id', 'left');
        $this->db->join($p . 'ams_categories pc', 'pc.id = c.parent_id', 'left');
        $this->db->join($p . 'ams_brands b', 'b.id = a.brand_id', 'left');
        $this->db->join($p . 'ams_models m', 'm.id = a.model_id', 'left');
        $this->db->join($p . 'ams_suppliers sp', 'sp.id = a.supplier_id', 'left');
        $this->db->join($p . 'ams_statuses st', 'st.id = a.status_id', 'left');
        $this->db->join($p . 'departments d', 'd.departmentid = a.department_id', 'left');
        $this->db->join($p . 'currencies cur', 'cur.id = a.currency', 'left');
        $this->db->where('a.id', (int) $id);
        if (! $includeDeleted) {
            $this->db->where('a.is_deleted', 0);
        }

        $asset = $this->db->get()->row();

        if ($asset) {
            $asset->location_name = ams_location_name($asset->location_id);
            $asset->custom_fields = get_custom_fields('ams_assets');
        }

        return $asset;
    }

    /**
     * Same checks as add() / update() without writing (import preview).
     * @return string|null error message
     */
    public function validate($input, $id = null)
    {
        $data = $this->prepare($input);
        if (isset($data['error'])) {
            return $data['error'];
        }

        $tag = trim($input['asset_tag'] ?? '');
        if ($id && $tag === '') {
            return _l('ams_field_required', _l('ams_asset_tag'));
        }
        if ($tag !== '' && $this->tag_exists($tag, $id)) {
            return _l('ams_tag_exists', e($tag));
        }
        if ($id) {
            return null;
        }

        $assignType = $input['assign_type'] ?? '';
        $assignId   = (int) ($input['assign_id_' . $assignType] ?? 0);
        if ($assignType === 'staff' && total_rows(db_prefix() . 'staff', ['staffid' => $assignId, 'active' => 1]) === 0) {
            return _l('ams_select_assignee');
        }
        if ($assignType === 'staff' && $assignId) {
            return null; // created In Store, then checked out
        }

        $status = ams_get_status($input['status_id'] ?? 0);
        if (! $status || ! $status['active'] || $status['type'] === 'deployed') {
            return _l('ams_select_initial_status');
        }
        if ($status['requires_location'] && empty($input['location_id'])) {
            return _l('ams_status_requires_location', e($status['name']));
        }
        if ($status['requires_note'] && trim($input['initial_note'] ?? '') === '') {
            return _l('ams_status_requires_note', e($status['name']));
        }
        if ($status['type'] === 'archived' && staff_cant('dispose', 'ams_assets')) {
            return _l('ams_no_dispose_permission');
        }

        return null;
    }

    public function tag_exists($tag, $exceptId = null)
    {
        $this->db->where('asset_tag', $tag);
        if ($exceptId) {
            $this->db->where('id !=', (int) $exceptId);
        }

        return $this->db->count_all_results($this->t('ams_assets')) > 0;
    }

    // ─── Create / update ──────────────────────────────────────────────────

    /**
     * $input: form post. Initial state: status_id + location_id, or assign_type/assign_id
     * to check the asset out immediately.
     *
     * @return array ['success' => bool, 'message' => string, 'id' => int]
     */
    public function add($input)
    {
        $data = $this->prepare($input);
        if (isset($data['error'])) {
            return ['success' => false, 'message' => $data['error']];
        }

        $assignType = $input['assign_type'] ?? '';
        $assignId   = (int) ($input['assign_id_' . $assignType] ?? 0);
        $assignNow  = in_array($assignType, ['staff', 'department', 'location']) && $assignId > 0;

        $status = ams_get_status($input['status_id'] ?? 0);
        if ($assignNow) {
            $status = ams_get_status_by_key('in_store') ?: $status;
        }
        if (! $status || ! $status['active'] || $status['type'] === 'deployed') {
            return ['success' => false, 'message' => _l('ams_select_initial_status')];
        }

        $locationId = (int) ($input['location_id'] ?? 0) ?: null;
        $note       = trim($input['initial_note'] ?? '');

        if (! $assignNow) {
            if ($status['requires_location'] && ! $locationId) {
                return ['success' => false, 'message' => _l('ams_status_requires_location', e($status['name']))];
            }
            if ($status['requires_note'] && $note === '') {
                return ['success' => false, 'message' => _l('ams_status_requires_note', e($status['name']))];
            }
            if ($status['type'] === 'archived' && staff_cant('dispose', 'ams_assets')) {
                return ['success' => false, 'message' => _l('ams_no_dispose_permission')];
            }
        }

        $manualTag = trim($input['asset_tag'] ?? '');
        if ($manualTag !== '' && $this->tag_exists($manualTag)) {
            return ['success' => false, 'message' => _l('ams_tag_exists', e($manualTag))];
        }

        $now = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        $data['asset_tag']    = $manualTag !== '' ? $manualTag : $this->generate_tag($data['category_id'] ?? null);
        $data['status_id']    = $status['id'];
        $data['location_id']  = $locationId;
        $data['created_by']   = get_staff_user_id();
        $data['date_created'] = $now;

        $this->db->insert($this->t('ams_assets'), $data);
        $id = (int) $this->db->insert_id();

        $this->add_history($id, [
            'action'          => 'create',
            'status_to'       => $status['id'],
            'location_to'     => $locationId,
            'asset_condition' => $data['asset_condition'] ?? null,
            'note'            => $note ?: null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        if (isset($input['custom_fields'])) {
            handle_custom_fields_post($id, $input['custom_fields']);
        }

        $this->audit($id, 'create', null);
        log_activity('AMS asset created [ID: ' . $id . ', Tag: ' . $data['asset_tag'] . ']');
        hooks()->do_action('ams_after_asset_created', $id);

        if ($assignNow) {
            $result = $this->checkout($id, [
                'assign_type'      => $assignType,
                'assign_id'        => $assignId,
                'department_id'    => $input['department_id'] ?? '',
                'expected_checkin' => $input['expected_checkin'] ?? '',
                'asset_condition'  => $data['asset_condition'] ?? '',
                'note'             => $note,
            ]);
            if (! $result['success']) {
                return ['success' => true, 'id' => $id, 'message' => _l('ams_created_but_not_assigned') . ' ' . $result['message']];
            }
        }

        return ['success' => true, 'id' => $id, 'message' => _l('added_successfully', _l('ams_asset'))];
    }

    public function update($id, $input)
    {
        $old = $this->db->where('id', (int) $id)->where('is_deleted', 0)->get($this->t('ams_assets'))->row_array();
        if (! $old) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $data = $this->prepare($input);
        if (isset($data['error'])) {
            return ['success' => false, 'message' => $data['error']];
        }

        $tag = trim($input['asset_tag'] ?? '');
        if ($tag === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_asset_tag'))];
        }
        if ($this->tag_exists($tag, $id)) {
            return ['success' => false, 'message' => _l('ams_tag_exists', e($tag))];
        }
        $data['asset_tag'] = $tag;

        $changes = [];
        foreach ($data as $field => $value) {
            if ((string) ($old[$field] ?? '') !== (string) ($value ?? '')) {
                $changes[$field] = [$this->display_value($field, $old[$field]), $this->display_value($field, $value)];
            }
        }

        $cfChanged = false;
        if (isset($input['custom_fields'])) {
            $cfChanged = handle_custom_fields_post($id, $input['custom_fields']);
        }

        if (! $changes && ! $cfChanged) {
            return ['success' => true, 'id' => $id, 'message' => _l('ams_no_changes')];
        }

        if ($changes) {
            $data['updated_by']   = get_staff_user_id();
            $data['date_updated'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update($this->t('ams_assets'), $data);
        }

        if ($cfChanged) {
            $changes['custom_fields'] = ['', _l('ams_custom_fields_updated')];
        }

        $this->audit($id, 'update', $changes);
        $this->add_history($id, ['action' => 'edit', 'note' => _l('ams_history_fields_changed', count($changes))]);
        hooks()->do_action('ams_after_asset_updated', $id);

        return ['success' => true, 'id' => $id, 'message' => _l('updated_successfully', _l('ams_asset'))];
    }

    /** Normalise the editable fields from a form post. Returns ['error' => ...] on failure. */
    private function prepare($input)
    {
        $data = [];
        foreach ($this->editable as $field) {
            if ($field === 'asset_tag') {
                continue;
            }
            $value = isset($input[$field]) ? trim((string) $input[$field]) : '';

            if (in_array($field, $this->dateFields)) {
                $data[$field] = $value !== '' ? to_sql_date($value) : null;
            } elseif (in_array($field, $this->intFields)) {
                $data[$field] = $value !== '' ? (int) $value : null;
            } elseif ($field === 'depreciation_method') {
                $data[$field] = in_array($value, ams_depreciation_methods(), true) ? $value : null;
            } elseif ($field === 'useful_life_months') {
                $data[$field] = is_numeric($value) && (int) $value > 0 ? (int) $value : null;
            } elseif ($field === 'purchase_cost' || $field === 'salvage_value') {
                $value        = str_replace([',', ' '], '', $value);
                $data[$field] = $value !== '' && is_numeric($value) ? round((float) $value, 2) : null;
            } else {
                $data[$field] = $value !== '' ? $value : null;
            }
        }

        if (empty($data['name'])) {
            return ['error' => _l('ams_field_required', _l('ams_asset_name'))];
        }
        if (empty($data['category_id'])) {
            return ['error' => _l('ams_field_required', _l('ams_category'))];
        }

        $data['source'] = in_array($data['source'], ['purchase', 'gift', 'lease', 'transfer']) ? $data['source'] : 'purchase';

        // Keep only the fields that belong to the chosen source.
        if ($data['source'] !== 'gift') {
            $data['gifted_by_type'] = $data['gifted_by_id'] = $data['gifted_by_name'] = null;
        } else {
            $type = $data['gifted_by_type'];
            if ($type === 'staff' || $type === 'supplier') {
                $data['gifted_by_id']   = (int) ($input['gifted_by_id_' . $type] ?? 0) ?: null;
                $data['gifted_by_name'] = null;
                if (! $data['gifted_by_id']) {
                    return ['error' => _l('ams_field_required', _l('ams_gifted_by'))];
                }
            } elseif ($type === 'other') {
                $data['gifted_by_id'] = null;
                if (empty($data['gifted_by_name'])) {
                    return ['error' => _l('ams_field_required', _l('ams_gifted_by'))];
                }
            } else {
                return ['error' => _l('ams_field_required', _l('ams_gifted_by'))];
            }
        }

        if ($data['source'] !== 'lease') {
            $data['lease_end_date'] = null;
        }

        if (! empty($data['warranty_start']) && ! empty($data['warranty_end']) && $data['warranty_end'] < $data['warranty_start']) {
            return ['error' => _l('ams_warranty_end_before_start')];
        }

        if (empty($data['currency'])) {
            $data['currency'] = get_base_currency()->id;
        }

        if ($data['salvage_value'] !== null && ($data['salvage_value'] < 0 || ($data['purchase_cost'] !== null && $data['salvage_value'] > $data['purchase_cost']))) {
            return ['error' => _l('ams_dep_salvage_above_cost')];
        }

        return $data;
    }

    /**
     * Next tag = PREFIX-CODE-00042. CODE comes from the category (or its parent),
     * falling back to the default code. One sequence per PREFIX-CODE.
     * Must run inside the caller's transaction (row lock on the sequence).
     */
    public function generate_tag($categoryId)
    {
        $prefix = strtoupper(trim(get_option('ams_asset_tag_prefix')));
        $sep    = get_option('ams_asset_tag_separator');
        $digits = max(1, (int) get_option('ams_asset_tag_digits'));
        $code   = $this->category_code($categoryId) ?: strtoupper(get_option('ams_default_category_code') ?: 'GEN');

        $base = implode($sep, array_filter([$prefix, $code], 'strlen'));

        $row = $this->db->query('SELECT next_number FROM ' . $this->t('ams_tag_sequences') . ' WHERE code = ? FOR UPDATE', [$base])->row();
        if (! $row) {
            $this->db->insert($this->t('ams_tag_sequences'), ['code' => $base, 'next_number' => 1]);
            $next = 1;
        } else {
            $next = (int) $row->next_number;
        }

        do {
            $tag = $base . $sep . str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            $next++;
        } while ($this->tag_exists($tag));

        $this->db->where('code', $base)->update($this->t('ams_tag_sequences'), ['next_number' => $next]);

        return $tag;
    }

    private function category_code($categoryId)
    {
        if (! $categoryId) {
            return null;
        }
        $cat = $this->db->where('id', (int) $categoryId)->get($this->t('ams_categories'))->row();
        if (! $cat) {
            return null;
        }
        if ($cat->code) {
            return $cat->code;
        }
        if ($cat->parent_id) {
            $parent = $this->db->where('id', $cat->parent_id)->get($this->t('ams_categories'))->row();

            return $parent ? $parent->code : null;
        }

        return null;
    }

    // ─── Lifecycle actions ────────────────────────────────────────────────

    /**
     * Check out to a Perfex staff member, a Perfex department or a location.
     */
    public function checkout($id, $input)
    {
        $asset = $this->get($id);
        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if ($asset->assigned_type) {
            return ['success' => false, 'message' => _l('ams_already_checked_out')];
        }
        if ($asset->status_type !== 'deployable') {
            return ['success' => false, 'message' => _l('ams_not_deployable', e($asset->status_name))];
        }

        $type     = $input['assign_type'] ?? '';
        $assignId = (int) ($input['assign_id'] ?? 0);

        if (! in_array($type, ['staff', 'department', 'location']) || ! $assignId) {
            return ['success' => false, 'message' => _l('ams_select_assignee')];
        }

        if ($type === 'staff' && total_rows(db_prefix() . 'staff', ['staffid' => $assignId, 'active' => 1]) === 0) {
            return ['success' => false, 'message' => _l('ams_select_assignee')];
        }
        if ($type === 'department' && total_rows(db_prefix() . 'departments', ['departmentid' => $assignId]) === 0) {
            return ['success' => false, 'message' => _l('ams_select_assignee')];
        }
        if ($type === 'location' && total_rows($this->t('ams_locations'), ['id' => $assignId]) === 0) {
            return ['success' => false, 'message' => _l('ams_select_assignee')];
        }

        $assigned = ams_get_status_by_key('assigned');
        if (! $assigned) {
            return ['success' => false, 'message' => _l('ams_system_status_missing')];
        }

        $department = (int) ($input['department_id'] ?? 0) ?: null;
        if ($type === 'department') {
            $department = $assignId;
        } elseif ($type === 'staff' && ! $department) {
            $department = ams_staff_primary_department($assignId);
        }

        $locationTo = $type === 'location' ? $assignId : $asset->location_id;
        $condition  = ($input['asset_condition'] ?? '') ?: $asset->asset_condition;
        $expected   = ! empty($input['expected_checkin']) ? to_sql_date($input['expected_checkin']) : null;
        $now        = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        $this->db->where('id', (int) $id)->update($this->t('ams_assets'), [
            'status_id'        => $assigned['id'],
            'assigned_type'    => $type,
            'assigned_id'      => $assignId,
            'department_id'    => $department,
            'location_id'      => $locationTo,
            'assigned_at'      => $now,
            'expected_checkin' => $expected,
            'asset_condition'  => $condition,
            'updated_by'       => get_staff_user_id(),
            'date_updated'     => $now,
        ]);

        $this->add_history($id, [
            'action'           => 'checkout',
            'status_from'      => $asset->status_id,
            'status_to'        => $assigned['id'],
            'assigned_type_to' => $type,
            'assigned_id_to'   => $assignId,
            'location_from'    => $asset->location_id,
            'location_to'      => $locationTo,
            'department_id'    => $department,
            'asset_condition'  => $condition,
            'note'             => trim($input['note'] ?? '') ?: null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        log_activity('AMS asset checked out [Tag: ' . $asset->asset_tag . ', To: ' . $type . ' #' . $assignId . ']');
        hooks()->do_action('ams_after_asset_checkout', ['asset_id' => $id, 'assign_type' => $type, 'assign_id' => $assignId]);

        return ['success' => true, 'message' => _l('ams_checked_out_success', e(ams_assignee_text($type, $assignId)))];
    }

    /**
     * Return a checked-out asset to a location with a (non-deployed) status.
     */
    public function checkin($id, $input)
    {
        $asset = $this->get($id);
        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if (! $asset->assigned_type) {
            return ['success' => false, 'message' => _l('ams_not_checked_out')];
        }

        $status = ams_get_status($input['status_id'] ?? 0) ?: ams_get_status_by_key('in_store');
        if (! $status || ! $status['active'] || in_array($status['type'], ['deployed', 'archived'])) {
            return ['success' => false, 'message' => _l('ams_select_valid_status')];
        }

        $locationId = (int) ($input['location_id'] ?? 0) ?: null;
        $note       = trim($input['note'] ?? '');

        if (! $locationId) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_location'))];
        }
        if ($status['requires_note'] && $note === '') {
            return ['success' => false, 'message' => _l('ams_status_requires_note', e($status['name']))];
        }

        $condition = ($input['asset_condition'] ?? '') ?: $asset->asset_condition;
        $now       = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        $this->db->where('id', (int) $id)->update($this->t('ams_assets'), [
            'status_id'        => $status['id'],
            'assigned_type'    => null,
            'assigned_id'      => null,
            'department_id'    => null,
            'assigned_at'      => null,
            'expected_checkin' => null,
            'location_id'      => $locationId,
            'asset_condition'  => $condition,
            'updated_by'       => get_staff_user_id(),
            'date_updated'     => $now,
        ]);

        $this->add_history($id, [
            'action'             => 'checkin',
            'status_from'        => $asset->status_id,
            'status_to'          => $status['id'],
            'assigned_type_from' => $asset->assigned_type,
            'assigned_id_from'   => $asset->assigned_id,
            'location_from'      => $asset->location_id,
            'location_to'        => $locationId,
            'department_id'      => $asset->department_id,
            'asset_condition'    => $condition,
            'note'               => $note ?: null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        log_activity('AMS asset checked in [Tag: ' . $asset->asset_tag . ']');
        hooks()->do_action('ams_after_asset_checkin', $id);

        return ['success' => true, 'message' => _l('ams_checked_in_success')];
    }

    /**
     * Lifecycle status change (Maintenance, Damaged, Sold, Donated, Stolen, Retired, In Store...).
     * - "deployed" statuses are only reachable through checkout.
     * - archived statuses need the "dispose" permission and end any assignment.
     * - deployable statuses (e.g. In Store) end any assignment too.
     * - pending / undeployable (e.g. Maintenance, Damaged) keep the current holder.
     */
    public function change_status($id, $input)
    {
        $asset = $this->get($id);
        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $status = ams_get_status($input['status_id'] ?? 0);
        if (! $status || ! $status['active'] || $status['type'] === 'deployed') {
            return ['success' => false, 'message' => _l('ams_select_valid_status')];
        }
        if ((int) $status['id'] === (int) $asset->status_id) {
            return ['success' => false, 'message' => _l('ams_status_unchanged')];
        }
        if ($status['type'] === 'archived' && staff_cant('dispose', 'ams_assets')) {
            return ['success' => false, 'message' => _l('ams_no_dispose_permission')];
        }

        $note       = trim($input['note'] ?? '');
        $locationId = (int) ($input['location_id'] ?? 0) ?: $asset->location_id;

        if ($status['requires_location'] && ! $locationId) {
            return ['success' => false, 'message' => _l('ams_status_requires_location', e($status['name']))];
        }
        if ($status['requires_note'] && $note === '') {
            return ['success' => false, 'message' => _l('ams_status_requires_note', e($status['name']))];
        }

        $endsAssignment = in_array($status['type'], ['archived', 'deployable']) && $asset->assigned_type;
        $now            = date('Y-m-d H:i:s');

        $update = [
            'status_id'    => $status['id'],
            'location_id'  => $locationId,
            'updated_by'   => get_staff_user_id(),
            'date_updated' => $now,
        ];
        if (! empty($input['asset_condition'])) {
            $update['asset_condition'] = $input['asset_condition'];
        }
        if ($endsAssignment) {
            $update += ['assigned_type' => null, 'assigned_id' => null, 'department_id' => null, 'assigned_at' => null, 'expected_checkin' => null];
        }

        $this->db->trans_begin();

        $this->db->where('id', (int) $id)->update($this->t('ams_assets'), $update);

        $this->add_history($id, [
            // Disposal / reinstatement (Ams_disposal_model) record their own action on the same row.
            'action'             => in_array($input['_history_action'] ?? '', ['dispose', 'reinstate'], true) ? $input['_history_action'] : 'status',
            'status_from'        => $asset->status_id,
            'status_to'          => $status['id'],
            'assigned_type_from' => $endsAssignment ? $asset->assigned_type : null,
            'assigned_id_from'   => $endsAssignment ? $asset->assigned_id : null,
            'assigned_type_to'   => $endsAssignment ? null : $asset->assigned_type,
            'assigned_id_to'     => $endsAssignment ? null : $asset->assigned_id,
            'location_from'      => $asset->location_id,
            'location_to'        => $locationId,
            'department_id'      => $asset->department_id,
            'asset_condition'    => $update['asset_condition'] ?? $asset->asset_condition,
            'note'               => $note ?: null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        log_activity('AMS asset status changed [Tag: ' . $asset->asset_tag . ', Status: ' . $status['name'] . ']');
        hooks()->do_action('ams_after_asset_status_changed', ['asset_id' => $id, 'status_id' => $status['id']]);

        return ['success' => true, 'message' => _l('ams_status_changed_success', e($status['name']))];
    }

    public function move_location($id, $locationId, $note = '')
    {
        $asset = $this->get($id);
        if (! $asset || ! $locationId || (int) $asset->location_id === (int) $locationId) {
            return false;
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_assets'), [
            'location_id'  => (int) $locationId,
            'updated_by'   => get_staff_user_id(),
            'date_updated' => date('Y-m-d H:i:s'),
        ]);

        $this->add_history($id, [
            'action'        => 'move',
            'status_to'     => $asset->status_id,
            'location_from' => $asset->location_id,
            'location_to'   => (int) $locationId,
            'note'          => $note ?: null,
        ]);

        return true;
    }

    /** Soft delete: the row and its history stay for traceability; the tag stays reserved. */
    public function delete($id, $reason = '')
    {
        $asset = $this->get($id);
        if (! $asset) {
            return false;
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_assets'), [
            'is_deleted'     => 1,
            'deleted_reason' => $reason ?: null,
            'deleted_by'     => get_staff_user_id(),
            'date_deleted'   => date('Y-m-d H:i:s'),
        ]);

        $this->add_history($id, ['action' => 'delete', 'status_from' => $asset->status_id, 'note' => $reason ?: null]);
        $this->audit($id, 'delete', ['asset_tag' => [$asset->asset_tag, null], 'name' => [$asset->name, null]]);
        log_activity('AMS asset deleted [ID: ' . $id . ', Tag: ' . $asset->asset_tag . ']');
        hooks()->do_action('ams_after_asset_deleted', $id);

        return true;
    }

    // ─── History / audit ──────────────────────────────────────────────────

    public function add_history($assetId, $row)
    {
        $row['asset_id']     = (int) $assetId;
        $row['staff_id']     = get_staff_user_id() ?: null;
        $row['date_created'] = date('Y-m-d H:i:s');
        $this->db->insert($this->t('ams_asset_history'), $row);
    }

    public function audit($assetId, $action, $changes)
    {
        $this->db->insert($this->t('ams_audit_log'), [
            'rel_type'     => 'ams_assets',
            'rel_id'       => (int) $assetId,
            'action'       => $action,
            'changes'      => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Human-readable value for the change log (resolves IDs to names at write time). */
    private function display_value($field, $value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($field) {
            case 'category_id':
                return ams_option_label(ams_category_options(), $value);
            case 'brand_id':
                return ams_option_label(ams_brand_options(), $value);
            case 'model_id':
                return ams_option_label(ams_model_options(), $value);
            case 'supplier_id':
                return ams_option_label(ams_supplier_options(), $value);
            case 'purchased_by':
                return get_staff_full_name($value);
            case 'department_id':
                return ams_department_name($value);
            case 'source':
                return ams_option_label(ams_source_options(), $value);
            case 'asset_condition':
                return ams_option_label(ams_condition_options(), $value);
            case 'currency':
                return ams_option_label(array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']], ams_currency_options()), $value);
            case 'gifted_by_id':
                return (string) $value;
            case 'depreciation_method':
                return _l('ams_dep_method_' . $value);
        }

        if (in_array($field, $this->dateFields)) {
            return _d($value);
        }

        return (string) $value;
    }

    // ─── Files ────────────────────────────────────────────────────────────

    public function get_files($assetId)
    {
        return $this->db->where('asset_id', (int) $assetId)
            ->order_by('is_image', 'desc')
            ->order_by('id', 'asc')
            ->get($this->t('ams_asset_files'))
            ->result_array();
    }

    public function get_file($fileId)
    {
        return $this->db->where('id', (int) $fileId)->get($this->t('ams_asset_files'))->row();
    }

    /**
     * Handles $_FILES['files'] (multiple). Returns [uploaded count, error messages].
     */
    public function upload_files($assetId)
    {
        $uploaded = 0;
        $errors   = [];

        if (empty($_FILES['files']['name'])) {
            return [0, [_l('ams_no_file_selected')]];
        }

        $maxBytes = max(1, (int) get_option('ams_max_upload_mb')) * 1024 * 1024;
        $dir      = ams_asset_upload_dir($assetId);
        // Perfex's _maybe_create_upload_path() is not recursive and assets/ may not exist yet.
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [0, [_l('ams_upload_failed')]];
        }
        if (! file_exists($dir . 'index.html')) {
            @file_put_contents($dir . 'index.html', '');
        }

        $names = (array) $_FILES['files']['name'];

        foreach ($names as $i => $originalName) {
            if ($originalName === '') {
                continue;
            }

            $error = $_FILES['files']['error'][$i];
            $tmp   = $_FILES['files']['tmp_name'][$i];
            $size  = (int) $_FILES['files']['size'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = e($originalName) . ': ' . _perfex_upload_error($error);
                continue;
            }
            if (! _upload_extension_allowed($originalName)) {
                $errors[] = e($originalName) . ': ' . _l('ams_extension_not_allowed');
                continue;
            }
            if ($size > $maxBytes) {
                $errors[] = e($originalName) . ': ' . _l('ams_file_too_large', get_option('ams_max_upload_mb'));
                continue;
            }

            $isImage  = @getimagesize($tmp) !== false;
            $fileName = unique_filename($dir, sanitize_file_name($originalName));

            if (! move_uploaded_file($tmp, $dir . $fileName)) {
                $errors[] = e($originalName) . ': ' . _l('ams_upload_failed');
                continue;
            }

            $this->db->insert($this->t('ams_asset_files'), [
                'asset_id'      => (int) $assetId,
                'file_name'     => $fileName,
                'original_name' => mb_substr($originalName, 0, 191),
                'filetype'      => get_mime_by_extension($fileName) ?: null,
                'filesize'      => $size,
                'is_image'      => $isImage ? 1 : 0,
                'staff_id'      => get_staff_user_id(),
                'date_created'  => date('Y-m-d H:i:s'),
            ]);
            $fileId = (int) $this->db->insert_id();

            $asset = $this->db->select('cover_file_id')->where('id', (int) $assetId)->get($this->t('ams_assets'))->row();
            if ($isImage && $asset && ! $asset->cover_file_id) {
                $this->db->where('id', (int) $assetId)->update($this->t('ams_assets'), ['cover_file_id' => $fileId]);
            }

            $uploaded++;
        }

        if ($uploaded) {
            $this->add_history($assetId, ['action' => 'files', 'note' => _l('ams_history_files_uploaded', $uploaded)]);
        }

        return [$uploaded, $errors];
    }

    public function delete_file($fileId)
    {
        $file = $this->get_file($fileId);
        if (! $file) {
            return false;
        }

        $path = ams_asset_upload_dir($file->asset_id) . $file->file_name;
        if (is_file($path)) {
            @unlink($path);
        }

        $this->db->where('id', (int) $fileId)->delete($this->t('ams_asset_files'));
        $this->db->where('id', (int) $file->asset_id)->where('cover_file_id', (int) $fileId)
            ->update($this->t('ams_assets'), ['cover_file_id' => null]);

        $this->add_history($file->asset_id, ['action' => 'files', 'note' => _l('ams_history_file_deleted', $file->original_name)]);

        return true;
    }

    public function set_cover($fileId)
    {
        $file = $this->get_file($fileId);
        if (! $file || ! $file->is_image) {
            return false;
        }

        $this->db->where('id', (int) $file->asset_id)->update($this->t('ams_assets'), ['cover_file_id' => (int) $fileId]);

        return true;
    }

    // ─── Dashboard ────────────────────────────────────────────────────────

    public function dashboard_stats()
    {
        $p     = db_prefix();
        $scope = ams_assets_scope_where('a');
        $base  = 'FROM ' . $p . 'ams_assets a WHERE a.is_deleted = 0 ' . $scope;

        $stats = [];

        $stats['total']     = (int) $this->db->query('SELECT COUNT(*) c ' . $base)->row()->c;
        $stats['purchased'] = (int) $this->db->query('SELECT COUNT(*) c ' . $base . ' AND a.source = "purchase"')->row()->c;
        $stats['gifted']    = (int) $this->db->query('SELECT COUNT(*) c ' . $base . ' AND a.source = "gift"')->row()->c;
        $stats['assigned']  = (int) $this->db->query('SELECT COUNT(*) c ' . $base . ' AND a.assigned_type IS NOT NULL')->row()->c;

        $stats['value'] = $this->db->query('SELECT a.currency, SUM(a.purchase_cost) total ' . $base . ' AND a.purchase_cost IS NOT NULL
            AND a.status_id NOT IN (SELECT id FROM ' . $p . 'ams_statuses WHERE type = "archived") GROUP BY a.currency')->result_array();

        $stats['by_status'] = $this->db->query('SELECT st.id, st.name, st.color, st.type, COUNT(a.id) total
            FROM ' . $p . 'ams_statuses st
            LEFT JOIN ' . $p . 'ams_assets a ON a.status_id = st.id AND a.is_deleted = 0 ' . $scope . '
            WHERE st.active = 1 GROUP BY st.id ORDER BY st.sort_order ASC')->result_array();

        $stats['top_categories'] = $this->db->query('SELECT c.id, IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) name, c.icon, COUNT(a.id) total
            FROM ' . $p . 'ams_assets a
            JOIN ' . $p . 'ams_categories c ON c.id = a.category_id
            LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id
            WHERE a.is_deleted = 0 ' . $scope . '
            GROUP BY c.id ORDER BY total DESC LIMIT 6')->result_array();

        $days          = (int) get_option('ams_warranty_expiring_days');
        $expiringWhere = ' AND a.warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';

        $stats['warranty_expiring_count'] = (int) $this->db->query('SELECT COUNT(*) c ' . $base . $expiringWhere)->row()->c;
        $stats['warranty_expiring'] = $this->db->query('SELECT a.id, a.asset_tag, a.name, a.warranty_end ' . $base . '
            AND a.warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)
            ORDER BY a.warranty_end ASC LIMIT 10')->result_array();

        $stats['latest'] = $this->db->query('SELECT a.id, a.asset_tag, a.name, a.assigned_type, a.assigned_id, a.warranty_end,
            st.name status_name, st.color status_color, c.name category_name
            FROM ' . $p . 'ams_assets a
            LEFT JOIN ' . $p . 'ams_statuses st ON st.id = a.status_id
            LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id
            WHERE a.is_deleted = 0 ' . $scope . '
            ORDER BY a.id DESC LIMIT 10')->result_array();

        return $stats;
    }
}
