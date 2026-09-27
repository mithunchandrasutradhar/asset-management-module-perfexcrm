<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$currencies = [];
foreach (ams_currency_options() as $cu) {
    $currencies[$cu['id']] = $cu['name'];
}
$fmt    = fn ($v, $cur) => $v !== null ? e(app_format_money($v, $currencies[$cur] ?? get_base_currency())) : '-';
$totals = [];
foreach ($summary as $s) {
    $k = (int) $s['currency'];
    $totals[$k]['assets']      = ($totals[$k]['assets'] ?? 0) + $s['assets'];
    $totals[$k]['cost']        = ($totals[$k]['cost'] ?? 0) + (float) $s['cost'];
    $totals[$k]['accumulated'] = ($totals[$k]['accumulated'] ?? 0) + (float) $s['accumulated'];
    $totals[$k]['book_value']  = ($totals[$k]['book_value'] ?? 0) + (float) $s['book_value'];
}
$lastRun = (int) get_option('ams_last_dep_run');
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('ams_valuation_report'); ?></h4>
            <div class="tw-flex tw-gap-2 tw-items-center">
                <span class="text-muted tw-text-sm"><?= $lastRun ? _l('ams_dep_last_run', e(_dt(date('Y-m-d H:i:s', $lastRun)))) : ''; ?></span>
                <?= form_open(admin_url('asset_management/reports/recalculate'), ['class' => 'tw-inline']); ?>
                <button type="submit" class="btn btn-default"><i class="fa-solid fa-calculator tw-mr-1"></i><?= _l('ams_dep_recalculate'); ?></button>
                <?= form_close(); ?>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-semibold tw-text-base"><?= _l('ams_valuation_summary'); ?></h4>
                    <form method="get" class="tw-flex tw-gap-3 tw-items-center">
                        <select name="group" class="form-control" style="width:auto" onchange="this.form.submit()">
                            <?php foreach (['category' => 'ams_category', 'location' => 'ams_location', 'department' => 'ams_department'] as $g => $label) { ?>
                            <option value="<?= $g; ?>" <?= $group === $g ? 'selected' : ''; ?>><?= _l('ams_group_by') . ' ' . _l($label); ?></option>
                            <?php } ?>
                        </select>
                        <div class="checkbox checkbox-primary tw-my-0">
                            <input type="checkbox" name="include_archived" id="ams_include_archived" value="1" <?= $include_archived ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label for="ams_include_archived"><?= _l('ams_include_disposed'); ?></label>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped tw-mb-0">
                        <thead>
                            <tr>
                                <th><?= _l($group === 'location' ? 'ams_location' : ($group === 'department' ? 'ams_department' : 'ams_category')); ?></th>
                                <th class="text-right"><?= _l('ams_assets'); ?></th>
                                <th class="text-right"><?= _l('ams_purchase_cost'); ?></th>
                                <th class="text-right"><?= _l('ams_dep_accumulated'); ?></th>
                                <th class="text-right"><?= _l('ams_dep_book_value'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary as $s) { ?>
                            <tr>
                                <td><?= $s['label'] !== null ? e($s['label']) : '<span class="text-muted">' . _l('ams_none') . '</span>'; ?></td>
                                <td class="text-right"><?= (int) $s['assets']; ?></td>
                                <td class="text-right"><?= $fmt($s['cost'], $s['currency']); ?></td>
                                <td class="text-right"><?= $fmt($s['accumulated'], $s['currency']); ?></td>
                                <td class="text-right tw-font-medium"><?= $fmt($s['book_value'], $s['currency']); ?></td>
                            </tr>
                            <?php } ?>
                            <?php if (! $summary) { ?>
                            <tr><td colspan="5" class="text-muted"><?= _l('ams_no_assets_yet'); ?></td></tr>
                            <?php } ?>
                        </tbody>
                        <?php if ($totals) { ?>
                        <tfoot>
                            <?php foreach ($totals as $cur => $t) { ?>
                            <tr class="tw-font-semibold">
                                <td><?= _l('ams_po_total'); ?><?= count($totals) > 1 ? ' (' . e($currencies[$cur] ?? '') . ')' : ''; ?></td>
                                <td class="text-right"><?= (int) $t['assets']; ?></td>
                                <td class="text-right"><?= $fmt($t['cost'], $cur); ?></td>
                                <td class="text-right"><?= $fmt($t['accumulated'], $cur); ?></td>
                                <td class="text-right"><?= $fmt($t['book_value'], $cur); ?></td>
                            </tr>
                            <?php } ?>
                        </tfoot>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="tw-mb-2 tw-flex tw-justify-end">
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <?= form_hidden('ams_include_archived', $include_archived ? '1' : '0'); ?>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_department'), _l('ams_location'),
                    _l('ams_purchase_date'), _l('ams_purchase_cost'), _l('ams_dep_method'), _l('ams_dep_life_months'),
                    _l('ams_dep_accumulated'), _l('ams_dep_book_value'), _l('ams_status'),
                ], 'ams-valuation', [], [
                    'data-last-order-identifier' => 'ams-valuation',
                    'data-default-order'         => get_table_last_order('ams-valuation'),
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-valuation', admin_url + 'asset_management/reports/valuation_table', [], [], {
            ams_include_archived: '[name="ams_include_archived"]'
        }, [0, 'asc']).columns([3, 8]).visible(false, false);
    });
</script>
</body>
</html>
