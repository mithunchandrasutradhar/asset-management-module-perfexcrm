<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Shared builder for the Setup (master data) tables. Each tables/setup_<entity>.php
 * passes its columns/joins/rules; this adds the standard name cell with row
 * options, the asset count, the active badge and permission-aware actions.
 *
 * $columns: list of [sql select "expr as alias", render callable($aRow) | null (plain escaped)]
 *           The first column must be the name column (it gets the row options).
 */
function ams_build_setup_table($entity, $columns, $join, $countSql, $rules)
{
    return App_table::find('ams_' . $entity)
        ->outputUsing(function ($params) use ($entity, $columns, $join, $countSql) {
            $t = db_prefix() . ams_setup_entities()[$entity]['table'];

            $aColumns = array_map(fn ($c) => $c[0], $columns);
            $aColumns[] = '(' . $countSql . ') as asset_count';
            $aColumns[] = $t . '.active as active';

            $where = [];
            if ($filtersWhere = $this->getWhereFromRules()) {
                $where[] = $filtersWhere;
            }

            $extra = [$t . '.id as id'];
            if ($entity === 'statuses') {
                $extra[] = $t . '.system_key as system_key';
                $extra[] = $t . '.color as color';
            }

            $result = data_tables_init($aColumns, 'id', $t, $join, $where, $extra);
            $output = $result['output'];

            $canEdit   = staff_can('edit', 'ams_setup');
            $canDelete = staff_can('delete', 'ams_setup');

            foreach ($result['rResult'] as $aRow) {
                $row = [];

                foreach ($columns as $i => $col) {
                    $alias = trim(substr($col[0], strrpos($col[0], ' as ') + 4));
                    $cell  = $col[1] ? call_user_func($col[1], $aRow) : e($aRow[$alias]);

                    if ($i === 0) {
                        $cell = '<span class="tw-font-medium">' . $cell . '</span><div class="row-options">';
                        $opts = [];
                        if ($canEdit) {
                            $opts[] = '<a href="#" onclick="ams_setup_edit(' . (int) $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
                        }
                        if ($canDelete && empty($aRow['system_key'])) {
                            $opts[] = '<a href="' . admin_url('asset_management/setup/delete/' . $entity . '/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
                        }
                        $cell .= implode(' | ', $opts) . '</div>';
                    }

                    $row[] = $cell;
                }

                $row[] = (int) $aRow['asset_count'];
                $row[] = $aRow['active']
                    ? '<span class="label label-success">' . _l('ams_active') . '</span>'
                    : '<span class="label label-default">' . _l('ams_inactive') . '</span>';

                $row['DT_RowClass'] = 'has-row-options';
                $output['aaData'][] = $row;
            }

            return $output;
        })
        ->setRules(array_merge($rules, [
            App_table_filter::new('active', 'BooleanRule')->label(_l('ams_active')),
        ]));
}

/** Headings for the manage view, matching the builder's column order. */
function ams_setup_table_headings($entity)
{
    $map = [
        'categories' => ['ams_name', 'ams_parent_category', 'ams_category_code', 'ams_description'],
        'brands'     => ['ams_name', 'ams_website'],
        'models'     => ['ams_name', 'ams_model_no', 'ams_brand', 'ams_category'],
        'statuses'   => ['ams_name', 'ams_status_type', 'ams_requires_location', 'ams_requires_note', 'ams_sort_order'],
        'locations'  => ['ams_name', 'ams_parent_location', 'ams_location_type', 'ams_location_manager'],
        'suppliers'  => ['ams_name', 'ams_contact_person', 'ams_phone', 'ams_email'],
    ];

    $headings   = array_map('_l', $map[$entity]);
    $headings[] = _l('ams_assets');
    $headings[] = _l('ams_active');

    return $headings;
}
