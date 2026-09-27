<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Physical audit campaigns.
 *
 * draft → in_progress (starting takes a snapshot of the assets in scope:
 * location incl. sub-locations, Perfex department, category incl.
 * sub-categories; disposed assets are left out) → completed / cancelled.
 * While in progress assets are recorded (scanner, phone camera "scan mode" or
 * the table): found where expected, misplaced (found at another location),
 * unexpected (not in the snapshot). Completing marks the rest missing and can
 * move misplaced / unexpected assets to the location where they were found.
 */
class Ams_audit_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function get($id)
    {
        $audit = $this->db->where('id', (int) $id)->get($this->t('ams_audits'))->row();
        if ($audit) {
            $audit->counts = $this->counts($id);
        }

        return $audit;
    }

    public function counts($id)
    {
        $counts = ['total' => 0, 'pending' => 0, 'found' => 0, 'misplaced' => 0, 'unexpected' => 0, 'missing' => 0];
        foreach ($this->db->query('SELECT result, COUNT(*) n FROM ' . $this->t('ams_audit_lines') . ' WHERE audit_id = ? GROUP BY result', [(int) $id])->result_array() as $r) {
            $counts[$r['result']] = (int) $r['n'];
            $counts['total'] += (int) $r['n'];
        }

        return $counts;
    }

    public function save($input, $id = null)
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_audit_title'))];
        }

        $data = [
            'title'           => mb_substr($title, 0, 191),
            'location_id'     => (int) ($input['location_id'] ?? 0) ?: null,
            'department_id'   => (int) ($input['department_id'] ?? 0) ?: null,
            'category_id'     => (int) ($input['category_id'] ?? 0) ?: null,
            'due_date'        => ! empty($input['due_date']) ? to_sql_date($input['due_date']) : null,
            'next_audit_date' => ! empty($input['next_audit_date']) ? to_sql_date($input['next_audit_date']) : null,
            'notes'           => trim((string) ($input['notes'] ?? '')) ?: null,
        ];

        if ($id) {
            $audit = $this->get($id);
            if (! $audit || in_array($audit->status, ['completed', 'cancelled'])) {
                return ['success' => false, 'message' => _l('ams_audit_closed')];
            }
            if ($audit->status !== 'draft') {
                // The scope is fixed once the snapshot is taken.
                unset($data['location_id'], $data['department_id'], $data['category_id']);
            }
            $this->db->where('id', (int) $id)->update($this->t('ams_audits'), $data);

            return ['success' => true, 'id' => (int) $id, 'message' => _l('updated_successfully', _l('ams_audit'))];
        }

        $data += ['status' => 'draft', 'created_by' => get_staff_user_id() ?: null, 'date_created' => date('Y-m-d H:i:s')];
        $this->db->insert($this->t('ams_audits'), $data);
        $newId = (int) $this->db->insert_id();
        $this->db->where('id', $newId)->update($this->t('ams_audits'), [
            'audit_no' => strtoupper(trim((string) get_option('ams_audit_prefix')) ?: 'AUD') . '-' . str_pad((string) $newId, 5, '0', STR_PAD_LEFT),
        ]);

        return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_audit'))];
    }

    /** Assets in the audit's scope (not deleted, not disposed). */
    private function scope_asset_ids($audit)
    {
        // Resolve the location / category trees first: they run their own queries.
        $locations  = $audit->location_id ? ams_location_with_children($audit->location_id) : [];
        $categories = $audit->category_id ? ams_category_with_children($audit->category_id) : [];

        $this->db->select('a.id')->from($this->t('ams_assets') . ' a')
            ->join($this->t('ams_statuses') . ' st', 'st.id = a.status_id', 'left')
            ->where('a.is_deleted', 0)
            ->group_start()->where('st.type !=', 'archived')->or_where('st.id IS NULL', null, false)->group_end();
        if ($locations) {
            $this->db->where_in('a.location_id', $locations);
        }
        if ($audit->department_id) {
            $this->db->where('a.department_id', (int) $audit->department_id);
        }
        if ($categories) {
            $this->db->where_in('a.category_id', $categories);
        }

        return array_map('intval', array_column($this->db->get()->result_array(), 'id'));
    }

    public function start($id)
    {
        $audit = $this->get($id);
        if (! $audit || $audit->status !== 'draft') {
            return ['success' => false, 'message' => _l('ams_audit_wrong_status')];
        }

        $ids = $this->scope_asset_ids($audit);
        if (! $ids) {
            return ['success' => false, 'message' => _l('ams_audit_no_assets')];
        }

        $locations = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->db->select('id, location_id')->where_in('id', $chunk)->get($this->t('ams_assets'))->result_array() as $r) {
                $locations[(int) $r['id']] = $r['location_id'];
            }
        }

        $rows = [];
        foreach ($ids as $assetId) {
            $rows[] = ['audit_id' => (int) $id, 'asset_id' => $assetId, 'expected_location_id' => $locations[$assetId] ?? null, 'result' => 'pending'];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->insert_batch($this->t('ams_audit_lines'), $chunk);
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_audits'), ['status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s')]);
        log_activity('AMS audit started [' . $audit->audit_no . ', ' . count($ids) . ' assets]');

        return ['success' => true, 'message' => _l('ams_audit_started', count($ids))];
    }

    /** Resolve what a scanner / camera / person typed: an asset tag, a serial number or a scan URL. */
    public function find_asset_by_code($code)
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        if (preg_match('~/scan/tag/([^/?#]+)~', $code, $m)) {
            $code = rawurldecode($m[1]);
        }
        $asset = $this->db->where('asset_tag', $code)->where('is_deleted', 0)->get($this->t('ams_assets'))->row();
        if (! $asset) {
            $matches = $this->db->where('serial_no', $code)->where('is_deleted', 0)->limit(2)->get($this->t('ams_assets'))->result();
            $asset   = count($matches) === 1 ? $matches[0] : null;
        }

        return $asset;
    }

    /**
     * Record an asset as seen. $input: found_location_id (default: the audit's
     * location, else where it was expected), asset_condition, note.
     */
    public function record($auditId, $asset, $input = [])
    {
        $audit = $this->db->where('id', (int) $auditId)->get($this->t('ams_audits'))->row();
        if (! $audit || $audit->status !== 'in_progress') {
            return ['success' => false, 'message' => _l('ams_audit_not_running')];
        }
        $asset = is_object($asset) ? $asset : $this->db->where('id', (int) $asset)->where('is_deleted', 0)->get($this->t('ams_assets'))->row();
        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_audit_code_unknown')];
        }

        $line     = $this->db->where('audit_id', (int) $auditId)->where('asset_id', (int) $asset->id)->get($this->t('ams_audit_lines'))->row();
        $explicit = (int) ($input['found_location_id'] ?? 0) ?: null;
        if (! $line) {
            // Not in the snapshot: seen here although expected elsewhere (or out of scope).
            $result   = 'unexpected';
            $expected = $asset->location_id;
            $found    = $explicit ?: ($audit->location_id ? (int) $audit->location_id : null);
        } else {
            // Without an explicit "found at" location it was seen where expected.
            $expected = $line->expected_location_id;
            $found    = $explicit ?: ($expected ? (int) $expected : null);
            $result   = (! $explicit || ! $expected || $explicit === (int) $expected) ? 'found' : 'misplaced';
        }

        $row = [
            'found_location_id' => $found,
            'result'            => $result,
            'asset_condition'   => in_array($input['asset_condition'] ?? '', array_column(ams_condition_options(), 'id'), true) ? $input['asset_condition'] : null,
            'note'              => mb_substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: null,
            'scanned_by'        => get_staff_user_id() ?: null,
            'scanned_at'        => date('Y-m-d H:i:s'),
        ];

        if ($line) {
            $this->db->where('id', (int) $line->id)->update($this->t('ams_audit_lines'), $row);
        } else {
            $this->db->insert($this->t('ams_audit_lines'), $row + ['audit_id' => (int) $auditId, 'asset_id' => (int) $asset->id, 'expected_location_id' => $expected]);
        }

        return ['success' => true, 'result' => $result, 'message' => _l('ams_audit_recorded', [e($asset->asset_tag), _l('ams_audit_result_' . $result)])];
    }

    /** Mark a line missing / back to pending by hand. */
    public function set_result($lineId, $result)
    {
        $line  = $this->db->where('id', (int) $lineId)->get($this->t('ams_audit_lines'))->row();
        $audit = $line ? $this->db->where('id', (int) $line->audit_id)->get($this->t('ams_audits'))->row() : null;
        if (! $audit || $audit->status !== 'in_progress' || ! in_array($result, ['pending', 'missing'], true) || $line->result === 'unexpected') {
            return ['success' => false, 'message' => _l('ams_audit_not_running')];
        }
        $this->db->where('id', (int) $lineId)->update($this->t('ams_audit_lines'), [
            'result'            => $result,
            'found_location_id' => null,
            'scanned_by'        => $result === 'pending' ? null : (get_staff_user_id() ?: null),
            'scanned_at'        => $result === 'pending' ? null : date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => _l('ams_audit_result_' . $result)];
    }

    public function complete($id, $input)
    {
        $audit = $this->get($id);
        if (! $audit || $audit->status !== 'in_progress') {
            return ['success' => false, 'message' => _l('ams_audit_wrong_status')];
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        @set_time_limit(600);
        // One transaction for the whole close-out (one history row per asset: thousands of writes on big audits).
        $this->db->trans_start();
        $this->db->where('audit_id', (int) $id)->where('result', 'pending')->update($this->t('ams_audit_lines'), ['result' => 'missing']);

        $moved = 0;
        $lines = $this->db->where('audit_id', (int) $id)->get($this->t('ams_audit_lines'))->result();
        foreach ($lines as $l) {
            if ($l->result === 'missing') {
                $this->ams_assets_model->add_history($l->asset_id, ['action' => 'audit', 'note' => _l('ams_audit_history_missing', $audit->audit_no)]);
                continue;
            }
            $this->db->where('id', (int) $l->asset_id)->update($this->t('ams_assets'), ['last_audit_date' => $today]);
            if (! empty($input['move_misplaced']) && in_array($l->result, ['misplaced', 'unexpected']) && $l->found_location_id
                && $this->ams_assets_model->move_location($l->asset_id, $l->found_location_id, _l('ams_audit_history_moved', $audit->audit_no))) {
                $moved++;
            }
            $this->ams_assets_model->add_history($l->asset_id, [
                'action' => 'audit',
                'note'   => _l('ams_audit_history_seen', [$audit->audit_no, _l('ams_audit_result_' . $l->result)]) . ($l->note ? ' - ' . $l->note : ''),
            ]);
        }

        $update = ['status' => 'completed', 'completed_at' => $now, 'completed_by' => get_staff_user_id() ?: null];
        if (! empty($input['next_audit_date'])) {
            $update['next_audit_date'] = to_sql_date($input['next_audit_date']);
        }
        $this->db->where('id', (int) $id)->update($this->t('ams_audits'), $update);
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => _l('ams_db_error')];
        }

        $c = $this->counts($id);
        log_activity('AMS audit completed [' . $audit->audit_no . ': found ' . $c['found'] . ', misplaced ' . $c['misplaced'] . ', unexpected ' . $c['unexpected'] . ', missing ' . $c['missing'] . ']');

        return ['success' => true, 'message' => _l('ams_audit_completed', [$c['missing'], $moved])];
    }

    public function cancel($id)
    {
        $audit = $this->get($id);
        if (! $audit || in_array($audit->status, ['completed', 'cancelled'])) {
            return ['success' => false, 'message' => _l('ams_audit_wrong_status')];
        }
        $this->db->where('id', (int) $id)->update($this->t('ams_audits'), ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);

        return ['success' => true, 'message' => _l('ams_audit_cancelled')];
    }

    public function delete($id)
    {
        $audit = $this->get($id);
        if (! $audit || ! in_array($audit->status, ['draft', 'cancelled'])) {
            return ['success' => false, 'message' => _l('ams_audit_cannot_delete')];
        }
        $this->db->where('id', (int) $id)->delete($this->t('ams_audits'));
        $this->db->where('audit_id', (int) $id)->delete($this->t('ams_audit_lines'));

        return ['success' => true, 'message' => _l('deleted', _l('ams_audit'))];
    }
}
