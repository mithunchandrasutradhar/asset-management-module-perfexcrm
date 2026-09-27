<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Stock ledger (all movements), or one item's movements (item profile tab).

return App_table::find('ams_movements')
    ->outputUsing(function ($params) {
        extract($params);

        $p = db_prefix();
        $t = $p . 'ams_stock_movements';

        $aColumns = [
            $t . '.date_created as date_created',
            'CONCAT(i.sku, " - ", i.name) as item_label',
            'i.kind as kind',
            $t . '.type as type',
            'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) as location_name',
            $t . '.qty as qty',
            'COALESCE(CONCAT(ms.firstname, " ", ms.lastname), md.name, sp.name) as party_name',
            $t . '.reference as reference',
            $t . '.note as note',
            'CONCAT(bs.firstname, " ", bs.lastname) as by_name',
        ];

        $join = [
            'JOIN ' . $p . 'ams_items i ON i.id = ' . $t . '.item_id',
            'LEFT JOIN ' . $p . 'ams_locations l ON l.id = ' . $t . '.location_id',
            'LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id',
            'LEFT JOIN ' . $p . 'staff ms ON ' . $t . '.assigned_type = "staff" AND ms.staffid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'departments md ON ' . $t . '.assigned_type = "department" AND md.departmentid = ' . $t . '.assigned_id',
            'LEFT JOIN ' . $p . 'ams_suppliers sp ON sp.id = ' . $t . '.supplier_id',
            'LEFT JOIN ' . $p . 'staff bs ON bs.staffid = ' . $t . '.staff_id',
        ];

        $where = [];
        if (! empty($item_id)) {
            // Access to this item was checked by the controller.
            $where[] = 'AND ' . $t . '.item_id = ' . (int) $item_id;
        } else {
            $kinds   = ams_item_viewable_kinds() ?: ['-'];
            $where[] = 'AND i.kind IN ("' . implode('","', $kinds) . '")';
        }

        if ($filtersWhere = $this->getWhereFromRules()) {
            $where[] = $filtersWhere;
        }

        $result = data_tables_init($aColumns, 'id', $t, $join, $where, [
            $t . '.id as id',
            $t . '.item_id as item_id',
            'i.unit as unit',
            $t . '.assigned_type as assigned_type',
            $t . '.assigned_id as assigned_id',
            $t . '.supplier_id as supplier_id',
            $t . '.staff_id as staff_id',
            $t . '.reason as reason',
        ]);
        $output = $result['output'];
        $kinds  = ams_item_kinds();

        foreach ($result['rResult'] as $aRow) {
            $qty   = (float) $aRow['qty'];
            $party = $aRow['assigned_type']
                ? ams_assignee_html($aRow['assigned_type'], $aRow['assigned_id'], $aRow['party_name'])
                : ($aRow['supplier_id'] ? '<i class="fa-solid fa-truck tw-text-neutral-400 tw-mr-1"></i>' . e($aRow['party_name']) : '');
            $type = e(_l('ams_mv_' . $aRow['type']));
            if ($aRow['reason']) {
                $type .= ' <span class="text-muted tw-text-xs">(' . e(_l('ams_reason_' . $aRow['reason'])) . ')</span>';
            }

            $row   = [];
            $row[] = e(_dt($aRow['date_created']));
            $row[] = '<a href="' . admin_url('asset_management/inventory/view/' . $aRow['item_id']) . '">' . e($aRow['item_label']) . '</a>';
            $row[] = e(_l($kinds[$aRow['kind']]['singular'] ?? $aRow['kind']));
            $row[] = $type;
            $row[] = e($aRow['location_name']);
            $row[] = '<span class="tw-font-semibold ' . ($qty < 0 ? 'text-danger' : 'text-success') . '">' . ($qty > 0 ? '+' : '') . ams_qty($qty) . '</span> <span class="text-muted tw-text-xs">' . e($aRow['unit']) . '</span>';
            $row[] = $party;
            $row[] = e($aRow['reference']);
            $row[] = nl2br(e($aRow['note']));
            $row[] = $aRow['staff_id'] ? ams_assignee_html('staff', $aRow['staff_id'], $aRow['by_name']) : '';

            $output['aaData'][] = $row;
        }

        return $output;
    })->setRules([
        App_table_filter::new('date_created', 'DateRule')->label(_l('ams_date')),
        App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_movement_type'))
            ->options(fn () => collect(ams_movement_type_options())->map(fn ($m) => ['value' => $m['id'], 'label' => $m['name']])->all()),
        App_table_filter::new('kind', 'MultiSelectRule')->label(_l('ams_item_kind'))->column('i.kind')
            ->options(fn () => collect(ams_item_kind_options())->map(fn ($k) => ['value' => $k['id'], 'label' => $k['name']])->all()),
        App_table_filter::new('location_id', 'MultiSelectRule')->label(_l('ams_location'))
            ->options(fn () => collect(ams_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all()),
        App_table_filter::new('issued_staff', 'MultiSelectRule')->label(_l('ams_issued_to_staff'))
            ->raw(function ($value, $operator) {
                $t   = db_prefix() . 'ams_stock_movements';
                $sql = '(' . $t . '.assigned_type = "staff" AND ' . $t . '.assigned_id IN (\'' . implode("','", (array) $value) . '\'))';

                return $operator === 'in' ? $sql : 'NOT ' . $sql;
            })
            ->options(fn () => collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all()),
        App_table_filter::new('department_id', 'MultiSelectRule')->label(_l('ams_department'))
            ->options(fn () => collect(ams_department_options())->map(fn ($d) => ['value' => $d['departmentid'], 'label' => $d['name']])->all()),
        App_table_filter::new('supplier_id', 'MultiSelectRule')->label(_l('ams_supplier'))
            ->options(fn () => collect(ams_supplier_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all()),
        App_table_filter::new('reference', 'TextRule')->label(_l('ams_reference')),
        App_table_filter::new('staff_id', 'MultiSelectRule')->label(_l('ams_done_by'))
            ->options(fn () => collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all()),
    ]);
