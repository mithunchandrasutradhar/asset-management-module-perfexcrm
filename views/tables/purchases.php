<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Purchase register: assets whose source is "purchase".

return App_table::find('ams_purchases')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_assets';

        $aColumns = [
            $t . '.purchase_date as purchase_date',
            $t . '.asset_tag as asset_tag',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'sp.name as supplier_name',
            $t . '.invoice_no as invoice_no',
            $t . '.order_no as order_no',
            'CONCAT(pb.firstname, " ", pb.lastname) as purchased_by_name',
            $t . '.purchase_cost as purchase_cost',
            $t . '.warranty_end as warranty_end',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
            'LEFT JOIN ' . $p . 'staff pb ON pb.staffid = ' . $t . '.purchased_by',
            'LEFT JOIN ' . $p . 'currencies cur ON cur.id = ' . $t . '.currency',
        ];

        $where = ['AND ' . $t . '.is_deleted = 0', 'AND ' . $t . '.source = "purchase"'];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            $t . '.purchased_by as purchased_by',
            'cur.name as currency_name',
        ]);

        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/assets/view/' . $aRow['id']);

            $row   = [];
            $row[] = e(_d($aRow['purchase_date']));
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['supplier_name']);
            $row[] = e($aRow['invoice_no']);
            $row[] = e($aRow['order_no']);
            $row[] = $aRow['purchased_by'] ? ams_assignee_html('staff', $aRow['purchased_by'], $aRow['purchased_by_name']) : '';
            $row[] = $aRow['purchase_cost'] !== null ? e(app_format_money($aRow['purchase_cost'], $aRow['currency_name'])) : '';
            $row[] = ams_warranty_html($aRow['warranty_end']);

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('purchase_date', 'DateRule')->label(_l('ams_purchase_date')),
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))->options(function () {
            return collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
        }),
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))->options(function () {
            return collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all();
        }),
        App_table_filter::new('purchased_by', 'MultiSelectRule')->label(_l('ams_purchased_by'))->options(function () {
            return collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all();
        }),
        App_table_filter::new('invoice_no', 'TextRule')->label(_l('ams_invoice_no')),
        App_table_filter::new('purchase_cost', 'NumberRule')->label(_l('ams_purchase_cost')),
        App_table_filter::new('warranty_end', 'DateRule')->label(_l('ams_warranty_end')),
    ]);
