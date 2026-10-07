<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Maintenance & repairs (per asset) and preventive maintenance schedules.
 * Starting a job can put the asset into a "pending" status (e.g. Maintenance,
 * holder kept); completing it restores the right status and, for scheduled
 * jobs, moves the schedule's next due date forward.
 *
 * Responsibility: a job or schedule can have responsible staff and / or Perfex
 * departments (tblams_maintenance_staff). Responsible staff are notified, see
 * their jobs, and may acknowledge, add notes, start and complete them without
 * the Maintenance "edit" permission.
 */
class Ams_maintenance_model extends App_Model
{
    public const CHECK_RESULTS = ['working', 'partial', 'not_working'];

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

        if (! $asset && ! $id) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_asset'))];
        }
        if ($title === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_title'))];
        }
        if (! in_array($type, array_column($this->types(), 'id'))) {
            $type = 'other';
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        if ($supplierId && total_rows($this->t('ams_suppliers'), ['id' => $supplierId]) === 0) {
            return ['success' => false, 'message' => _l('ams_invalid_value', _l('ams_supplier'))];
        }
        $cost = trim(str_replace(',', '', (string) ($input['cost'] ?? '')));
        if ($cost !== '' && (! is_numeric($cost) || (float) $cost < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }

        // Responsible staff / departments: only staff who may edit jobs change them.
        $setResponsible = ! empty($input['responsible_posted']) || ! $id;
        $responsible    = $this->parse_responsible($input);
        if (isset($responsible['error'])) {
            return ['success' => false, 'message' => $responsible['error']];
        }
        if ($setResponsible && ! $responsible['entries'] && get_option('ams_mt_responsible_required') == '1') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_responsible'))];
        }

        // A job opened from an issue report: the report must be an open issue on this asset without a job yet.
        $requestId = (int) ($input['request_id'] ?? 0);
        if (! $id && $requestId) {
            $req = $this->db->where('id', $requestId)->get($this->t('ams_requests'))->row();
            if (! $req || $req->type !== 'issue' || (int) $req->asset_id !== $assetId
                || ! in_array($req->status, ['pending_dept', 'pending_manager', 'approved'], true)) {
                return ['success' => false, 'message' => _l('ams_invalid_value', _l('ams_request'))];
            }
            if (total_rows($this->t('ams_maintenance'), ['request_id' => $requestId, 'status !=' => 'cancelled']) > 0) {
                return ['success' => false, 'message' => _l('ams_mt_request_has_job')];
            }
        }

        $data = [
            'asset_id'    => $assetId,
            'type'        => $type,
            'title'       => mb_substr($title, 0, 191),
            'supplier_id' => $supplierId ?: null,
            'due_date'    => ! empty($input['due_date']) ? to_sql_date($input['due_date']) : null,
            'notes'       => trim((string) ($input['notes'] ?? '')) ?: null,
        ];
        if ($cost !== '') {
            $data['cost'] = round((float) $cost, 2);
        }
        if ($setResponsible) {
            $data['resp_departments'] = $responsible['departments'];
        }

        if ($id) {
            $row = $this->get($id);
            if (! $row || in_array($row->status, ['completed', 'cancelled'])) {
                return ['success' => false, 'message' => _l('ams_mt_closed')];
            }
            unset($data['asset_id']); // the asset of a job never changes
            // A new due date re-arms the overdue reminder.
            if ($data['due_date'] !== $row->due_date) {
                $data['overdue_notified_at'] = null;
            }
            $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), $data);
            if ($setResponsible) {
                $this->set_job_responsible($this->get($id), $responsible['entries']);
            }

            return ['success' => true, 'id' => (int) $id, 'message' => _l('updated_successfully', _l('ams_maintenance'))];
        }

        $data += [
            'status'       => 'scheduled',
            'request_id'   => $requestId ?: null,
            // schedule_id is only set by the cron (process_due_schedules), never from a form.
            'created_by'   => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert($this->t('ams_maintenance'), $data);
        $newId = (int) $this->db->insert_id();

        $this->history($assetId, _l('ams_mt_history_created', $data['title']));
        log_activity('AMS maintenance created [Asset: ' . $asset->asset_tag . ', ' . $data['title'] . ']');

        $this->set_job_responsible($this->get($newId), $responsible['entries']);

        if (! empty($input['start_now'])) {
            $started = $this->start($newId, $input);
            if (! $started['success']) {
                return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_maintenance')) . ' ' . $started['message']];
            }
        }

        return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_maintenance'))];
    }

    /** The first active status of a type ("pending" = the seeded Maintenance, "undeployable" = Damaged). */
    private function status_of_type($type)
    {
        foreach (ams_get_statuses(true) as $s) {
            if ($s['type'] === $type) {
                return $s;
            }
        }

        return null;
    }

    private function maintenance_status()
    {
        return $this->status_of_type('pending');
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
        // Starting the work also counts as picking it up.
        if (! $job->acknowledged_at) {
            $update['acknowledged_by'] = get_staff_user_id() ?: null;
            $update['acknowledged_at'] = date('Y-m-d H:i:s');
        }

        // Optionally mark the asset as under maintenance (the holder is kept).
        $status = $this->maintenance_status();
        if (! empty($input['set_asset_status']) && $status && $asset && $asset->status_type !== 'archived' && (int) $asset->status_id !== (int) $status['id']) {
            $r = $this->ams_assets_model->change_status($job->asset_id, ['status_id' => $status['id'], 'note' => $job->title]);
            if ($r['success']) {
                $update['status_before'] = $asset->status_id;
            }
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), $update);
        $this->add_note($id, _l('ams_mt_note_started'), 'system');

        return ['success' => true, 'message' => _l('ams_mt_started')];
    }

    /** The responsible person confirms they have picked the job up. */
    public function acknowledge($id)
    {
        $job = $this->get($id);
        if (! $job || ! in_array($job->status, ['scheduled', 'in_progress'], true)) {
            return ['success' => false, 'message' => _l('ams_mt_closed')];
        }
        if ($job->acknowledged_at) {
            return ['success' => false, 'message' => _l('ams_mt_already_acknowledged')];
        }
        $this->db->where('id', (int) $id)->where('acknowledged_at IS NULL', null, false)
            ->update($this->t('ams_maintenance'), ['acknowledged_by' => get_staff_user_id() ?: null, 'acknowledged_at' => date('Y-m-d H:i:s')]);
        $this->add_note($id, _l('ams_mt_note_acknowledged'), 'system');

        return ['success' => true, 'message' => _l('ams_mt_acknowledged_success')];
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
        if (! $endDate || $endDate > date('Y-m-d')) {
            return ['success' => false, 'message' => _l('ams_mt_end_date_future')];
        }
        if ($job->start_date && $endDate < $job->start_date) {
            return ['success' => false, 'message' => _l('ams_mt_end_before_start', _d($job->start_date))];
        }
        $downtime = trim((string) ($input['downtime_hours'] ?? ''));
        if ($downtime !== '' && (! is_numeric($downtime) || (float) $downtime < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }

        // Is the asset working after the job? Required for inspection / preventive work.
        $check = (string) ($input['check_result'] ?? '');
        if ($check !== '' && ! in_array($check, self::CHECK_RESULTS, true)) {
            return ['success' => false, 'message' => _l('ams_invalid_value', _l('ams_mt_check_result'))];
        }
        if ($check === '' && in_array($job->type, ['inspection', 'preventive'], true)) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_check_result'))];
        }
        $resolution = trim((string) ($input['resolution'] ?? ''));

        $this->db->trans_begin();

        $this->db->where('id', (int) $id)->where_in('status', ['scheduled', 'in_progress'])->update($this->t('ams_maintenance'), [
            'status'         => 'completed',
            'start_date'     => $job->start_date ?: $endDate,
            'end_date'       => $endDate,
            'cost'           => $cost === '' ? null : round((float) $cost, 2),
            'downtime_hours' => $downtime === '' ? null : round((float) $downtime, 2),
            'resolution'     => $resolution ?: null,
            'check_result'   => $check ?: null,
            'completed_by'   => get_staff_user_id() ?: null,
            'date_completed' => $now,
        ]);
        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_mt_closed')];
        }

        $this->restore_asset_status($job);
        $this->history($job->asset_id, _l('ams_mt_history_completed', $job->title) . ($check ? ' (' . _l('ams_mt_check_' . $check) . ')' : ''));
        $this->add_note($id, _l('ams_mt_note_completed') . ($check ? ' - ' . _l('ams_mt_check_' . $check) : ''), 'system');

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
                    'fulfilment_note' => $resolution ?: $job->title,
                ]);
                ams_notify([$req->staff_id], 'ams_notify_request_updated', [$req->request_no, _l('ams_req_status_fulfilled')], 'asset_management/requests/view/' . (int) $req->id);
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        log_activity('AMS maintenance completed [#' . (int) $id . ', ' . $job->title . ']');

        $message = _l('ams_mt_completed');
        $followup = null;

        // Not working: optionally mark the asset Damaged and open a repair job for the same people.
        if ($check === 'not_working') {
            if (! empty($input['set_damaged'])) {
                $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
                $damaged = $this->status_of_type('undeployable');
                $asset   = $this->ams_assets_model->get($job->asset_id);
                if ($damaged && $asset && $asset->status_type !== 'archived' && (int) $asset->status_id !== (int) $damaged['id']) {
                    $r = $this->ams_assets_model->change_status($job->asset_id, ['status_id' => $damaged['id'], 'note' => $job->title . ': ' . _l('ams_mt_check_not_working')]);
                    if (! $r['success']) {
                        $message .= ' ' . $r['message'];
                    }
                }
            }
            if (! empty($input['create_followup'])) {
                $followup = $this->create_followup($job, $resolution);
                if ($followup) {
                    $message .= ' ' . _l('ams_mt_followup_created');
                }
            }
        }

        $this->notify_closed($job, 'completed');

        return ['success' => true, 'message' => $message, 'id' => $followup];
    }

    /** A repair job for an asset found not working, with the same responsible people. */
    private function create_followup($job, $resolution)
    {
        $this->db->insert($this->t('ams_maintenance'), [
            'asset_id'     => (int) $job->asset_id,
            'type'         => 'repair',
            'title'        => mb_substr(_l('ams_mt_followup_title', $job->title), 0, 191),
            'supplier_id'  => $job->supplier_id,
            'resp_departments' => $job->resp_departments,
            'status'       => 'scheduled',
            'due_date'     => date('Y-m-d', strtotime('+7 days')),
            'notes'        => $resolution ?: null,
            'created_by'   => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
        $newId = (int) $this->db->insert_id();
        if (! $newId) {
            return null;
        }
        $this->db->where('id', (int) $job->id)->update($this->t('ams_maintenance'), ['followup_job_id' => $newId]);
        $this->history($job->asset_id, _l('ams_mt_history_created', _l('ams_mt_followup_title', $job->title)));

        $entries = array_map(fn ($r) => ['staff_id' => $r['staff_id'], 'department_id' => $r['department_id']], $this->responsible('job', $job->id));
        $this->set_job_responsible($this->get($newId), $entries);

        return $newId;
    }

    public function cancel($id)
    {
        $job = $this->get($id);
        if (! $job || ! in_array($job->status, ['scheduled', 'in_progress'])) {
            return ['success' => false, 'message' => _l('ams_mt_closed')];
        }
        $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), ['status' => 'cancelled', 'date_completed' => date('Y-m-d H:i:s')]);
        $this->restore_asset_status($job);
        $this->skip_schedule_occurrence($job);
        $this->history($job->asset_id, _l('ams_mt_history_cancelled', $job->title));
        $this->add_note($id, _l('ams_mt_note_cancelled'), 'system');
        $this->notify_closed($job, 'cancelled');

        return ['success' => true, 'message' => _l('ams_mt_cancelled')];
    }

    public function delete($id)
    {
        $job = $this->get($id);
        if (! $job || $job->status === 'in_progress') {
            return ['success' => false, 'message' => _l('ams_mt_cannot_delete')];
        }
        $this->db->where('id', (int) $id)->delete($this->t('ams_maintenance'));
        $this->db->where('rel_type', 'job')->where('rel_id', (int) $id)->delete($this->t('ams_maintenance_staff'));
        $this->db->where('job_id', (int) $id)->delete($this->t('ams_maintenance_notes'));
        $this->db->where('followup_job_id', (int) $id)->update($this->t('ams_maintenance'), ['followup_job_id' => null]);
        if ($job->status === 'scheduled') {
            $this->skip_schedule_occurrence($job);
        }
        $this->history($job->asset_id, _l('ams_mt_history_deleted', $job->title));
        log_activity('AMS maintenance deleted [#' . (int) $id . ', ' . $job->title . ']');

        return ['success' => true, 'message' => _l('deleted', _l('ams_maintenance'))];
    }

    /**
     * A cron-created job was cancelled / deleted: move its schedule on to the next
     * occurrence so it keeps running (otherwise that due date stays "already
     * reminded" and the schedule never fires again).
     */
    private function skip_schedule_occurrence($job)
    {
        if (! $job->schedule_id) {
            return;
        }
        $s = $this->get_schedule($job->schedule_id);
        if (! $s || ! $s->active) {
            return;
        }
        $next = $s->next_due;
        while ($next <= date('Y-m-d')) {
            $next = $this->add_interval($next, $s->interval_value, $s->interval_unit);
        }
        if ($next === $s->next_due && $s->reminded_for !== $s->next_due) {
            return; // not yet processed for this date: nothing is stuck
        }
        if ($next === $s->next_due) {
            $next = $this->add_interval($next, $s->interval_value, $s->interval_unit);
        }
        $this->db->where('id', (int) $s->id)->update($this->t('ams_maintenance_schedules'), ['next_due' => $next, 'reminded_for' => null]);
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

        // Another job on the asset is still in progress: it inherits the restore instead.
        $other = $this->db->where('asset_id', (int) $job->asset_id)->where('id !=', (int) $job->id)
            ->where('status', 'in_progress')->order_by('id', 'asc')->get($this->t('ams_maintenance'))->row();
        if ($other) {
            if (! $other->status_before) {
                $this->db->where('id', (int) $other->id)->update($this->t('ams_maintenance'), ['status_before' => $job->status_before]);
            }

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

    // ─── Responsibility ───────────────────────────────────────────────────

    /** Options for the Department and Staff pickers; each staff member carries their department ids for filtering. */
    public function responsible_options()
    {
        $map = [];
        foreach ($this->db->select('staffid, departmentid')->get($this->t('staff_departments'))->result_array() as $r) {
            $map[(int) $r['staffid']][] = (int) $r['departmentid'];
        }
        $staff = array_map(fn ($s) => [
            'id'    => (int) $s['staffid'],
            'name'  => $s['firstname'] . ' ' . $s['lastname'],
            'depts' => $map[(int) $s['staffid']] ?? [],
        ], ams_staff_options());
        $depts = array_map(fn ($d) => ['id' => (int) $d['departmentid'], 'name' => $d['name']], ams_department_options());

        return ['staff' => $staff, 'departments' => $depts];
    }

    /**
     * Posted responsible_departments[] + responsible_staff[] → the responsible entries.
     * When departments are chosen, every chosen staff member must belong to one of them;
     * a chosen department none of whose members was picked is responsible as a whole.
     * Without departments, any active staff member can be chosen.
     * Returns ['entries' => [['staff_id' =>, 'department_id' =>], ...], 'departments' => '1,3'|null] or ['error' => ...].
     */
    public function parse_responsible($input)
    {
        $deptIds  = array_values(array_unique(array_filter(array_map('intval', (array) ($input['responsible_departments'] ?? [])))));
        $staffIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['responsible_staff'] ?? [])))));

        $members = []; // department id => member staff ids
        foreach ($deptIds as $d) {
            if (total_rows($this->t('departments'), ['departmentid' => $d]) === 0) {
                return ['error' => _l('ams_invalid_value', _l('ams_mt_resp_department'))];
            }
            $members[$d] = array_map('intval', array_column($this->db->select('staffid')->where('departmentid', $d)
                ->get($this->t('staff_departments'))->result_array(), 'staffid'));
        }

        $entries = [];
        foreach ($staffIds as $id) {
            if (total_rows($this->t('staff'), ['staffid' => $id, 'active' => 1]) === 0) {
                return ['error' => _l('ams_invalid_value', _l('ams_mt_resp_staff'))];
            }
            if ($deptIds && ! array_filter($members, fn ($m) => in_array($id, $m, true))) {
                return ['error' => _l('ams_mt_staff_not_in_department', e(get_staff_full_name($id)))];
            }
            $entries[] = ['staff_id' => $id, 'department_id' => null];
        }
        // Departments where nobody was picked: the whole department is responsible.
        foreach ($members as $d => $m) {
            if (! array_intersect($m, $staffIds)) {
                $entries[] = ['staff_id' => null, 'department_id' => $d];
            }
        }

        return ['entries' => $entries, 'departments' => $deptIds ? implode(',', $deptIds) : null];
    }

    /** Form values of a job / schedule: ['departments' => [ids], 'staff' => [ids]]. */
    public function responsible_form_values($relType, $row)
    {
        $rows = $this->responsible($relType, $row->id);

        return [
            'departments' => array_values(array_filter(array_map('intval', explode(',', (string) $row->resp_departments)))),
            'staff'       => array_values(array_map('intval', array_filter(array_column($rows, 'staff_id')))),
        ];
    }

    /** Responsible rows of a job / schedule with display names. */
    public function responsible($relType, $relId)
    {
        return $this->responsible_map($relType, [(int) $relId])[(int) $relId] ?? [];
    }

    /** [rel_id => rows] for many jobs / schedules at once (lists). */
    public function responsible_map($relType, array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }
        $p    = db_prefix();
        $rows = $this->db->query('SELECT ms.rel_id, ms.staff_id, ms.department_id, s.firstname, s.lastname, s.active, d.name dept_name
            FROM ' . $p . 'ams_maintenance_staff ms
            LEFT JOIN ' . $p . 'staff s ON s.staffid = ms.staff_id
            LEFT JOIN ' . $p . 'departments d ON d.departmentid = ms.department_id
            WHERE ms.rel_type = ? AND ms.rel_id IN (' . implode(',', $ids) . ')
            ORDER BY ms.department_id IS NOT NULL, s.firstname, d.name', [$relType])->result_array();

        $map = [];
        foreach ($rows as $r) {
            $r['label'] = $r['staff_id']
                ? trim($r['firstname'] . ' ' . $r['lastname']) . ($r['active'] ? '' : ' (' . _l('ams_inactive') . ')')
                : _l('ams_mt_department_label', $r['dept_name']);
            $r['value'] = $r['staff_id'] ? 's:' . $r['staff_id'] : 'd:' . $r['department_id'];
            $map[(int) $r['rel_id']][] = $r;
        }

        return $map;
    }

    public function responsible_label(array $rows)
    {
        return implode(', ', array_column($rows, 'label'));
    }

    /** Staff ids behind responsible entries (department members expanded, active staff only). */
    public function staff_ids_for(array $entries)
    {
        $ids   = [];
        $depts = [];
        foreach ($entries as $e) {
            if (! empty($e['staff_id'])) {
                $ids[] = (int) $e['staff_id'];
            } elseif (! empty($e['department_id'])) {
                $depts[] = (int) $e['department_id'];
            }
        }
        if ($depts) {
            $ids = array_merge($ids, array_map('intval', array_column($this->db->query('SELECT sd.staffid FROM ' . db_prefix() . 'staff_departments sd
                JOIN ' . db_prefix() . 'staff s ON s.staffid = sd.staffid AND s.active = 1
                WHERE sd.departmentid IN (' . implode(',', $depts) . ')')->result_array(), 'staffid')));
        }
        if ($ids) {
            $ids = array_map('intval', array_column($this->db->select('staffid')->where_in('staffid', array_unique($ids))->where('active', 1)
                ->get(db_prefix() . 'staff')->result_array(), 'staffid'));
        }

        return array_values(array_unique($ids));
    }

    public function job_staff_ids($jobId)
    {
        return $this->staff_ids_for($this->responsible('job', $jobId));
    }

    /** Is the staff member responsible for the job (directly or through a department)? */
    public function is_responsible($jobId, $staffId = null)
    {
        $staffId = (int) ($staffId ?: get_staff_user_id());

        return (bool) $this->db->query('SELECT 1 FROM ' . db_prefix() . 'ams_maintenance_staff ms
            WHERE ms.rel_type = "job" AND ms.rel_id = ? AND (ms.staff_id = ? OR ms.department_id IN
                (SELECT departmentid FROM ' . db_prefix() . 'staff_departments WHERE staffid = ?)) LIMIT 1', [(int) $jobId, $staffId, $staffId])->row();
    }

    /** SQL condition: the job (alias / table $jobCol) is the staff member's responsibility. */
    public static function mine_sql($jobCol, $staffId, $relType = 'job')
    {
        $p       = db_prefix();
        $staffId = (int) $staffId;
        $relType = $relType === 'schedule' ? 'schedule' : 'job';

        return 'EXISTS (SELECT 1 FROM ' . $p . 'ams_maintenance_staff ms_m WHERE ms_m.rel_type = "' . $relType . '" AND ms_m.rel_id = ' . $jobCol
            . ' AND (ms_m.staff_id = ' . $staffId . ' OR ms_m.department_id IN (SELECT departmentid FROM ' . $p . 'staff_departments WHERE staffid = ' . $staffId . ')))';
    }

    /** Does the staff member have maintenance schedules (directly or through a department)? */
    public function has_schedules($staffId = null)
    {
        $staffId = (int) ($staffId ?: get_staff_user_id());

        return (bool) $this->db->query('SELECT 1 FROM ' . $this->t('ams_maintenance_schedules') . ' s WHERE '
            . self::mine_sql('s.id', $staffId, 'schedule') . ' LIMIT 1')->row();
    }

    public function can_view_job($job)
    {
        return $job && (staff_can('view', 'ams_maintenance') || $this->is_responsible($job->id));
    }

    /** Start / complete / acknowledge / add notes: editors, or the responsible staff. */
    public function can_work_job($job)
    {
        return $job && (staff_can('edit', 'ams_maintenance') || $this->is_responsible($job->id));
    }

    /**
     * Replace the responsible entries of a job / schedule. Returns the added and
     * removed entries (by s:ID / d:ID key).
     */
    private function replace_responsible($relType, $relId, array $entries)
    {
        $current = [];
        foreach ($this->db->where('rel_type', $relType)->where('rel_id', (int) $relId)->get($this->t('ams_maintenance_staff'))->result_array() as $r) {
            $current[$r['staff_id'] ? 's' . $r['staff_id'] : 'd' . $r['department_id']] = $r;
        }
        $wanted = [];
        foreach ($entries as $e) {
            $wanted[! empty($e['staff_id']) ? 's' . $e['staff_id'] : 'd' . $e['department_id']] = $e;
        }

        $added   = array_diff_key($wanted, $current);
        $removed = array_diff_key($current, $wanted);
        foreach ($removed as $r) {
            $this->db->where('id', (int) $r['id'])->delete($this->t('ams_maintenance_staff'));
        }
        foreach ($added as $e) {
            $this->db->insert($this->t('ams_maintenance_staff'), [
                'rel_type'      => $relType,
                'rel_id'        => (int) $relId,
                'staff_id'      => $e['staff_id'] ?: null,
                'department_id' => $e['department_id'] ?: null,
                'added_by'      => get_staff_user_id() ?: null,
                'date_added'    => date('Y-m-d H:i:s'),
            ]);
        }

        return ['added' => array_values($added), 'removed' => array_values($removed)];
    }

    /** Set a job's responsible people: logs the change, notifies the newly added staff (and the issue reporter). */
    public function set_job_responsible($job, array $entries, $notify = true)
    {
        if (! $job) {
            return;
        }
        $change = $this->replace_responsible('job', $job->id, $entries);
        if (! $change['added'] && ! $change['removed']) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = $this->responsible('job', $job->id);
        $upd  = [];
        if ($rows && ! $job->assigned_at) {
            $upd['assigned_at'] = $now;
        }
        if ($change['added']) {
            // New people: they still have to pick it up.
            $upd += ['acknowledged_by' => null, 'acknowledged_at' => null, 'ack_alerted_at' => null, 'assigned_at' => $now];
        }
        if ($upd) {
            $this->db->where('id', (int) $job->id)->update($this->t('ams_maintenance'), $upd);
        }

        $label = $this->responsible_label($rows) ?: _l('ams_mt_unassigned');
        $this->add_note($job->id, _l('ams_mt_note_responsible', $label), 'system');
        $this->history($job->asset_id, _l('ams_mt_history_responsible', [$job->title, $label]));

        if (! $notify || ! in_array($job->status, ['scheduled', 'in_progress'], true)) {
            return;
        }

        // Staff behind the newly added entries who were not already responsible through another entry.
        $kept      = array_filter($rows, fn ($r) => ! in_array($r['value'], array_map(fn ($e) => ! empty($e['staff_id']) ? 's:' . $e['staff_id'] : 'd:' . $e['department_id'], $change['added']), true));
        $newStaff  = array_diff($this->staff_ids_for($change['added']), $this->staff_ids_for($kept));
        $this->notify_assigned($job, $newStaff, $label);

        // A job from an issue report: tell the reporter who is handling it.
        if ($job->request_id && $rows) {
            $req = $this->db->where('id', (int) $job->request_id)->get($this->t('ams_requests'))->row();
            if ($req) {
                ams_notify([$req->staff_id], 'ams_notify_mt_handling', [$req->request_no, $label], 'asset_management/requests/view/' . (int) $req->id);
            }
        }
    }


    private function asset_label($assetId)
    {
        $a = $this->db->select('asset_tag, name')->where('id', (int) $assetId)->get($this->t('ams_assets'))->row();

        return $a ? $a->asset_tag . ' - ' . $a->name : '';
    }

    private function job_link($jobId)
    {
        return 'asset_management/maintenance/view/' . (int) $jobId;
    }

    private function notify_assigned($job, array $staffIds, $label)
    {
        if (! $staffIds) {
            return;
        }
        $asset = $this->asset_label($job->asset_id);
        ams_notify($staffIds, 'ams_notify_mt_assigned', [$job->title, $asset], $this->job_link($job->id));
        foreach ($staffIds as $staffId) {
            if ((int) $staffId === (int) get_staff_user_id()) {
                continue;
            }
            ams_send_email('ams-maintenance-assigned', $staffId, [
                '{ams_item}'        => $asset,
                '{ams_details}'     => $job->title,
                '{ams_due_date}'    => $job->due_date ? _d($job->due_date) : '-',
                '{ams_responsible}' => $label,
                '{ams_link}'        => admin_url($this->job_link($job->id)),
            ]);
        }
    }

    /** Completed / cancelled: tell the other responsible staff and whoever created the job. */
    private function notify_closed($job, $status)
    {
        $ids = $this->job_staff_ids($job->id);
        if ($job->created_by) {
            $ids[] = (int) $job->created_by;
        }
        ams_notify($ids, 'ams_notify_mt_closed', [_l('ams_mt_status_' . $status), $job->title, $this->asset_label($job->asset_id)], $this->job_link($job->id));
    }

    /** Managers get maintenance alerts when nobody is responsible, or when the setting says so. */
    private function managers_for($hasResponsible)
    {
        return (! $hasResponsible || get_option('ams_mt_notify_managers') == '1') ? ams_manager_recipients() : [];
    }

    /** Bulk: add or replace the responsible people of several open jobs. */
    public function bulk_responsible(array $jobIds, array $parsed, $mode)
    {
        $entries = $parsed['entries'];
        $done = 0;
        foreach (array_unique(array_map('intval', $jobIds)) as $id) {
            $job = $this->get($id);
            if (! $job || ! in_array($job->status, ['scheduled', 'in_progress'], true)) {
                continue;
            }
            $target = $entries;
            if ($mode === 'add') {
                $target = array_merge(array_map(fn ($r) => ['staff_id' => $r['staff_id'], 'department_id' => $r['department_id']], $this->responsible('job', $id)), $entries);
            } else {
                $this->db->where('id', (int) $id)->update($this->t('ams_maintenance'), ['resp_departments' => $parsed['departments']]);
            }
            $this->set_job_responsible($job, $target);
            $done++;
        }

        return ['success' => $done > 0, 'message' => $done ? _l('ams_mt_bulk_done', $done) : _l('ams_mt_bulk_none')];
    }

    // ─── Progress notes ───────────────────────────────────────────────────

    public function add_note($jobId, $note, $type = 'note')
    {
        $note = trim((string) $note);
        if ($note === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_note'))];
        }
        if ($type === 'note') {
            $job = $this->get($jobId);
            if (! $job) {
                return ['success' => false, 'message' => _l('ams_not_found')];
            }
        }
        $this->db->insert($this->t('ams_maintenance_notes'), [
            'job_id'       => (int) $jobId,
            'type'         => $type === 'system' ? 'system' : 'note',
            'note'         => mb_substr($note, 0, 5000),
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => _l('ams_mt_note_added')];
    }

    public function notes($jobId)
    {
        return $this->db->select('n.*, s.firstname, s.lastname')->from($this->t('ams_maintenance_notes') . ' n')
            ->join($this->t('staff') . ' s', 's.staffid = n.staff_id', 'left')
            ->where('n.job_id', (int) $jobId)->order_by('n.date_created', 'desc')->order_by('n.id', 'desc')->get()->result_array();
    }

    // ─── Staff lifecycle ──────────────────────────────────────────────────

    /** Open jobs a staff member is directly responsible for. */
    public function open_jobs_for_staff($staffId)
    {
        return (int) $this->db->query('SELECT COUNT(DISTINCT m.id) c FROM ' . $this->t('ams_maintenance') . ' m
            JOIN ' . $this->t('ams_maintenance_staff') . ' ms ON ms.rel_type = "job" AND ms.rel_id = m.id AND ms.staff_id = ?
            WHERE m.status IN ("scheduled", "in_progress")', [(int) $staffId])->row()->c;
    }

    /** Deleted staff: their jobs and schedules move to the "transfer data to" staff member. */
    public function transfer_staff($from, $to)
    {
        foreach ($this->db->where('staff_id', (int) $from)->get($this->t('ams_maintenance_staff'))->result_array() as $r) {
            $exists = total_rows($this->t('ams_maintenance_staff'), ['rel_type' => $r['rel_type'], 'rel_id' => $r['rel_id'], 'staff_id' => (int) $to]) > 0;
            if ($exists) {
                $this->db->where('id', (int) $r['id'])->delete($this->t('ams_maintenance_staff'));
            } else {
                $this->db->where('id', (int) $r['id'])->update($this->t('ams_maintenance_staff'), ['staff_id' => (int) $to]);
            }
        }
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
        $parsed = $this->parse_responsible($input);
        if (isset($parsed['error'])) {
            return ['success' => false, 'message' => $parsed['error']];
        }
        $responsible = $parsed['entries'];
        if (! $responsible && get_option('ams_mt_responsible_required') == '1') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_responsible'))];
        }

        $data = [
            'title'          => mb_substr($title, 0, 191),
            'type'           => in_array($input['type'] ?? '', array_column($this->types(), 'id')) ? $input['type'] : 'preventive',
            'interval_value' => $value,
            'interval_unit'  => $unit,
            'next_due'       => $next,
            'supplier_id'    => (int) ($input['supplier_id'] ?? 0) ?: null,
            'active'         => ! empty($input['active']) ? 1 : 0,
            'resp_departments' => $parsed['departments'],
        ];

        if ($data['supplier_id'] && total_rows($this->t('ams_suppliers'), ['id' => $data['supplier_id']]) === 0) {
            return ['success' => false, 'message' => _l('ams_invalid_value', _l('ams_supplier'))];
        }

        if ($id) {
            $old = $this->get_schedule($id);
            if (! $old) {
                return ['success' => false, 'message' => _l('ams_not_found')];
            }
            // Only a new due date re-arms the reminder (otherwise every edit would re-send it).
            if ($old->next_due !== $data['next_due']) {
                $data['reminded_for'] = null;
            }
            $this->db->where('id', (int) $id)->update($this->t('ams_maintenance_schedules'), $data);
            $change = $this->replace_responsible('schedule', $id, $responsible);
            $this->notify_schedule_assigned($this->get_schedule($id), $change['added']);

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
            $sid    = (int) $this->db->insert_id();
            $change = $this->replace_responsible('schedule', $sid, $responsible);
            $this->notify_schedule_assigned($this->get_schedule($sid), $change['added']);
        }

        if (! $created) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_assets'))];
        }

        return ['success' => true, 'message' => _l('ams_mt_schedules_created', $created)];
    }

    private function notify_schedule_assigned($s, array $added)
    {
        if (! $s || ! $added || ! $s->active) {
            return;
        }
        $staffIds = $this->staff_ids_for($added);
        $asset    = $this->asset_label($s->asset_id);
        $link     = 'asset_management/maintenance/schedules?mine=1';
        $who      = $this->responsible_label($this->responsible('schedule', $s->id));
        ams_notify($staffIds, 'ams_notify_mt_schedule_assigned', [$s->title, $asset, _d($s->next_due)], $link);
        foreach ($staffIds as $staffId) {
            if ((int) $staffId === (int) get_staff_user_id()) {
                continue;
            }
            ams_send_email('ams-maintenance-schedule-assigned', $staffId, [
                '{ams_item}'        => $asset,
                '{ams_details}'     => $s->title . ' - ' . _l('ams_mt_every', [(int) $s->interval_value, _l('ams_unit_' . $s->interval_unit)]),
                '{ams_due_date}'    => _d($s->next_due),
                '{ams_responsible}' => $who,
                '{ams_link}'        => admin_url($link),
            ]);
        }
    }

    public function delete_schedule($id)
    {
        if (! $this->get_schedule($id)) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        // Jobs it already opened stay (with their history) but no longer point at it.
        $this->db->where('schedule_id', (int) $id)->update($this->t('ams_maintenance'), ['schedule_id' => null]);
        $this->db->where('id', (int) $id)->delete($this->t('ams_maintenance_schedules'));
        $this->db->where('rel_type', 'schedule')->where('rel_id', (int) $id)->delete($this->t('ams_maintenance_staff'));

        return ['success' => true, 'message' => _l('deleted', _l('ams_mt_schedule'))];
    }

    /**
     * Cron: for schedules due within the lead time, open a "scheduled" job (once
     * per due date) with the schedule's responsible people, and tell them (and the
     * asset managers, per setting).
     */
    public function process_due_schedules()
    {
        $until   = date('Y-m-d', strtotime('+' . max(0, (int) get_option('ams_maintenance_lead_days')) . ' days'));
        $created = 0;

        $rows = $this->db->query('SELECT s.*, a.asset_tag, a.name asset_name FROM ' . $this->t('ams_maintenance_schedules') . ' s
            JOIN ' . $this->t('ams_assets') . ' a ON a.id = s.asset_id AND a.is_deleted = 0
            WHERE s.active = 1 AND s.next_due <= ? AND (s.reminded_for IS NULL OR s.reminded_for <> s.next_due)', [$until])->result_array();

        foreach ($rows as $s) {
            $open = $this->db->where('schedule_id', (int) $s['id'])->where_in('status', ['scheduled', 'in_progress'])
                ->order_by('id', 'desc')->get($this->t('ams_maintenance'))->row();
            $responsible = array_map(fn ($r) => ['staff_id' => $r['staff_id'], 'department_id' => $r['department_id']], $this->responsible('schedule', $s['id']));

            if (! $open) {
                $this->db->insert($this->t('ams_maintenance'), [
                    'asset_id'     => (int) $s['asset_id'],
                    'schedule_id'  => (int) $s['id'],
                    'type'         => $s['type'],
                    'title'        => $s['title'],
                    'supplier_id'  => $s['supplier_id'],
                    'resp_departments' => $s['resp_departments'],
                    'status'       => 'scheduled',
                    'due_date'     => $s['next_due'],
                    'date_created' => date('Y-m-d H:i:s'),
                ]);
                $jobId = (int) $this->db->insert_id();
                // The due notice below replaces the "assigned" one for cron-opened jobs.
                $this->set_job_responsible($this->get($jobId), $responsible, false);
                $created++;
            } else {
                $jobId = (int) $open->id;
            }

            // Mark first: a crash while sending must not send the same reminder again.
            $this->db->where('id', (int) $s['id'])->update($this->t('ams_maintenance_schedules'), ['reminded_for' => $s['next_due']]);

            $label      = $s['asset_tag'] . ' - ' . $s['asset_name'];
            $staffIds   = $this->job_staff_ids($jobId);
            $recipients = array_values(array_unique(array_merge($staffIds, $this->managers_for((bool) $staffIds))));
            $who        = $this->responsible_label($this->responsible('job', $jobId)) ?: _l('ams_mt_unassigned');

            ams_notify($recipients, 'ams_notify_maintenance_due', [$s['title'], $label, _d($s['next_due'])], $this->job_link($jobId));
            foreach ($recipients as $staffId) {
                ams_send_email('ams-maintenance-due', $staffId, [
                    '{ams_item}'        => $label,
                    '{ams_details}'     => $s['title'],
                    '{ams_due_date}'    => _d($s['next_due']),
                    '{ams_responsible}' => $who,
                    '{ams_link}'        => admin_url($this->job_link($jobId)),
                ]);
            }
        }

        return $created;
    }

    /**
     * Cron (every 6 hours): overdue jobs remind their responsible staff every N days
     * (managers once); jobs not acknowledged N days after assignment alert the managers once.
     */
    public function process_followups()
    {
        $sent  = 0;
        $every = max(1, (int) get_option('ams_mt_overdue_days'));
        $limit = date('Y-m-d H:i:s', strtotime('-' . $every . ' days'));

        $overdue = $this->db->query('SELECT m.*, a.asset_tag, a.name asset_name FROM ' . $this->t('ams_maintenance') . ' m
            JOIN ' . $this->t('ams_assets') . ' a ON a.id = m.asset_id AND a.is_deleted = 0
            WHERE m.status IN ("scheduled", "in_progress") AND m.due_date < CURDATE()
              AND (m.overdue_notified_at IS NULL OR m.overdue_notified_at <= ?)', [$limit])->result();

        foreach ($overdue as $job) {
            $first = ! $job->overdue_notified_at;
            $this->db->where('id', (int) $job->id)->update($this->t('ams_maintenance'), ['overdue_notified_at' => date('Y-m-d H:i:s')]);

            $label    = $job->asset_tag . ' - ' . $job->asset_name;
            $staffIds = $this->job_staff_ids($job->id);
            $who      = $this->responsible_label($this->responsible('job', $job->id)) ?: _l('ams_mt_unassigned');

            ams_notify($staffIds, 'ams_notify_mt_overdue', [$job->title, $label, _d($job->due_date)], $this->job_link($job->id));
            foreach ($staffIds as $staffId) {
                ams_send_email('ams-maintenance-overdue', $staffId, [
                    '{ams_item}'        => $label,
                    '{ams_details}'     => $job->title,
                    '{ams_due_date}'    => _d($job->due_date),
                    '{ams_responsible}' => $who,
                    '{ams_link}'        => admin_url($this->job_link($job->id)),
                ]);
            }
            if ($first) {
                $managers = array_diff($this->managers_for((bool) $staffIds), $staffIds);
                ams_notify($managers, 'ams_notify_mt_overdue_manager', [$job->title, $label, _d($job->due_date), $who], $this->job_link($job->id));
            }
            $sent++;
        }

        $ackDays = (int) get_option('ams_mt_ack_days');
        if ($ackDays > 0) {
            $before = date('Y-m-d H:i:s', strtotime('-' . $ackDays . ' days'));
            $late   = $this->db->query('SELECT m.*, a.asset_tag, a.name asset_name FROM ' . $this->t('ams_maintenance') . ' m
                JOIN ' . $this->t('ams_assets') . ' a ON a.id = m.asset_id AND a.is_deleted = 0
                WHERE m.status = "scheduled" AND m.acknowledged_at IS NULL AND m.ack_alerted_at IS NULL
                  AND m.assigned_at IS NOT NULL AND m.assigned_at <= ?', [$before])->result();
            foreach ($late as $job) {
                $this->db->where('id', (int) $job->id)->update($this->t('ams_maintenance'), ['ack_alerted_at' => date('Y-m-d H:i:s')]);
                $who = $this->responsible_label($this->responsible('job', $job->id));
                ams_notify(ams_manager_recipients(), 'ams_notify_mt_not_ack', [$job->title, $job->asset_tag . ' - ' . $job->asset_name, $who], $this->job_link($job->id));
                $sent++;
            }
        }

        return $sent;
    }
}
