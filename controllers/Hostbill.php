<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * HostBill integration screens: orders & fulfilment, product mappings,
 * sync log, and the settings "Test connection" action.
 * Permission group: ams_hostbill (view / edit / sync).
 */
class Hostbill extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['fulfilment', 'resync', 'sync_now', 'save_mapping', 'delete_mapping', 'refresh_products', 'push_now', 'test_connection', 'new_webhook_secret']);
        $this->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');
    }

    // ─── Orders ───────────────────────────────────────────────────────────

    public function index()
    {
        redirect(admin_url('asset_management/hostbill/orders'));
    }

    public function orders()
    {
        $this->require_cap('view');

        $data['title'] = _l('ams_hb_orders');
        $data['table'] = App_table::find('ams_hb_orders');
        $this->load->view(AMS_MODULE_NAME . '/hostbill/orders', $data);
    }

    public function orders_table($perfexClientId = 0)
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }

        App_table::find('ams_hb_orders')->output(['perfex_client_id' => (int) $perfexClientId]);
    }

    public function order($id)
    {
        $this->require_cap('view');

        $order = $this->ams_hostbill_model->get_order($id);
        if (! $order) {
            show_404();
        }

        $data['title'] = _l('ams_hb_order') . ' #' . ($order['order_number'] ?: $order['hb_order_id']);
        $data['order'] = $order;
        $data['lines'] = $this->ams_hostbill_model->get_order_lines($order['hb_order_id']);
        $this->load->view(AMS_MODULE_NAME . '/hostbill/order', $data);
    }

    public function fulfilment($id)
    {
        if (staff_cant('edit', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $this->json($this->ams_hostbill_model->set_fulfilment($id, $this->input->post('fulfilment'), $this->input->post('fulfilment_note')));
    }

    public function resync($id)
    {
        if (staff_cant('sync', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $order = $this->ams_hostbill_model->get_order($id);
        if (! $order) {
            $this->json(['success' => false, 'message' => _l('ams_not_found')]);
        }

        $result = $this->ams_hostbill_model->process_order($order['hb_order_id']);
        $this->ams_hostbill_model->push_pending();
        $this->json($result);
    }

    public function sync_now()
    {
        if (staff_cant('sync', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $this->json($this->ams_hostbill_model->sync());
    }

    // ─── Product mappings ─────────────────────────────────────────────────

    public function products()
    {
        $this->require_cap('view');

        $data['title']      = _l('ams_hb_products');
        $data['table']      = App_table::find('ams_hb_mappings');
        $data['hb_options'] = $this->ams_hostbill_model->product_options();
        $data['items']      = $this->db->select('id, CONCAT(sku, " - ", name) name', false)
            ->where('kind', 'stock')->where('active', 1)->order_by('name')
            ->get(db_prefix() . 'ams_items')->result_array();
        $data['locations']  = ams_location_options(true);

        $this->load->view(AMS_MODULE_NAME . '/hostbill/products', $data);
    }

    public function mappings_table()
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }

        App_table::find('ams_hb_mappings')->output();
    }

    public function mapping($id)
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }

        header('Content-Type: application/json');
        echo json_encode($this->ams_hostbill_model->get_mapping($id) ?: []);
    }

    public function save_mapping()
    {
        if (staff_cant('edit', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')], false);
        }

        $id     = (int) $this->input->post('id');
        $result = $this->ams_hostbill_model->save_mapping($this->input->post(), $id ?: null);
        if ($result['success']) {
            $this->ams_hostbill_model->push_pending([$this->input->post('item_id')]);
        }
        $this->json($result, false);
    }

    public function delete_mapping($id)
    {
        if (staff_cant('edit', 'ams_hostbill')) {
            access_denied('ams_hostbill');
        }

        $result = $this->ams_hostbill_model->delete_mapping($id);
        set_alert($result['success'] ? 'success' : 'warning', $result['message']);
        redirect(admin_url('asset_management/hostbill/products'));
    }

    public function refresh_products()
    {
        if (staff_cant('sync', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $a = $this->ams_hostbill_model->refresh_products();
        if ($a['success']) {
            $this->ams_hostbill_model->refresh_mapped_quantities();
        }
        $this->json($a);
    }

    public function push_now()
    {
        if (staff_cant('sync', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        // Re-publish every mapping, not only the pending ones.
        $this->db->where('push_stock', 1)->update(db_prefix() . 'ams_hb_product_map', ['push_pending' => 1]);
        $r = $this->ams_hostbill_model->push_pending();
        $this->json([
            'success' => $r['failed'] === 0,
            'message' => _l('ams_hb_push_result', [$r['pushed'], $r['failed']]),
        ]);
    }

    // ─── Sync log ─────────────────────────────────────────────────────────

    public function log()
    {
        $this->require_cap('view');

        $data['title'] = _l('ams_hb_sync_log');
        $data['table'] = App_table::find('ams_hb_log');
        $this->load->view(AMS_MODULE_NAME . '/hostbill/log', $data);
    }

    public function log_table()
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }

        App_table::find('ams_hb_log')->output();
    }

    public function log_entry($id)
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }

        $row = $this->db->where('id', (int) $id)->get(db_prefix() . 'ams_hb_sync_log')->row_array();
        header('Content-Type: application/json');
        echo json_encode($row ?: []);
    }

    // ─── Settings helpers ─────────────────────────────────────────────────

    public function test_connection()
    {
        if (! is_admin() && staff_cant('edit', 'ams_settings')) {
            $this->json(['success' => false, 'message' => _l('access_denied')], false);
        }

        $this->json($this->ams_hostbill_model->test_connection(), false);
    }

    public function new_webhook_secret()
    {
        if (! is_admin() && staff_cant('edit', 'ams_settings')) {
            access_denied('ams_settings');
        }

        update_option('ams_hb_webhook_secret', bin2hex(random_bytes(20)));
        set_alert('success', _l('ams_hb_secret_regenerated'));
        redirect(admin_url('settings?group=ams_hostbill'));
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function require_cap($capability)
    {
        if (staff_cant($capability, 'ams_hostbill')) {
            access_denied('ams_hostbill');
        }
    }

    /** JSON response; with $alert, a success message is also shown after the page reloads. */
    private function json($result, $alert = true)
    {
        if ($alert && ! empty($result['success'])) {
            set_alert('success', $result['message']);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '']);
        exit;
    }
}
