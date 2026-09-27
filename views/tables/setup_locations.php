<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_locations';

return ams_build_setup_table('locations', [
    [$t . '.name as name', null],
    ['pl.name as parent_name', null],
    [$t . '.type as type', fn ($aRow) => e(ams_option_label(ams_location_type_options(), $aRow['type']))],
    ['CONCAT(ms.firstname, " ", ms.lastname) as manager_name', null],
], [
    'LEFT JOIN ' . $t . ' pl ON pl.id = ' . $t . '.parent_id',
    'LEFT JOIN ' . $p . 'staff ms ON ms.staffid = ' . $t . '.manager_staff_id',
], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND (x.location_id = ' . $t . '.id OR x.location_id IN (SELECT cl.id FROM ' . $t . ' cl WHERE cl.parent_id = ' . $t . '.id))', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
    App_table_filter::new('parent_id', 'SelectRule')->label(_l('ams_parent_location'))->options(function () {
        return collect(ams_parent_location_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all();
    }),
    App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_location_type'))->options(function () {
        return collect(ams_location_type_options())->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->all();
    }),
    App_table_filter::new('manager_staff_id', 'MultiSelectRule')->label(_l('ams_location_manager'))->options(function () {
        return collect(ams_staff_options())->map(fn ($s) => ['value' => $s['staffid'], 'label' => $s['firstname'] . ' ' . $s['lastname']])->all();
    }),
]);
