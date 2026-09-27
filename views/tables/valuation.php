<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Asset valuation: cost, accumulated depreciation and net book value (stored values, see Ams_finance_model).

return App_table::find('ams_valuation')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_assets';

        $method = 'COALESCE(' . $t . '.depreciation_method, c.depreciation_method, pc.depreciation_method)';
        $life   = 'COALESCE(' . $t . '.useful_life_months, c.useful_life_months, pc.useful_life_months)';

        $aColumns = [
            $t . '.asset_tag as asset_tag',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'dp.name as department_name',
            'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as location_name',
            $t . '.purchase_date as purchase_date',
            $t . '.purchase_cost as purchase_cost',
            $method . ' as dep_method',
            $life . ' as dep_life',
            $t . '.dep_accumulated as dep_accumulated',
            $t . '.dep_book_value as dep_book_value',
            'st.name as status_name',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'departments dp ON dp.departmentid = ' . $t . '.department_id',
            'LEFT JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
            'LEFT JOIN ' . $p . 'ams_statuses st ON st.id = ' . $t . '.status_id',
            'LEFT JOIN ' . $p . 'currencies cur ON cur.id = ' . $t . '.currency',
        ];

        $where = ['AND ' . $t . '.is_deleted = 0'];
        if (empty($include_archived)) {
            $where[] = 'AND (st.type IS NULL OR st.type <> "archived")';
        }
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', 'cur.name as currency_name', 'st.color as status_color']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url   = admin_url('asset_management/assets/view/' . $aRow['id']);
            $money = fn ($v) => $v !== null ? e(app_format_money($v, $aRow['currency_name'])) : '';

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['department_name']);
            $row[] = e($aRow['location_name']);
            $row[] = e(_d($aRow['purchase_date']));
            $row[] = $money($aRow['purchase_cost']);
            $row[] = e(_l('ams_dep_method_' . ($aRow['dep_method'] ?: 'none')));
            $row[] = $aRow['dep_method'] && $aRow['dep_life'] ? (int) $aRow['dep_life'] : '';
            $row[] = $money($aRow['dep_accumulated']);
            $row[] = '<span class="tw-font-medium">' . $money($aRow['dep_book_value']) . '</span>';
            $row[] = ams_status_badge($aRow['status_name'], $aRow['status_color']);

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))->raw(function ($value, $operator) {
            $ids = [];
            foreach ((array) $value as $id) {
                $ids = array_merge($ids, ams_category_with_children((int) $id));
            }
            $sql = db_prefix() . 'ams_assets.category_id IN (' . implode(',', $ids ?: [0]) . ')';

            return $operator === 'in' ? $sql : 'NOT ' . $sql;
        })->options(fn () => collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()),
        App_table_filter::new('location_id', 'MultiSelectRule')->label(_l('ams_location'))
            ->options(fn () => collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all()),
        App_table_filter::new('department_id', 'MultiSelectRule')->label(_l('ams_department'))
            ->options(fn () => collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all()),
        App_table_filter::new('dep_method', 'SelectRule')->label(_l('ams_dep_method'))->raw(function ($value, $operator) {
            $p   = db_prefix();
            $col = 'COALESCE(' . $p . 'ams_assets.depreciation_method, c.depreciation_method, pc.depreciation_method, "none")';
            $sql = $col . ' = ' . get_instance()->db->escape((string) $value);

            return $operator === 'equal' ? $sql : 'NOT (' . $sql . ')';
        })->options(fn () => collect(ams_depreciation_method_options())->map(fn ($m) => ['value' => $m['id'], 'label' => $m['name']])->all()),
        App_table_filter::new('purchase_date', 'DateRule')->label(_l('ams_purchase_date')),
        App_table_filter::new('purchase_cost', 'NumberRule')->label(_l('ams_purchase_cost')),
        App_table_filter::new('dep_book_value', 'NumberRule')->label(_l('ams_dep_book_value')),
        App_table_filter::new('fully_depreciated', 'BooleanRule')->label(_l('ams_dep_fully_depreciated'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_assets';
            $sql = '(' . $t . '.dep_accumulated > 0 AND ' . $t . '.dep_accumulated >= ' . $t . '.purchase_cost - IFNULL(' . $t . '.salvage_value, 0) - 0.005)';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
    ]);
