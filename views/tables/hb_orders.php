<?php

defined('BASEPATH') or exit('No direct script access allowed');

// HostBill orders synced into Perfex (all, or one Perfex customer's).

return App_table::find('ams_hb_orders')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_hb_orders';

        $aColumns = [
            $t . '.order_date as order_date',
            $t . '.order_number as order_number',
            $t . '.client_name as client_name',
            $t . '.status as status',
            $t . '.invoice_status as invoice_status',
            $t . '.total as total',
            '(SELECT GROUP_CONCAT(CONCAT(ROUND(l.hb_qty), "× ", IFNULL(i.name, IFNULL(l.product_name, CONCAT("#", l.hb_product_id)))) SEPARATOR ", ") FROM ' . $p . 'ams_hb_order_lines l LEFT JOIN ' . $p . 'ams_items i ON i.id = l.item_id WHERE l.hb_order_id = ' . $t . '.hb_order_id AND l.state <> "unmapped") as items_summary',
            '(SELECT GROUP_CONCAT(DISTINCT l.state) FROM ' . $p . 'ams_hb_order_lines l WHERE l.hb_order_id = ' . $t . '.hb_order_id AND l.state NOT IN ("unmapped")) as line_states',
            $t . '.fulfilment as fulfilment',
            $t . '.last_synced_at as last_synced_at',
        ];

        $where = [];
        if (! empty($perfex_client_id)) {
            $where[] = 'AND ' . $t . '.perfex_client_id = ' . (int) $perfex_client_id;
        }
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, [], $where, [
            $t . '.id as id',
            $t . '.hb_order_id as hb_order_id',
            $t . '.client_email as client_email',
            $t . '.perfex_client_id as perfex_client_id',
            $t . '.needs_attention as needs_attention',
            $t . '.has_mapped as has_mapped',
        ]);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url    = admin_url('asset_management/hostbill/order/' . $aRow['id']);
            $client = e($aRow['client_name']);
            if ($aRow['perfex_client_id']) {
                $client = '<a href="' . admin_url('clients/client/' . $aRow['perfex_client_id']) . '">' . $client . '</a>';
            }
            if ($aRow['client_email']) {
                $client .= '<br><span class="text-muted tw-text-xs">' . e($aRow['client_email']) . '</span>';
            }

            $states = array_filter(explode(',', (string) $aRow['line_states']));
            $stock  = implode(' ', array_map('ams_hb_line_state_badge', $states));
            if ($aRow['needs_attention']) {
                $stock = '<i class="fa-solid fa-triangle-exclamation text-danger tw-mr-1" title="' . _l('ams_hb_needs_attention') . '"></i>' . $stock;
            }

            $row   = [];
            $row[] = e(_dt($aRow['order_date']));
            $row[] = '<a href="' . $url . '" class="tw-font-medium">#' . e($aRow['order_number'] ?: $aRow['hb_order_id']) . '</a><div class="row-options"><a href="' . $url . '">' . _l('view') . '</a></div>';
            $row[] = $client;
            $row[] = ams_hb_status_badge($aRow['status']);
            $row[] = ams_hb_status_badge($aRow['invoice_status']);
            $row[] = $aRow['total'] !== null ? e(number_format((float) $aRow['total'], 2)) : '';
            $row[] = e($aRow['items_summary']) ?: '<span class="text-muted">' . _l('ams_hb_no_mapped_items') . '</span>';
            $row[] = $stock;
            $row[] = $aRow['has_mapped'] ? ams_hb_fulfilment_badge($aRow['fulfilment']) : '';
            $row[] = e(_dt($aRow['last_synced_at']));

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('order_date', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_hb_order_status'))
            ->options(fn () => collect(['Pending', 'Active', 'Cancelled', 'Fraud'])->map(fn ($s) => ['value' => $s, 'label' => $s])->all()),
        App_table_filter::new('invoice_status', 'MultiSelectRule')->label(_l('ams_hb_invoice_status'))
            ->options(fn () => collect(['Paid', 'Unpaid', 'Refunded', 'Cancelled'])->map(fn ($s) => ['value' => $s, 'label' => $s])->all()),
        App_table_filter::new('fulfilment', 'SelectRule')->label(_l('ams_hb_fulfilment'))
            ->options(fn () => collect(['pending', 'picked', 'delivered'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_hb_fulfilment_' . $s)])->all()),
        App_table_filter::new('has_mapped', 'BooleanRule')->label(_l('ams_hb_has_mapped')),
        App_table_filter::new('needs_attention', 'BooleanRule')->label(_l('ams_hb_needs_attention')),
        App_table_filter::new('client_name', 'TextRule')->label(_l('ams_hb_client')),
        App_table_filter::new('client_email', 'TextRule')->label(_l('ams_email')),
    ]);
