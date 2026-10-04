<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CSV / Excel import of assets and suppliers.
 *
 * run() is used twice with the same mapping: first as a preview (validation
 * only, nothing written), then for real. Every row goes through the same model
 * methods as the screens (Ams_assets_model::add/update, setup save),
 * so the same rules, history, audit log and hooks apply. Existing records are
 * matched by asset tag / supplier name and either skipped or updated.
 */
class Ams_import_model extends App_Model
{
    private $cache = [];

    private $dryRun = true;

    private $options = [];

    public function types()
    {
        $types = [];
        if (staff_can('create', 'ams_assets') || staff_can('edit', 'ams_assets')) {
            $types['assets'] = 'ams_assets';
        }
        if (staff_can('create', 'ams_setup') || staff_can('edit', 'ams_setup')) {
            $types['suppliers'] = 'ams_suppliers';
        }

        return $types;
    }

    /** Importable fields: key => [label, required, header aliases (lower case)]. */
    public function fields($type)
    {
        $f = fn ($label, $req, $aliases = []) => ['label' => $label, 'required' => $req, 'aliases' => $aliases];

        switch ($type) {
            case 'assets':
                return [
                    'asset_tag'           => $f('ams_asset_tag', false, ['tag', 'asset tag', 'asset no', 'asset number', 'asset id']),
                    'name'                => $f('ams_asset_name', true, ['name', 'asset', 'asset name', 'product', 'product name', 'item', 'description']),
                    'serial_no'           => $f('ams_serial_no', false, ['serial', 'serial no', 'serial number', 's/n', 'sn']),
                    'category'            => $f('ams_category', true, ['category', 'type', 'category name']),
                    'brand'               => $f('ams_brand', false, ['brand', 'make', 'manufacturer']),
                    'model'               => $f('ams_model', false, ['model', 'model name']),
                    'status'              => $f('ams_status', false, ['status']),
                    'location'            => $f('ams_location', false, ['location', 'storage', 'store', 'room', 'site']),
                    'assigned_email'      => $f('ams_import_assigned_email', false, ['assigned to', 'assigned email', 'staff email', 'user email', 'employee email', 'email']),
                    'department'          => $f('ams_department', false, ['department', 'dept', 'cost centre', 'cost center']),
                    'condition'           => $f('ams_condition', false, ['condition']),
                    'source'              => $f('ams_source', false, ['source']),
                    'supplier'            => $f('ams_supplier', false, ['supplier', 'vendor']),
                    'purchase_date'       => $f('ams_purchase_date', false, ['purchase date', 'purchased', 'date of purchase', 'bought on']),
                    'purchase_cost'       => $f('ams_purchase_cost', false, ['purchase cost', 'cost', 'price', 'purchase price', 'amount', 'value']),
                    'currency'            => $f('ams_currency', false, ['currency']),
                    'invoice_no'          => $f('ams_invoice_no', false, ['invoice', 'invoice no', 'invoice number']),
                    'order_no'            => $f('ams_order_no', false, ['order', 'order no', 'po', 'po number', 'order number']),
                    'warranty_start'      => $f('ams_warranty_start', false, ['warranty start']),
                    'warranty_end'        => $f('ams_warranty_end', false, ['warranty end', 'warranty', 'warranty expiry', 'warranty until']),
                    'warranty_provider'   => $f('ams_warranty_provider', false, ['warranty provider']),
                    'depreciation_method' => $f('ams_dep_method', false, ['depreciation method', 'depreciation']),
                    'useful_life_months'  => $f('ams_dep_life_months', false, ['useful life', 'useful life months', 'life months']),
                    'salvage_value'       => $f('ams_dep_salvage_value', false, ['salvage', 'salvage value', 'residual value']),
                    'notes'               => $f('ams_notes', false, ['notes', 'note', 'remarks', 'comment', 'comments']),
                ];
            case 'suppliers':
                return [
                    'name'           => $f('ams_name', true, ['name', 'supplier', 'supplier name', 'vendor', 'company']),
                    'contact_person' => $f('ams_contact_person', false, ['contact', 'contact person', 'contact name']),
                    'phone'          => $f('ams_phone', false, ['phone', 'mobile', 'telephone', 'tel']),
                    'email'          => $f('ams_email', false, ['email', 'e-mail', 'mail']),
                    'website'        => $f('ams_website', false, ['website', 'web', 'url']),
                    'address'        => $f('ams_address', false, ['address']),
                    'notes'          => $f('ams_notes', false, ['notes', 'note', 'remarks']),
                ];
        }

        return [];
    }

    /** Guess field → column from the header row (exact alias or field label match). */
    public function auto_map($type, $headers)
    {
        $norm = fn ($s) => trim(preg_replace('/[^a-z0-9\/]+/', ' ', strtolower((string) $s)));
        $cols = array_map($norm, $headers);
        $map  = [];
        $used = [];
        foreach ($this->fields($type) as $key => $def) {
            $candidates = array_merge([$norm($key), $norm(_l($def['label']))], array_map($norm, $def['aliases']));
            foreach ($candidates as $cand) {
                $idx = array_search($cand, $cols, true);
                if ($idx !== false && ! isset($used[$idx])) {
                    $map[$key] = $idx;
                    $used[$idx] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param array $rows    data rows (header removed)
     * @param array $map     field => column index
     * @param array $options mode (skip|update), date_format, create_missing, notify
     * @return array results [[row, status (create|update|unchanged|skip|error), ref, message]], counts
     */
    public function run($type, array $rows, array $map, array $options, $commit)
    {
        @set_time_limit(600);
        $this->dryRun  = ! $commit;
        $this->options = $options + ['mode' => 'skip', 'date_format' => 'Y-m-d', 'create_missing' => 0, 'notify' => 0];
        $this->cache   = [];

        $results = [];
        $seen    = [];
        foreach ($rows as $i => $row) {
            $rowNo = $i + 2; // spreadsheet row number (header = 1)
            $val   = function ($field) use ($row, $map) {
                return isset($map[$field]) && $map[$field] !== '' ? trim((string) ($row[(int) $map[$field]] ?? '')) : null;
            };
            try {
                switch ($type) {
                    case 'assets':
                        $r = $this->asset_row($val, $seen);
                        break;
                    case 'suppliers':
                        $r = $this->supplier_row($val, $seen);
                        break;
                    default:
                        $r = ['error', '', _l('ams_invalid_request')];
                }
            } catch (Throwable $e) {
                $r = ['error', '', $e->getMessage()];
            }
            $results[] = ['row' => $rowNo, 'status' => $r[0], 'ref' => $r[1], 'message' => $r[2]];
        }

        $counts = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'skip' => 0, 'error' => 0];
        foreach ($results as $r) {
            $counts[$r['status']]++;
        }
        if ($commit) {
            log_activity('AMS import (' . $type . '): ' . $counts['create'] . ' created, ' . $counts['update'] . ' updated, ' . $counts['error'] . ' errors');
        }

        return ['results' => $results, 'counts' => $counts];
    }

    // ─── Assets ───────────────────────────────────────────────────────────

    private function asset_row($val, &$seen)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $notes = [];

        $tag = (string) $val('asset_tag');
        if ($tag !== '') {
            $key = strtolower($tag);
            if (isset($seen[$key])) {
                return ['error', $tag, _l('ams_import_duplicate_in_file', $seen[$key])];
            }
            $seen[$key] = $tag;
        }
        $existing = $tag !== '' ? $this->db->where('asset_tag', $tag)->where('is_deleted', 0)->get(db_prefix() . 'ams_assets')->row_array() : null;

        if ($existing && $this->options['mode'] !== 'update') {
            return ['skip', $tag, _l('ams_import_exists_skipped')];
        }
        if ($existing ? staff_cant('edit', 'ams_assets') : staff_cant('create', 'ams_assets')) {
            return ['error', $tag, _l('access_denied')];
        }

        // Start from the saved record (update) or defaults (create), then apply mapped columns.
        if ($existing) {
            $input = $existing;
            foreach (['purchase_date', 'lease_end_date', 'warranty_start', 'warranty_end'] as $d) {
                $input[$d] = $existing[$d] ? _d($existing[$d]) : '';
            }
            if (in_array($existing['gifted_by_type'], ['staff', 'supplier'])) {
                $input['gifted_by_id_' . $existing['gifted_by_type']] = $existing['gifted_by_id'];
            }
        } else {
            $input = ['source' => 'purchase', 'status_id' => ams_get_status_by_key('in_store')['id'] ?? 0, 'currency' => get_base_currency()->id, 'asset_condition' => 'good'];
        }

        foreach (['name', 'serial_no', 'invoice_no', 'order_no', 'warranty_provider', 'notes'] as $f) {
            if ($val($f) !== null && ($val($f) !== '' || ! $existing)) {
                $input[$f] = $val($f);
            }
        }
        if ($tag !== '') {
            $input['asset_tag'] = $tag;
        }

        $lookups = [
            'category' => ['categories', 'category_id'],
            'brand'    => ['brands', 'brand_id'],
            'model'    => ['models', 'model_id'],
            'supplier' => ['suppliers', 'supplier_id'],
            'location' => ['locations', 'location_id'],
        ];
        foreach ($lookups as $col => [$entity, $field]) {
            if (($v = $val($col)) === null || $v === '') {
                continue;
            }
            $id = $this->lookup($entity, $v, $notes);
            if ($id === null) {
                return ['error', $tag, _l('ams_import_not_found', [_l($this->entity_label($entity)), $v])];
            }
            $input[$field] = $id;
        }
        if (($v = $val('department')) !== null && $v !== '') {
            $id = $this->lookup_department($v);
            if (! $id) {
                return ['error', $tag, _l('ams_import_not_found', [_l('ams_department'), $v])];
            }
            $input['department_id'] = $id;
        }
        if (($v = $val('status')) !== null && $v !== '' && ! $existing) {
            $st = $this->lookup_status($v);
            if (! $st) {
                return ['error', $tag, _l('ams_import_not_found', [_l('ams_status'), $v])];
            }
            $input['status_id'] = $st;
        }
        if (($v = $val('currency')) !== null && $v !== '') {
            $cur = $this->lookup_currency($v);
            if (! $cur) {
                return ['error', $tag, _l('ams_import_not_found', [_l('ams_currency'), $v])];
            }
            $input['currency'] = $cur;
        }
        if (($v = $val('condition')) !== null && $v !== '') {
            $input['asset_condition'] = $this->option_value(ams_condition_options(), $v);
            if (! $input['asset_condition']) {
                return ['error', $tag, _l('ams_import_invalid_value', [_l('ams_condition'), $v])];
            }
        }
        if (($v = $val('source')) !== null && $v !== '') {
            $input['source'] = $this->option_value(ams_source_options(), $v);
            if (! $input['source'] || $input['source'] === 'gift') {
                return ['error', $tag, _l('ams_import_invalid_value', [_l('ams_source'), $v])];
            }
        }
        if (($v = $val('depreciation_method')) !== null && $v !== '') {
            $input['depreciation_method'] = $this->option_value(ams_depreciation_method_options(), $v);
            if (! $input['depreciation_method']) {
                return ['error', $tag, _l('ams_import_invalid_value', [_l('ams_dep_method'), $v])];
            }
        }
        foreach (['purchase_date', 'warranty_start', 'warranty_end'] as $f) {
            if (($v = $val($f)) !== null && $v !== '') {
                $d = $this->parse_date($v);
                if (! $d) {
                    return ['error', $tag, _l('ams_import_invalid_date', [_l('ams_' . $f), $v])];
                }
                $input[$f] = _d($d);
            }
        }
        foreach (['purchase_cost', 'salvage_value', 'useful_life_months'] as $f) {
            if (($v = $val($f)) !== null && $v !== '') {
                $n = $this->parse_number($v);
                if ($n === null || $n < 0) {
                    return ['error', $tag, _l('ams_import_invalid_number', [$f, $v])];
                }
                $input[$f] = $n;
            }
        }

        $email = (string) $val('assigned_email');
        if ($email !== '' && ! $existing) {
            $staffId = $this->lookup_staff($email);
            if (! $staffId) {
                return ['error', $tag, _l('ams_import_not_found', [_l('ams_assign_type_staff'), $email])];
            }
            $input['assign_type']     = 'staff';
            $input['assign_id_staff'] = $staffId;
        }
        if (! $existing) {
            $input['initial_note'] = _l('ams_import_history_note');
        }

        $error = $this->ams_assets_model->validate($input, $existing['id'] ?? null);
        if ($error) {
            return ['error', $tag, $error];
        }

        $label = $existing ? $existing['asset_tag'] : ($tag !== '' ? $tag : $input['name']);
        if ($this->dryRun) {
            return [$existing ? 'update' : 'create', $label, implode(' ', $notes)];
        }

        if ($existing) {
            $r = $this->ams_assets_model->update($existing['id'], $input);
            if ($r['success'] && isset($input['location_id']) && (int) $input['location_id'] !== (int) $existing['location_id']) {
                $this->ams_assets_model->move_location($existing['id'], $input['location_id'], _l('ams_import_history_note'));
            }
        } else {
            $GLOBALS['ams_import_silent'] = empty($this->options['notify']);
            $r = $this->ams_assets_model->add($input);
            unset($GLOBALS['ams_import_silent']);
        }
        if (! $r['success']) {
            return ['error', $label, $r['message']];
        }
        $savedTag = $this->db->select('asset_tag')->where('id', (int) $r['id'])->get(db_prefix() . 'ams_assets')->row()->asset_tag ?? $label;
        $status   = $existing ? ($r['message'] === _l('ams_no_changes') ? 'unchanged' : 'update') : 'create';

        return [$status, $savedTag, trim(implode(' ', $notes) . ($existing ? '' : ' ' . ($r['message'] !== _l('added_successfully', _l('ams_asset')) ? $r['message'] : '')))];
    }

    // ─── Suppliers ────────────────────────────────────────────────────────

    private function supplier_row($val, &$seen)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_setup_model');

        $name = (string) $val('name');
        if ($name === '') {
            return ['error', '', _l('ams_field_required', _l('ams_name'))];
        }
        $key = mb_strtolower($name);
        if (isset($seen[$key])) {
            return ['error', $name, _l('ams_import_duplicate_in_file', $name)];
        }
        $seen[$key] = $name;

        $existing = $this->db->where('LOWER(name)', $key)->get(db_prefix() . 'ams_suppliers')->row_array();
        if ($existing && $this->options['mode'] !== 'update') {
            return ['skip', $name, _l('ams_import_exists_skipped')];
        }
        if ($existing ? staff_cant('edit', 'ams_setup') : staff_cant('create', 'ams_setup')) {
            return ['error', $name, _l('access_denied')];
        }

        $input = $existing ?: ['active' => 1];
        foreach (['name', 'contact_person', 'phone', 'email', 'website', 'address', 'notes'] as $f) {
            if ($val($f) !== null && ($val($f) !== '' || ! $existing)) {
                $input[$f] = $val($f);
            }
        }
        if (! empty($input['email']) && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            return ['error', $name, _l('ams_import_invalid_value', [_l('ams_email'), $input['email']])];
        }

        if ($this->dryRun) {
            return [$existing ? 'update' : 'create', $name, ''];
        }
        $r = $this->ams_setup_model->save('suppliers', $input, $existing['id'] ?? null);
        if (! $r['success']) {
            return ['error', $name, $r['message']];
        }
        $this->cache = []; // new supplier names become resolvable

        return [$existing ? 'update' : 'create', $name, ''];
    }

    // ─── Lookups ──────────────────────────────────────────────────────────

    /**
     * Setup entity by name (categories also by code). Missing ones are created
     * when the option is on (in the preview they are only announced).
     */
    private function lookup($entity, $value, &$notes)
    {
        $key = mb_strtolower(trim($value));
        if (! isset($this->cache[$entity])) {
            $this->cache[$entity] = [];
            $cols = $entity === 'categories' ? 'id, name, code' : 'id, name';
            foreach ($this->db->select($cols)->get(db_prefix() . 'ams_' . $entity)->result_array() as $r) {
                $this->cache[$entity][mb_strtolower($r['name'])] ??= (int) $r['id'];
                if (! empty($r['code'])) {
                    $this->cache[$entity]['code:' . mb_strtolower($r['code'])] = (int) $r['id'];
                }
            }
        }
        if (isset($this->cache[$entity][$key])) {
            return $this->cache[$entity][$key];
        }
        if (isset($this->cache[$entity]['code:' . $key])) {
            return $this->cache[$entity]['code:' . $key];
        }
        if (empty($this->options['create_missing']) || staff_cant('create', 'ams_setup')) {
            return null;
        }

        $notes[] = _l('ams_import_will_create', [_l($this->entity_label($entity)), trim($value)]);
        if ($this->dryRun) {
            return $this->cache[$entity][$key] = -1 - count($this->cache[$entity]); // placeholder, resolvable within the preview
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_setup_model');
        $data = ['name' => trim($value), 'active' => 1];
        if ($entity === 'locations') {
            $data['type'] = 'storage';
        }
        $r = $this->ams_setup_model->save($entity, $data);

        return $this->cache[$entity][$key] = $r['success'] ? (int) $r['id'] : null;
    }

    private function entity_label($entity)
    {
        return ['categories' => 'ams_category', 'brands' => 'ams_brand', 'models' => 'ams_model', 'suppliers' => 'ams_supplier', 'locations' => 'ams_location'][$entity] ?? 'ams_name';
    }

    private function lookup_department($name)
    {
        foreach (ams_department_options() as $d) {
            if (mb_strtolower($d['name']) === mb_strtolower(trim($name))) {
                return (int) $d['departmentid'];
            }
        }

        return null;
    }

    private function lookup_status($name)
    {
        foreach (ams_get_statuses(true) as $s) {
            if (mb_strtolower($s['name']) === mb_strtolower(trim($name))) {
                return (int) $s['id'];
            }
        }

        return null;
    }

    private function lookup_currency($code)
    {
        foreach (ams_currency_options() as $c) {
            if (strtolower($c['name']) === strtolower(trim($code))) {
                return (int) $c['id'];
            }
        }

        return null;
    }

    private function lookup_staff($email)
    {
        $s = $this->db->select('staffid')->where('LOWER(email)', mb_strtolower(trim($email)))->where('active', 1)->get(db_prefix() . 'staff')->row();

        return $s ? (int) $s->staffid : null;
    }

    /** Option list value by id or (translated) label, case-insensitive. */
    private function option_value($options, $value)
    {
        $v = mb_strtolower(trim($value));
        foreach ($options as $o) {
            if ((string) $o['id'] !== '' && (mb_strtolower((string) $o['id']) === $v || mb_strtolower($o['name']) === $v || str_replace('_', ' ', mb_strtolower((string) $o['id'])) === $v)) {
                return $o['id'];
            }
        }

        return null;
    }

    // ─── Parsing ──────────────────────────────────────────────────────────

    /** Returns Y-m-d or null. ISO dates always work; others use the chosen format. */
    public function parse_date($value)
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        $fmt = in_array($this->options['date_format'] ?? '', ['d/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'], true) ? $this->options['date_format'] : 'd/m/Y';
        foreach ([$fmt, str_replace('Y', 'y', $fmt)] as $f) {
            $d = DateTime::createFromFormat('!' . $f, $value);
            if ($d && $d->format($f) === $value) {
                return $d->format('Y-m-d');
            }
            // Accept values without leading zeros (1/2/2025).
            $d = DateTime::createFromFormat('!' . str_replace(['d', 'm'], ['j', 'n'], $f), $value);
            if ($d && $d->format(str_replace(['d', 'm'], ['j', 'n'], $f)) === $value) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    public function parse_number($value)
    {
        $v = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $value));

        return $v !== '' && is_numeric($v) ? round((float) $v, 2) : null;
    }

    private function parse_bool($value)
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'yes', 'y', 'true', 'x'], true);
    }
}
