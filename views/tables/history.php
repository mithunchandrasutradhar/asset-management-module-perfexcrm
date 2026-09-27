<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Asset profile → History tab (legacy asset_status timeline, extended).

return App_table::find('ams_history')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_asset_history';

        $aColumns = [
            $t . '.date_created as date_created',
            $t . '.action as action',
            'st.name as status_name',
            'COALESCE(CONCAT(hs.firstname, " ", hs.lastname), hd.name, hl.name) as assignee_name',
            'lt.name as location_name',
            'd.name as department_name',
            $t . '.note as note',
            'CONCAT(bs.firstname, " ", bs.lastname) as by_name',
        ];

        // Assignee shown = the holder after the action, or the one it came back from on check-in.
        $assignType = 'COALESCE(' . $t . '.assigned_type_to, ' . $t . '.assigned_type_from)';
        $assignId   = 'COALESCE(' . $t . '.assigned_id_to, ' . $t . '.assigned_id_from)';

        $join = [
            'LEFT JOIN ' . $p . 'ams_statuses st ON st.id = ' . $t . '.status_to',
            'LEFT JOIN ' . $p . 'ams_locations lt ON lt.id = ' . $t . '.location_to',
            'LEFT JOIN ' . $p . 'departments d ON d.departmentid = ' . $t . '.department_id',
            'LEFT JOIN ' . $p . 'staff hs ON ' . $assignType . ' = "staff" AND hs.staffid = ' . $assignId,
            'LEFT JOIN ' . $p . 'departments hd ON ' . $assignType . ' = "department" AND hd.departmentid = ' . $assignId,
            'LEFT JOIN ' . $p . 'ams_locations hl ON ' . $assignType . ' = "location" AND hl.id = ' . $assignId,
            'LEFT JOIN ' . $p . 'staff bs ON bs.staffid = ' . $t . '.staff_id',
        ];

        $where = ['AND ' . $t . '.asset_id = ' . (int) $asset_id];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            'st.color as status_color',
            $t . '.staff_id as staff_id',
            $assignType . ' as h_assign_type',
            $assignId . ' as h_assign_id',
            $t . '.assigned_type_to as assigned_type_to',
        ]);

        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $assignee = ams_assignee_html($aRow['h_assign_type'], $aRow['h_assign_id'], $aRow['assignee_name']);
            if ($assignee && $aRow['action'] === 'checkin') {
                $assignee = '<span class="text-muted">' . _l('ams_returned_from') . '</span> ' . $assignee;
            }

            $row   = [];
            $row[] = e(_dt($aRow['date_created']));
            $row[] = '<span class="tw-font-medium">' . e(_l('ams_action_' . $aRow['action'])) . '</span>';
            $row[] = ams_status_badge($aRow['status_name'], $aRow['status_color']);
            $row[] = $assignee;
            $row[] = e($aRow['location_name']);
            $row[] = e($aRow['department_name']);
            $row[] = nl2br(e($aRow['note']));
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('action', 'MultiSelectRule')->label(_l('ams_action'))->options(function () {
            return collect(['create', 'checkout', 'checkin', 'status', 'move', 'edit', 'files', 'accepted', 'declined', 'transfer', 'maintenance', 'license', 'dispose', 'reinstate', 'audit', 'delete'])
                ->map(fn ($a) => ['value' => $a, 'label' => _l('ams_action_' . $a)])->all();
        }),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('staff_id', 'MultiSelectRule')->label(_l('ams_done_by'))->options(function () {
            return collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all();
        }),
    ]);
