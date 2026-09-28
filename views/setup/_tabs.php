<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Assets → Setup: one grouped section - master data, department approvers and the
// module settings - shown as Perfex tabs. Each tab only for staff allowed to open it.
// $active = entity key (categories, brands, ...), 'approvers', 'settings_general' or 'settings_hostbill';
// $help (optional) = help text for the info icon after the title.
$tabs = [];
if (staff_can('view', 'ams_setup')) {
    foreach (ams_setup_entities() as $key => $e) {
        $tabs[$key] = ['label' => _l($e['plural']), 'url' => admin_url('asset_management/setup/index/' . $key)];
    }
    $tabs['approvers'] = ['label' => _l('ams_department_approvers'), 'url' => admin_url('asset_management/approvers')];
    $tabs['changelog'] = ['label' => _l('ams_setup_changelog'), 'url' => admin_url('asset_management/setup/changelog')];
}
if (ams_can_view_settings()) {
    $tabs['settings_general']  = ['label' => _l('ams_setup_tab_general'), 'url' => admin_url('asset_management/configuration/index/general')];
    $tabs['settings_hostbill'] = ['label' => _l('ams_setup_tab_hostbill'), 'url' => admin_url('asset_management/configuration/index/hostbill')];
}
?>
<h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_menu_setup') . ams_help_icon($help ?? ''); ?></h4>
<div class="horizontal-scrollable-tabs tw-mb-4">
    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <?php foreach ($tabs as $key => $t) { ?>
            <li role="presentation" class="<?= $key === $active ? 'active' : ''; ?>">
                <a href="<?= $t['url']; ?>"><?= e($t['label']); ?></a>
            </li>
            <?php } ?>
        </ul>
    </div>
</div>
