<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Setup → Asset Management → Department Approvers (per Perfex department). */
class Approvers extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['save']);
        $this->load->model(AMS_MODULE_NAME . '/ams_people_model');
    }

    public function index()
    {
        if (staff_cant('view', 'ams_setup')) {
            access_denied('ams_setup');
        }

        $data['title'] = _l('ams_department_approvers');
        $data['table'] = App_table::find('ams_approvers');
        $data['staff'] = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options());
        $this->load->view(AMS_MODULE_NAME . '/approvers/manage', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }
        App_table::find('ams_approvers')->output();
    }

    public function get($departmentId)
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }
        header('Content-Type: application/json');
        echo json_encode(['department_id' => (int) $departmentId, 'staff_ids' => $this->ams_people_model->department_approvers($departmentId)]);
    }

    public function save()
    {
        if (staff_cant('edit', 'ams_setup')) {
            $result = ['success' => false, 'message' => _l('access_denied')];
        } else {
            $result = $this->ams_people_model->save_department_approvers($this->input->post('department_id'), (array) $this->input->post('staff_ids'));
        }
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
