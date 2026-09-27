<?php

defined('BASEPATH') or exit('No direct script access allowed');

// HostBill product mappings with reconciliation: Perfex available vs pushed vs HostBill qty.

return App_table::find('ams_hb_mappings')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_hb_product_map';

        // Available at the mapping's location, or the default sales location, or everywhere.
        $locExpr   = 'IFNULL(' . $t . '.location_id, NULLIF(' . (int) get_option('ams_hb_sales_location_id') . ', 0))';
        $available = '(SELECT IFNULL(SUM(sl.on_hand - sl.reserved), 0) FROM ' . $p . 'ams_stock_levels sl WHERE sl.item_id = ' . $t . '.item_id AND (' . $locExpr . ' IS NULL OR sl.location_id = ' . $locExpr . '))';

        $aColumns = [
            'CONCAT(i.sku, " - ", i.name) as item_label',
            'CONCAT("#", ' . $t . '.hb_product_id, " ", IFNULL(hp.name, "")) as hb_label',
            $t . '.qty_multiplier as qty_multiplier',
            'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as location_name',
            $available . ' as perfex_available',
            $t . '.last_pushed_qty as last_pushed_qty',
            'hp.qty as hb_qty',
            $t . '.last_pushed_at as last_pushed_at',
            $t . '.push_stock as push_stock',
            $t . '.active as active',
        ];

        $join = [
            'JOIN ' . $p . 'ams_items i ON i.id = ' . $t . '.item_id',
            'LEFT JOIN ' . $p . 'ams_hb_products hp ON hp.hb_product_id = ' . $t . '.hb_product_id',
            'LEFT JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
        ];

        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            $t . '.item_id as item_id',
            $t . '.location_id as map_location_id',
            $t . '.push_pending as push_pending',
            $t . '.last_push_error as last_push_error',
            'hp.stock_enabled as hb_stock_enabled',
            'i.unit as unit',
        ]);
        $output  = $result['output'];
        $canEdit = staff_can('edit', 'ams_hostbill');

        $this->ci->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');

        foreach ($result['rResult'] as $aRow) {
            $item = '<a href="' . admin_url('asset_management/inventory/view/' . $aRow['item_id']) . '" class="tw-font-medium">' . e($aRow['item_label']) . '</a>';
            if ($canEdit) {
                $item .= '<div class="row-options"><a href="#" onclick="ams_hb_edit_mapping(' . (int) $aRow['id'] . '); return false;">' . _l('edit') . '</a>'
                    . ' | <a href="' . admin_url('asset_management/hostbill/delete_mapping/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a></div>';
            }

            $publishable = $this->ci->ams_hostbill_model->publishable_qty([
                'item_id'        => $aRow['item_id'],
                'location_id'    => $aRow['map_location_id'],
                'qty_multiplier' => $aRow['qty_multiplier'],
            ]);
            $hbQty       = $aRow['hb_qty'] !== null ? (float) $aRow['hb_qty'] : null;
            $inSync      = $hbQty !== null && abs($hbQty - $publishable) < 0.0001;

            $push = '';
            if (! $aRow['push_stock']) {
                $push = '<span class="text-muted">' . _l('ams_hb_push_off') . '</span>';
            } elseif ($aRow['last_push_error']) {
                $push = '<span class="text-danger" title="' . e($aRow['last_push_error']) . '"><i class="fa-solid fa-circle-xmark tw-mr-1"></i>' . _l('ams_hb_push_failed') . '</span>';
            } elseif ($aRow['push_pending']) {
                $push = '<span class="text-warning"><i class="fa-regular fa-clock tw-mr-1"></i>' . _l('ams_hb_push_pending') . '</span>';
            } else {
                $push = '<span class="text-success"><i class="fa-solid fa-circle-check tw-mr-1"></i>' . e(_dt($aRow['last_pushed_at'])) . '</span>';
            }

            $row   = [];
            $row[] = $item;
            $row[] = e($aRow['hb_label']);
            $row[] = ams_qty($aRow['qty_multiplier']);
            $row[] = e($aRow['location_name']) ?: '<span class="text-muted">' . _l('ams_hb_default_location') . '</span>';
            $row[] = ams_qty($aRow['perfex_available']) . ' <span class="text-muted tw-text-xs">' . e($aRow['unit']) . '</span>';
            $row[] = '<span class="tw-font-semibold">' . $publishable . '</span>';
            $row[] = $hbQty === null ? '<span class="text-muted">?</span>'
                : '<span class="' . ($inSync ? 'text-success' : 'text-danger tw-font-semibold') . '">' . ams_qty($hbQty) . ($inSync ? '' : ' <i class="fa-solid fa-not-equal" title="' . _l('ams_hb_out_of_sync') . '"></i>') . '</span>';
            $row[] = $push;
            $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('ams_active') . '</span>' : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
        App_table_filter::new('push_pending', 'BooleanRule')->label(_l('ams_hb_push_pending')),
        App_table_filter::new('push_failed', 'BooleanRule')->label(_l('ams_hb_push_failed'))->raw(function ($value) {
            $col = db_prefix() . 'ams_hb_product_map.last_push_error';

            return $value == '1' ? $col . ' IS NOT NULL' : $col . ' IS NULL';
        }),
    ]);
