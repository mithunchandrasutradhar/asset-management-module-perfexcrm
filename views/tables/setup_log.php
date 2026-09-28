<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Assets → Setup → Change log: who created / changed / deleted master data (old → new values).

return App_table::find('ams_setup_log')
    ->outputUsing(function ($params) {
        $p        = db_prefix();
        $t        = $p . 'ams_audit_log';
        $entities = ams_setup_entities();

        // Record name from its own table; deleted records keep their name in the change row.
        $names = [];
        $join  = ['LEFT JOIN ' . $p . 'staff s ON s.staffid = ' . $t . '.staff_id'];
        foreach ($entities as $key => $cfg) {
            $alias   = 'm_' . $key;
            $join[]  = 'LEFT JOIN ' . $p . $cfg['table'] . ' ' . $alias . ' ON ' . $t . '.rel_type = "' . $cfg['table'] . '" AND ' . $alias . '.id = ' . $t . '.rel_id';
            $names[] = $alias . '.name';
        }

        $aColumns = [
            $t . '.date_created as date_created',
            $t . '.rel_type as rel_type',
            'COALESCE(' . implode(', ', $names) . ') as record_name',
            $t . '.action as action',
            $t . '.changes as changes',
            'CONCAT(s.firstname, " ", s.lastname) as by_name',
        ];

        $types = array_map(fn ($cfg) => '"' . $cfg['table'] . '"', $entities);
        $where = ['AND ' . $t . '.rel_type IN (' . implode(',', $types) . ')'];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.staff_id as staff_id', $t . '.rel_id as rel_id']);
        $output = $result['output'];

        $typeLabel = [];
        foreach ($entities as $cfg) {
            $typeLabel[$cfg['table']] = _l($cfg['singular']);
        }

        foreach ($result['rResult'] as $aRow) {
            $changes = json_decode((string) $aRow['changes'], true) ?: [];
            $name    = $aRow['record_name'] ?: ($changes['name'][0] ?? ('#' . (int) $aRow['rel_id']));
            $html    = '';
            foreach ($changes as $field => $values) {
                $html .= '<div><span class="tw-font-medium">' . e(_l('ams_field_' . $field, '', false)) . ':</span> '
                    . '<span class="text-muted">' . e((string) ($values[0] ?? '')) . '</span>'
                    . ' <i class="fa-solid fa-arrow-right-long tw-mx-1 tw-text-neutral-400"></i> '
                    . e((string) ($values[1] ?? '')) . '</div>';
            }

            $row   = [];
            $row[] = e(_dt($aRow['date_created']));
            $row[] = e($typeLabel[$aRow['rel_type']] ?? $aRow['rel_type']);
            $row[] = '<span class="tw-font-medium">' . e($name) . '</span>';
            $row[] = e(_l('ams_audit_' . $aRow['action']));
            $row[] = $html ?: '<span class="text-muted">-</span>';
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('rel_type', 'MultiSelectRule')->label(_l('ams_setup_record_type'))->options(function () {
            return collect(ams_setup_entities())->map(fn ($cfg) => ['value' => $cfg['table'], 'label' => _l($cfg['singular'])])->values()->all();
        }),
        App_table_filter::new('action', 'SelectRule')->label(_l('ams_action'))->options(function () {
            return collect(['create', 'update', 'delete'])->map(fn ($a) => ['value' => $a, 'label' => _l('ams_audit_' . $a)])->all();
        }),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
    ]);
