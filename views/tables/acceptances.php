<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Acceptances of one staff member (My Assets, or the per-staff page).

return App_table::find('ams_acceptances')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_acceptances';

        $aColumns = [
            $t . '.date_created as date_created',
            'IF(' . $t . '.rel_type = "asset", CONCAT(a.asset_tag, " - ", a.name), CONCAT(ROUND(co.qty), " × ", i.sku, " - ", i.name)) as item_label',
            $t . '.rel_type as rel_type',
            $t . '.status as status',
            $t . '.date_responded as date_responded',
            $t . '.signed_name as signed_name',
            'CONCAT(rs.firstname, " ", rs.lastname) as requested_by_name',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_assets a ON ' . $t . '.rel_type = "asset" AND a.id = ' . $t . '.rel_id',
            'LEFT JOIN ' . $p . 'ams_item_checkouts co ON ' . $t . '.rel_type = "accessory" AND co.id = ' . $t . '.rel_id',
            'LEFT JOIN ' . $p . 'ams_items i ON i.id = co.item_id',
            'LEFT JOIN ' . $p . 'staff rs ON rs.staffid = ' . $t . '.requested_by',
        ];

        $where = ['AND ' . $t . '.staff_id = ' . (int) $staff_id];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.rel_id as rel_id', $t . '.requested_by as requested_by']);
        $output = $result['output'];
        $mine   = ! empty($mine) && (int) $staff_id === (int) get_staff_user_id();

        foreach ($result['rResult'] as $aRow) {
            $item = e($aRow['item_label']);
            if ($aRow['rel_type'] === 'asset' && ams_can_view_asset(['assigned_type' => 'staff', 'assigned_id' => $staff_id])) {
                $item = '<a href="' . admin_url('asset_management/assets/view/' . $aRow['rel_id']) . '">' . $item . '</a>';
            }

            $actions = [];
            if ($mine && $aRow['status'] === 'pending') {
                $actions[] = '<a href="#" class="tw-font-semibold" onclick="ams_open_acceptance(' . (int) $aRow['id'] . '); return false;">' . _l('ams_review_and_sign') . '</a>';
            } elseif ($aRow['status'] !== 'pending') {
                $actions[] = '<a href="#" onclick="ams_open_acceptance(' . (int) $aRow['id'] . '); return false;">' . _l('view') . '</a>';
            }

            $row   = [];
            $row[] = e(_dt($aRow['date_created'])) . ($actions ? '<div class="row-options">' . implode(' | ', $actions) . '</div>' : '');
            $row[] = $item;
            $row[] = e(_l($aRow['rel_type'] === 'asset' ? 'ams_asset' : 'ams_accessory'));
            $row[] = ams_acceptance_status_badge($aRow['status']);
            $row[] = e(_dt($aRow['date_responded']));
            $row[] = e($aRow['signed_name']);
            $row[] = $aRow['requested_by'] ? e($aRow['requested_by_name']) : '';

            $row['DT_RowClass'] = 'has-row-options' . ($mine && $aRow['status'] === 'pending' ? ' warning' : '');
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('status', 'MultiSelectRule')->label(_l('ams_status'))
            ->options(fn () => collect(['pending', 'accepted', 'declined', 'cancelled'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_acc_status_' . $s)])->all()),
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
    ]);
