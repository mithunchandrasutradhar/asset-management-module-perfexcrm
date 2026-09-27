<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_suppliers';

return ams_build_setup_table('suppliers', [
    [$t . '.name as name', null],
    [$t . '.contact_person as contact_person', null],
    [$t . '.phone as phone', null],
    [$t . '.email as email', function ($aRow) {
        return $aRow['email'] ? '<a href="mailto:' . e($aRow['email']) . '">' . e($aRow['email']) . '</a>' : '';
    }],
], [], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.supplier_id = ' . $t . '.id', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
    App_table_filter::new('contact_person', 'TextRule')->label(_l('ams_contact_person')),
    App_table_filter::new('email', 'TextRule')->label(_l('ams_email')),
]);
