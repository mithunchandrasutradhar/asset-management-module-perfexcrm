<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/_setup_builder.php';

$p = db_prefix();
$t = $p . 'ams_statuses';

$yesNo = fn ($field) => fn ($aRow) => $aRow[$field] ? _l('settings_yes') : _l('settings_no');

return ams_build_setup_table('statuses', [
    [$t . '.name as name', function ($aRow) {
        $html = ams_status_badge($aRow['name'], $aRow['color']);

        return $aRow['system_key'] ? $html . ' <span class="text-muted tw-text-xs">(' . _l('ams_system') . ')</span>' : $html;
    }],
    [$t . '.type as type', fn ($aRow) => e(ams_option_label(ams_status_type_options(), $aRow['type']))],
    [$t . '.requires_location as requires_location', $yesNo('requires_location')],
    [$t . '.requires_note as requires_note', $yesNo('requires_note')],
    [$t . '.sort_order as sort_order', null],
], [], 'SELECT COUNT(*) FROM ' . $p . 'ams_assets x WHERE x.is_deleted = 0 AND x.status_id = ' . $t . '.id', [
    App_table_filter::new('name', 'TextRule')->label(_l('ams_name')),
    App_table_filter::new('type', 'MultiSelectRule')->label(_l('ams_status_type'))->options(function () {
        return collect(ams_status_type_options())->map(fn ($s) => ['value' => $s['id'], 'label' => $s['name']])->all();
    }),
]);
