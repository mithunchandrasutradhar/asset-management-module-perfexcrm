<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_brands';

return ams_build_setup_table('brands', [
    [$t . '.name as name', null],
    [$t . '.website as website', null],
], [], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.brand_id = ' . $t . '.id', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
]);
