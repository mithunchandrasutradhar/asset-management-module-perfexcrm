<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Software licences with seat usage and expiry.

return App_table::find('ams_licenses')
    ->outputUsing(function ($params) {
        $p     = db_prefix();
        $t     = $p . 'ams_licenses';
        $used  = '(SELECT COUNT(*) FROM ' . $p . 'ams_license_seats ls WHERE ls.license_id = ' . $t . '.id AND ls.released_at IS NULL)';

        $aColumns = [
            $t . '.name as name',
            'b.name as brand_name',
            $t . '.license_type as license_type',
            $used . ' as seats_used',
            $t . '.seats as seats',
            $t . '.expiry_date as expiry_date',
            'sp.name as supplier_name',
            $t . '.purchase_cost as purchase_cost',
            $t . '.active as active',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_brands b ON b.id = ' . $t . '.brand_id',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
        ];

        $where = [];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id']);
        $output = $result['output'];
        $cur    = get_base_currency();
        $today  = date('Y-m-d');
        $soon   = date('Y-m-d', strtotime('+' . max(1, (int) get_option('ams_license_reminder_days')) . ' days'));

        foreach ($result['rResult'] as $aRow) {
            $url = admin_url('asset_management/licenses/view/' . $aRow['id']);
            $opts = '<a href="' . $url . '">' . _l('view') . '</a>';
            if (staff_can('edit', 'ams_licenses')) {
                $opts .= ' | <a href="' . admin_url('asset_management/licenses/license/' . $aRow['id']) . '">' . _l('edit') . '</a>';
            }

            $expiry = '';
            if ($aRow['expiry_date']) {
                $class  = $aRow['expiry_date'] < $today ? 'text-danger tw-font-semibold' : ($aRow['expiry_date'] <= $soon ? 'text-warning' : '');
                $expiry = '<span class="' . $class . '">' . e(_d($aRow['expiry_date'])) . ($aRow['expiry_date'] < $today ? ' (' . _l('ams_lic_expired') . ')' : '') . '</span>';
            }
            $full = (int) $aRow['seats_used'] >= (int) $aRow['seats'];

            $row   = [];
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['name']) . '</a><div class="row-options">' . $opts . '</div>';
            $row[] = e($aRow['brand_name']);
            $row[] = e(_l('ams_lic_type_' . $aRow['license_type']));
            $row[] = '<span class="' . ($full ? 'text-warning tw-font-semibold' : '') . '">' . (int) $aRow['seats_used'] . ' / ' . (int) $aRow['seats'] . '</span>';
            $row[] = max(0, (int) $aRow['seats'] - (int) $aRow['seats_used']);
            $row[] = $expiry;
            $row[] = e($aRow['supplier_name']);
            $row[] = $aRow['purchase_cost'] !== null ? e(app_format_money($aRow['purchase_cost'], $cur)) : '';
            $row[] = $aRow['active'] ? '<span class="label label-success">' . _l('ams_active') . '</span>' : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('name', 'TextRule')->label(_l('ams_lic_name')),
        App_table_filter::new('license_type', 'MultiSelectRule')->label(_l('ams_lic_type'))
            ->options(fn () => collect(['subscription', 'perpetual', 'per_device', 'site', 'oem', 'open_source'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_lic_type_' . $s)])->all()),
        App_table_filter::new('brand_id', 'MultiSelectRule')->label(_l('ams_brand'))
            ->options(fn () => collect(ams_brand_options())->map(fn ($b) => ['value' => $b['id'], 'label' => $b['name']])->all()),
        App_table_filter::new('expiry_date', 'DateRule')->label(_l('ams_lic_expiry')),
        App_table_filter::new('expired', 'BooleanRule')->label(_l('ams_lic_expired'))->raw(function ($value) {
            $c = db_prefix() . 'ams_licenses.expiry_date';

            return $value == '1' ? $c . ' < CURDATE()' : '(' . $c . ' IS NULL OR ' . $c . ' >= CURDATE())';
        }),
        App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
    ]);
