<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Core dashboard widget (Dashboard Options → "Assets Overview"). Registered for
// staff with global asset view or any inventory view; each part respects its permission.
$CI = &get_instance();
$CI->load->model(AMS_MODULE_NAME . '/ams_assets_model');
$CI->load->model(AMS_MODULE_NAME . '/ams_inventory_model');
$amsCanAssets = staff_can('view', 'ams_assets');
$amsStats     = $amsCanAssets ? $CI->ams_assets_model->dashboard_stats() : null;
$amsInv       = $CI->ams_inventory_model->dashboard_stats();
?>
<div class="widget" id="widget-<?= create_widget_id(); ?>" data-name="<?= _l('ams_widget_overview'); ?>">
    <div class="panel_s">
        <div class="panel-body padding-10">
            <div class="widget-dragger"></div>
            <div class="tw-flex tw-justify-between tw-items-center tw-p-1.5">
                <p class="tw-font-semibold tw-flex tw-items-center tw-mb-0 tw-space-x-1.5 rtl:tw-space-x-reverse">
                    <i class="fa-solid fa-laptop tw-text-neutral-500 tw-mr-1"></i>
                    <span class="tw-text-neutral-700"><?= _l('ams_widget_overview'); ?></span>
                </p>
                <?php if ($amsCanAssets) { ?>
                <a href="<?= admin_url('asset_management'); ?>"><?= _l('home_widget_view_all'); ?></a>
                <?php } ?>
            </div>
            <hr class="-tw-mx-3 tw-mt-2 tw-mb-3">
            <?php if ($amsInv['kinds'] && ($amsInv['low'] || $amsInv['out'])) { ?>
            <div class="tw-flex tw-gap-3 tw-mb-3">
                <?php if ($amsInv['out']) { ?>
                <a href="<?= admin_url('asset_management/inventory/levels'); ?>" class="text-danger tw-font-medium"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i><?= _l('ams_out_of_stock'); ?>: <?= (int) $amsInv['out']; ?></a>
                <?php } ?>
                <?php if ($amsInv['low']) { ?>
                <a href="<?= admin_url('asset_management/inventory/levels'); ?>" class="text-warning tw-font-medium"><i class="fa-solid fa-arrow-trend-down tw-mr-1"></i><?= _l('ams_low_stock'); ?>: <?= (int) $amsInv['low']; ?></a>
                <?php } ?>
            </div>
            <?php } ?>
            <?php if ($amsCanAssets) { ?>
            <div class="tw-grid tw-grid-cols-2 tw-gap-2 tw-mb-3 tw-text-center">
                <div>
                    <div class="tw-text-2xl tw-font-bold"><?= (int) $amsStats['total']; ?></div>
                    <div class="tw-text-xs tw-text-neutral-500"><?= _l('ams_total_assets'); ?></div>
                </div>
                <div>
                    <div class="tw-text-2xl tw-font-bold text-info"><?= (int) $amsStats['assigned']; ?></div>
                    <div class="tw-text-xs tw-text-neutral-500"><?= _l('ams_checked_out'); ?></div>
                </div>
            </div>
            <table class="table tw-mb-0">
                <tbody>
                    <?php foreach ($amsStats['by_status'] as $s) {
                        if (! $s['total']) {
                            continue;
                        } ?>
                    <tr>
                        <td><?= ams_status_badge($s['name'], $s['color']); ?></td>
                        <td class="text-right"><a href="<?= admin_url('asset_management/assets?status_id=' . $s['id']); ?>"><?= (int) $s['total']; ?></a></td>
                    </tr>
                    <?php } ?>
                    <?php if ($amsStats['warranty_expiring_count']) { ?>
                    <tr>
                        <td class="text-warning"><?= _l('ams_warranty_expiring', (int) get_option('ams_warranty_expiring_days')); ?></td>
                        <td class="text-right text-warning"><?= (int) $amsStats['warranty_expiring_count']; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } elseif ($amsInv['kinds']) { ?>
            <p class="tw-mb-0 tw-text-neutral-600"><?= _l('ams_inventory_value'); ?>: <strong><?= e(app_format_money($amsInv['value'], get_base_currency())); ?></strong></p>
            <?php } ?>
        </div>
    </div>
</div>
