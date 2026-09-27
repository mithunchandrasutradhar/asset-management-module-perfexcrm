<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$tile = function ($label, $value, $icon, $href = null) {
    $inner = '<div class="tw-flex tw-items-center tw-gap-3">
        <div class="tw-rounded-md tw-bg-neutral-100 tw-p-3"><i class="' . $icon . ' tw-text-lg tw-text-neutral-600"></i></div>
        <div><p class="tw-text-neutral-500 tw-mb-0 tw-text-sm">' . $label . '</p>
        <p class="tw-font-semibold tw-text-2xl tw-mb-0">' . $value . '</p></div></div>';

    return '<div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">'
        . ($href ? '<a href="' . $href . '" class="tw-text-inherit">' . $inner . '</a>' : $inner)
        . '</div></div></div>';
};

$valueHtml = [];
foreach ($stats['value'] as $v) {
    $valueHtml[] = e(ams_format_money($v['total'], $v['currency']));
}
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('ams_dashboard'); ?></h4>
            <?php if (staff_can('create', 'ams_assets')) { ?>
            <a href="<?= admin_url('asset_management/assets/asset'); ?>" class="btn btn-primary">
                <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_asset'); ?>
            </a>
            <?php } ?>
        </div>

        <div class="row">
            <?= $tile(_l('ams_total_assets'), (int) $stats['total'], 'fa-solid fa-laptop', admin_url('asset_management/assets')); ?>
            <?= $tile(_l('ams_checked_out'), (int) $stats['assigned'], 'fa-solid fa-user-check'); ?>
            <?= $tile(_l('ams_purchased_gifted'), (int) $stats['purchased'] . ' <span class="tw-text-neutral-400 tw-text-lg">/ ' . (int) $stats['gifted'] . '</span>', 'fa-solid fa-gift'); ?>
            <?= $tile(_l('ams_asset_value'), $valueHtml ? implode('<br>', $valueHtml) : '-', 'fa-solid fa-coins'); ?>
        </div>

        <?php if ($inventory['kinds']) { ?>
        <div class="row">
            <?= $tile(_l('ams_inventory_value'), e(app_format_money($inventory['value'], get_base_currency())), 'fa-solid fa-boxes-stacked', admin_url('asset_management/inventory/levels')); ?>
            <?= $tile(_l('ams_low_stock'), '<span class="' . ($inventory['low'] ? 'text-warning' : '') . '">' . (int) $inventory['low'] . '</span>', 'fa-solid fa-arrow-trend-down'); ?>
            <?= $tile(_l('ams_out_of_stock'), '<span class="' . ($inventory['out'] ? 'text-danger' : '') . '">' . (int) $inventory['out'] . '</span>', 'fa-solid fa-triangle-exclamation'); ?>
            <?php if (in_array('accessory', $inventory['kinds'])) { ?>
            <?= $tile(_l('ams_accessories_out'), ams_qty($inventory['accessories_out']), 'fa-solid fa-computer-mouse', admin_url('asset_management/inventory/checkouts')); ?>
            <?php } ?>
        </div>
        <?php } ?>

        <?php
        $p   = db_prefix();
        $ops = [];
        if (staff_can('view', 'ams_maintenance')) {
            $ops[] = $tile(_l('ams_mt_open'), total_rows($p . 'ams_maintenance', 'status IN ("scheduled","in_progress")'), 'fa-solid fa-screwdriver-wrench', admin_url('asset_management/maintenance'));
            $ops[] = $tile(_l('ams_mt_overdue'), '<span class="text-danger">' . total_rows($p . 'ams_maintenance', 'status IN ("scheduled","in_progress") AND due_date < CURDATE()') . '</span>', 'fa-solid fa-clock', admin_url('asset_management/maintenance'));
        }
        if (staff_can('view', 'ams_licenses')) {
            $ops[] = $tile(_l('ams_lic_expiring_soon'), '<span class="text-warning">' . total_rows($p . 'ams_licenses', 'active = 1 AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) get_option('ams_license_reminder_days') . ' DAY)') . '</span>', 'fa-solid fa-key', admin_url('asset_management/licenses'));
        }
        if (staff_can('view', 'ams_procurement')) {
            $ops[] = $tile(_l('ams_po_awaiting'), total_rows($p . 'ams_purchase_orders', 'status IN ("pending_approval","approved","sent","partially_received")'), 'fa-solid fa-cart-shopping', admin_url('asset_management/procurement'));
        }
        if (staff_can('view', 'ams_assets')) {
            $CI =& get_instance();
            $CI->load->model(AMS_MODULE_NAME . '/ams_finance_model');
            $bv = array_map(fn ($v) => e(ams_format_money($v['total'], $v['currency'])), $CI->ams_finance_model->book_value_totals());
            $ops[] = $tile(_l('ams_dep_book_value'), $bv ? implode('<br>', $bv) : '-', 'fa-solid fa-chart-line', staff_can('view', 'ams_reports') ? admin_url('asset_management/reports/valuation') : null);
        }
        if (staff_can('view', 'ams_audits')) {
            $ops[] = $tile(_l('ams_audits_running'), total_rows($p . 'ams_audits', ['status' => 'in_progress']), 'fa-solid fa-clipboard-check', admin_url('asset_management/audits'));
        }
        if ($ops) { ?>
        <div class="row"><?= implode('', $ops); ?></div>
        <?php } ?>

        <div class="row">
            <div class="col-md-5">
                <?php if ($inventory['kinds']) { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_stock_alerts'); ?></h4>
                        <?php if (! $inventory['alerts']) { ?>
                        <p class="text-muted tw-mb-0"><?= _l('ams_no_stock_alerts'); ?></p>
                        <?php } else { ?>
                        <table class="table tw-mb-0">
                            <tbody>
                                <?php foreach ($inventory['alerts'] as $al) { ?>
                                <tr>
                                    <td>
                                        <a href="<?= admin_url('asset_management/inventory/view/' . $al['id']); ?>"><?= e($al['sku']); ?></a>
                                        <span class="text-muted"><?= e($al['name']); ?></span>
                                    </td>
                                    <td class="text-right"><?= ams_qty($al['available']); ?> / <?= ams_qty($al['reorder_level']); ?> <?= e($al['unit']); ?></td>
                                    <td class="text-right"><?= ams_stock_state_badge($al['state']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_assets_by_status'); ?></h4>
                        <table class="table table-hover tw-mb-0">
                            <tbody>
                                <?php foreach ($stats['by_status'] as $s) { ?>
                                <tr>
                                    <td><?= ams_status_badge($s['name'], $s['color']); ?></td>
                                    <td class="text-right">
                                        <a href="<?= admin_url('asset_management/assets?status_id=' . $s['id']); ?>" class="tw-font-semibold"><?= (int) $s['total']; ?></a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_warranty_expiring', (int) get_option('ams_warranty_expiring_days')); ?></h4>
                        <?php if (! $stats['warranty_expiring']) { ?>
                        <p class="text-muted tw-mb-0"><?= _l('ams_nothing_expiring'); ?></p>
                        <?php } else { ?>
                        <table class="table tw-mb-0">
                            <tbody>
                                <?php foreach ($stats['warranty_expiring'] as $w) { ?>
                                <tr>
                                    <td><a href="<?= admin_url('asset_management/assets/view/' . $w['id']); ?>"><?= e($w['asset_tag']); ?></a> <span class="text-muted"><?= e($w['name']); ?></span></td>
                                    <td class="text-right"><?= ams_warranty_html($w['warranty_end']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_top_categories'); ?></h4>
                        <?php if (! $stats['top_categories']) { ?>
                        <p class="text-muted tw-mb-0"><?= _l('ams_no_assets_yet'); ?></p>
                        <?php } else { ?>
                        <div class="row">
                            <?php foreach ($stats['top_categories'] as $c) { ?>
                            <div class="col-sm-4 col-xs-6 tw-mb-3">
                                <a href="<?= admin_url('asset_management/assets?category_id=' . $c['id']); ?>" class="tw-block tw-rounded-md tw-border tw-border-solid tw-border-neutral-200 tw-p-3 tw-text-center hover:tw-bg-neutral-50">
                                    <?php if ($c['icon']) { ?><i class="<?= e($c['icon']); ?> tw-text-xl tw-text-neutral-500"></i><?php } ?>
                                    <p class="tw-font-semibold tw-text-2xl tw-mb-0"><?= (int) $c['total']; ?></p>
                                    <p class="tw-text-neutral-600 tw-text-sm tw-mb-0 tw-truncate"><?= e($c['name']); ?></p>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-justify-between tw-items-center">
                            <h4 class="tw-my-0 tw-font-semibold tw-text-lg"><?= _l('ams_latest_assets'); ?></h4>
                            <a href="<?= admin_url('asset_management/assets'); ?>"><?= _l('home_widget_view_all'); ?></a>
                        </div>
                        <table class="table tw-mt-3 tw-mb-0">
                            <thead>
                                <tr>
                                    <th><?= _l('ams_asset_tag'); ?></th>
                                    <th><?= _l('ams_asset_name'); ?></th>
                                    <th><?= _l('ams_status'); ?></th>
                                    <th><?= _l('ams_assigned_to'); ?></th>
                                    <th><?= _l('ams_warranty'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['latest'] as $l) { ?>
                                <tr>
                                    <td><a href="<?= admin_url('asset_management/assets/view/' . $l['id']); ?>"><?= e($l['asset_tag']); ?></a></td>
                                    <td><?= e($l['name']); ?></td>
                                    <td><?= ams_status_badge($l['status_name'], $l['status_color']); ?></td>
                                    <td><?= ams_assignee_html($l['assigned_type'], $l['assigned_id']); ?></td>
                                    <td><?= ams_warranty_html($l['warranty_end']); ?></td>
                                </tr>
                                <?php } ?>
                                <?php if (! $stats['latest']) { ?>
                                <tr><td colspan="5" class="text-muted"><?= _l('ams_no_assets_yet'); ?></td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
