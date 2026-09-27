<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Reports → Warranty expiry: in-service assets with a warranty end date.

return App_table::find('ams_warranty')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_assets';

        $aColumns = [
            $t . '.warranty_end as warranty_end',
            'DATEDIFF(' . $t . '.warranty_end, CURDATE()) as days_left',
            $t . '.asset_tag as asset_tag',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            $t . '.serial_no as serial_no',
            'COALESCE(' . $t . '.warranty_provider, sp.name) as provider',
            $t . '.warranty_start as warranty_start',
            'COALESCE(CONCAT(hs.firstname, " ", hs.lastname), hd.name, hl.name) as assignee_name',
            'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as location_name',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
            'LEFT JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
            'LEFT JOIN ' . $p . 'staff hs ON ' . $t . '.assigned_type = "staff" AND hs.staffid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'departments hd ON ' . $t . '.assigned_type = "department" AND hd.departmentid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'ams_locations hl ON ' . $t . '.assigned_type = "location" AND hl.id = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'ams_statuses st ON st.id = ' . $t . '.status_id',
        ];

        $where = ['AND ' . $t . '.is_deleted = 0', 'AND ' . $t . '.warranty_end IS NOT NULL', 'AND (st.type IS NULL OR st.type <> "archived")'];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.assigned_type as assigned_type', $t . '.assigned_id as assigned_id']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url  = admin_url('asset_management/assets/view/' . $aRow['id']);
            $days = (int) $aRow['days_left'];

            $row   = [];
            $row[] = ams_warranty_html($aRow['warranty_end']);
            $row[] = '<span class="' . ($days < 0 ? 'text-danger' : ($days <= (int) get_option('ams_warranty_expiring_days') ? 'text-warning tw-font-semibold' : '')) . '">' . $days . '</span>';
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['serial_no']);
            $row[] = e($aRow['provider']);
            $row[] = e(_d($aRow['warranty_start']));
            $row[] = ams_assignee_html($aRow['assigned_type'], $aRow['assigned_id'], $aRow['assignee_name']);
            $row[] = e($aRow['location_name']);

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('warranty_end', 'DateRule')->label(_l('ams_warranty_end')),
        App_table_filter::new('expired', 'BooleanRule')->label(_l('ams_warranty_expired'))->raw(function ($value) {
            $sql = db_prefix() . 'ams_assets.warranty_end < CURDATE()';

            return $value == '1' ? $sql : 'NOT (' . $sql . ')';
        }),
        App_table_filter::new('expiring_soon', 'BooleanRule')->label(_l('ams_warranty_expiring_soon'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_assets';
            $sql = '(' . $t . '.warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . (int) get_option('ams_warranty_expiring_days') . ' DAY))';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
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
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))
            ->options(fn () => collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all()),
    ]);
