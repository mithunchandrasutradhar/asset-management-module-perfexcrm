<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * "My Assets": what the logged-in staff member holds, acceptances waiting for
 * their signature, and their requests. Always scoped to the current user.
 */
class My_assets extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['respond']);
        $this->load->model(AMS_MODULE_NAME . '/ams_people_model');
    }

    public function index()
    {
        if (! ams_can_use_my_assets()) {
            access_denied('ams_assets');
        }

        $me = (int) get_staff_user_id();

        $data['title']      = _l('ams_my_assets');
        $data['holdings']   = $this->ams_people_model->holdings($me);
        $data['my_assets']  = $this->db->select('id, asset_tag, name')->where('assigned_type', 'staff')->where('assigned_id', $me)
            ->where('is_deleted', 0)->order_by('asset_tag')->get(db_prefix() . 'ams_assets')->result_array();
        $data['categories'] = ams_category_options(true);
        $data['items']      = $this->db->select('id, kind, CONCAT(sku, " - ", name) name', false)->where('active', 1)
            ->where_in('kind', ['accessory', 'consumable', 'stock'])->order_by('name')->get(db_prefix() . 'ams_items')->result_array();

        $this->load->view(AMS_MODULE_NAME . '/my_assets/index', $data);
    }

    // Tables (always the current user's rows)

    public function assets_table()
    {
        $this->ajax_guard();
        App_table::find('ams_assets')->output(['status_id' => 0, 'category_id' => 0, 'holder_staff' => (int) get_staff_user_id()]);
    }

    public function checkouts_table()
    {
        $this->ajax_guard();
        App_table::find('ams_checkouts')->output(['item_id' => 0, 'holder_staff' => (int) get_staff_user_id()]);
    }

    public function acceptances_table()
    {
        $this->ajax_guard();
        App_table::find('ams_acceptances')->output(['staff_id' => (int) get_staff_user_id(), 'mine' => true]);
    }

    public function requests_table()
    {
        $this->ajax_guard();
        App_table::find('ams_requests')->output(['staff_id' => (int) get_staff_user_id()]);
    }

    // Acceptance actions (only the assignee)

    public function acceptance($id)
    {
        $acc = $this->ams_people_model->get_acceptance($id);
        if (! $acc || ((int) $acc->staff_id !== (int) get_staff_user_id() && staff_cant('view', 'ams_assets'))) {
            ajax_access_denied();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'id'             => (int) $acc->id,
            'status'         => $acc->status,
            'label'          => $this->ams_people_model->acceptance_label($acc),
            'terms'          => $acc->terms,
            'signed_name'    => $acc->signed_name,
            'note'           => $acc->note,
            'date_responded' => $acc->date_responded ? _dt($acc->date_responded) : '',
            'signature_url'  => $acc->signature_file ? admin_url('asset_management/my_assets/signature/' . (int) $acc->id) : '',
            'mine'           => (int) $acc->staff_id === (int) get_staff_user_id(),
        ]);
    }

    public function respond($id)
    {
        if (! $this->input->post() || ! $this->input->is_ajax_request()) {
            show_404();
        }

        $accept = $this->input->post('decision') === 'accept';
        $result = $this->ams_people_model->respond($id, $accept, [
            'signature'   => $this->input->post('signature', false),
            'signed_name' => $this->input->post('signed_name'),
            'note'        => $this->input->post('note'),
        ]);

        if ($result['success']) {
            set_alert('success', $result['message']);
        }
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /** Signature image: the signer, or staff with global asset view. */
    public function signature($id)
    {
        $acc = $this->ams_people_model->get_acceptance($id);
        if (! $acc || ! $acc->signature_file || ((int) $acc->staff_id !== (int) get_staff_user_id() && staff_cant('view', 'ams_assets'))) {
            show_404();
        }

        $path = AMS_UPLOAD_PATH . 'signatures/' . basename($acc->signature_file);
        if (! is_file($path)) {
            show_404();
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function ajax_guard()
    {
        if (! ams_can_use_my_assets()) {
            ajax_access_denied();
        }
    }
}
