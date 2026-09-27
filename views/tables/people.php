<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Assets by Staff: Perfex staff with what each one holds.

return App_table::find('ams_people')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'staff';

        $assetsSql = '(SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.assigned_type = "staff" AND x.assigned_id = ' . $t . '.staffid)';
        $accSql    = '(SELECT IFNULL(SUM(c.qty - c.returned_qty), 0) FROM ' . $p . 'ams_item_checkouts c WHERE c.status = "open" AND c.assigned_type = "staff" AND c.assigned_id = ' . $t . '.staffid)';
        $overdue   = '((SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.assigned_type = "staff" AND x.assigned_id = ' . $t . '.staffid AND x.expected_checkin < CURDATE())'
            . ' + (SELECT COUNT(*) FROM ' . $p . 'ams_item_checkouts c WHERE c.status = "open" AND c.assigned_type = "staff" AND c.assigned_id = ' . $t . '.staffid AND c.expected_return < CURDATE()))';

        $aColumns = [
            'CONCAT(' . $t . '.firstname, " ", ' . $t . '.lastname) as full_name',
            '(SELECT GROUP_CONCAT(d.name SEPARATOR ", ") FROM ' . $p . 'staff_departments sd JOIN ' . $p . 'departments d ON d.departmentid = sd.departmentid WHERE sd.staffid = ' . $t . '.staffid) as departments',
            $assetsSql . ' as assets_held',
            $accSql . ' as accessories_held',
            $overdue . ' as overdue_count',
            '(SELECT COUNT(*) FROM ' . $p . 'ams_acceptances ac WHERE ac.status = "pending" AND ac.staff_id = ' . $t . '.staffid) as pending_acceptances',
            '(SELECT COUNT(*) FROM ' . $p . 'ams_requests r WHERE r.status IN ("pending_dept", "pending_manager", "approved") AND r.staff_id = ' . $t . '.staffid) as open_requests',
            $t . '.active as active',
        ];

        $where = ['AND ' . $t . '.is_not_staff = 0'];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'staffid', $t, [], $where, [$t . '.staffid as staffid']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/people/staff/' . $aRow['staffid']);

            $row   = [];
            $row[] = '<a href="' . $url . '">' . staff_profile_image($aRow['staffid'], ['staff-profile-image-small', 'tw-mr-1']) . e($aRow['full_name']) . '</a>'
                . '<div class="row-options"><a href="' . $url . '">' . _l('view') . '</a> | <a href="' . admin_url('staff/member/' . $aRow['staffid']) . '">' . _l('ams_staff_profile') . '</a></div>';
            $row[] = e($aRow['departments']);
            $row[] = (int) $aRow['assets_held'] ?: '<span class="text-muted">0</span>';
            $row[] = (float) $aRow['accessories_held'] ? ams_qty($aRow['accessories_held']) : '<span class="text-muted">0</span>';
            $row[] = (int) $aRow['overdue_count'] ? '<span class="text-danger tw-font-semibold">' . (int) $aRow['overdue_count'] . '</span>' : '<span class="text-muted">0</span>';
            $row[] = (int) $aRow['pending_acceptances'] ? '<span class="text-warning tw-font-semibold">' . (int) $aRow['pending_acceptances'] . '</span>' : '<span class="text-muted">0</span>';
            $row[] = (int) $aRow['open_requests'] ?: '<span class="text-muted">0</span>';
            $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('ams_active') . '</span>' : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('department', 'MultiSelectRule')->label(_l('ams_department'))
            ->raw(function ($value, $operator) {
                $sql = db_prefix() . 'staff.staffid IN (SELECT staffid FROM ' . db_prefix() . 'staff_departments WHERE departmentid IN (\'' . implode("','", (array) $value) . '\'))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(fn () => collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all()),
        App_table_filter::new('holds_items', 'BooleanRule')->label(_l('ams_holds_items'))->raw(function ($value) {
            $p   = db_prefix();
            $sql = '(EXISTS (SELECT 1 FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.assigned_type = "staff" AND x.assigned_id = ' . $p . 'staff.staffid)'
                . ' OR EXISTS (SELECT 1 FROM ' . $p . 'ams_item_checkouts c WHERE c.status = "open" AND c.assigned_type = "staff" AND c.assigned_id = ' . $p . 'staff.staffid))';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
        App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
    ]);
