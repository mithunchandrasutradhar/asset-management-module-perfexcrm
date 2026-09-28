<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Assets → HostBill Inventory (read-only): HostBill product stock with low /
 * out-of-stock states, per-product low-stock levels, "Refresh now", and the
 * sync log. Permission group ams_hostbill: view / edit (levels) / sync (refresh).
 */
class Hostbill extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['refresh', 'set_level', 'test_connection']);
        $this->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');
    }

    public function index()
    {
        $this->require_cap('view');

        $data['title']  = _l('ams_hb_inventory');
        $data['table']  = App_table::find('ams_hb_inventory');
        $data['counts'] = $this->ams_hostbill_model->counts();
        $data['tab']    = 'inventory';
        $this->load->view(AMS_MODULE_NAME . '/hostbill/inventory', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_hostbill')) {
            ajax_access_denied();
        }
        App_table::find('ams_hb_inventory')->output();
    }

    /** Per-product low-stock level (empty = default). */
    public function set_level($hbProductId)
    {
        if (staff_cant('edit', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_hostbill_model->set_level($hbProductId, $this->input->post('low_level')));
    }

    public function refresh()
    {
        if (staff_cant('sync', 'ams_hostbill')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        $this->json($this->ams_hostbill_model->refresh());
    }

    // ─── Sync log ─────────────────────────────────────────────────────────

    public function log()
    {
        $this->require_cap('view');

        $data['title'] = _l('ams_hb_sync_log');
        $data['table'] = App_table::find('ams_hb_log');
        $data['tab']   = 'log';
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

    // ─── Settings helper ──────────────────────────────────────────────────

    public function test_connection()
    {
        if (! is_admin() && staff_cant('edit', 'ams_settings')) {
            $this->json(['success' => false, 'message' => _l('access_denied')], false);
        }
        $this->json($this->ams_hostbill_model->test_connection(), false);
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function require_cap($capability)
    {
        if (staff_cant($capability, 'ams_hostbill')) {
            access_denied('ams_hostbill');
        }
    }

    /** JSON response; with $alert a success message is also shown after the page reloads. */
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
