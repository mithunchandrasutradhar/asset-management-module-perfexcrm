<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Licence seats: of a licence, of a staff member (My Assets) or of an asset.

return App_table::find('ams_license_seats')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_license_seats';

        $aColumns = [
            'l.name as license_name',
            'COALESCE(CONCAT(ss.firstname, " ", ss.lastname), CONCAT(sa.asset_tag, " - ", sa.name)) as holder_label',
            $t . '.assigned_at as assigned_at',
            $t . '.note as note',
            $t . '.released_at as released_at',
            'CONCAT(bs.firstname, " ", bs.lastname) as by_name',
        ];

        $join = [
            'JOIN ' . $p . 'ams_licenses l ON l.id = ' . $t . '.license_id',
            'LEFT JOIN ' . $p . 'staff ss ON ' . $t . '.assigned_type = "staff" AND ss.staffid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'ams_assets sa ON ' . $t . '.assigned_type = "asset" AND sa.id = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'staff bs ON bs.staffid = ' . $t . '.assigned_by',
        ];

        $where = [];
        if (! empty($license_id)) {
            $where[] = 'AND ' . $t . '.license_id = ' . (int) $license_id;
        }
        if (! empty($holder_staff)) {
            $where[] = 'AND ' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id = ' . (int) $holder_staff . ' AND ' . $t . '.released_at IS NULL';
        }
        if (! empty($asset_id)) {
            $where[] = 'AND ' . $t . '.assigned_type = "asset" AND ' . $t . '.assigned_id = ' . (int) $asset_id . ' AND ' . $t . '.released_at IS NULL';
        }
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result  = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id', $t . '.license_id as license_id', $t . '.assigned_type as assigned_type', $t . '.assigned_id as assigned_id', $t . '.assigned_by as assigned_by',
        ]);
        $output  = $result['output'];
        $canEdit = staff_can('edit', 'ams_licenses');

        foreach ($result['rResult'] as $aRow) {
            $holder = $aRow['assigned_type'] === 'staff'
                ? ams_assignee_html('staff', $aRow['assigned_id'], $aRow['holder_label'])
                : '<i class="fa-solid fa-laptop tw-text-neutral-400 tw-mr-1"></i><a href="' . admin_url('asset_management/assets/view/' . $aRow['assigned_id']) . '">' . e($aRow['holder_label']) . '</a>';

            $name = staff_can('view', 'ams_licenses')
                ? '<a href="' . admin_url('asset_management/licenses/view/' . $aRow['license_id']) . '">' . e($aRow['license_name']) . '</a>'
                : e($aRow['license_name']);
            if ($canEdit && ! $aRow['released_at']) {
                $name .= '<div class="row-options"><a href="#" class="text-danger" onclick="ams_release_seat(' . (int) $aRow['id'] . '); return false;">' . _l('ams_lic_release') . '</a></div>';
            }

            $row   = [];
            $row[] = $name;
            $row[] = $holder;
            $row[] = e(_dt($aRow['assigned_at']));
            $row[] = e($aRow['note']);
            $row[] = $aRow['released_at'] ? e(_dt($aRow['released_at'])) : '<span class="label label-success">' . _l('ams_lic_in_use') . '</span>';
            $row[] = $aRow['assigned_by'] ? e($aRow['by_name']) : '';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('assigned_type', 'SelectRule')->label(_l('ams_lic_assigned_to_type'))
            ->options(fn () => [['value' => 'staff', 'label' => _l('ams_assign_type_staff')], ['value' => 'asset', 'label' => _l('ams_asset')]]),
        App_table_filter::new('in_use', 'BooleanRule')->label(_l('ams_lic_in_use'))->raw(function ($value) {
            $c = db_prefix() . 'ams_license_seats.released_at';

            return $value == '1' ? $c . ' IS NULL' : $c . ' IS NOT NULL';
        }),
    ]);
