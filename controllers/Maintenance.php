<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Maintenance jobs and preventive schedules. Permission group: ams_maintenance. */
class Maintenance extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['save', 'start', 'complete', 'cancel', 'delete', 'save_schedule', 'delete_schedule']);
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
    }

    public function index()
    {
        $this->require_cap('view');

        $data['title']     = _l('ams_maintenance');
        $data['table']     = App_table::find('ams_maintenance');
        $data['types']     = $this->ams_maintenance_model->types();
        $data['suppliers'] = ams_supplier_options(true);
        $data['assets']    = $this->asset_options();
        $data['request']   = null;

        // "Create maintenance" from an issue report.
        if ($reqId = (int) $this->input->get('request_id')) {
            $req = $this->db->where('id', $reqId)->where('type', 'issue')->get(db_prefix() . 'ams_requests')->row();
            if ($req) {
                $data['request'] = $req;
            }
        }

        $this->load->view(AMS_MODULE_NAME . '/maintenance/manage', $data);
    }

    public function table($assetId = 0)
    {
        if (staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        App_table::find('ams_maintenance')->output(['asset_id' => (int) $assetId]);
    }

    public function get($id)
    {
        if (staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        $job = $this->ams_maintenance_model->get($id);
        if ($job) {
            foreach (['due_date', 'start_date', 'end_date'] as $f) {
                $job->{$f} = $job->{$f} ? _d($job->{$f}) : '';
            }
        }
        $this->json_raw($job ?: []);
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
        $this->require_json_cap('edit');
        $this->json($this->ams_maintenance_model->start($id, $this->input->post() ?: []));
    }

    public function complete($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_maintenance_model->complete($id, $this->input->post() ?: []));
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

    // ─── Schedules ────────────────────────────────────────────────────────

    public function schedules()
    {
        $this->require_cap('view');

        $data['title']     = _l('ams_mt_schedules');
        $data['table']     = App_table::find('ams_schedules');
        $data['types']     = $this->ams_maintenance_model->types();
        $data['suppliers'] = ams_supplier_options(true);
        $data['assets']    = $this->asset_options();
        $this->load->view(AMS_MODULE_NAME . '/maintenance/schedules', $data);
    }

    public function schedules_table()
    {
        if (staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        App_table::find('ams_schedules')->output();
    }

    public function get_schedule($id)
    {
        if (staff_cant('view', 'ams_maintenance')) {
            ajax_access_denied();
        }
        $s = $this->ams_maintenance_model->get_schedule($id);
        if ($s) {
            $s->next_due = _d($s->next_due);
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
