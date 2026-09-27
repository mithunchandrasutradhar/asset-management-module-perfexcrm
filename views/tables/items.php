<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Inventory items of one kind (accessory / consumable / stock), with live totals.

$amsIn = fn ($values) => "'" . implode("','", (array) $values) . "'";

// NB: data_tables_init() splits columns at the first " as", so no SQL alias may start with "as".
$amsAvailable = '(IFNULL(lv.on_hand, 0) - IFNULL(lv.reserved, 0))';

return App_table::find('ams_items')
    ->outputUsing(function ($params) use ($amsAvailable) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_items';

        $aColumns = [
            $t . '.sku as sku',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'b.name as brand_name',
            'IFNULL(lv.on_hand, 0) as on_hand',
            'IFNULL(co.out_qty, 0) as out_qty',
            $amsAvailable . ' as available',
            $t . '.reorder_level as reorder_level',
            '(' . $amsAvailable . ' - ' . $t . '.reorder_level) as state_rank',
            $t . '.cost as cost',
            '((IFNULL(lv.on_hand, 0) + IFNULL(co.out_qty, 0)) * IFNULL(' . $t . '.cost, 0)) as stock_value',
            $t . '.active as active',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'ams_brands b ON b.id = ' . $t . '.brand_id',
            'LEFT JOIN (SELECT item_id, SUM(on_hand) on_hand, SUM(reserved) reserved FROM ' . $p . 'ams_stock_levels GROUP BY item_id) lv ON lv.item_id = ' . $t . '.id',
            'LEFT JOIN (SELECT item_id, SUM(qty - returned_qty) out_qty FROM ' . $p . 'ams_item_checkouts WHERE status = "open" GROUP BY item_id) co ON co.item_id = ' . $t . '.id',
        ];

        $custom_fields = get_table_custom_fields('ams_items');
        foreach ($custom_fields as $key => $field) {
            $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
            array_push($customFieldsColumns, $selectAs);
            array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
            array_push($join, 'LEFT JOIN ' . $p . 'customfieldsvalues as ctable_' . $key . ' ON ' . $t . '.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
        }

        $kind  = in_array($kind, array_keys(ams_item_kinds())) ? $kind : 'accessory';
        $where = ['AND ' . $t . '.kind = "' . $kind . '"'];

        // Accessories with "View (own)" only: just the items the user currently holds.
        if (! ams_item_can('view', $kind)) {
            $where[] = 'AND ' . $t . '.id IN (SELECT item_id FROM ' . $p . 'ams_item_checkouts WHERE status = "open" AND assigned_type = "staff" AND assigned_id = ' . (int) get_staff_user_id() . ')';
        }

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.unit as unit']);
        $output = $result['output'];

        $canEdit   = ams_item_can('edit', $kind);
        $canDelete = ams_item_can('delete', $kind);
        $currency  = get_base_currency();

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/inventory/view/' . $aRow['id']);

            $sku = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['sku']) . '</a><div class="row-options"><a href="' . $url . '">' . _l('view') . '</a>';
            if ($canEdit) {
                $sku .= ' | <a href="' . admin_url('asset_management/inventory/item/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }
            if ($canDelete) {
                $sku .= ' | <a href="' . admin_url('asset_management/inventory/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }
            $sku .= '</div>';

            $unit  = ' <span class="text-muted tw-text-xs">' . e($aRow['unit']) . '</span>';
            $state = ams_stock_state($aRow['available'], $aRow['reorder_level']);

            $row   = [];
            $row[] = $sku;
            $row[] = '<a href="' . $url . '">' . e($aRow['name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['brand_name']);
            $row[] = ams_qty($aRow['on_hand']) . $unit;
            $row[] = ams_qty($aRow['out_qty']);
            $row[] = '<span class="tw-font-semibold">' . ams_qty($aRow['available']) . '</span>';
            $row[] = ams_qty($aRow['reorder_level']);
            $row[] = ams_stock_state_badge($state);
            $row[] = $aRow['cost'] !== null ? e(app_format_money($aRow['cost'], $currency)) : '';
            $row[] = e(app_format_money($aRow['stock_value'], $currency));
            $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('ams_active') . '</span>' : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

            foreach ($customFieldsColumns as $customFieldColumn) {
                $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
            }

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('sku', 'TextRule')->label(_l('ams_sku')),
        App_table_filter::new('name', 'TextRule')->label(_l('ams_item_name')),
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))
            ->raw(function ($value, $operator) use ($amsIn) {
                $sql = '(' . db_prefix() . 'ams_items.category_id IN (' . $amsIn($value) . ') OR c.parent_id IN (' . $amsIn($value) . '))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(fn () => collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()),
        App_table_filter::new('brand_id', 'MultiSelectRule')->label(_l('ams_brand'))
            ->options(fn () => collect(ams_brand_options())->map(fn ($b) => ['value' => $b['id'], 'label' => $b['name']])->all()),
        App_table_filter::new('stock_state', 'SelectRule')->label(_l('ams_stock_state'))
            ->raw(function ($value, $operator) use ($amsAvailable) {
                $reorder = db_prefix() . 'ams_items.reorder_level';
                $sql     = [
                    'out' => $amsAvailable . ' <= 0',
                    'low' => '(' . $amsAvailable . ' > 0 AND ' . $reorder . ' > 0 AND ' . $amsAvailable . ' <= ' . $reorder . ')',
                    'ok'  => '(' . $amsAvailable . ' > 0 AND (' . $reorder . ' <= 0 OR ' . $amsAvailable . ' > ' . $reorder . '))',
                ][$value] ?? '1=1';

                return $operator === 'not_equal' ? 'NOT ' . $sql : $sql;
            })
            ->options(fn () => collect(['out', 'low', 'ok'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_stock_state_' . $s)])->all()),
        App_table_filter::new('reorder_level', 'NumberRule')->label(_l('ams_reorder_level')),
        App_table_filter::new('cost', 'NumberRule')->label(_l('ams_unit_cost')),
        App_table_filter::new('is_sellable', 'BooleanRule')->label(_l('ams_is_sellable')),
        App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
    ]);
