<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Assets → HostBill Inventory: HostBill products (read-only copy) with low-stock state.

return App_table::find('ams_hb_inventory')
    ->outputUsing(function ($params) {
        $CI = &get_instance();
        $CI->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');

        $t = db_prefix() . 'ams_hb_products';

        $aColumns = [
            $t . '.name as name',
            $t . '.orderpage_name as orderpage_name',
            $t . '.qty as qty',
            ams_hb_level_sql($t) . ' as effective_level',
            // Numeric so the column sorts by severity: 4 out, 3 low, 2 in stock, 1 not tracked.
            'FIELD(' . ams_hb_state_sql($t) . ', "not_tracked", "in_stock", "low", "out") as stock_state',
            $t . '.stock_enabled as stock_enabled',
            $t . '.visible as visible',
            $t . '.synced_at as synced_at',
        ];

        $where = ['AND ' . $CI->ams_hostbill_model->scope_sql($t)];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'hb_product_id', $t, [], $where, [$t . '.hb_product_id as hb_product_id', $t . '.low_level as low_level']);
        $output = $result['output'];

        $canEdit  = staff_can('edit', 'ams_hostbill') && get_option('ams_hb_allow_override') == '1';
        $override = get_option('ams_hb_allow_override') == '1';

        foreach ($result['rResult'] as $aRow) {
            $aRow['stock_state'] = [1 => 'not_tracked', 2 => 'in_stock', 3 => 'low', 4 => 'out'][(int) $aRow['stock_state']] ?? 'not_tracked';
            $name = '<span class="tw-font-medium">' . e($aRow['name']) . '</span> <span class="text-muted tw-text-sm">#' . (int) $aRow['hb_product_id'] . '</span>';
            if ($canEdit) {
                $name .= '<div class="row-options"><a href="#" onclick="ams_hb_level(' . (int) $aRow['hb_product_id'] . ', ' . json_encode((string) $aRow['low_level']) . ', ' . json_encode($aRow['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . '); return false;">'
                    . _l('ams_hb_set_level') . '</a></div>';
            }

            $level = '';
            if ($aRow['stock_state'] !== 'not_tracked') {
                $level = ams_qty($aRow['effective_level']) . ($override && $aRow['low_level'] === null ? ' <span class="text-muted tw-text-sm">(' . _l('ams_hb_default') . ')</span>' : '');
            }

            $row   = [];
            $row[] = $name;
            $row[] = e($aRow['orderpage_name']);
            $row[] = $aRow['stock_state'] === 'not_tracked' ? '<span class="text-muted">-</span>' : '<span class="tw-font-semibold">' . ams_qty($aRow['qty']) . '</span>';
            $row[] = $level;
            $row[] = ams_hb_stock_badge($aRow['stock_state']);
            $row[] = $aRow['visible'] ? _l('settings_yes') : '<span class="text-muted">' . _l('settings_no') . '</span>';
            $row[] = e(_dt($aRow['synced_at']));

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('stock_state', 'MultiSelectRule')->label(_l('ams_status'))->raw(function ($value, $operator) {
            $values = array_map(fn ($v) => get_instance()->db->escape((string) $v), (array) $value);
            $sql    = ams_hb_state_sql(db_prefix() . 'ams_hb_products') . ' IN (' . (implode(',', $values) ?: "''") . ')';

            return $operator === 'in' ? $sql : 'NOT (' . $sql . ')';
        })->options(fn () => collect(['in_stock', 'low', 'out', 'not_tracked'])->map(fn ($s) => ['value' => $s, 'label' => _l('ams_hb_state_' . $s)])->all()),
        App_table_filter::new('orderpage_id', 'MultiSelectRule')->label(_l('ams_hb_group'))->options(function () {
            return collect(get_instance()->db->query('SELECT DISTINCT orderpage_id id, orderpage_name name FROM ' . db_prefix() . 'ams_hb_products WHERE orderpage_id IS NOT NULL ORDER BY orderpage_name')->result_array())
                ->map(fn ($g) => ['value' => $g['id'], 'label' => $g['name'] ?: '#' . $g['id']])->all();
        }),
        App_table_filter::new('qty', 'NumberRule')->label(_l('ams_quantity')),
        App_table_filter::new('visible', 'BooleanRule')->label(_l('ams_hb_visible')),
        App_table_filter::new('synced_at', 'DateRule')->label(_l('ams_hb_last_refreshed')),
    ]);
