<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Preventive maintenance schedules.

return App_table::find('ams_schedules')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_maintenance_schedules';

        $aColumns = [
            'CONCAT(a.asset_tag, " - ", a.name) as asset_label',
            $t . '.title as title',
            $t . '.type as type',
            $t . '.interval_value as interval_value',
            $t . '.next_due as next_due',
            $t . '.last_done as last_done',
            'sp.name as supplier_name',
            $t . '.active as active',
        ];

        $join = [
            'JOIN ' . $p . 'ams_assets a ON a.id = ' . $t . '.asset_id AND a.is_deleted = 0',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
        ];

        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result  = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.asset_id as asset_id', $t . '.interval_unit as interval_unit']);
        $output  = $result['output'];
        $canEdit = staff_can('edit', 'ams_maintenance');
        $canDel  = staff_can('delete', 'ams_maintenance');
        $today   = date('Y-m-d');

        foreach ($result['rResult'] as $aRow) {
            $opts = [];
            if ($canEdit) {
                $opts[] = '<a href="#" onclick="ams_sch_edit(' . (int) $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
            }
            if ($canDel) {
                $opts[] = '<a href="' . admin_url('asset_management/maintenance/delete_schedule/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
            }

            $row   = [];
            $row[] = '<a href="' . admin_url('asset_management/assets/view/' . $aRow['asset_id']) . '">' . e($aRow['asset_label']) . '</a>';
            $row[] = '<span class="tw-font-medium">' . e($aRow['title']) . '</span>' . ($opts ? '<div class="row-options">' . implode(' | ', $opts) . '</div>' : '');
            $row[] = e(_l('ams_mt_type_' . $aRow['type']));
            $row[] = e(_l('ams_mt_every', [(int) $aRow['interval_value'], _l('ams_unit_' . $aRow['interval_unit'])]));
            $row[] = '<span class="' . ($aRow['next_due'] < $today ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($aRow['next_due'])) . '</span>';
            $row[] = e(_d($aRow['last_done']));
            $row[] = e($aRow['supplier_name']);
            $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('ams_active') . '</span>' : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('next_due', 'DateRule')->label(_l('ams_mt_next_due')),
        App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_mt_type'))
            ->options(fn () => collect(['repair', 'upgrade', 'preventive', 'inspection', 'software', 'other'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_mt_type_' . $s)])->all()),
        App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
    ]);
