<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_categories';

return ams_build_setup_table('categories', [
    [$t . '.name as name', null],
    ['pc.name as parent_name', null],
    [$t . '.code as code', null],
    [$t . '.description as description', null],
], [
    'LEFT JOIN ' . $t . ' pc ON pc.id = ' . $t . '.parent_id',
], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND (x.category_id = ' . $t . '.id OR x.category_id IN (SELECT cc.id FROM ' . $t . ' cc WHERE cc.parent_id = ' . $t . '.id))', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
    App_table_filter::new('parent_id', 'SelectRule')->label(_l('ams_parent_category'))->options(function () {
        return collect(ams_parent_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all();
    }),
    App_table_filter::new('code', 'TextRule')->label(_l('ams_category_code')),
]);
