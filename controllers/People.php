<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * "Assets by Staff": every Perfex staff member with what they hold, and a
 * per-staff page (also linked from the Perfex staff profile). Global asset view required.
 */
class People extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['send_reminders']);
        $this->load->model(AMS_MODULE_NAME . '/ams_people_model');

        if (staff_cant('view', 'ams_assets')) {
            $this->input->is_ajax_request() ? ajax_access_denied() : access_denied('ams_assets');
        }
    }

    public function index()
    {
        $data['title'] = _l('ams_assets_by_staff');
        $data['table'] = App_table::find('ams_people');
        $this->load->view(AMS_MODULE_NAME . '/people/manage', $data);
    }

    public function table()
    {
        App_table::find('ams_people')->output();
    }

    public function staff($staffId)
    {
        $staff = $this->db->where('staffid', (int) $staffId)->get(db_prefix() . 'staff')->row();
        if (! $staff) {
            show_404();
        }

        $data['title']    = get_staff_full_name($staffId);
        $data['staff']    = $staff;
        $data['holdings'] = $this->ams_people_model->holdings($staffId);
        $data['depts']    = array_column($this->db->query('SELECT d.name FROM ' . db_prefix() . 'staff_departments sd JOIN ' . db_prefix() . 'departments d ON d.departmentid = sd.departmentid WHERE sd.staffid = ?', [(int) $staffId])->result_array(), 'name');
        $this->load->view(AMS_MODULE_NAME . '/people/staff', $data);
    }

    /** Manual run of the overdue reminders (the cron does the same every few hours). */
    public function send_reminders()
    {
        if ($this->input->method() !== 'post' || staff_cant('checkin', 'ams_assets')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);

            return;
        }

        $sent = $this->ams_people_model->send_overdue_reminders();
        set_alert('success', _l('ams_reminders_sent', $sent));
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => _l('ams_reminders_sent', $sent)]);
    }

    public function assets_table($staffId)
    {
        App_table::find('ams_assets')->output(['status_id' => 0, 'category_id' => 0, 'holder_staff' => (int) $staffId]);
    }

    public function checkouts_table($staffId)
    {
        // Accessory checkouts belong to the accessories permission, not assets.
        if (! ams_item_can('view', 'accessory')) {
            ajax_access_denied();
        }
        App_table::find('ams_checkouts')->output(['item_id' => 0, 'holder_staff' => (int) $staffId]);
    }

    public function acceptances_table($staffId)
    {
        App_table::find('ams_acceptances')->output(['staff_id' => (int) $staffId]);
    }
}
