<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_models';

return ams_build_setup_table('models', [
    [$t . '.name as name', null],
    [$t . '.model_no as model_no', null],
    ['b.name as brand_name', null],
    ['IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name', null],
], [
    'LEFT JOIN ' . $p . 'ams_brands b ON b.id = ' . $t . '.brand_id',
    'LEFT JOIN ' . $p . 'ams_categories c ON c.id = ' . $t . '.category_id',
    'LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id',
], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.model_id = ' . $t . '.id', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
    App_table_filter::new('brand_id', 'MultiSelectRule')->label(_l('ams_brand'))->options(function () {
        return collect(ams_brand_options())->map(fn ($b) => ['value' => $b['id'], 'label' => $b['name']])->all();
    }),
    App_table_filter::new('category_id', 'MultiSelectRule')->label(_l('ams_category'))->options(function () {
        return collect(ams_category_options())->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all();
    }),
]);
