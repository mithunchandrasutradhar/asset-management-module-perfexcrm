<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Software licences and seats. Permission group: ams_licenses. */
class Licenses extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['assign', 'release', 'delete']);
        $this->load->model(AMS_MODULE_NAME . '/ams_license_model');
    }

    public function index()
    {
        $this->require_cap('view');
        $data['title'] = _l('ams_licenses');
        $data['table'] = App_table::find('ams_licenses');
        $this->load->view(AMS_MODULE_NAME . '/licenses/manage', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_licenses')) {
            ajax_access_denied();
        }
        App_table::find('ams_licenses')->output();
    }

    public function license($id = '')
    {
        $lic = $id !== '' ? $this->ams_license_model->get($id) : null;
        if ($id !== '' && ! $lic) {
            show_404();
        }
        $this->require_cap($lic ? 'edit' : 'create');

        if ($this->input->post()) {
            $r = $this->ams_license_model->save($this->input->post(), $lic ? $lic->id : null);
            if ($r['success']) {
                set_alert('success', $r['message']);
                redirect(admin_url('asset_management/licenses/view/' . $r['id']));
            }
            set_alert('danger', $r['message']);
            $data['posted'] = $this->input->post();
        }

        $data['title']      = $lic ? _l('ams_edit_record', _l('ams_license')) . ' - ' . $lic->name : _l('ams_new_record', _l('ams_license'));
        $data['license']    = $lic;
        $data['types']      = $this->ams_license_model->types();
        $data['brands']     = ams_brand_options(true);
        $data['categories'] = ams_category_options(true);
        $data['suppliers']  = ams_supplier_options(true);
        $this->load->view(AMS_MODULE_NAME . '/licenses/license', $data);
    }

    public function view($id)
    {
        $this->require_cap('view');
        $lic = $this->ams_license_model->get($id);
        if (! $lic) {
            show_404();
        }

        $data['title']   = $lic->name;
        $data['license'] = $lic;
        $data['staff']   = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options());
        $data['assets']  = $this->db->select('id, CONCAT(asset_tag, " - ", name) name', false)->where('is_deleted', 0)->order_by('asset_tag')->get(db_prefix() . 'ams_assets')->result_array();
        $this->load->view(AMS_MODULE_NAME . '/licenses/view', $data);
    }

    /** Seats: of one licence, of one staff member (My Assets), or of one asset. */
    public function seats_table($licenseId = 0)
    {
        if (staff_cant('view', 'ams_licenses')) {
            ajax_access_denied();
        }
        App_table::find('ams_license_seats')->output(['license_id' => (int) $licenseId, 'asset_id' => (int) $this->input->post('ams_asset_id')]);
    }

    public function my_seats_table()
    {
        if (staff_cant('view', 'ams_licenses') && staff_cant('view_own', 'ams_licenses')) {
            ajax_access_denied();
        }
        App_table::find('ams_license_seats')->output(['holder_staff' => (int) get_staff_user_id()]);
    }

    public function reveal_key($id)
    {
        if (staff_cant('view_keys', 'ams_licenses')) {
            $this->json_raw(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json_raw(['success' => true, 'key' => $this->ams_license_model->reveal_key($id)]);
    }

    public function assign($id)
    {
        if (staff_cant('edit', 'ams_licenses')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_license_model->assign_seat($id, $this->input->post()));
    }

    public function release($seatId)
    {
        if (staff_cant('edit', 'ams_licenses')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_license_model->release_seat($seatId));
    }

    public function delete($id)
    {
        $this->require_cap('delete');
        $r = $this->ams_license_model->delete($id);
        set_alert($r['success'] ? 'success' : 'warning', $r['message']);
        redirect(admin_url($r['success'] ? 'asset_management/licenses' : 'asset_management/licenses/view/' . (int) $id));
    }

    private function require_cap($cap)
    {
        if (staff_cant($cap, 'ams_licenses')) {
            access_denied('ams_licenses');
        }
    }

    private function json($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }
        $this->json_raw(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '']);
    }

    private function json_raw($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
