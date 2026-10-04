<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Maintenance jobs (all, one asset's, or one staff member's: mine = staff id).

return App_table::find('ams_maintenance')
    ->outputUsing(function ($params) {
        extract($params);

        $CI = &get_instance();
        $CI->load->model(AMS_MODULE_NAME . "/ams_maintenance_model");

        $p = db_prefix();
        $t = $p . 'ams_maintenance';

        $aColumns = [
            '1',
            $t . '.id as id',
            'CONCAT(a.asset_tag, " - ", a.name) as asset_label',
            $t . '.title as title',
            $t . '.type as type',
            $t . '.status as status',
            '2',
            $t . '.due_date as due_date',
            $t . '.start_date as start_date',
            $t . '.end_date as end_date',
            'sp.name as supplier_name',
            $t . '.cost as cost',
        ];

        $join = [
            'JOIN ' . $p . 'ams_assets a ON a.id = ' . $t . '.asset_id',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
        ];

        $where = [];
        if (! empty($asset_id)) {
            $where[] = 'AND ' . $t . '.asset_id = ' . (int) $asset_id;
        }
        if (! empty($mine)) {
            $where[] = 'AND ' . Ams_maintenance_model::mine_sql($t . '.id', (int) $mine);
        }
        if (! empty($open_only)) {
            $where[] = 'AND ' . $t . '.status IN ("scheduled", "in_progress")';
        }
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.asset_id as asset_id', $t . '.schedule_id as schedule_id', $t . '.request_id as request_id',
            $t . '.acknowledged_at as acknowledged_at', $t . '.check_result as check_result',
        ]);
        $output = $result['output'];

        $people = $CI->ams_maintenance_model->responsible_map('job', array_column($result['rResult'], 'id'));
        $me     = (int) get_staff_user_id();
        $myJobs = $CI->db->query('SELECT DISTINCT rel_id FROM ' . $p . 'ams_maintenance_staff ms WHERE ms.rel_type = "job" AND (ms.staff_id = ? OR ms.department_id IN
            (SELECT departmentid FROM ' . $p . 'staff_departments WHERE staffid = ?))', [$me, $me])->result_array();
        $myJobs = array_flip(array_map('intval', array_column($myJobs, 'rel_id')));

        $canEdit   = staff_can('edit', 'ams_maintenance');
        $canDelete = staff_can('delete', 'ams_maintenance');
        $canAsset  = staff_can('view', 'ams_assets');
        $cur       = get_base_currency();
        $today     = date('Y-m-d');
        $badge     = ['scheduled' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'default'];
        $checkCss  = ['working' => 'text-success', 'partial' => 'text-warning', 'not_working' => 'text-danger'];

        foreach ($result['rResult'] as $aRow) {
            $id      = (int) $aRow['id'];
            $open    = in_array($aRow['status'], ['scheduled', 'in_progress']);
            $isMine  = isset($myJobs[$id]);
            $canWork = $canEdit || $isMine;
            $opts    = ['<a href="' . admin_url('asset_management/maintenance/view/' . $id) . '">' . _l('view') . '</a>'];
            if ($canWork && $aRow['status'] === 'scheduled') {
                $opts[] = '<a href="#" onclick="ams_mt_action(\'start\', ' . $id . '); return false;">' . _l('ams_mt_start') . '</a>';
            }
            if ($canWork && $open) {
                $opts[] = '<a href="#" onclick="ams_mt_action(\'complete\', ' . $id . ', \'' . e($aRow['type']) . '\'); return false;">' . _l('ams_mt_complete') . '</a>';
            }
            if ($canEdit && $open) {
                $opts[] = '<a href="#" onclick="ams_mt_edit(' . $id . '); return false;">' . _l('edit') . '</a>';
                $opts[] = '<a href="#" class="text-danger" onclick="ams_mt_cancel(' . $id . '); return false;">' . _l('ams_mt_cancel') . '</a>';
            }
            if ($canDelete && $aRow['status'] !== 'in_progress') {
                $opts[] = '<a href="' . admin_url('asset_management/maintenance/delete/' . $id) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }

            $title = '<a href="' . admin_url('asset_management/maintenance/view/' . $id) . '" class="tw-font-medium">' . e($aRow['title']) . '</a>';
            if ($aRow['schedule_id']) {
                $title .= ' <i class="fa-regular fa-calendar-check text-muted" title="' . _l('ams_mt_from_schedule') . '"></i>';
            }
            if ($aRow['request_id']) {
                $title .= ' <a href="' . admin_url('asset_management/requests/view/' . $aRow['request_id']) . '" title="' . _l('ams_request') . '"><i class="fa-regular fa-comment-dots"></i></a>';
            }
            if ($aRow['check_result']) {
                $title .= ' <i class="fa-solid fa-circle ' . $checkCss[$aRow['check_result']] . ' tw-text-xs" title="' . e(_l('ams_mt_check_' . $aRow['check_result'])) . '"></i>';
            }
            $title .= '<div class="row-options">' . implode(' | ', $opts) . '</div>';

            $overdue = $open && $aRow['due_date'] && $aRow['due_date'] < $today;
            $rows    = $people[$id] ?? [];
            if ($rows) {
                $resp = e($CI->ams_maintenance_model->responsible_label($rows));
                if ($open) {
                    $resp .= $aRow['acknowledged_at']
                        ? ' <i class="fa-solid fa-check text-success" title="' . e(_l('ams_mt_acknowledged')) . '"></i>'
                        : ' <i class="fa-regular fa-hourglass-half text-warning" title="' . e(_l('ams_mt_not_acknowledged')) . '"></i>';
                }
            } else {
                $resp = $open ? '<span class="label label-default">' . _l('ams_mt_unassigned') . '</span>' : '';
            }

            $row   = [];
            $row[] = '<div class="checkbox"><input type="checkbox" value="' . $id . '"><label></label></div>';
            $row[] = $id;
            $row[] = $canAsset ? '<a href="' . admin_url('asset_management/assets/view/' . $aRow['asset_id']) . '">' . e($aRow['asset_label']) . '</a>' : e($aRow['asset_label']);
            $row[] = $title;
            $row[] = e(_l('ams_mt_type_' . $aRow['type']));
            $row[] = '<span class="label label-' . ($badge[$aRow['status']] ?? 'default') . '">' . _l('ams_mt_status_' . $aRow['status']) . '</span>';
            $row[] = $resp;
            $row[] = $aRow['due_date'] ? '<span class="' . ($overdue ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($aRow['due_date'])) . '</span>' : '';
            $row[] = e(_d($aRow['start_date']));
            $row[] = e(_d($aRow['end_date']));
            $row[] = e($aRow['supplier_name']);
            $row[] = $aRow['cost'] !== null ? e(app_format_money($aRow['cost'], $cur)) : '';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('mine', 'BooleanRule')->label(_l('ams_mt_my_jobs'))->raw(function ($value) {
            $sql = Ams_maintenance_model::mine_sql(db_prefix() . 'ams_maintenance.id', (int) get_staff_user_id());

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
        App_table_filter::new('responsible_staff', 'MultiSelectRule')->label(_l('ams_mt_responsible'))
            ->options(fn () => collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all())
            ->raw(function ($value, $operator) {
                $ids = array_filter(array_map('intval', (array) $value));
                if (! $ids) {
                    return '';
                }
                $sql = '(' . implode(' OR ', array_map(fn ($id) => Ams_maintenance_model::mine_sql(db_prefix() . 'ams_maintenance.id', $id), $ids)) . ')';

                return $operator === 'not_in' ? 'NOT ' . $sql : $sql;
            }),
        App_table_filter::new('unassigned', 'BooleanRule')->label(_l('ams_mt_unassigned'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_maintenance';
            $sql = 'NOT EXISTS (SELECT 1 FROM ' . db_prefix() . 'ams_maintenance_staff ms_u WHERE ms_u.rel_type = "job" AND ms_u.rel_id = ' . $t . '.id)';

            return $value == '1' ? $sql : 'NOT (' . $sql . ')';
        }),
        App_table_filter::new('not_acknowledged', 'BooleanRule')->label(_l('ams_mt_not_acknowledged'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_maintenance';
            $sql = '(' . $t . '.status IN ("scheduled", "in_progress") AND ' . $t . '.acknowledged_at IS NULL AND ' . $t . '.assigned_at IS NOT NULL)';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['scheduled', 'in_progress', 'completed', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_status_' . $s)])->all()),
        App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_mt_type'))
            ->options(fn () => collect(['repair', 'upgrade', 'preventive', 'inspection', 'software', 'other'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_type_' . $s)])->all()),
        App_table_filter::new('check_result', 'MultiSelectRule')->label(_l('ams_mt_check_result'))
            ->options(fn () => collect(['working', 'partial', 'not_working'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_check_' . $s)])->all()),
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))
            ->options(fn () => collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all()),
        App_table_filter::new('due_date', 'DateRule')->label(_l('ams_mt_due_date')),
        App_table_filter::new('end_date', 'DateRule')->label(_l('ams_mt_end_date')),
        App_table_filter::new('cost', 'NumberRule')->label(_l('ams_mt_cost')),
        App_table_filter::new('overdue', 'BooleanRule')->label(_l('ams_overdue'))->raw(function ($value) {
            $t   = db_prefix() . 'ams_maintenance';
            $sql = '(' . $t . '.status IN ("scheduled", "in_progress") AND ' . $t . '.due_date < CURDATE())';

            return $value == '1' ? $sql : 'NOT ' . $sql;
        }),
    ]);
