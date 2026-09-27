<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Quantity-tracked inventory: accessories, consumables, stock items.
 * Each kind is governed by its own permission group (see ams_item_kinds()).
 */
class Inventory extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['delete', 'action', 'checkin']);
        $this->load->model(AMS_MODULE_NAME . '/ams_inventory_model');
    }

    // ─── Item lists ───────────────────────────────────────────────────────

    public function index($kind = 'accessory')
    {
        $kinds = ams_item_kinds();
        if (! isset($kinds[$kind])) {
            show_404();
        }
        if (! ams_item_can_view_kind_page($kind)) {
            access_denied($kinds[$kind]['perm']);
        }

        $data['title']     = _l($kinds[$kind]['plural']);
        $data['kind']      = $kind;
        $data['kind_cfg']  = $kinds[$kind];
        $data['table']     = App_table::find('ams_items');

        $this->load->view(AMS_MODULE_NAME . '/inventory/manage', $data);
    }

    public function table($kind)
    {
        if (! ams_item_can_view_kind_page($kind)) {
            ajax_access_denied();
        }

        App_table::find('ams_items')->output(['kind' => $kind]);
    }

    // ─── Create / edit / delete ───────────────────────────────────────────

    public function item($id = '')
    {
        $item = $id !== '' ? $this->ams_inventory_model->get_item($id) : null;
        if ($id !== '' && ! $item) {
            show_404();
        }

        $kind  = $item ? $item->kind : ($this->input->get('kind') ?: $this->input->post('kind'));
        $kinds = ams_item_kinds();
        if (! isset($kinds[$kind])) {
            show_404();
        }
        if (! ams_item_can($item ? 'edit' : 'create', $kind)) {
            access_denied($kinds[$kind]['perm']);
        }

        if ($this->input->post()) {
            $post         = $this->input->post();
            $post['kind'] = $kind;
            $result       = $this->ams_inventory_model->save_item($post, $item ? $item->id : null);

            if ($result['success']) {
                set_alert('success', $result['message']);
                redirect(admin_url('asset_management/inventory/view/' . $result['id']));
            }

            set_alert('danger', $result['message']);
            $data['posted'] = $post;
        }

        $data['title']      = $item ? _l('ams_edit_record', _l($kinds[$kind]['singular'])) . ' - ' . $item->sku : _l('ams_new_record', _l($kinds[$kind]['singular']));
        $data['item']       = $item;
        $data['kind']       = $kind;
        $data['kind_cfg']   = $kinds[$kind];
        $data['categories'] = ams_category_options(true);
        $data['brands']     = ams_brand_options(true);
        $data['locations']  = ams_location_options(true);

        $this->load->view(AMS_MODULE_NAME . '/inventory/item', $data);
    }

    public function delete($id)
    {
        $item = $this->ams_inventory_model->get_item($id);
        if (! $item || ! ams_item_can('delete', $item->kind)) {
            access_denied('ams_items');
        }

        $result = $this->ams_inventory_model->delete_item($id);
        set_alert($result['success'] ? 'success' : 'warning', $result['message']);

        redirect($result['success']
            ? admin_url('asset_management/inventory/index/' . $item->kind)
            : admin_url('asset_management/inventory/view/' . (int) $id));
    }

    // ─── Profile ──────────────────────────────────────────────────────────

    public function view($id)
    {
        $item = $this->ams_inventory_model->get_item($id);
        if (! $item) {
            show_404();
        }
        if (! $this->can_view_item($item)) {
            access_denied(ams_item_kinds()[$item->kind]['perm']);
        }

        $data['title']       = $item->sku . ' - ' . $item->name;
        $data['item']        = $item;
        $data['kind_cfg']    = ams_item_kinds()[$item->kind];
        $data['levels']      = $this->ams_inventory_model->levels($id);
        $data['locations']   = ams_location_options(true);
        $data['suppliers']   = ams_supplier_options(true);
        $data['staff']       = ams_staff_options();
        $data['departments'] = ams_department_options();

        $this->load->view(AMS_MODULE_NAME . '/inventory/view', $data);
    }

    // ─── Stock operations (AJAX, JSON) ────────────────────────────────────

    /** $op: receive | issue | checkout | transfer | adjust */
    public function action($op, $id)
    {
        if (! $this->input->post() || ! $this->input->is_ajax_request()) {
            show_404();
        }

        $capabilities = ['receive' => 'adjust', 'transfer' => 'adjust', 'adjust' => 'adjust', 'issue' => 'issue', 'checkout' => 'checkout'];
        $item         = $this->ams_inventory_model->get_item($id);

        if (! $item || ! isset($capabilities[$op])) {
            $this->json(['success' => false, 'message' => _l('ams_invalid_request')]);
        }
        if (! ams_item_can($capabilities[$op], $item->kind)) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }
        // Accessories are checked out (and returned); consumables / stock items are issued.
        if (($op === 'checkout' && $item->kind !== 'accessory') || ($op === 'issue' && $item->kind === 'accessory')) {
            $this->json(['success' => false, 'message' => _l('ams_invalid_request')]);
        }

        $this->json($this->ams_inventory_model->{$op}($id, $this->input->post()));
    }

    public function checkin($checkoutId)
    {
        if (! $this->input->post() || ! $this->input->is_ajax_request()) {
            show_404();
        }
        if (staff_cant('checkout', 'ams_accessories')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $this->json($this->ams_inventory_model->checkin($checkoutId, $this->input->post()));
    }

    // ─── Ledger / levels / checkouts ──────────────────────────────────────

    public function movements()
    {
        if (! ams_item_viewable_kinds()) {
            access_denied('ams_stock');
        }

        $data['title'] = _l('ams_stock_movements');
        $data['table'] = App_table::find('ams_movements');
        $this->load->view(AMS_MODULE_NAME . '/inventory/movements', $data);
    }

    public function movements_table($itemId = 0)
    {
        if ($itemId) {
            // The item ledger shows everyone's issues/checkouts: global view only (not view_own holders).
            $item = $this->ams_inventory_model->get_item($itemId);
            if (! $item || ! ams_item_can('view', $item->kind)) {
                ajax_access_denied();
            }
        } elseif (! ams_item_viewable_kinds()) {
            ajax_access_denied();
        }

        App_table::find('ams_movements')->output(['item_id' => (int) $itemId]);
    }

    public function levels()
    {
        if (! ams_item_viewable_kinds()) {
            access_denied('ams_stock');
        }

        $data['title'] = _l('ams_stock_levels');
        $data['table'] = App_table::find('ams_levels');
        $this->load->view(AMS_MODULE_NAME . '/inventory/levels', $data);
    }

    public function levels_table()
    {
        if (! ams_item_viewable_kinds()) {
            ajax_access_denied();
        }

        App_table::find('ams_levels')->output();
    }

    public function checkouts()
    {
        if (! ams_item_can_view_kind_page('accessory')) {
            access_denied('ams_accessories');
        }

        $data['title']     = _l('ams_accessory_checkouts');
        $data['table']     = App_table::find('ams_checkouts');
        $data['locations'] = ams_location_options(true);
        $this->load->view(AMS_MODULE_NAME . '/inventory/checkouts', $data);
    }

    public function checkouts_table($itemId = 0)
    {
        if (! ams_item_can_view_kind_page('accessory')) {
            ajax_access_denied();
        }

        App_table::find('ams_checkouts')->output(['item_id' => (int) $itemId]);
    }

    // ─── Internals ────────────────────────────────────────────────────────

    /** Global view of the kind, or (accessories) view_own while holding some of it. */
    private function can_view_item($item)
    {
        if (ams_item_can('view', $item->kind)) {
            return true;
        }

        return $item->kind === 'accessory'
            && staff_can('view_own', 'ams_accessories')
            && total_rows(db_prefix() . 'ams_item_checkouts', [
                'item_id'       => (int) $item->id,
                'status'        => 'open',
                'assigned_type' => 'staff',
                'assigned_id'   => get_staff_user_id(),
            ]) > 0;
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
