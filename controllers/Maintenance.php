<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Maintenance jobs and preventive schedules. Permission group: ams_maintenance.
 * "view" sees every job, "view_own" only the jobs the staff member is responsible
 * for. Responsible staff may open, acknowledge, add notes to, start and complete
 * their own jobs without "edit".
 */
class Maintenance extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['save', 'start', 'complete', 'cancel', 'delete', 'save_schedule', 'delete_schedule', 'acknowledge', 'add_note', 'bulk_responsible']);
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
    }

    public function index()
    {
        if (! ams_can_see_maintenance()) {
            access_denied('ams_maintenance');
        }

        $data['title']       = _l('ams_maintenance');
        $data['table']       = App_table::find('ams_maintenance');
        $data['types']       = $this->ams_maintenance_model->types();
        $data['suppliers']   = ams_supplier_options(true);
        $data['assets']      = $this->asset_options();
        $data['responsible'] = $this->ams_maintenance_model->responsible_options();
        $data['request']     = null;

        // "Create maintenance" from an issue report.
        if ($reqId = (int) $this->input->get('request_id')) {
            $req = $this->db->where('id', $reqId)->where('type', 'issue')->get(db_prefix() . 'ams_requests')->row();
            if ($req) {
                $data['request'] = $req;
            }
        }

        $this->load->view(AMS_MODULE_NAME . '/maintenance/manage', $data);
    }

    /** One job: asset summary, responsible people, progress notes and actions. */
    public function view($id)
    {
        $job = $this->ams_maintenance_model->get($id);
        if (! $job) {
            show_404();
        }
        if (! $this->ams_maintenance_model->can_view_job($job)) {
            access_denied('ams_maintenance');
        }

        $p = db_prefix();
        $data['title']       = $job->title;
        $data['job']         = $job;
        $data['asset']       = $this->db->query('SELECT a.id, a.asset_tag, a.name, a.serial_no, a.assigned_type, a.assigned_id, s.name status_name, s.color status_color,
                IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) category_name, l.name location_name
            FROM ' . $p . 'ams_assets a
            LEFT JOIN ' . $p . 'ams_statuses s ON s.id = a.status_id
            LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id
            LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id
            LEFT JOIN ' . $p . 'ams_locations l ON l.id = a.location_id
            WHERE a.id = ?', [(int) $job->asset_id])->row();
        $data['responsible'] = $this->ams_maintenance_model->responsible('job', $job->id);
        $data['notes']       = $this->ams_maintenance_model->notes($job->id);
        $data['supplier']    = $job->supplier_id ? $this->db->where('id', (int) $job->supplier_id)->get($p . 'ams_suppliers')->row() : null;
        $data['request']     = $job->request_id ? $this->db->where('id', (int) $job->request_id)->get($p . 'ams_requests')->row() : null;
        $data['followup']    = $job->followup_job_id ? $this->ams_maintenance_model->get($job->followup_job_id) : null;
        $data['canWork']     = $this->ams_maintenance_model->can_work_job($job);
        $data['canEdit']     = staff_can('edit', 'ams_maintenance');
        $data['canDelete']   = staff_can('delete', 'ams_maintenance');
        $data['canAsset']    = staff_can('view', 'ams_assets');
        $data['types']       = $this->ams_maintenance_model->types();
        $data['suppliers']   = ams_supplier_options(true);
        $data['options']     = $this->ams_maintenance_model->responsible_options();
        $this->load->view(AMS_MODULE_NAME . '/maintenance/view', $data);
    }

    /**
     * Jobs table. /table/{assetId} for one asset; ?mine=1 or no "view" permission
     * limits it to the current user's jobs.
     */
    public function table($assetId = 0)
    {
        if (! ams_can_see_maintenance()) {
            ajax_access_denied();
        }
        $mine = staff_cant('view', 'ams_maintenance') || $this->input->get('mine') ? (int) get_staff_user_id() : 0;
        App_table::find('ams_maintenance')->output(['asset_id' => (int) $assetId, 'mine' => $mine]);
    }

    /** My Assets → My Maintenance Jobs, and Assets by Staff → staff page (managers). */
    public function staff_table($staffId = 0)
    {
        $staffId = (int) $staffId ?: (int) get_staff_user_id();
        if ($staffId !== (int) get_staff_user_id() && staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        App_table::find('ams_maintenance')->output(['asset_id' => 0, 'mine' => $staffId, 'open_only' => (bool) $this->input->get('open')]);
    }

    public function get($id)
    {
        $job = $this->ams_maintenance_model->get($id);
        if (! $job || ! $this->ams_maintenance_model->can_view_job($job)) {
            ajax_access_denied();
        }
        foreach (['due_date', 'start_date', 'end_date'] as $f) {
            $job->{$f} = $job->{$f} ? _d($job->{$f}) : '';
        }
        $job->responsible = $this->ams_maintenance_model->responsible_form_values('job', $job);
        $this->json_raw($job);
    }

    public function save()
    {
        $id = (int) $this->input->post('id');
        if (staff_cant($id ? 'edit' : 'create', 'ams_maintenance')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_maintenance_model->save($this->input->post(), $id ?: null));
    }

    public function start($id)
    {
        $this->require_work($id);
        $this->json($this->ams_maintenance_model->start($id, $this->input->post() ?: []));
    }

    public function complete($id)
    {
        $this->require_work($id);
        $this->json($this->ams_maintenance_model->complete($id, $this->input->post() ?: []));
    }

    public function acknowledge($id)
    {
        $job = $this->ams_maintenance_model->get($id);
        // Only the people responsible pick a job up (an editor who is not responsible cannot do it for them).
        if (! $job || ! $this->ams_maintenance_model->is_responsible($job->id)) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_maintenance_model->acknowledge($id));
    }

    public function add_note($id)
    {
        $this->require_work($id);
        $this->json($this->ams_maintenance_model->add_note($id, $this->input->post('note')));
    }

    public function cancel($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_maintenance_model->cancel($id));
    }

    public function delete($id)
    {
        $this->require_cap('delete');
        $r = $this->ams_maintenance_model->delete($id);
        set_alert($r['success'] ? 'success' : 'warning', $r['message']);
        redirect(admin_url('asset_management/maintenance'));
    }

    /** Bulk: add or replace the responsible people of the selected open jobs. */
    public function bulk_responsible()
    {
        $this->require_json_cap('edit');
        $entries = $this->ams_maintenance_model->parse_responsible($this->input->post());
        if (isset($entries['error'])) {
            $this->json(['success' => false, 'message' => $entries['error']]);
        }
        $mode = $this->input->post('mode') === 'replace' ? 'replace' : 'add';
        if (! $entries['entries'] && $mode === 'add') {
            $this->json(['success' => false, 'message' => _l('ams_field_required', _l('ams_mt_responsible'))]);
        }
        $this->json($this->ams_maintenance_model->bulk_responsible((array) $this->input->post('ids'), $entries, $mode));
    }

    // ─── Schedules ────────────────────────────────────────────────────────

    /** All schedules ("view"), or only the ones the staff member is responsible for (?mine=1, or no "view"). */
    public function schedules()
    {
        if (! $this->can_see_schedules()) {
            access_denied('ams_maintenance');
        }

        $data['mine']        = staff_cant('view', 'ams_maintenance') || $this->input->get('mine');
        $data['title']       = _l('ams_mt_schedules');
        $data['table']       = App_table::find('ams_schedules');
        $data['types']       = $this->ams_maintenance_model->types();
        $data['suppliers']   = ams_supplier_options(true);
        $data['assets']      = $this->asset_options();
        $data['responsible'] = $this->ams_maintenance_model->responsible_options();
        $this->load->view(AMS_MODULE_NAME . '/maintenance/schedules', $data);
    }

    public function schedules_table()
    {
        if (! $this->can_see_schedules()) {
            ajax_access_denied();
        }
        $mine = staff_cant('view', 'ams_maintenance') || $this->input->get('mine') ? (int) get_staff_user_id() : 0;
        App_table::find('ams_schedules')->output(['mine' => $mine]);
    }

    private function can_see_schedules()
    {
        return ams_can_see_maintenance() || $this->ams_maintenance_model->has_schedules();
    }

    public function get_schedule($id)
    {
        if (staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        $s = $this->ams_maintenance_model->get_schedule($id);
        if ($s) {
            $s->next_due    = _d($s->next_due);
            $s->responsible = $this->ams_maintenance_model->responsible_form_values('schedule', $s);
        }
        $this->json_raw($s ?: []);
    }

    public function save_schedule()
    {
        $id = (int) $this->input->post('id');
        if (staff_cant($id ? 'edit' : 'create', 'ams_maintenance')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_maintenance_model->save_schedule($this->input->post(), $id ?: null));
    }

    public function delete_schedule($id)
    {
        $this->require_cap('delete');
        $r = $this->ams_maintenance_model->delete_schedule($id);
        set_alert('success', $r['message']);
        redirect(admin_url('asset_management/maintenance/schedules'));
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function asset_options()
    {
        return $this->db->select('id, CONCAT(asset_tag, " - ", name) name', false)->where('is_deleted', 0)
            ->order_by('asset_tag')->get(db_prefix() . 'ams_assets')->result_array();
    }

    private function require_cap($cap)
    {
        if (staff_cant($cap, 'ams_maintenance')) {
            access_denied('ams_maintenance');
        }
    }

    private function require_json_cap($cap)
    {
        if (staff_cant($cap, 'ams_maintenance')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
    }

    /** Editors, or the staff responsible for the job. */
    private function require_work($id)
    {
        if (! $this->ams_maintenance_model->can_work_job($this->ams_maintenance_model->get($id))) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
    }

    private function json($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }
        $this->json_raw(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '', 'id' => $result['id'] ?? null]);
    }

    private function json_raw($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
