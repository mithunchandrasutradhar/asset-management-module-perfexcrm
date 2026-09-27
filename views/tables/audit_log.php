<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Asset profile → Change log tab (field-level old → new values).

return App_table::find('ams_audit_log')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_audit_log';

        $aColumns = [
            $t . '.date_created as date_created',
            $t . '.action as action',
            $t . '.changes as changes',
            'CONCAT(s.firstname, " ", s.lastname) as by_name',
        ];

        $join = ['LEFT JOIN ' . $p . 'staff s ON s.staffid = ' . $t . '.staff_id'];

        $where = [
            'AND ' . $t . '.rel_type = "ams_assets"',
            'AND ' . $t . '.rel_id = ' . (int) $asset_id,
        ];

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.staff_id as staff_id']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $changes = json_decode((string) $aRow['changes'], true) ?: [];
            $html    = '';
            foreach ($changes as $field => $values) {
                $html .= '<div><span class="tw-font-medium">' . e(_l('ams_field_' . $field, '', false)) . ':</span> '
                    . '<span class="text-muted">' . e((string) ($values[0] ?? '')) . '</span>'
                    . ' <i class="fa-solid fa-arrow-right-long tw-mx-1 tw-text-neutral-400"></i> '
                    . e((string) ($values[1] ?? '')) . '</div>';
            }

            $row   = [];
            $row[] = e(_dt($aRow['date_created']));
            $row[] = e(_l('ams_audit_' . $aRow['action']));
            $row[] = $html ?: '<span class="text-muted">-</span>';
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('action', 'SelectRule')->label(_l('ams_action'))->options(function () {
            return collect(['create', 'update', 'delete'])->map(fn ($a) => ['value' => $a, 'label' => _l('ams_audit_' . $a)])->all();
        }),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
    ]);
