<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Accessory check-outs (all, or one item's). "View (own)" users see only their own.

return App_table::find('ams_checkouts')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_item_checkouts';

        $aColumns = [
            $t . '.date_created as date_created',
            'CONCAT(i.sku, " - ", i.name) as item_label',
            $t . '.qty as qty',
            $t . '.returned_qty as returned_qty',
            '(' . $t . '.qty - ' . $t . '.returned_qty) as outstanding',
            'COALESCE(CONCAT(cs.firstname, " ", cs.lastname), cd.name) as holder_name',
            'd.name as department_name',
            $t . '.expected_return as expected_return',
            $t . '.status as status',
            'CONCAT(bs.firstname, " ", bs.lastname) as by_name',
        ];

        $join = [
            'JOIN ' . $p . 'ams_items i ON i.id = ' . $t . '.item_id',
            'LEFT JOIN ' . $p . 'staff cs ON ' . $t . '.assigned_type = "staff" AND cs.staffid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'departments cd ON ' . $t . '.assigned_type = "department" AND cd.departmentid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'departments d ON d.departmentid = ' . $t . '.department_id',
            'LEFT JOIN ' . $p . 'staff bs ON bs.staffid = ' . $t . '.staff_id',
        ];

        $where = [];
        if (! empty($item_id)) {
            $where[] = 'AND ' . $t . '.item_id = ' . (int) $item_id;
        }
        if (! empty($holder_staff)) {
            // My Assets / Assets-by-staff page (access checked by the controller).
            $where[] = 'AND ' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id = ' . (int) $holder_staff;
        } elseif (staff_cant('view', 'ams_accessories')) {
            $where[] = 'AND ' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id = ' . (int) get_staff_user_id();
        }

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            $t . '.item_id as item_id',
            'i.unit as unit',
            $t . '.assigned_type as assigned_type',
            $t . '.assigned_id as assigned_id',
            $t . '.location_id as location_id',
            $t . '.staff_id as staff_id',
            $t . '.note as note',
        ]);
        $output     = $result['output'];
        $canCheckin = staff_can('checkout', 'ams_accessories');
        $today      = date('Y-m-d');

        foreach ($result['rResult'] as $aRow) {
            $open    = $aRow['status'] === 'open';
            $overdue = $open && $aRow['expected_return'] && $aRow['expected_return'] < $today;

            $date = e(_dt($aRow['date_created']));
            if ($open && $canCheckin) {
                $date .= '<div class="row-options"><a href="#" onclick="ams_checkin(' . (int) $aRow['id'] . ', \'' . ams_qty($aRow['outstanding']) . '\', ' . (int) $aRow['location_id'] . '); return false;">' . _l('ams_checkin') . '</a></div>';
            }

            $row   = [];
            $row[] = $date;
            $row[] = '<a href="' . admin_url('asset_management/inventory/view/' . $aRow['item_id']) . '">' . e($aRow['item_label']) . '</a>';
            $row[] = ams_qty($aRow['qty']) . ' <span class="text-muted tw-text-xs">' . e($aRow['unit']) . '</span>';
            $row[] = ams_qty($aRow['returned_qty']);
            $row[] = '<span class="tw-font-semibold">' . ams_qty($aRow['outstanding']) . '</span>';
            $row[] = ams_assignee_html($aRow['assigned_type'], $aRow['assigned_id'], $aRow['holder_name']);
            $row[] = e($aRow['department_name']);
            $row[] = $aRow['expected_return'] ? '<span class="' . ($overdue ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($aRow['expected_return'])) . ($overdue ? ' (' . _l('ams_overdue') . ')' : '') . '</span>' : '';
            $row[] = $open ? '<span class="label label-info">' . _l('ams_checkout_open') . '</span>' : '<span class="label label-default">' . _l('ams_checkout_closed') . '</span>';
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('status', 'SelectRule')->label(_l('ams_status'))
            ->options(fn () => [['value' => 'open', 'label' => _l('ams_checkout_open')], ['value' => 'closed', 'label' => _l('ams_checkout_closed')]]),
        App_table_filter::new('holder_staff', 'MultiSelectRule')->label(_l('ams_assigned_staff'))
            ->raw(function ($value, $operator) {
                $t   = db_prefix() . 'ams_item_checkouts';
                $sql = '(' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id IN (\'' . implode("','", (array) $value) . '\'))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(fn () => collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all()),
        App_table_filter::new('department_id', 'MultiSelectRule')->label(_l('ams_department'))
            ->options(fn () => collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all()),
        App_table_filter::new('overdue', 'BooleanRule')->label(_l('ams_overdue'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_item_checkouts';
            $sql = '(' . $t . '.status = "open" AND ' . $t . '.expected_return < CURDATE())';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('expected_return', 'DateRule')->label(_l('ams_expected_return')),
    ]);
