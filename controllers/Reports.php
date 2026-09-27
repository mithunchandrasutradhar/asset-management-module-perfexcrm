<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Finance reports: asset valuation (cost, accumulated depreciation, net book
 * value) and the disposal register. Permission: "AMS - Reports" → View.
 */
class Reports extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['recalculate']);
        if (staff_cant('view', 'ams_reports')) {
            access_denied('ams_reports');
        }
        $this->load->model(AMS_MODULE_NAME . '/ams_finance_model');
    }

    /** Reports hub: every report the staff member may open, grouped. */
    public function index()
    {
        $data['title'] = _l('ams_reports');
        $this->load->view(AMS_MODULE_NAME . '/reports/index', $data);
    }

    // ─── Asset history (check-out / check-in / status for all assets) ─────

    public function history()
    {
        $data['title'] = _l('ams_report_history');
        $data['table'] = App_table::find('ams_history_all');
        $this->load->view(AMS_MODULE_NAME . '/reports/history', $data);
    }

    public function history_table()
    {
        App_table::find('ams_history_all')->output();
    }

    // ─── Warranty expiry ──────────────────────────────────────────────────

    public function warranty()
    {
        $data['title'] = _l('ams_report_warranty');
        $data['table'] = App_table::find('ams_warranty');
        $this->load->view(AMS_MODULE_NAME . '/reports/warranty', $data);
    }

    public function warranty_table()
    {
        App_table::find('ams_warranty')->output();
    }

    // ─── Maintenance cost ─────────────────────────────────────────────────

    public function maintenance_cost()
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_reports_model');

        $from  = $this->input->get('from') ? to_sql_date($this->input->get('from')) : date('Y-01-01');
        $to    = $this->input->get('to') ? to_sql_date($this->input->get('to')) : date('Y-m-d');
        $from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) ? $from : date('Y-01-01');
        $to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) && $to >= $from ? $to : date('Y-m-d');
        $group = in_array($this->input->get('group'), ['supplier', 'type', 'asset']) ? $this->input->get('group') : 'category';

        $data['title']   = _l('ams_report_maintenance_cost');
        $data['from']    = $from;
        $data['to']      = $to;
        $data['group']   = $group;
        $data['summary'] = $this->ams_reports_model->maintenance_cost($from, $to, $group);
        $data['monthly'] = $this->ams_reports_model->maintenance_monthly($from, $to);
        $this->load->view(AMS_MODULE_NAME . '/reports/maintenance_cost', $data);
    }

    // ─── Depreciation forecast ────────────────────────────────────────────

    public function depreciation()
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_reports_model');
        $months = in_array((int) $this->input->get('months'), [6, 12, 24, 36]) ? (int) $this->input->get('months') : 12;

        $data['title']    = _l('ams_report_depreciation');
        $data['months']   = $months;
        $data['forecast'] = $this->ams_reports_model->depreciation_forecast($months);
        $this->load->view(AMS_MODULE_NAME . '/reports/depreciation', $data);
    }

    public function valuation()
    {
        $group    = in_array($this->input->get('group'), ['location', 'department']) ? $this->input->get('group') : 'category';
        $archived = $this->input->get('include_archived') === '1';

        $data['title']            = _l('ams_valuation_report');
        $data['table']            = App_table::find('ams_valuation');
        $data['group']            = $group;
        $data['include_archived'] = $archived;
        $data['summary']          = $this->ams_finance_model->valuation_summary($group, $archived);
        $this->load->view(AMS_MODULE_NAME . '/reports/valuation', $data);
    }

    public function valuation_table()
    {
        App_table::find('ams_valuation')->output(['include_archived' => $this->input->post('ams_include_archived') === '1']);
    }

    public function recalculate()
    {
        if (! $this->input->post()) {
            show_404();
        }
        $n = $this->ams_finance_model->recalculate();
        update_option('ams_last_dep_run', time());
        set_alert('success', _l('ams_dep_recalculated', $n));
        redirect(admin_url('asset_management/reports/valuation'));
    }

    public function disposals()
    {
        $data['title'] = _l('ams_disposal_register');
        $data['table'] = App_table::find('ams_disposals');
        $data['totals'] = $this->db->query('SELECT currency, COUNT(*) n, SUM(proceeds) proceeds, SUM(book_value) book_value, SUM(gain_loss) gain_loss
            FROM ' . db_prefix() . 'ams_disposals GROUP BY currency')->result_array();
        $this->load->view(AMS_MODULE_NAME . '/reports/disposals', $data);
    }

    public function disposals_table()
    {
        App_table::find('ams_disposals')->output();
    }
}
