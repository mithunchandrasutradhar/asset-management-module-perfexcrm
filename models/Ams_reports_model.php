<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Aggregates for the report pages (the detail grids are App_table data tables). */
class Ams_reports_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    /** Completed maintenance cost between two dates (end date), grouped. Costs are in the base currency. */
    public function maintenance_cost($from, $to, $group)
    {
        $p = db_prefix();
        switch ($group) {
            case 'supplier':
                $label = 'sp.name';
                $key   = 'm.supplier_id';
                break;
            case 'type':
                $label = 'm.type';
                $key   = 'm.type';
                break;
            case 'asset':
                $label = 'CONCAT(a.asset_tag, " - ", a.name)';
                $key   = 'm.asset_id';
                break;
            default:
                $label = 'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name))';
                $key   = 'a.category_id';
        }

        return $this->db->query('SELECT ' . $label . ' label, COUNT(*) jobs, SUM(IFNULL(m.cost, 0)) cost, SUM(IFNULL(m.downtime_hours, 0)) downtime
            FROM ' . $p . 'ams_maintenance m
            JOIN ' . $p . 'ams_assets a ON a.id = m.asset_id
            LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id
            LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id
            LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = m.supplier_id
            WHERE m.status = "completed" AND m.end_date BETWEEN ? AND ?
            GROUP BY ' . $key . '
            ORDER BY cost DESC', [$from, $to])->result_array();
    }

    /** Cost per month in the period (every month present, zero-filled) for the chart. */
    public function maintenance_monthly($from, $to)
    {
        $rows = $this->db->query('SELECT DATE_FORMAT(end_date, "%Y-%m") ym, SUM(IFNULL(cost, 0)) cost, COUNT(*) jobs
            FROM ' . $this->t('ams_maintenance') . ' WHERE status = "completed" AND end_date BETWEEN ? AND ? GROUP BY ym', [$from, $to])->result_array();
        $byMonth = array_column($rows, null, 'ym');

        $out = [];
        for ($d = new DateTime(substr($from, 0, 7) . '-01'), $end = new DateTime(substr($to, 0, 7) . '-01'); $d <= $end; $d->modify('+1 month')) {
            $ym    = $d->format('Y-m');
            $out[] = ['ym' => $ym, 'cost' => (float) ($byMonth[$ym]['cost'] ?? 0), 'jobs' => (int) ($byMonth[$ym]['jobs'] ?? 0)];
        }

        return $out;
    }

    /**
     * Depreciation expense forecast for the next $months months (from the
     * current month), per month and per category, for assets still in service.
     * Amounts are per currency.
     */
    public function depreciation_forecast($months = 12)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_finance_model');

        $start = date('Y-m');
        $end   = date('Y-m', strtotime(date('Y-m-01') . ' +' . ($months - 1) . ' months'));

        $assets = $this->db->query('SELECT a.id, a.category_id, a.purchase_cost, a.purchase_date, a.depreciation_method, a.useful_life_months,
                a.salvage_value, a.currency, IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) category_name
            FROM ' . $this->t('ams_assets') . ' a
            LEFT JOIN ' . $this->t('ams_categories') . ' c ON c.id = a.category_id
            LEFT JOIN ' . $this->t('ams_categories') . ' pc ON pc.id = c.parent_id
            LEFT JOIN ' . $this->t('ams_statuses') . ' st ON st.id = a.status_id
            LEFT JOIN ' . $this->t('ams_disposals') . ' d ON d.asset_id = a.id
            WHERE a.is_deleted = 0 AND d.id IS NULL AND (st.type IS NULL OR st.type <> "archived")
              AND a.purchase_cost > 0 AND a.purchase_date IS NOT NULL')->result();

        $monthsList = [];
        for ($d = new DateTime($start . '-01'); $d->format('Y-m') <= $end; $d->modify('+1 month')) {
            $monthsList[] = $d->format('Y-m');
        }

        $byMonth    = []; // [currency][ym] => amount
        $byCategory = []; // [currency][category] => [amount, assets]
        foreach ($assets as $a) {
            $s = $this->ams_finance_model->schedule($a);
            if (! $s['applicable']) {
                continue;
            }
            $counted = false;
            foreach ($s['rows'] as $r) {
                $ym = substr($r['date'], 0, 7);
                if ($ym < $start || $ym > $end || $r['depreciation'] <= 0) {
                    continue;
                }
                $cur = (int) $a->currency;
                $cat = $a->category_name ?: '-';
                $byMonth[$cur][$ym] = ($byMonth[$cur][$ym] ?? 0) + $r['depreciation'];
                $byCategory[$cur][$cat]['amount'] = ($byCategory[$cur][$cat]['amount'] ?? 0) + $r['depreciation'];
                if (! $counted) {
                    $byCategory[$cur][$cat]['assets'] = ($byCategory[$cur][$cat]['assets'] ?? 0) + 1;
                    $counted = true;
                }
            }
        }
        foreach ($byCategory as &$cats) {
            uasort($cats, fn ($x, $y) => $y['amount'] <=> $x['amount']);
        }

        return ['months' => $monthsList, 'by_month' => $byMonth, 'by_category' => $byCategory];
    }
}
