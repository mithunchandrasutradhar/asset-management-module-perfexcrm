<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Physical audit campaigns.

return App_table::find('ams_audits')
    ->outputUsing(function ($params) {
        $p  = db_prefix();
        $t  = $p . 'ams_audits';
        $ln = $p . 'ams_audit_lines';

        $aColumns = [
            $t . '.audit_no as audit_no',
            $t . '.title as title',
            'CONCAT_WS(" / ", IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)), dp.name, c.name) as scope',
            $t . '.status as status',
            $t . '.due_date as due_date',
            '(SELECT COUNT(*) FROM ' . $ln . ' x WHERE x.audit_id = ' . $t . '.id AND x.result <> "unexpected") as expected_count',
            '(SELECT COUNT(*) FROM ' . $ln . ' x WHERE x.audit_id = ' . $t . '.id AND x.result IN ("found", "misplaced")) as seen_count',
            '(SELECT COUNT(*) FROM ' . $ln . ' x WHERE x.audit_id = ' . $t . '.id AND x.result = "missing") as missing_count',
            $t . '.completed_at as completed_at',
            $t . '.next_audit_date as next_audit_date',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
            'LEFT JOIN ' . $p . 'departments dp ON dp.departmentid = ' . $t . '.department_id',
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
        ];

        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id']);
        $output = $result['output'];
        $today  = date('Y-m-d');

        foreach ($result['rResult'] as $aRow) {
            $url      = admin_url('asset_management/audits/view/' . $aRow['id']);
            $expected = (int) $aRow['expected_count'];
            $seen     = (int) $aRow['seen_count'];
            $pct      = $expected ? (int) floor($seen * 100 / $expected) : 0;
            $overdue  = in_array($aRow['status'], ['draft', 'in_progress']) && $aRow['due_date'] && $aRow['due_date'] < $today;

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['audit_no']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['title']) . '</a>';
            $row[] = $aRow['scope'] !== '' && $aRow['scope'] !== null ? e($aRow['scope']) : '<span class="text-muted">' . _l('ams_audit_scope_all') . '</span>';
            $row[] = ams_audit_status_badge($aRow['status']);
            $row[] = $aRow['due_date'] ? '<span class="' . ($overdue ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($aRow['due_date'])) . '</span>' : '';
            $row[] = $expected ? '<div class="progress tw-mb-0" style="min-width:90px"><div class="progress-bar progress-bar-success" style="width:' . $pct . '%">' . $seen . '/' . $expected . '</div></div>' : '';
            $row[] = (int) $aRow['missing_count'] ? '<span class="text-danger tw-font-semibold">' . (int) $aRow['missing_count'] . '</span>' : '<span class="text-muted">0</span>';
            $row[] = e(_dt($aRow['completed_at']));
            $row[] = e(_d($aRow['next_audit_date']));

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['draft', 'in_progress', 'completed', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_audit_status_' . $s)])->all()),
        App_table_filter::new('location_id', 'MultiSelectRule')->label(_l('ams_location'))
            ->options(fn () => collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all()),
        App_table_filter::new('due_date', 'DateRule')->label(_l('ams_audit_due_date')),
        App_table_filter::new('next_audit_date', 'DateRule')->label(_l('ams_audit_next_date')),
    ]);
