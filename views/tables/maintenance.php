<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Maintenance jobs (all, or one asset's).

return App_table::find('ams_maintenance')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_maintenance';

        $aColumns = [
            $t . '.id as id',
            'CONCAT(a.asset_tag, " - ", a.name) as asset_label',
            $t . '.title as title',
            $t . '.type as type',
            $t . '.status as status',
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
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.asset_id as asset_id', $t . '.schedule_id as schedule_id', $t . '.request_id as request_id']);
        $output = $result['output'];

        $canEdit   = staff_can('edit', 'ams_maintenance');
        $canDelete = staff_can('delete', 'ams_maintenance');
        $cur       = get_base_currency();
        $today     = date('Y-m-d');
        $badge     = ['scheduled' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'default'];

        foreach ($result['rResult'] as $aRow) {
            $open = in_array($aRow['status'], ['scheduled', 'in_progress']);
            $opts = [];
            if ($canEdit && $aRow['status'] === 'scheduled') {
                $opts[] = '<a href="#" onclick="ams_mt_action(\'start\', ' . (int) $aRow['id'] . '); return false;">' . _l('ams_mt_start') . '</a>';
            }
            if ($canEdit && $open) {
                $opts[] = '<a href="#" onclick="ams_mt_action(\'complete\', ' . (int) $aRow['id'] . '); return false;">' . _l('ams_mt_complete') . '</a>';
                $opts[] = '<a href="#" onclick="ams_mt_edit(' . (int) $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
                $opts[] = '<a href="#" class="text-danger" onclick="ams_mt_cancel(' . (int) $aRow['id'] . '); return false;">' . _l('ams_mt_cancel') . '</a>';
            }
            if ($canDelete && $aRow['status'] !== 'in_progress') {
                $opts[] = '<a href="' . admin_url('asset_management/maintenance/delete/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }

            $title = '<span class="tw-font-medium">' . e($aRow['title']) . '</span>';
            if ($aRow['schedule_id']) {
                $title .= ' <i class="fa-regular fa-calendar-check text-muted" title="' . _l('ams_mt_from_schedule') . '"></i>';
            }
            if ($aRow['request_id']) {
                $title .= ' <a href="' . admin_url('asset_management/requests/view/' . $aRow['request_id']) . '" title="' . _l('ams_request') . '"><i class="fa-regular fa-comment-dots"></i></a>';
            }
            $title .= $opts ? '<div class="row-options">' . implode(' | ', $opts) . '</div>' : '';

            $overdue = $open && $aRow['due_date'] && $aRow['due_date'] < $today;

            $row   = [];
            $row[] = (int) $aRow['id'];
            $row[] = '<a href="' . admin_url('asset_management/assets/view/' . $aRow['asset_id']) . '">' . e($aRow['asset_label']) . '</a>';
            $row[] = $title;
            $row[] = e(_l('ams_mt_type_' . $aRow['type']));
            $row[] = '<span class="label label-' . ($badge[$aRow['status']] ?? 'default') . '">' . _l('ams_mt_status_' . $aRow['status']) . '</span>';
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
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['scheduled', 'in_progress', 'completed', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_status_' . $s)])->all()),
        App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_mt_type'))
            ->options(fn () => collect(['repair', 'upgrade', 'preventive', 'inspection', 'software', 'other'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_type_' . $s)])->all()),
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
