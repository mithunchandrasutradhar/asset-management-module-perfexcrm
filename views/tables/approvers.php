<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Perfex departments with the staff who approve their asset requests.

return App_table::find('ams_approvers')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'departments';

        $aColumns = [
            $t . '.name as name',
            '(SELECT GROUP_CONCAT(CONCAT(s.firstname, " ", s.lastname) SEPARATOR ", ") FROM ' . $p . 'ams_department_approvers da JOIN ' . $p . 'staff s ON s.staffid = da.staff_id WHERE da.department_id = ' . $t . '.departmentid) as approver_names',
            '(SELECT COUNT(*) FROM ' . $p . 'staff_departments sd WHERE sd.departmentid = ' . $t . '.departmentid) as member_count',
            '(SELECT COUNT(*) FROM ' . $p . 'ams_requests r WHERE r.department_id = ' . $t . '.departmentid AND r.status = "pending_dept") as pending_count',
        ];

        $result  = data_tables_init($aColumns, 'departmentid', $t, [], [], [$t . '.departmentid as departmentid']);
        $output  = $result['output'];
        $canEdit = staff_can('edit', 'ams_setup');

        foreach ($result['rResult'] as $aRow) {
            $name = '<span class="tw-font-medium">' . e($aRow['name']) . '</span>';
            if ($canEdit) {
                $name .= '<div class="row-options"><a href="#" onclick="ams_edit_approvers(' . (int) $aRow['departmentid'] . ', ' . e(json_encode($aRow['name'])) . '); return false;">' . _l('edit') . '</a></div>';
            }

            $row   = [];
            $row[] = $name;
            $row[] = $aRow['approver_names'] ? e($aRow['approver_names']) : '<span class="text-muted">' . _l('ams_no_approvers') . '</span>';
            $row[] = (int) $aRow['member_count'];
            $row[] = (int) $aRow['pending_count'];

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    });
