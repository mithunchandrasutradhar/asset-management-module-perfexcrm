<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Purchase orders & goods receipts. Permission group: ams_procurement. */
class Procurement extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['submit', 'decide', 'send', 'receive', 'cancel', 'delete']);
        $this->load->model(AMS_MODULE_NAME . '/ams_procurement_model');
    }

    public function index()
    {
        $this->require_cap('view');
        $data['title'] = _l('ams_purchase_orders');
        $data['table'] = App_table::find('ams_pos');
        $this->load->view(AMS_MODULE_NAME . '/procurement/manage', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_procurement')) {
            ajax_access_denied();
        }
        App_table::find('ams_pos')->output();
    }

    public function po($id = '')
    {
        $po = $id !== '' ? $this->ams_procurement_model->get($id) : null;
        if ($id !== '' && ! $po) {
            show_404();
        }
        $this->require_cap($po ? 'edit' : 'create');
        if ($po && ! $this->ams_procurement_model->editable($po)) {
            set_alert('warning', _l('ams_po_not_editable'));
            redirect(admin_url('asset_management/procurement/view/' . $po->id));
        }

        if ($this->input->post()) {
            $r = $this->ams_procurement_model->save($this->input->post(), $po ? $po->id : null);
            if ($r['success']) {
                set_alert('success', $r['message']);
                redirect(admin_url('asset_management/procurement/view/' . $r['id']));
            }
            set_alert('danger', $r['message']);
            $data['posted'] = $this->input->post();
        }

        // New PO from an approved asset request: pre-fill one line.
        $data['request'] = null;
        if (! $po && ($reqId = (int) $this->input->get('request_id'))) {
            $data['request'] = $this->db->where('id', $reqId)->where_in('type', ['asset', 'accessory', 'consumable'])->get(db_prefix() . 'ams_requests')->row();
        }

        $data['title']      = $po ? _l('ams_edit_record', _l('ams_purchase_order')) . ' ' . $po->po_number : _l('ams_new_record', _l('ams_purchase_order'));
        $data['po']         = $po;
        $data['suppliers']  = ams_supplier_options(true);
        $data['locations']  = ams_location_options(true);
        $data['categories'] = ams_category_options(true);
        $data['brands']     = ams_brand_options(true);
        $data['models']     = ams_model_options(true);
        $data['items']      = $this->db->select('id, CONCAT(sku, " - ", name) name, cost', false)->where('active', 1)->order_by('name')->get(db_prefix() . 'ams_items')->result_array();
        $this->load->view(AMS_MODULE_NAME . '/procurement/po', $data);
    }

    public function view($id)
    {
        $this->require_cap('view');
        $po = $this->ams_procurement_model->get($id);
        if (! $po) {
            show_404();
        }

        $data['title']     = $po->po_number;
        $data['po']        = $po;
        $data['receipts']  = $this->ams_procurement_model->receipts($id);
        $data['locations'] = ams_location_options(true);
        $this->load->view(AMS_MODULE_NAME . '/procurement/view', $data);
    }

    public function submit($id)
    {
        $this->require_json_cap('create');
        $this->json($this->ams_procurement_model->submit($id));
    }

    public function decide($id)
    {
        $this->require_json_cap('approve_po');
        $this->json($this->ams_procurement_model->decide($id, $this->input->post('decision') === 'approve', $this->input->post('note')));
    }

    public function send($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_procurement_model->send($id, $this->input->post('email') === '1'));
    }

    public function receive($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_procurement_model->receive($id, $this->input->post() ?: []));
    }

    public function cancel($id)
    {
        $this->require_json_cap('edit');
        $this->json($this->ams_procurement_model->cancel($id));
    }

    public function delete($id)
    {
        $this->require_cap('delete');
        $r = $this->ams_procurement_model->delete($id);
        set_alert($r['success'] ? 'success' : 'warning', $r['message']);
        redirect(admin_url($r['success'] ? 'asset_management/procurement' : 'asset_management/procurement/view/' . (int) $id));
    }

    public function pdf($id)
    {
        $this->require_cap('view');
        $po = $this->ams_procurement_model->get($id);
        if (! $po) {
            show_404();
        }
        $this->ams_procurement_model->pdf($po)->Output($po->po_number . '.pdf', $this->input->get('output_type') === 'D' ? 'D' : 'I');
    }

    private function require_cap($cap)
    {
        if (staff_cant($cap, 'ams_procurement')) {
            access_denied('ams_procurement');
        }
    }

    private function require_json_cap($cap)
    {
        if (staff_cant($cap, 'ams_procurement')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
    }

    private function json($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '']);
        exit;
    }
}
