<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Purchase register (legacy "Purchase" page): assets whose source is Purchase. */
class Purchases extends AdminController
{
    public function index()
    {
        if (staff_cant('view', 'ams_assets')) {
            access_denied('ams_assets');
        }

        $data['title'] = _l('ams_purchase_register');
        $data['table'] = App_table::find('ams_purchases');

        $this->load->view(AMS_MODULE_NAME . '/purchases/manage', $data);
    }

    public function table()
    {
        if (staff_cant('view', 'ams_assets')) {
            ajax_access_denied();
        }

        App_table::find('ams_purchases')->output();
    }
}
