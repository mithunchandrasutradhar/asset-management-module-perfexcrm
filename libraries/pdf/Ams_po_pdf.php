<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once APPPATH . 'libraries/pdf/App_pdf.php';

/** Purchase order PDF (Perfex App_pdf / TCPDF), template: views/procurement/po_pdf.php */
class Ams_po_pdf extends App_pdf
{
    protected $po;

    public function __construct($po)
    {
        load_pdf_language(get_option('active_language'));
        $GLOBALS['ams_po_pdf'] = $po;

        parent::__construct();

        $this->po = $po;
        $this->SetTitle($po->po_number);
    }

    public function prepare()
    {
        $this->set_view_vars('po', $this->po);

        return $this->build();
    }

    protected function type()
    {
        return 'ams_purchase_order';
    }

    protected function file_path()
    {
        return module_views_path(AMS_MODULE_NAME, 'procurement/po_pdf.php');
    }
}
