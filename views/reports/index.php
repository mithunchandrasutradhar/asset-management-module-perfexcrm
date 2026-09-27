<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
// Reports hub: every report grid is a standard Perfex data table (search, filters, export).
$canAssets = staff_can('view', 'ams_assets');
$groups    = [
    'ams_reports_group_assets' => [
        [$canAssets, 'asset_management/assets', 'fa-solid fa-laptop', 'ams_report_register', 'ams_report_register_desc'],
        [$canAssets, 'asset_management/people', 'fa-solid fa-users', 'ams_assets_by_staff', 'ams_report_people_desc'],
        [true, 'asset_management/reports/history', 'fa-solid fa-clock-rotate-left', 'ams_report_history', 'ams_report_history_desc'],
        [true, 'asset_management/reports/warranty', 'fa-solid fa-shield-halved', 'ams_report_warranty', 'ams_report_warranty_desc'],
        [$canAssets, 'asset_management/purchases', 'fa-solid fa-receipt', 'ams_menu_purchases', 'ams_report_purchases_desc'],
    ],
    'ams_reports_group_finance' => [
        [true, 'asset_management/reports/valuation', 'fa-solid fa-scale-balanced', 'ams_valuation_report', 'ams_report_valuation_desc'],
        [true, 'asset_management/reports/depreciation', 'fa-solid fa-chart-line', 'ams_report_depreciation', 'ams_report_depreciation_desc'],
        [true, 'asset_management/reports/maintenance_cost', 'fa-solid fa-screwdriver-wrench', 'ams_report_maintenance_cost', 'ams_report_maintenance_cost_desc'],
        [true, 'asset_management/reports/disposals', 'fa-solid fa-box-archive', 'ams_disposal_register', 'ams_report_disposals_desc'],
    ],
    'ams_reports_group_stock' => [
        [(bool) ams_item_viewable_kinds(), 'asset_management/inventory/levels', 'fa-solid fa-boxes-stacked', 'ams_stock_levels', 'ams_report_levels_desc'],
        [(bool) ams_item_viewable_kinds(), 'asset_management/inventory/movements', 'fa-solid fa-right-left', 'ams_stock_movements', 'ams_report_movements_desc'],
        [staff_can('view', 'ams_hostbill'), 'asset_management/hostbill/orders', 'fa-solid fa-cart-shopping', 'ams_hb_menu_orders', 'ams_report_hb_desc'],
    ],
    'ams_reports_group_operations' => [
        [staff_can('view', 'ams_audits'), 'asset_management/audits', 'fa-solid fa-clipboard-check', 'ams_audits', 'ams_report_audits_desc'],
        [staff_can('view', 'ams_maintenance'), 'asset_management/maintenance', 'fa-solid fa-wrench', 'ams_maintenance', 'ams_report_maintenance_desc'],
        [staff_can('view', 'ams_licenses'), 'asset_management/licenses', 'fa-solid fa-key', 'ams_licenses', 'ams_report_licenses_desc'],
        [staff_can('view', 'ams_procurement'), 'asset_management/procurement', 'fa-solid fa-file-invoice', 'ams_purchase_orders', 'ams_report_pos_desc'],
    ],
];
?>
<div id="wrapper">
    <div class="content">
        <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-1"><?= _l('ams_reports'); ?></h4>
        <p class="text-muted tw-mb-4"><?= _l('ams_reports_intro'); ?></p>
        <?php foreach ($groups as $title => $items) {
            $items = array_filter($items, fn ($i) => $i[0]);
            if (! $items) {
                continue;
            } ?>
        <h5 class="tw-font-semibold tw-text-neutral-600 tw-uppercase tw-text-sm tw-mb-2"><?= _l($title); ?></h5>
        <div class="row tw-mb-2">
            <?php foreach ($items as $i) { ?>
            <div class="col-md-3 col-sm-6">
                <a href="<?= admin_url($i[1]); ?>" class="panel_s tw-block hover:tw-bg-neutral-50" style="min-height:118px">
                    <div class="panel-body">
                        <p class="tw-font-semibold tw-mb-1 tw-text-neutral-800"><i class="<?= $i[2]; ?> tw-mr-2 tw-text-neutral-500"></i><?= _l($i[3]); ?></p>
                        <p class="text-muted tw-text-sm tw-mb-0"><?= _l($i[4]); ?></p>
                    </div>
                </a>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
