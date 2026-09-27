<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Assets extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['delete', 'delete_file', 'set_cover', 'dispose', 'reinstate', 'bulk_action', 'upload_files', 'checkout', 'checkin', 'change_status']);
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
    }

    // ─── List ─────────────────────────────────────────────────────────────

    public function index()
    {
        if (! ams_can_view_assets()) {
            access_denied('ams_assets');
        }

        $data['title']       = _l('ams_assets');
        $data['table']       = App_table::find('ams_assets');
        $data['statuses']    = ams_get_statuses(true);
        $data['locations']   = ams_location_options(true);
        $data['status_id']   = (int) $this->input->get('status_id') ?: '';
        $data['category_id'] = (int) $this->input->get('category_id') ?: '';

        $this->load->view(AMS_MODULE_NAME . '/assets/manage', $data);
    }

    public function table()
    {
        if (! ams_can_view_assets()) {
            ajax_access_denied();
        }

        App_table::find('ams_assets')->output([
            'status_id'   => (int) $this->input->post('ams_status_id'),
            'category_id' => (int) $this->input->post('ams_category_id'),
        ]);
    }

    // ─── Create / edit ────────────────────────────────────────────────────

    public function asset($id = '')
    {
        $isNew = $id === '';

        if (($isNew && staff_cant('create', 'ams_assets')) || (! $isNew && staff_cant('edit', 'ams_assets'))) {
            access_denied('ams_assets');
        }

        if ($this->input->post()) {
            $post = $this->input->post();
            $result = $isNew ? $this->ams_assets_model->add($post) : $this->ams_assets_model->update($id, $post);

            if ($result['success']) {
                set_alert('success', $result['message']);
                redirect(admin_url('asset_management/assets/view/' . $result['id']));
            }

            set_alert('danger', $result['message']);
            $data['posted'] = $post;
        }

        if (! $isNew) {
            $data['asset'] = $this->ams_assets_model->get($id);
            if (! $data['asset']) {
                show_404();
            }
        }

        $data['title']       = $isNew ? _l('ams_new_asset') : _l('ams_edit_asset') . ' - ' . $data['asset']->asset_tag;
        $data['categories']  = ams_category_options(true);
        $data['brands']      = ams_brand_options(true);
        $data['models']      = ams_model_options(true);
        $data['suppliers']   = ams_supplier_options(true);
        $data['locations']   = ams_location_options(true);
        $data['staff']       = ams_staff_options();
        $data['departments'] = ams_department_options();
        $data['currencies']  = ams_currency_options();
        $data['statuses']    = array_values(array_filter(ams_get_statuses(true), fn ($s) => $s['type'] !== 'deployed'));

        $this->load->view(AMS_MODULE_NAME . '/assets/asset', $data);
    }

    // ─── Profile ──────────────────────────────────────────────────────────

    public function view($id)
    {
        $asset = $this->ams_assets_model->get($id);

        if (! $asset) {
            show_404();
        }
        if (! ams_can_view_asset($asset)) {
            access_denied('ams_assets');
        }

        $data['title']       = $asset->asset_tag . ' - ' . $asset->name;
        $data['asset']       = $asset;
        $data['files']       = $this->ams_assets_model->get_files($id);
        $data['statuses']    = ams_get_statuses(true);
        $data['locations']   = ams_location_options(true);
        $data['staff']       = ams_staff_options();
        $data['departments'] = ams_department_options();

        // Maintenance tab (cost roll-up + modals)
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
        $data['maintenance_cost'] = $this->ams_maintenance_model->asset_total_cost($id);
        $data['mt_types']         = $this->ams_maintenance_model->types();
        $data['suppliers']        = ams_supplier_options(true);

        // Finance tab: depreciation schedule, TCO, disposal record.
        $this->load->model(AMS_MODULE_NAME . '/ams_finance_model');
        $this->load->model(AMS_MODULE_NAME . '/ams_disposal_model');
        $data['depreciation'] = $this->ams_finance_model->schedule($asset);
        $data['tco']          = $this->ams_finance_model->tco($asset);
        $data['disposal']     = $this->ams_disposal_model->get_by_asset($id);
        $data['archived']     = array_values(array_filter($data['statuses'], fn ($s) => $s['type'] === 'archived'));

        $this->load->view(AMS_MODULE_NAME . '/assets/view', $data);
    }

    // ─── Disposal ─────────────────────────────────────────────────────────

    public function dispose($id)
    {
        $this->require_post_permission('dispose');
        $this->load->model(AMS_MODULE_NAME . '/ams_disposal_model');
        $this->json_result($this->ams_disposal_model->dispose($id, $this->input->post()));
    }

    public function reinstate($id)
    {
        $this->require_post_permission('dispose');
        $this->load->model(AMS_MODULE_NAME . '/ams_disposal_model');
        $this->json_result($this->ams_disposal_model->reinstate($id, $this->input->post()));
    }

    // ─── Labels (QR / barcode PDF) ────────────────────────────────────────

    /** ?ids=1,2,3 (from the list's bulk actions) or /labels/ID (asset page). */
    public function labels($id = '')
    {
        if (! ams_can_view_assets()) {
            access_denied('ams_assets');
        }

        $ids = $id !== '' ? [(int) $id] : array_slice(array_filter(array_map('intval', explode(',', (string) $this->input->get('ids')))), 0, 1000);
        if (! $ids) {
            show_404();
        }

        $assets = $this->db->select('id, asset_tag, name, serial_no, assigned_type, assigned_id')->where_in('id', $ids)->where('is_deleted', 0)
            ->order_by('asset_tag')->get(db_prefix() . 'ams_assets')->result();
        $assets = array_values(array_filter($assets, 'ams_can_view_asset'));
        if (! $assets) {
            show_404();
        }

        $this->load->library(AMS_MODULE_NAME . '/ams_labels');
        $name = count($assets) === 1 ? 'label-' . preg_replace('/[^A-Za-z0-9_-]/', '', $assets[0]->asset_tag) : 'asset-labels';
        $this->ams_labels->pdf($assets)->Output($name . '.pdf', $this->input->get('download') ? 'D' : 'I');
    }

    public function history_table($id)
    {
        $this->ajax_asset_or_deny($id);
        App_table::find('ams_history')->output(['asset_id' => (int) $id]);
    }

    public function audit_table($id)
    {
        $this->ajax_asset_or_deny($id);
        App_table::find('ams_audit_log')->output(['asset_id' => (int) $id]);
    }

    // ─── Lifecycle actions (AJAX, JSON) ───────────────────────────────────

    public function checkout($id)
    {
        $this->require_post_permission('checkout');

        $post              = $this->input->post();
        $post['assign_id'] = $post['assign_id_' . ($post['assign_type'] ?? '')] ?? 0;

        $this->json_result($this->ams_assets_model->checkout($id, $post));
    }

    public function checkin($id)
    {
        $this->require_post_permission('checkin');
        $this->json_result($this->ams_assets_model->checkin($id, $this->input->post()));
    }

    public function change_status($id)
    {
        $this->require_post_permission('edit');
        $this->json_result($this->ams_assets_model->change_status($id, $this->input->post()));
    }

    public function delete($id)
    {
        if (staff_cant('delete', 'ams_assets')) {
            access_denied('ams_assets');
        }

        if ($this->ams_assets_model->delete($id)) {
            set_alert('success', _l('deleted', _l('ams_asset')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('ams_asset')));
        }

        redirect(admin_url('asset_management/assets'));
    }

    public function bulk_action()
    {
        if (! $this->input->post()) {
            show_404();
        }

        $ids     = (array) $this->input->post('ids');
        $deleted = 0;
        $updated = 0;
        $errors  = [];

        if ($this->input->post('mass_delete')) {
            if (staff_can('delete', 'ams_assets')) {
                foreach ($ids as $id) {
                    $deleted += $this->ams_assets_model->delete($id) ? 1 : 0;
                }
            }
        } elseif (staff_can('edit', 'ams_assets')) {
            $statusId   = (int) $this->input->post('status_id');
            $locationId = (int) $this->input->post('location_id');
            $note       = (string) $this->input->post('note');

            foreach ($ids as $id) {
                $changed = false;

                if ($statusId) {
                    $result = $this->ams_assets_model->change_status($id, [
                        'status_id'   => $statusId,
                        'location_id' => $locationId,
                        'note'        => $note,
                    ]);
                    if ($result['success']) {
                        $changed = true;
                    } elseif (! in_array($result['message'], $errors)) {
                        $errors[] = $result['message'];
                    }
                } elseif ($locationId) {
                    $changed = $this->ams_assets_model->move_location($id, $locationId, $note);
                }

                $updated += $changed ? 1 : 0;
            }
        }

        if ($deleted) {
            set_alert('success', _l('total_items_deleted', $deleted));
        } elseif ($updated) {
            set_alert('success', _l('ams_bulk_updated', $updated));
        }
        if ($errors) {
            set_alert('warning', implode('<br>', array_map('e', $errors)));
        }

        echo json_encode(['success' => true]);
    }

    // ─── Files ────────────────────────────────────────────────────────────

    public function upload_files($id)
    {
        if (staff_cant('edit', 'ams_assets') || ! $this->ams_assets_model->get($id)) {
            access_denied('ams_assets');
        }

        [$uploaded, $errors] = $this->ams_assets_model->upload_files($id);

        if ($uploaded) {
            set_alert('success', _l('ams_files_uploaded', $uploaded));
        }
        if ($errors) {
            set_alert('warning', implode('<br>', $errors));
        }

        redirect(admin_url('asset_management/assets/view/' . (int) $id . '?tab=files'));
    }

    /** Serves a protected file after a permission check (uploads folder is not web-accessible). */
    public function file($fileId, $download = 0)
    {
        $file  = $this->ams_assets_model->get_file($fileId);
        $asset = $file ? $this->ams_assets_model->get($file->asset_id) : null;

        if (! $asset || ! ams_can_view_asset($asset)) {
            show_404();
        }

        $path = ams_asset_upload_dir($file->asset_id) . $file->file_name;
        if (! is_file($path)) {
            show_404();
        }

        // Inline only for plain raster images (never SVG/HTML, whatever getimagesize() said); everything else downloads.
        $safe   = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $ext    = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));
        $inline = ! $download && $file->is_image && isset($safe[$ext]);
        $mime   = $inline ? $safe[$ext] : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $file->original_name) . '"');
        readfile($path);
        exit;
    }

    public function delete_file($fileId)
    {
        $file = $this->ams_assets_model->get_file($fileId);
        if (! $file || staff_cant('edit', 'ams_assets')) {
            access_denied('ams_assets');
        }

        $this->ams_assets_model->delete_file($fileId);
        set_alert('success', _l('deleted', _l('ams_file')));
        redirect(admin_url('asset_management/assets/view/' . (int) $file->asset_id . '?tab=files'));
    }

    public function set_cover($fileId)
    {
        $file = $this->ams_assets_model->get_file($fileId);
        if (! $file || staff_cant('edit', 'ams_assets')) {
            access_denied('ams_assets');
        }

        $this->ams_assets_model->set_cover($fileId);
        redirect(admin_url('asset_management/assets/view/' . (int) $file->asset_id . '?tab=files'));
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function ajax_asset_or_deny($id)
    {
        $asset = $this->ams_assets_model->get($id);
        if (! $asset || ! ams_can_view_asset($asset)) {
            ajax_access_denied();
        }
    }

    private function require_post_permission($capability)
    {
        if (! $this->input->post() || ! $this->input->is_ajax_request()) {
            show_404();
        }
        if (staff_cant($capability, 'ams_assets')) {
            $this->json_result(['success' => false, 'message' => _l('access_denied')]);
        }
    }

    private function json_result($result)
    {
        if (! empty($result['success'])) {
            set_alert('success', $result['message']);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => ! empty($result['success']), 'message' => $result['message'] ?? '']);
        exit;
    }
}
