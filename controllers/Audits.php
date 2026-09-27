<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Physical audit campaigns. Permission group: ams_audits. */
class Audits extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['save', 'start', 'record', 'line_result', 'scan_mode', 'complete', 'cancel', 'delete']);
        $this->load->model(AMS_MODULE_NAME . '/ams_audit_model');
    }

    public function index()
    {
        $this->require_cap('view');
        $data['title']       = _l('ams_audits');
        $data['table']       = App_table::find('ams_audits');
        $data['locations']   = ams_location_options(true);
        $data['departments'] = array_map(fn ($d) => ['id' => $d['departmentid'], 'name' => $d['name']], ams_department_options());
        $data['categories']  = ams_category_options(true);
        $this->load->view(AMS_MODULE_NAME . '/audits/manage', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_audits')) {
            ajax_access_denied();
        }
        App_table::find('ams_audits')->output();
    }

    public function get($id)
    {
        if (staff_cant('view', 'ams_audits')) {
            ajax_access_denied();
        }
        $a = $this->ams_audit_model->get($id);
        if ($a) {
            $a->due_date        = $a->due_date ? _d($a->due_date) : '';
            $a->next_audit_date = $a->next_audit_date ? _d($a->next_audit_date) : '';
        }
        $this->json_raw($a ?: []);
    }

    public function save()
    {
        $id = (int) $this->input->post('id');
        if (staff_cant($id ? 'edit' : 'create', 'ams_audits')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $r = $this->ams_audit_model->save($this->input->post(), $id ?: null);
        $this->json($r + ['redirect' => ! $id && $r['success'] ? admin_url('asset_management/audits/view/' . $r['id']) : null]);
    }

    public function view($id)
    {
        $this->require_cap('view');
        $audit = $this->ams_audit_model->get($id);
        if (! $audit) {
            show_404();
        }

        $data['title']     = $audit->audit_no . ' - ' . $audit->title;
        $data['audit']     = $audit;
        $data['locations'] = ams_location_options(true);
        $data['scanning']  = (int) $this->session->userdata('ams_scan_audit') === (int) $audit->id;
        $this->load->view(AMS_MODULE_NAME . '/audits/view', $data);
    }

    public function lines_table($id)
    {
        if (staff_cant('view', 'ams_audits')) {
            ajax_access_denied();
        }
        App_table::find('ams_audit_lines')->output(['audit_id' => (int) $id]);
    }

    public function start($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_audit_model->start($id));
    }

    /** Record a scanned / typed code (tag, serial or scan URL). */
    public function record($id)
    {
        $this->require_json_cap('edit');
        $asset = $this->ams_audit_model->find_asset_by_code($this->input->post('code'));
        if (! $asset) {
            $this->json_raw(['success' => false, 'message' => _l('ams_audit_code_unknown') . ': ' . e((string) $this->input->post('code'))]);
        }
        $r = $this->ams_audit_model->record($id, $asset, $this->input->post());
        $this->json_raw(['success' => $r['success'], 'message' => $r['message'], 'result' => $r['result'] ?? null, 'counts' => $this->ams_audit_model->counts($id)]);
    }

    /** Table row actions: found (by asset id) / missing / pending. */
    public function line_result($lineId)
    {
        $this->require_json_cap('edit');
        $result = (string) $this->input->post('result');
        if ($result === 'found') {
            $line = $this->db->where('id', (int) $lineId)->get(db_prefix() . 'ams_audit_lines')->row();
            $r    = $line ? $this->ams_audit_model->record($line->audit_id, (int) $line->asset_id, $this->input->post()) : ['success' => false, 'message' => _l('ams_not_found')];
        } else {
            $r = $this->ams_audit_model->set_result($lineId, $result);
        }
        $this->json_raw(['success' => $r['success'], 'message' => $r['message']]);
    }

    /** Phone camera "scan mode": label QR codes opened by this staff member are recorded in this audit. */
    public function scan_mode($id, $on = 1)
    {
        $this->require_cap('edit');
        $audit = $this->ams_audit_model->get($id);
        if ($on && $audit && $audit->status === 'in_progress') {
            $this->session->set_userdata('ams_scan_audit', (int) $id);
            set_alert('success', _l('ams_audit_scan_mode_on'));
        } else {
            $this->session->unset_userdata('ams_scan_audit');
            set_alert('success', _l('ams_audit_scan_mode_off'));
        }
        redirect(admin_url('asset_management/audits/view/' . (int) $id));
    }

    public function complete($id)
    {
        $this->require_json_cap('edit');
        $r = $this->ams_audit_model->complete($id, $this->input->post());
        if ($r['success'] && (int) $this->session->userdata('ams_scan_audit') === (int) $id) {
            $this->session->unset_userdata('ams_scan_audit');
        }
        $this->json($r);
    }

    public function cancel($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_audit_model->cancel($id));
    }

    public function delete($id)
    {
        $this->require_cap('delete');
        $r = $this->ams_audit_model->delete($id);
        set_alert($r['success'] ? 'success' : 'warning', $r['message']);
        redirect(admin_url($r['success'] ? 'asset_management/audits' : 'asset_management/audits/view/' . (int) $id));
    }

    private function require_cap($cap)
    {
        if (staff_cant($cap, 'ams_audits')) {
            access_denied('ams_audits');
        }
    }

    private function require_json_cap($cap)
    {
        if (staff_cant($cap, 'ams_audits')) {
            $this->json_raw(['success' => false, 'message' => _l('access_denied')]);
        }
    }

    private function json($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }
        $this->json_raw(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '', 'redirect' => $result['redirect'] ?? null]);
    }

    private function json_raw($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
