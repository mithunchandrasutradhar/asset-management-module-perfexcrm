<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Module dashboard: admin/asset_management */
class Asset_management extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
    }

    public function index()
    {
        if (! ams_can_view_assets()) {
            access_denied('ams_assets');
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_inventory_model');

        $data['title']     = _l('ams_dashboard');
        $data['stats']     = $this->ams_assets_model->dashboard_stats();
        $data['inventory'] = $this->ams_inventory_model->dashboard_stats();

        $this->load->view(AMS_MODULE_NAME . '/dashboard', $data);
    }
}
