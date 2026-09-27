<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Maintenance & repairs (per asset) and preventive maintenance schedules.
 * Starting a job can put the asset into a "pending" status (e.g. Maintenance,
 * holder kept); completing it restores the right status and, for scheduled
 * jobs, moves the schedule's next due date forward.
 */
class Ams_maintenance_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function types()
    {
        return array_map(fn ($t) => ['id' => $t, 'name' => _l('ams_mt_type_' . $t)], ['repair', 'upgrade', 'preventive', 'inspection', 'software', 'other']);
    }

    public function get($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_maintenance'))->row();
    }

    public function save($input, $id = null)
    {
        $assetId = (int) ($input['asset_id'] ?? 0);
        $asset   = $this->db->where('id', $assetId)->where('is_deleted', 0)->get($this->t('ams_assets'))->row();
        $title   = trim((string) ($input['title'] ?? ''));
        $type    = $input['type'] ?? 'repair';

        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_asset'))];
        }
        if ($title === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_title'))];
        }
        if (! in_array($type, array_column($this->types(), 'id'))) {
            $type = 'other';
        }

        $data = [
            'asset_id'    => $assetId,
            'type'        => $type,
            'title'       => mb_substr($title, 0, 191),
            'supplier_id' => (int) ($input['supplier_id'] ?? 0) ?: null,
            'due_date'    => ! empty($input['due_date']) ? to_sql_date($input['due_date']) : null,
            'notes'       => trim((string) ($input['notes'] ?? '')) ?: null,
        ];

        if ($id) {
            $row = $this->get($id);
            if (! $row || in_array($row->status, ['completed', 'cancelled'])) {
                return ['success' => false, 'message' => _l('ams_mt_closed')];
            }
            unset($data['asset_id']); // the asset of a job never changes
            $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), $data);

            return ['success' => true, 'id' => (int) $id, 'message' => _l('updated_successfully', _l('ams_maintenance'))];
        }

        $data += [
            'status'       => 'scheduled',
            'request_id'   => (int) ($input['request_id'] ?? 0) ?: null,
            'schedule_id'  => (int) ($input['schedule_id'] ?? 0) ?: null,
            'created_by'   => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert($this->t('ams_maintenance'), $data);
        $newId = (int) $this->db->insert_id();

        $this->history($assetId, _l('ams_mt_history_created', $data['title']));
        log_activity('AMS maintenance created [Asset: ' . $asset->asset_tag . ', ' . $data['title'] . ']');

        if (! empty($input['start_now'])) {
            $started = $this->start($newId, $input);
            if (! $started['success']) {
                return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_maintenance')) . ' ' . $started['message']];
            }
        }

        return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_maintenance'))];
    }

    /** The first active "pending"-type status (the seeded "Maintenance"). */
    private function maintenance_status()
    {
        foreach (ams_get_statuses(true) as $s) {
            if ($s['type'] === 'pending') {
                return $s;
            }
        }

        return null;
    }

    public function start($id, $input = [])
    {
        $job = $this->get($id);
        if (! $job || $job->status !== 'scheduled') {
            return ['success' => false, 'message' => _l('ams_mt_cannot_start')];
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $asset  = $this->ams_assets_model->get($job->asset_id);
        $update = [
            'status'     => 'in_progress',
            'start_date' => ! empty($input['start_date']) ? to_sql_date($input['start_date']) : date('Y-m-d'),
        ];

        // Optionally mark the asset as under maintenance (the holder is kept).
        $status = $this->maintenance_status();
        if (! empty($input['set_asset_status']) && $status && $asset && $asset->status_type !== 'archived' && (int) $asset->status_id !== (int) $status['id']) {
            $r = $this->ams_assets_model->change_status($job->asset_id, ['status_id' => $status['id'], 'note' => $job->title]);
            if ($r['success']) {
                $update['status_before'] = $asset->status_id;
            }
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), $update);

        return ['success' => true, 'message' => _l('ams_mt_started')];
    }

    public function complete($id, $input)
    {
        $job = $this->get($id);
        if (! $job || ! in_array($job->status, ['scheduled', 'in_progress'])) {
            return ['success' => false, 'message' => _l('ams_mt_closed')];
        }

        $cost = trim((string) ($input['cost'] ?? ''));
        if ($cost !== '' && (! is_numeric($cost) || (float) $cost < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }

        $endDate = ! empty($input['end_date']) ? to_sql_date($input['end_date']) : date('Y-m-d');
        $now     = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), [
            'status'         => 'completed',
            'start_date'     => $job->start_date ?: $endDate,
            'end_date'       => $endDate,
            'cost'           => $cost === '' ? null : round((float) $cost, 2),
            'downtime_hours' => is_numeric($input['downtime_hours'] ?? '') ? (float) $input['downtime_hours'] : null,
            'resolution'     => trim((string) ($input['resolution'] ?? '')) ?: null,
            'completed_by'   => get_staff_user_id() ?: null,
            'date_completed' => $now,
        ]);

        $this->restore_asset_status($job);
        $this->history($job->asset_id, _l('ams_mt_history_completed', $job->title));

        // Scheduled job: the next due date counts from when the work was actually done.
        if ($job->schedule_id) {
            $s = $this->db->where('id', (int) $job->schedule_id)->get($this->t('ams_maintenance_schedules'))->row();
            if ($s) {
                $this->db->where('id', (int) $s->id)->update($this->t('ams_maintenance_schedules'), [
                    'last_done'    => $endDate,
                    'next_due'     => $this->add_interval($endDate, $s->interval_value, $s->interval_unit),
                    'reminded_for' => null,
                ]);
            }
        }

        // Job created from an issue report: close the request.
        if ($job->request_id) {
            $req = $this->db->where('id', (int) $job->request_id)->get($this->t('ams_requests'))->row();
            if ($req && in_array($req->status, ['pending_dept', 'pending_manager', 'approved'])) {
                $this->db->where('id', (int) $req->id)->update($this->t('ams_requests'), [
                    'status'          => 'fulfilled',
                    'fulfilled_by'    => get_staff_user_id() ?: null,
                    'fulfilled_at'    => $now,
                    'fulfilment_note' => trim((string) ($input['resolution'] ?? '')) ?: $job->title,
                ]);
                ams_notify([$req->staff_id], 'ams_notify_request_updated', [$req->request_no, _l('ams_req_status_fulfilled')], 'asset_management/requests/view/' . (int) $req->id);
            }
        }

        log_activity('AMS maintenance completed [#' . (int) $id . ', ' . $job->title . ']');

        return ['success' => true, 'message' => _l('ams_mt_completed')];
    }

    public function cancel($id)
    {
        $job = $this->get($id);
        if (! $job || ! in_array($job->status, ['scheduled', 'in_progress'])) {
            return ['success' => false, 'message' => _l('ams_mt_closed')];
        }
        $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), ['status' => 'cancelled', 'date_completed' => date('Y-m-d H:i:s')]);
        $this->restore_asset_status($job);

        return ['success' => true, 'message' => _l('ams_mt_cancelled')];
    }

    public function delete($id)
    {
        $job = $this->get($id);
        if (! $job || $job->status === 'in_progress') {
            return ['success' => false, 'message' => _l('ams_mt_cannot_delete')];
        }
        $this->db->where('id', (int) $id)->delete($this->t('ams_maintenance'));

        return ['success' => true, 'message' => _l('deleted', _l('ams_maintenance'))];
    }

    /**
     * If the job put the asset into Maintenance and it is still there, restore it:
     * still held by someone → the system "Assigned" status; otherwise the status it
     * had before (or In Store).
     */
    private function restore_asset_status($job)
    {
        if (! $job->status_before) {
            return;
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $asset  = $this->ams_assets_model->get($job->asset_id);
        $maint  = $this->maintenance_status();
        if (! $asset || ! $maint || (int) $asset->status_id !== (int) $maint['id']) {
            return; // someone already moved it on
        }

        if ($asset->assigned_type) {
            $target = ams_get_status_by_key('assigned');
        } else {
            $target = ams_get_status($job->status_before);
            if (! $target || in_array($target['type'], ['deployed', 'archived'])) {
                $target = ams_get_status_by_key('in_store');
            }
        }
        if (! $target) {
            return;
        }

        $this->db->where('id', (int) $asset->id)->update($this->t('ams_assets'), ['status_id' => $target['id'], 'date_updated' => date('Y-m-d H:i:s')]);
        $this->db->insert($this->t('ams_asset_history'), [
            'asset_id'         => (int) $asset->id,
            'action'           => 'status',
            'status_from'      => (int) $asset->status_id,
            'status_to'        => (int) $target['id'],
            'assigned_type_to' => $asset->assigned_type,
            'assigned_id_to'   => $asset->assigned_id,
            'location_to'      => $asset->location_id,
            'note'             => _l('ams_mt_history_status_restored', $job->title),
            'staff_id'         => get_staff_user_id() ?: null,
            'date_created'     => date('Y-m-d H:i:s'),
        ]);
    }

    private function history($assetId, $note)
    {
        $this->db->insert($this->t('ams_asset_history'), [
            'asset_id'     => (int) $assetId,
            'action'       => 'maintenance',
            'note'         => $note,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
    }

    public function asset_total_cost($assetId)
    {
        return (float) $this->db->query('SELECT IFNULL(SUM(cost), 0) c FROM ' . $this->t('ams_maintenance') . ' WHERE asset_id = ? AND status = "completed"', [(int) $assetId])->row()->c;
    }

    // ─── Schedules ────────────────────────────────────────────────────────

    public function add_interval($date, $value, $unit)
    {
        $unit = in_array($unit, ['day', 'week', 'month', 'year']) ? $unit : 'month';

        return date('Y-m-d', strtotime($date . ' +' . max(1, (int) $value) . ' ' . $unit));
    }

    public function get_schedule($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_maintenance_schedules'))->row();
    }

    /** Create (one schedule per selected asset) or edit a single schedule. */
    public function save_schedule($input, $id = null)
    {
        $title = trim((string) ($input['title'] ?? ''));
        $value = (int) ($input['interval_value'] ?? 0);
        $unit  = $input['interval_unit'] ?? 'month';
        $next  = ! empty($input['next_due']) ? to_sql_date($input['next_due']) : null;

        if ($title === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_title'))];
        }
        if ($value < 1 || ! in_array($unit, ['day', 'week', 'month', 'year'])) {
            return ['success' => false, 'message' => _l('ams_mt_interval_invalid')];
        }
        if (! $next) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_next_due'))];
        }

        $data = [
            'title'          => mb_substr($title, 0, 191),
            'type'           => in_array($input['type'] ?? '', array_column($this->types(), 'id')) ? $input['type'] : 'preventive',
            'interval_value' => $value,
            'interval_unit'  => $unit,
            'next_due'       => $next,
            'supplier_id'    => (int) ($input['supplier_id'] ?? 0) ?: null,
            'active'         => ! empty($input['active']) ? 1 : 0,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t('ams_maintenance_schedules'), $data + ['reminded_for' => null]);

            return ['success' => true, 'message' => _l('updated_successfully', _l('ams_mt_schedule'))];
        }

        $assetIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['asset_ids'] ?? [])))));
        if (! $assetIds) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_assets'))];
        }
        $created = 0;
        foreach ($assetIds as $assetId) {
            if (total_rows($this->t('ams_assets'), ['id' => $assetId, 'is_deleted' => 0]) === 0) {
                continue;
            }
            $created++;
            $this->db->insert($this->t('ams_maintenance_schedules'), $data + [
                'asset_id'     => $assetId,
                'created_by'   => get_staff_user_id() ?: null,
                'date_created' => date('Y-m-d H:i:s'),
            ]);
        }

        if (! $created) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_assets'))];
        }

        return ['success' => true, 'message' => _l('ams_mt_schedules_created', $created)];
    }

    public function delete_schedule($id)
    {
        $this->db->where('id', (int) $id)->delete($this->t('ams_maintenance_schedules'));

        return ['success' => true, 'message' => _l('deleted', _l('ams_mt_schedule'))];
    }

    /**
     * Cron: for schedules due within the lead time, open a "scheduled" job (once
     * per due date) and tell the asset managers.
     */
    public function process_due_schedules()
    {
        $until   = date('Y-m-d', strtotime('+' . max(0, (int) get_option('ams_maintenance_lead_days')) . ' days'));
        $created = 0;

        $rows = $this->db->query('SELECT s.*, a.asset_tag, a.name asset_name FROM ' . $this->t('ams_maintenance_schedules') . ' s
            JOIN ' . $this->t('ams_assets') . ' a ON a.id = s.asset_id AND a.is_deleted = 0
            WHERE s.active = 1 AND s.next_due <= ? AND (s.reminded_for IS NULL OR s.reminded_for <> s.next_due)', [$until])->result_array();

        foreach ($rows as $s) {
            $open = total_rows($this->t('ams_maintenance'), ['schedule_id' => (int) $s['id'], 'status' => 'scheduled'])
                + total_rows($this->t('ams_maintenance'), ['schedule_id' => (int) $s['id'], 'status' => 'in_progress']);

            if (! $open) {
                $this->db->insert($this->t('ams_maintenance'), [
                    'asset_id'     => (int) $s['asset_id'],
                    'schedule_id'  => (int) $s['id'],
                    'type'         => $s['type'],
                    'title'        => $s['title'],
                    'supplier_id'  => $s['supplier_id'],
                    'status'       => 'scheduled',
                    'due_date'     => $s['next_due'],
                    'date_created' => date('Y-m-d H:i:s'),
                ]);
                $created++;
            }

            $label = $s['asset_tag'] . ' - ' . $s['asset_name'];
            ams_notify(ams_manager_recipients(), 'ams_notify_maintenance_due', [$s['title'], $label, _d($s['next_due'])], 'asset_management/maintenance');
            foreach (ams_manager_recipients() as $staffId) {
                ams_send_email('ams-maintenance-due', $staffId, [
                    '{ams_item}'     => $label,
                    '{ams_details}'  => $s['title'],
                    '{ams_due_date}' => _d($s['next_due']),
                    '{ams_link}'     => admin_url('asset_management/maintenance'),
                ]);
            }

            $this->db->where('id', (int) $s['id'])->update($this->t('ams_maintenance_schedules'), ['reminded_for' => $s['next_due']]);
        }

        return $created;
    }
}
