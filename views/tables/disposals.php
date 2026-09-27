<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Disposal register: how assets left the company, with book value and gain / loss.

return App_table::find('ams_disposals')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_disposals';

        $aColumns = [
            $t . '.disposal_date as disposal_date',
            'a.asset_tag as asset_tag',
            'a.name as asset_name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            $t . '.method as method',
            $t . '.recipient as recipient',
            $t . '.reference as reference',
            $t . '.book_value as book_value',
            $t . '.proceeds as proceeds',
            $t . '.gain_loss as gain_loss',
            'CONCAT(s.firstname, " ", s.lastname) as by_name',
        ];

        $join = [
            'JOIN ' . $p . 'ams_assets a ON a.id = ' . $t . '.asset_id',
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'staff s ON s.staffid = ' . $t . '.staff_id',
            'LEFT JOIN ' . $p . 'currencies cur ON cur.id = ' . $t . '.currency',
        ];

        $where = ['AND a.is_deleted = 0'];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id', $t . '.asset_id as asset_id', $t . '.staff_id as staff_id', $t . '.reason as reason', 'cur.name as currency_name']);
        $output = $result['output'];

        foreach ($result['rResult'] as $aRow) {
            $url   = admin_url('asset_management/assets/view/' . $aRow['asset_id']);
            $money = fn ($v) => $v !== null ? e(app_format_money($v, $aRow['currency_name'])) : '';
            $gl    = $aRow['gain_loss'];

            $row   = [];
            $row[] = e(_d($aRow['disposal_date']));
            $row[] = '<a href="' . $url . '" class="tw-font-medium">' . e($aRow['asset_tag']) . '</a>';
            $row[] = '<a href="' . $url . '">' . e($aRow['asset_name']) . '</a>' . ($aRow['reason'] ? '<div class="text-muted tw-text-sm">' . e($aRow['reason']) . '</div>' : '');
            $row[] = e($aRow['category_name']);
            $row[] = e(_l('ams_disp_method_' . $aRow['method']));
            $row[] = e($aRow['recipient']);
            $row[] = e($aRow['reference']);
            $row[] = $money($aRow['book_value']);
            $row[] = $money($aRow['proceeds']);
            $row[] = $gl !== null ? '<span class="' . ((float) $gl < 0 ? 'text-danger' : 'text-success') . '">' . $money($gl) . '</span>' : '';
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('method', 'MultiSelectRule')->label(_l('ams_disp_method'))
            ->options(fn () => collect(ams_disposal_method_options())->map(fn ($m) => ['value' => $m['id'], 'label' => $m['name']])->all()),
        App_table_filter::new('disposal_date', 'DateRule')->label(_l('ams_disp_date')),
        App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))->raw(function ($value, $operator) {
            $ids = [];
            foreach ((array) $value as $id) {
                $ids = array_merge($ids, ams_category_with_children((int) $id));
            }
            $sql = 'a.category_id IN (' . implode(',', $ids ?: [0]) . ')';

            return $operator === 'in' ? $sql : 'NOT ' . $sql;
        })->options(fn () => collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()),
        App_table_filter::new('proceeds', 'NumberRule')->label(_l('ams_disp_proceeds')),
        App_table_filter::new('gain_loss', 'NumberRule')->label(_l('ams_disp_gain_loss')),
    ]);
