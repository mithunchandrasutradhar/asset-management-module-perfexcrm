<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Assets → Asset List → Deleted: soft-deleted assets (history kept), with Restore.

return App_table::find('ams_assets_deleted')
    ->outputUsing(function ($params) {
        $p = db_prefix();
        $t = $p . 'ams_assets';

        $aColumns = [
            $t . '.asset_tag as asset_tag',
            $t . '.name as name',
            'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name',
            $t . '.serial_no as serial_no',
            $t . '.date_deleted as date_deleted',
            'CONCAT(ds.firstname, " ", ds.lastname) as deleted_by_name',
            $t . '.deleted_reason as deleted_reason',
        ];

        $join = [
            'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
            'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
            'LEFT JOIN ' . $p . 'staff ds ON ds.staffid = ' . $t . '.deleted_by',
        ];

        $where = ['AND ' . $t . '.is_deleted = 1'];
        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [$t . '.id as id']);
        $output = $result['output'];

        $canRestore = staff_can('delete', 'ams_assets');
        foreach ($result['rResult'] as $aRow) {
            $tag = '<span class="tw-font-medium">' . e($aRow['asset_tag']) . '</span>';
            if ($canRestore) {
                $tag .= '<div class="row-options"><a href="' . admin_url('asset_management/assets/restore/' . $aRow['id']) . '" class="ams-post">' . _l('ams_restore') . '</a></div>';
            }

            $row   = [];
            $row[] = $tag;
            $row[] = e($aRow['name']);
            $row[] = e($aRow['category_name']);
            $row[] = e($aRow['serial_no']);
            $row[] = e(_dt($aRow['date_deleted']));
            $row[] = e($aRow['deleted_by_name']);
            $row[] = nl2br(e($aRow['deleted_reason']));

            $row['DT_RowClass'] = 'has-row-options';
            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('date_deleted', 'DateRule')->label(_l('ams_date_deleted')),
        App_table_filter::new('category_id', 'SelectRule')->label(_l('ams_category'))
            ->options(fn () => collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()),
    ]);
