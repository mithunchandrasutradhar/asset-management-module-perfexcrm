<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Asset requests & issue reports with two-stage approval:
 * department approver (Perfex department of the requester) → manager ("approve").
 */
class Requests extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['create', 'decide', 'fulfil', 'cancel', 'delete']);
        $this->load->model(AMS_MODULE_NAME . '/ams_people_model');
    }

    public function index()
    {
        if (! ams_can_see_requests_page()) {
            access_denied('ams_requests');
        }

        $data['title'] = _l('ams_requests');
        $data['table'] = App_table::find('ams_requests');
        $this->load->view(AMS_MODULE_NAME . '/requests/manage', $data);
    }

    public function table()
    {
        if (! ams_can_see_requests_page()) {
            ajax_access_denied();
        }

        App_table::find('ams_requests')->output(['staff_id' => 0]);
    }

    public function create()
    {
        if (! $this->input->post() || ! $this->input->is_ajax_request()) {
            show_404();
        }
        if (staff_cant('create', 'ams_requests')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $this->json($this->ams_people_model->create_request($this->input->post()));
    }

    public function view($id)
    {
        $req = $this->ams_people_model->get_request($id);
        if (! $req) {
            show_404();
        }
        if (! $this->ams_people_model->can_view_request($req)) {
            access_denied('ams_requests');
        }

        $data['title']      = $req->request_no . ' - ' . $req->subject;
        $data['req']        = $req;
        $data['can_decide'] = $this->ams_people_model->can_decide($req);
        $data['locations']  = ams_location_options(true);

        // Fulfilment choices: deployable, unassigned assets (matching category first) or stock items.
        $data['assets'] = [];
        $data['items']  = [];
        if ($req->status === 'approved') {
            if ($req->type === 'asset') {
                $data['assets'] = $this->db->query('SELECT a.id, CONCAT(a.asset_tag, " - ", a.name, IF(l.name IS NULL, "", CONCAT(" (", l.name, ")"))) name
                    FROM ' . db_prefix() . 'ams_assets a
                    JOIN ' . db_prefix() . 'ams_statuses s ON s.id = a.status_id AND s.type = "deployable"
                    LEFT JOIN ' . db_prefix() . 'ams_locations l ON l.id = a.location_id
                    LEFT JOIN ' . db_prefix() . 'ams_categories c ON c.id = a.category_id
                    WHERE a.is_deleted = 0 AND a.assigned_type IS NULL
                    ORDER BY (a.category_id = ? OR c.parent_id = ?) DESC, a.asset_tag', [(int) $req->category_id, (int) $req->category_id])->result_array();
            } elseif (in_array($req->type, ['accessory', 'consumable'])) {
                $kinds         = $req->type === 'accessory' ? ['accessory'] : ['consumable', 'stock'];
                $data['items'] = $this->db->select('id, CONCAT(sku, " - ", name) name', false)->where('active', 1)->where_in('kind', $kinds)
                    ->order_by('(id = ' . (int) $req->item_id . ')', 'DESC', false)->order_by('name')->get(db_prefix() . 'ams_items')->result_array();
            }
        }

        $this->load->view(AMS_MODULE_NAME . '/requests/view', $data);
    }

    public function decide($id)
    {
        if (! $this->input->post()) {
            show_404();
        }
        $this->json($this->ams_people_model->decide($id, $this->input->post('decision') === 'approve', $this->input->post('note')));
    }

    public function fulfil($id)
    {
        if (! $this->input->post()) {
            show_404();
        }
        $this->json($this->ams_people_model->fulfil($id, $this->input->post()));
    }

    public function cancel($id)
    {
        $this->json($this->ams_people_model->cancel($id));
    }

    public function delete($id)
    {
        if (staff_cant('delete', 'ams_requests')) {
            access_denied('ams_requests');
        }
        $this->ams_people_model->delete_request($id);
        set_alert('success', _l('deleted', _l('ams_request')));
        redirect(admin_url('asset_management/requests'));
    }

    private function json($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '', 'id' => $result['id'] ?? null]);
        exit;
    }
}
