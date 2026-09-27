<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Admin-only: import cleaned master data from the legacy Asset Management app. */
class Legacy_import extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['run']);

        if (! is_admin()) {
            access_denied('ams_legacy_import');
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_legacy_import_model');
    }

    public function index()
    {
        $data['title']       = _l('ams_legacy_import');
        $data['rows']        = $this->ams_legacy_import_model->preview();
        $data['imported_at'] = get_option('ams_legacy_import_date');

        $this->load->view(AMS_MODULE_NAME . '/legacy_import', $data);
    }

    public function run()
    {
        if (! $this->input->post()) {
            show_404();
        }

        $summary = $this->ams_legacy_import_model->run();

        if ($summary === false) {
            set_alert('danger', _l('ams_db_error'));
        } else {
            set_alert('success', _l('ams_legacy_import_done', [$summary['created'], $summary['codes'], $summary['skipped']]));
        }

        redirect(admin_url('asset_management/legacy_import'));
    }
}
