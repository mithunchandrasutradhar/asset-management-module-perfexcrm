<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Purchase orders.

return App_table::find('ams_pos')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_purchase_orders';

        $aColumns = [
            $t . '.po_number as po_number',
            $t . '.order_date as order_date',
            'sp.name as supplier_name',
            '(SELECT COUNT(*) FROM ' . $p . 'ams_po_lines pl WHERE pl.po_id = ' . $t . '.id) as line_count',
            $t . '.total as total',
            $t . '.expected_date as expected_date',
            $t . '.status as status',
            'CONCAT(cs.firstname, " ", cs.lastname) as created_name',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
            'LEFT JOIN ' . $p . 'staff cs ON cs.staffid = ' . $t . '.created_by',
        ];

        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.created_by as created_by']);
        $output = $result['output'];
        $cur    = get_base_currency();

        foreach ($result['rResult'] as $aRow) {
            $url  = admin_url('asset_management/procurement/view/' . $aRow['id']);
            $opts = '<a href="' . $url . '">' . _l('view') . '</a> | <a href="' . admin_url('asset_management/procurement/pdf/' . $aRow['id']) . '" target="_blank">PDF</a>';

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['po_number']) . '</a><div class="row-options">' . $opts . '</div>';
            $row[] = e(_d($aRow['order_date']));
            $row[] = e($aRow['supplier_name']);
            $row[] = (int) $aRow['line_count'];
            $row[] = e(app_format_money($aRow['total'], $cur));
            $row[] = e(_d($aRow['expected_date']));
            $row[] = ams_po_status_badge($aRow['status']);
            $row[] = $aRow['created_by'] ? e($aRow['created_name']) : '';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['draft', 'pending_approval', 'approved', 'rejected', 'sent', 'partially_received', 'received', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_po_status_' . $s)])->all()),
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))
            ->options(fn () => collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all()),
        App_table_filter::new('order_date', 'DateRule')->label(_l('ams_po_order_date')),
        App_table_filter::new('total', 'NumberRule')->label(_l('ams_po_total')),
    ]);
