<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Scanning: the QR code on an asset label opens scan/tag/{TAG}; the scan page
 * also takes a typed or USB-scanner code (tag, serial or scanned URL).
 * When the staff member has an audit in "scan mode", the asset is recorded in
 * that audit instead of just being opened.
 */
class Scan extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['record']);
        $this->load->model(AMS_MODULE_NAME . '/ams_audit_model');
        if (! ams_can_view_assets() && staff_cant('edit', 'ams_audits')) {
            access_denied('ams_assets');
        }
    }

    public function index()
    {
        if (($code = trim((string) $this->input->get('code'))) !== '') {
            $this->resolve($code, $this->direct_navigation());
        }

        $this->show();
    }

    public function tag($tag = '')
    {
        $this->resolve(rawurldecode((string) $tag), $this->direct_navigation());
    }

    /** POST from the confirm button (browsers that don't tell how the scan URL was opened). */
    public function record()
    {
        $this->resolve(trim((string) $this->input->post('code')), true);
    }

    private function show($confirm = null, $code = '')
    {
        $data['title']         = _l('ams_scan');
        $data['scan_audit']    = $this->active_audit();
        $data['confirm_asset'] = $confirm;
        $data['confirm_code']  = $code;
        $this->load->view(AMS_MODULE_NAME . '/scan/index', $data);
    }

    /**
     * True when the browser says the URL was opened directly (camera / scanner / typed,
     * or a click by the user) - not loaded by an image or frame in some page. Recording
     * into an audit changes data, so anything else gets a confirm button (POST).
     */
    private function direct_navigation()
    {
        $dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        $user = $_SERVER['HTTP_SEC_FETCH_USER'] ?? '';

        return $dest === 'document' && ($site === 'none' || $user === '?1');
    }

    private function resolve($code, $direct = false)
    {
        $asset = $this->ams_audit_model->find_asset_by_code($code);

        $audit = $this->active_audit();
        if ($audit) {
            if ($asset && ! $direct) {
                $this->show($asset, $code);
                exit;
            }
            if (! $asset) {
                set_alert('danger', _l('ams_audit_code_unknown') . ': ' . e($code));
            } else {
                $r = $this->ams_audit_model->record($audit->id, $asset);
                set_alert($r['success'] ? ($r['result'] === 'found' ? 'success' : 'warning') : 'danger', $r['message']);
            }
            redirect(admin_url('asset_management/audits/view/' . $audit->id));
        }

        if (! $asset) {
            set_alert('warning', _l('ams_scan_not_found', e($code)));
            redirect(admin_url('asset_management/scan'));
        }
        if (! ams_can_view_asset($asset)) {
            access_denied('ams_assets');
        }
        redirect(admin_url('asset_management/assets/view/' . $asset->id));
    }

    /** The audit this staff member is scanning into (session), if still running and allowed. */
    private function active_audit()
    {
        $id = (int) $this->session->userdata('ams_scan_audit');
        if (! $id || staff_cant('edit', 'ams_audits')) {
            return null;
        }
        $audit = $this->ams_audit_model->get($id);
        if (! $audit || $audit->status !== 'in_progress') {
            $this->session->unset_userdata('ams_scan_audit');

            return null;
        }

        return $audit;
    }
}
