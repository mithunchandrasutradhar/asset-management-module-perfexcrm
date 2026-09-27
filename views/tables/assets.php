<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Asset list — standard Perfex App_table: server-side search, sort, pagination,
 * Filters (saved/shared), export, column visibility, custom fields.
 */

/** IN (...) list from already-escaped filter values. */
$amsIn = function ($values) {
    return "'" . implode("','", (array) $values) . "'";
};

return App_table::find('ams_assets')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_assets';

        $aColumns = [
            '1',
            $t . '.asset_tag as asset_tag',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'CONCAT_WS(" ", b.name, m.name) as brand_model',
            $t . '.serial_no as serial_no',
            'st.name as status_name',
            'COALESCE(CONCAT(sa.firstname, " ", sa.lastname), ad.name, al.name) as assigned_to_name',
            'd.name as department_name',
            'loc.name as location_name',
            $t . '.warranty_end as warranty_end',
            $t . '.purchase_cost as purchase_cost',
            $t . '.purchase_date as purchase_date',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'ams_brands b ON b.id = ' . $t . '.brand_id',
            'LEFT JOIN ' . $p . 'ams_models m ON m.id = ' . $t . '.model_id',
            'LEFT JOIN ' . $p . 'ams_statuses st ON st.id = ' . $t . '.status_id',
            'LEFT JOIN ' . $p . 'ams_locations loc ON loc.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'departments d ON d.departmentid = ' . $t . '.department_id',
            'LEFT JOIN ' . $p . 'staff sa ON ' . $t . '.assigned_type = "staff" AND sa.staffid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'departments ad ON ' . $t . '.assigned_type = "department" AND ad.departmentid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'ams_locations al ON ' . $t . '.assigned_type = "location" AND al.id = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'currencies cur ON cur.id = ' . $t . '.currency',
        ];

        $custom_fields = get_table_custom_fields('ams_assets');

        foreach ($custom_fields as $key => $field) {
            $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
            array_push($customFieldsColumns, $selectAs);
            array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
            array_push($join, 'LEFT JOIN ' . $p . 'customfieldsvalues as ctable_' . $key . ' ON ' . $t . '.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
        }

        $where = ['AND ' . $t . '.is_deleted = 0'];

        if ($scope = ams_assets_scope_where($t)) {
            $where[] = $scope;
        }

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        // My Assets / Assets-by-staff page: one holder only (access checked by the controller).
        if (! empty($holder_staff)) {
            $where[] = 'AND ' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id = ' . (int) $holder_staff;
        }

        // Drill-down links from the dashboard (?status_id= / ?category_id=)
        if (! empty($status_id)) {
            $where[] = 'AND ' . $t . '.status_id = ' . (int) $status_id;
        }
        if (! empty($category_id)) {
            $where[] = 'AND (' . $t . '.category_id = ' . (int) $category_id . ' OR c.parent_id = ' . (int) $category_id . ')';
        }

        if (count($custom_fields) > 4) {
            @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            'st.color as status_color',
            $t . '.assigned_type as assigned_type',
            $t . '.assigned_id as assigned_id',
            'cur.name as currency_name',
        ]);

        $output  = $result['output'];
        $rResult = $result['rResult'];

        $canEdit   = staff_can('edit', 'ams_assets');
        $canDelete = staff_can('delete', 'ams_assets');

        foreach ($rResult as $aRow) {
            $row = [];
            $url = admin_url('asset_management/assets/view/' . $aRow['id']);

            $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

            $tag = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>';
            $tag .= '<div class="row-options"><a href="' . $url . '">' . _l('view') . '</a>';
            if ($canEdit) {
                $tag .= ' | <a href="' . admin_url('asset_management/assets/asset/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }
            if ($canDelete) {
                $tag .= ' | <a href="' . admin_url('asset_management/assets/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }
            $tag .= '</div>';
            $row[] = $tag;

            $row[] = '<a href="' . $url . '">' . e($aRow['name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['brand_model']);
            $row[] = e($aRow['serial_no']);
            $row[] = ams_status_badge($aRow['status_name'], $aRow['status_color']);
            $row[] = ams_assignee_html($aRow['assigned_type'], $aRow['assigned_id'], $aRow['assigned_to_name']);
            $row[] = e($aRow['department_name']);
            $row[] = e($aRow['location_name']);
            $row[] = ams_warranty_html($aRow['warranty_end']);
            $row[] = $aRow['purchase_cost'] !== null ? e(app_format_money($aRow['purchase_cost'], $aRow['currency_name'])) : '';
            $row[] = e(_d($aRow['purchase_date']));

            foreach ($customFieldsColumns as $customFieldColumn) {
                $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
            }

            $row['DT_RowClass'] = 'has-row-options';
            $row                = hooks()->apply_filters('ams_assets_table_row_data', $row, $aRow);

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('asset_tag', 'TextRule')->label(_l('ams_asset_tag')),
        App_table_filter::new('name', 'TextRule')->label(_l('ams_asset_name')),
        App_table_filter::new('serial_no', 'TextRule')->label(_l('ams_serial_no')),
        App_table_filter::new('status_id', 'MultiSelectRule')->label(_l('ams_status'))->options(function () {
            return collect(ams_get_statuses())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
        }),
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))
            ->raw(function ($value, $operator) use ($amsIn) {
                $sql = '(' . db_prefix() . 'ams_assets.category_id IN (' . $amsIn($value) . ') OR c.parent_id IN (' . $amsIn($value) . '))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(function () {
                return collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all();
            }),
        App_table_filter::new('brand_id', 'MultiSelectRule')->label(_l('ams_brand'))->options(function () {
            return collect(ams_brand_options())->map(fn ($b) => ['value' => $b['id'], 'label' => $b['name']])->all();
        }),
        App_table_filter::new('model_id', 'MultiSelectRule')->label(_l('ams_model'))->options(function () {
            return collect(ams_model_options())->map(fn ($m) => ['value' => $m['id'], 'label' => $m['name']])->all();
        }),
        App_table_filter::new('assigned_staff', 'MultiSelectRule')->label(_l('ams_assigned_staff'))
            ->raw(function ($value, $operator) use ($amsIn) {
                $t   = db_prefix() . 'ams_assets';
                $sql = '(' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id IN (' . $amsIn($value) . '))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(function () {
                return collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all();
            }),
        App_table_filter::new('department_id', 'MultiSelectRule')->label(_l('ams_department'))->options(function () {
            return collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all();
        }),
        App_table_filter::new('location_id', 'MultiSelectRule')->label(_l('ams_location'))->options(function () {
            return collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all();
        }),
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))->options(function () {
            return collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
        }),
        App_table_filter::new('source', 'SelectRule')->label(_l('ams_source'))->options(function () {
            return collect(ams_source_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
        }),
        App_table_filter::new('asset_condition', 'MultiSelectRule')->label(_l('ams_condition'))->options(function () {
            return collect(ams_condition_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
        }),
        App_table_filter::new('checked_out', 'BooleanRule')->label(_l('ams_checked_out'))->raw(function ($value) {
            return db_prefix() . 'ams_assets.assigned_type IS ' . ($value == '1' ? 'NOT NULL' : 'NULL');
        }),
        App_table_filter::new('warranty_valid', 'BooleanRule')->label(_l('ams_warranty_valid'))->raw(function ($value) {
            $col = db_prefix() . 'ams_assets.warranty_end';

            return $value == '1' ? $col . ' >= CURDATE()' : '(' . $col . ' IS NULL OR ' . $col . ' < CURDATE())';
        }),
        App_table_filter::new('purchase_date', 'DateRule')->label(_l('ams_purchase_date')),
        App_table_filter::new('warranty_end', 'DateRule')->label(_l('ams_warranty_end')),
        App_table_filter::new('purchase_cost', 'NumberRule')->label(_l('ams_purchase_cost')),
    ]);
