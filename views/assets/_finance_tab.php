<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Asset page → Finance: depreciation, total cost of ownership, disposal.
$cur    = $a->currency_name;
$money  = fn ($v) => $v !== null ? e(app_format_money($v, $cur)) : '-';
$base   = get_base_currency();
$policy = $dep['policy'];
$row    = fn ($label, $value) => '<tr><td class="tw-font-medium tw-text-neutral-500" style="width:45%">' . $label . '</td><td>' . $value . '</td></tr>';

// Yearly roll-up of the monthly schedule.
$years = [];
foreach ($dep['rows'] as $r) {
    $y = substr($r['date'], 0, 4);
    $years[$y]['depreciation'] = ($years[$y]['depreciation'] ?? 0) + $r['depreciation'];
    $years[$y]['book_value']   = $r['book_value'];
    $years[$y]['accumulated']  = $r['accumulated'];
}
$today = date('Y-m-d');
?>
<div role="tabpanel" class="tab-pane <?= $active ? 'active' : ''; ?>" id="tab_finance">
    <div class="row">
        <div class="col-md-6">
            <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_depreciation'); ?></h4>
            <table class="table table-bordered">
                <tbody>
                    <?= $row(_l('ams_dep_method'), e(_l('ams_dep_method_' . $policy['method'])) . ' <span class="text-muted tw-text-sm">(' . e(_l('ams_dep_source_' . $policy['source'])) . ($policy['source'] === 'category' && $policy['category'] ? ': ' . e($policy['category']) : '') . ')</span>'); ?>
                    <?php if ($dep['applicable']) { ?>
                    <?= $row(_l('ams_dep_life_months'), (int) $policy['life'] . ' <span class="text-muted">(' . _l('ams_dep_ends', e(_d($dep['end_date']))) . ')</span>'); ?>
                    <?= $row(_l('ams_purchase_cost'), $money($a->purchase_cost)); ?>
                    <?= $row(_l('ams_dep_salvage_value'), $money($policy['salvage'])); ?>
                    <?= $row(_l('ams_dep_months_elapsed'), (int) $dep['months_elapsed'] . ' / ' . (int) $policy['life']); ?>
                    <?= $row(_l('ams_dep_accumulated'), $money($dep['accumulated'])); ?>
                    <?= $row('<strong>' . _l('ams_dep_book_value') . '</strong>', '<strong>' . $money($dep['book_value']) . '</strong>' . ($disposal ? ' <span class="text-muted tw-text-sm">(' . _l('ams_dep_frozen_at', e(_d($disposal->disposal_date))) . ')</span>' : '')); ?>
                    <?php } else { ?>
                    <?= $row(_l('ams_dep_book_value'), $money($a->purchase_cost) . '<div class="text-muted tw-text-sm">' . e($dep['reason']) . '</div>'); ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_tco'); ?></h4>
            <table class="table table-bordered">
                <tbody>
                    <?= $row(_l('ams_purchase_cost'), $money($a->purchase_cost)); ?>
                    <?= $row(_l('ams_tco_maintenance'), e(app_format_money($tco['maintenance'], $base))); ?>
                    <?= $row(_l('ams_tco_licenses'), e(app_format_money($tco['licenses'], $base))); ?>
                    <?= $row('<strong>' . _l('ams_tco_total') . '</strong>', $tco['mixed'] ? '<span class="text-muted">' . _l('ams_tco_mixed') . '</span>' : '<strong>' . e(app_format_money($tco['total'], $cur ?: $base)) . '</strong>'); ?>
                </tbody>
            </table>
            <p class="text-muted tw-text-sm"><?= _l('ams_tco_help'); ?></p>

            <?php if ($disposal) { ?>
            <h4 class="tw-font-semibold tw-text-base"><?= _l('ams_disposal'); ?></h4>
            <table class="table table-bordered">
                <tbody>
                    <?= $row(_l('ams_disp_method'), e(_l('ams_disp_method_' . $disposal->method))); ?>
                    <?= $row(_l('ams_disp_date'), e(_d($disposal->disposal_date))); ?>
                    <?= $row(_l('ams_disp_recipient'), e($disposal->recipient) ?: '-'); ?>
                    <?= $row(_l('ams_disp_reference'), e($disposal->reference) ?: '-'); ?>
                    <?= $row(_l('ams_disp_book_value'), $money($disposal->book_value)); ?>
                    <?= $row(_l('ams_disp_proceeds'), $money($disposal->proceeds)); ?>
                    <?= $row(_l('ams_disp_gain_loss'), $disposal->gain_loss !== null ? '<span class="' . ((float) $disposal->gain_loss < 0 ? 'text-danger' : 'text-success') . '">' . $money($disposal->gain_loss) . '</span>' : '-'); ?>
                    <?= $row(_l('ams_reason'), nl2br(e($disposal->reason)) ?: '-'); ?>
                    <?= $row(_l('ams_done_by'), $disposal->staff_id ? ams_assignee_html('staff', $disposal->staff_id) . ' <span class="text-muted">' . e(_dt($disposal->date_created)) . '</span>' : '-'); ?>
                </tbody>
            </table>
            <?php } ?>
        </div>
    </div>

    <?php if ($dep['applicable']) { ?>
    <div class="tw-flex tw-justify-between tw-items-center tw-mt-2">
        <h4 class="tw-my-0 tw-font-semibold tw-text-base"><?= _l('ams_dep_schedule'); ?></h4>
        <a href="#" class="ams-dep-toggle"><?= _l('ams_dep_toggle_monthly'); ?></a>
    </div>
    <div class="table-responsive ams-dep-yearly">
        <table class="table table-striped tw-mt-2">
            <thead><tr><th><?= _l('ams_year'); ?></th><th class="text-right"><?= _l('ams_depreciation'); ?></th><th class="text-right"><?= _l('ams_dep_accumulated'); ?></th><th class="text-right"><?= _l('ams_dep_book_value'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($years as $y => $v) { ?>
                <tr class="<?= $y === date('Y') ? 'info' : ''; ?>">
                    <td><?= e($y); ?></td>
                    <td class="text-right"><?= $money($v['depreciation']); ?></td>
                    <td class="text-right"><?= $money($v['accumulated']); ?></td>
                    <td class="text-right"><?= $money($v['book_value']); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="table-responsive ams-dep-monthly hide">
        <table class="table table-striped tw-mt-2">
            <thead><tr><th>#</th><th><?= _l('ams_date'); ?></th><th class="text-right"><?= _l('ams_depreciation'); ?></th><th class="text-right"><?= _l('ams_dep_accumulated'); ?></th><th class="text-right"><?= _l('ams_dep_book_value'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($dep['rows'] as $r) { ?>
                <tr class="<?= $r['date'] <= $today ? '' : 'text-muted'; ?>">
                    <td><?= (int) $r['period']; ?></td>
                    <td><?= e(_d($r['date'])); ?></td>
                    <td class="text-right"><?= $money($r['depreciation']); ?></td>
                    <td class="text-right"><?= $money($r['accumulated']); ?></td>
                    <td class="text-right"><?= $money($r['book_value']); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>
</div>
