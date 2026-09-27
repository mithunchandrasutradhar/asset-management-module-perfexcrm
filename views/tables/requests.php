<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Requests: one requester's (My Assets), or everything the user may see.

return App_table::find('ams_requests')
    ->outputUsing(function ($params) {
        extract($params);

        $p  = db_prefix();
        $t  = $p . 'ams_requests';
        $me = (int) get_staff_user_id();

        $aColumns = [
            $t . '.request_no as request_no',
            $t . '.date_created as date_created',
            'CONCAT(rq.firstname, " ", rq.lastname) as requester_name',
            'd.name as department_name',
            $t . '.type as type',
            $t . '.subject as subject',
            $t . '.qty as qty',
            $t . '.priority as priority',
            $t . '.needed_by as needed_by',
            $t . '.status as status',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'staff rq ON rq.staffid = ' . $t . '.staff_id',
            'LEFT JOIN ' . $p . 'departments d ON d.departmentid = ' . $t . '.department_id',
        ];

        $where = [];
        if (! empty($staff_id)) {
            $where[] = 'AND ' . $t . '.staff_id = ' . (int) $staff_id;
        } elseif (staff_cant('view', 'ams_requests') && staff_cant('approve', 'ams_requests')) {
            // Department approvers: their departments' requests (plus their own).
            $depts   = ams_my_approver_departments() ?: [0];
            $where[] = 'AND (' . $t . '.department_id IN (' . implode(',', array_map('intval', $depts)) . ') OR ' . $t . '.staff_id = ' . $me . ')';
        }
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.staff_id as staff_id']);
        $output = $result['output'];
        $prio   = ['low' => 'default', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/requests/view/' . $aRow['id']);

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['request_no']) . '</a><div class="row-options"><a href="' . $url . '">' . _l('view') . '</a></div>';
            $row[] = e(_dt($aRow['date_created']));
            $row[] = ams_assignee_html('staff', $aRow['staff_id'], $aRow['requester_name']);
            $row[] = e($aRow['department_name']);
            $row[] = e(_l('ams_req_type_' . $aRow['type']));
            $row[] = '<a href="' . $url . '">' . e($aRow['subject']) . '</a>';
            $row[] = ams_qty($aRow['qty']);
            $row[] = '<span class="label label-' . ($prio[$aRow['priority']] ?? 'default') . '">' . _l('ams_priority_' . $aRow['priority']) . '</span>';
            $row[] = e(_d($aRow['needed_by']));
            $row[] = ams_request_status_badge($aRow['status']);

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['pending_dept', 'pending_manager', 'approved', 'rejected', 'fulfilled', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_req_status_' . $s)])->all()),
        App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_req_type'))
            ->options(fn () => collect(['asset', 'accessory', 'consumable', 'issue'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_req_type_' . $s)])->all()),
        App_table_filter::new('priority', 'MultiSelectRule')->label(_l('ams_priority'))
            ->options(fn () => collect(['low', 'normal', 'high', 'urgent'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_priority_' . $s)])->all()),
        App_table_filter::new('department_id', 'MultiSelectRule')->label(_l('ams_department'))
            ->options(fn () => collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all()),
        App_table_filter::new('staff_id', 'MultiSelectRule')->label(_l('ams_requester'))
            ->options(fn () => collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all()),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('needed_by', 'DateRule')->label(_l('ams_needed_by')),
    ]);
