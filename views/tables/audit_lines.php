<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Assets of one audit and what was found.

return App_table::find('ams_audit_lines')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_audit_lines';

        $aColumns = [
            'a.asset_tag as asset_tag',
            'a.name as asset_name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            'IF(pel.id IS NULL, el.name, CONCAT(pel.name, " › ", el.name)) as expected_location',
            'IF(pfl.id IS NULL, fl.name, CONCAT(pfl.name, " › ", fl.name)) as found_location',
            $t . '.result as result',
            $t . '.asset_condition as line_condition',
            $t . '.note as note',
            'CONCAT(s.firstname, " ", s.lastname) as scanned_by_name',
            $t . '.scanned_at as scanned_at',
        ];

        $join = [
            'JOIN ' . $p . 'ams_assets a ON a.id = ' . $t . '.asset_id',
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'ams_locations el ON el.id = ' . $t . '.expected_location_id',
            'LEFT JOIN ' . $p . 'ams_locations pel ON pel.id = el.parent_id',
            'LEFT JOIN ' . $p . 'ams_locations fl ON fl.id = ' . $t . '.found_location_id',
            'LEFT JOIN ' . $p . 'ams_locations pfl ON pfl.id = fl.parent_id',
            'LEFT JOIN ' . $p . 'staff s ON s.staffid = ' . $t . '.scanned_by',
            'JOIN ' . $p . 'ams_audits au ON au.id = ' . $t . '.audit_id',
        ];

        $where = ['AND ' . $t . '.audit_id = ' . (int) $audit_id];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.asset_id as asset_id', $t . '.scanned_by as scanned_by', 'au.status as audit_status']);
        $output = $result['output'];
        $canEdit = staff_can('edit', 'ams_audits');

        foreach ($result['rResult'] as $aRow) {
            $opts = [];
            if ($canEdit && $aRow['audit_status'] === 'in_progress') {
                if (! in_array($aRow['result'], ['found', 'unexpected'])) {
                    $opts[] = '<a href="#" onclick="ams_audit_line(' . (int) $aRow['id'] . ', \'found\'); return false;">' . _l('ams_audit_result_found') . '</a>';
                }
                if ($aRow['result'] !== 'unexpected' && $aRow['result'] !== 'missing') {
                    $opts[] = '<a href="#" class="text-danger" onclick="ams_audit_line(' . (int) $aRow['id'] . ', \'missing\'); return false;">' . _l('ams_audit_result_missing') . '</a>';
                }
                if ($aRow['result'] !== 'pending' && $aRow['result'] !== 'unexpected') {
                    $opts[] = '<a href="#" onclick="ams_audit_line(' . (int) $aRow['id'] . ', \'pending\'); return false;">' . _l('ams_audit_reset') . '</a>';
                }
            }

            $url   = admin_url('asset_management/assets/view/' . $aRow['asset_id']);
            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>' . ($opts ? '<div class="row-options">' . implode(' | ', $opts) . '</div>' : '');
            $row[] = '<a href="' . $url . '">' . e($aRow['asset_name']) . '</a>';
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['expected_location']);
            $row[] = e($aRow['found_location']);
            $row[] = ams_audit_result_badge($aRow['result']);
            $row[] = $aRow['line_condition'] ? e(ams_option_label(ams_condition_options(), $aRow['line_condition'])) : '';
            $row[] = e($aRow['note']);
            $row[] = $aRow['scanned_by'] ? ams_assignee_html('staff', $aRow['scanned_by'], $aRow['scanned_by_name']) : '';
            $row[] = e(_dt($aRow['scanned_at']));

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('result', 'MultiSelectRule')->label(_l('ams_audit_result'))
            ->options(fn () => collect(['pending', 'found', 'misplaced', 'unexpected', 'missing'])->map(fn ($r) => ['value' => $r, 'label' => _l('ams_audit_result_' . $r)])->all()),
        App_table_filter::new('expected_location_id', 'MultiSelectRule')->label(_l('ams_audit_expected_location'))
            ->options(fn () => collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all()),
    ]);
