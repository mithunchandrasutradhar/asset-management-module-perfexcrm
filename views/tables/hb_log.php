<?php

defined('BASEPATH') or exit('No direct script access allowed');

// HostBill API / webhook log (request, response, status, duration).

return App_table::find('ams_hb_log')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_hb_sync_log';

        $aColumns = [
            $t . '.date_created as date_created',
            $t . '.direction as direction',
            $t . '.api_call as api_call',
            $t . '.success as success',
            $t . '.http_code as http_code',
            $t . '.duration_ms as duration_ms',
            $t . '.error as error',
            'CONCAT(s.firstname, " ", s.lastname) as by_name',
        ];

        $join  = ['LEFT JOIN ' . $p . 'staff s ON s.staffid = ' . $t . '.staff_id'];
        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.staff_id as staff_id']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $row   = [];
            $row[] = e(_dt($aRow['date_created'])) . '<div class="row-options"><a href="#" onclick="ams_hb_log_entry(' . (int) $aRow['id'] . '); return false;">' . _l('view') . '</a></div>';
            $row[] = e(_l('ams_hb_dir_' . $aRow['direction']));
            $row[] = '<code>' . e($aRow['api_call']) . '</code>';
            $row[] = $aRow['success'] ? '<span class="label label-success">' . _l('ams_hb_ok') . '</span>' : '<span class="label label-danger">' . _l('ams_hb_error') . '</span>';
            $row[] = e($aRow['http_code']);
            $row[] = $aRow['duration_ms'] !== null ? (int) $aRow['duration_ms'] . ' ms' : '';
            $row[] = e(mb_substr((string) $aRow['error'], 0, 160));
            $row[] = $aRow['staff_id'] ? e($aRow['by_name']) : '<span class="text-muted">' . _l('ams_hb_system') . '</span>';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('direction', 'MultiSelectRule')->label(_l('ams_hb_direction'))
            ->options(fn () => collect(['pull', 'push', 'webhook', 'test'])->map(fn ($d) => ['value' => $d, 'label' => _l('ams_hb_dir_' . $d)])->all()),
        App_table_filter::new('success', 'BooleanRule')->label(_l('ams_hb_ok')),
        App_table_filter::new('api_call', 'TextRule')->label(_l('ams_hb_api_call')),
    ]);
