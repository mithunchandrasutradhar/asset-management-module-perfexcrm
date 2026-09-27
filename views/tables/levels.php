<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Stock on hand per item and location.

return App_table::find('ams_levels')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_stock_levels';

        $aColumns = [
            'i.sku as sku',
            'i.name as item_name',
            'i.kind as kind',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as location_name',
            $t . '.on_hand as on_hand',
            $t . '.reserved as reserved',
            '(' . $t . '.on_hand - ' . $t . '.reserved) as available',
            '(' . $t . '.on_hand * IFNULL(i.cost, 0)) as stock_value',
        ];

        $join = [
            'JOIN ' . $p . 'ams_items i ON i.id = ' . $t . '.item_id',
            'JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = i.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
        ];

        $kinds = ams_item_viewable_kinds() ?: ['-'];
        $where = [
            'AND i.kind IN ("' . implode('","', $kinds) . '")',
            'AND (' . $t . '.on_hand <> 0 OR ' . $t . '.reserved <> 0)',
        ];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.item_id as item_id', 'i.unit as unit']);
        $output = $result['output'];
        $kinds  = ams_item_kinds();
        $cur    = get_base_currency();

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/inventory/view/' . $aRow['item_id']);

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['sku']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['item_name']) . '</a>';
            $row[] = e(_l($kinds[$aRow['kind']]['singular'] ?? $aRow['kind']));
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['location_name']);
            $row[] = ams_qty($aRow['on_hand']) . ' <span class="text-muted tw-text-xs">' . e($aRow['unit']) . '</span>';
            $row[] = ams_qty($aRow['reserved']);
            $row[] = '<span class="tw-font-semibold">' . ams_qty($aRow['available']) . '</span>';
            $row[] = e(app_format_money($aRow['stock_value'], $cur));

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('kind', 'MultiSelectRule')->label(_l('ams_item_kind'))->column('i.kind')
            ->options(fn () => collect(ams_item_kind_options())->map(fn ($k) => ['value' => $k['id'], 'label' => $k['name']])->all()),
        App_table_filter::new('location_id', 'MultiSelectRule')->label(_l('ams_location'))
            ->options(fn () => collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all()),
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))
            ->raw(function ($value, $operator) {
                $in  = "'" . implode("','", (array) $value) . "'";
                $sql = '(i.category_id IN (' . $in . ') OR c.parent_id IN (' . $in . '))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(fn () => collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()),
        App_table_filter::new('on_hand', 'NumberRule')->label(_l('ams_on_hand')),
    ]);
