<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$currencies = [];
foreach (ams_currency_options() as $cu) {
    $currencies[$cu['id']] = $cu['name'];
}
$fmt = fn ($v, $cur) => e(app_format_money((float) $v, $currencies[$cur] ?? get_base_currency()));
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('ams_disposal_register'); ?></h4>
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <?php if ($totals) { ?>
        <div class="row">
            <?php foreach ($totals as $t) { ?>
            <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body tw-py-3">
                <p class="tw-text-neutral-500 tw-mb-0 tw-text-sm"><?= _l('ams_disposed'); ?> (<?= (int) $t['n']; ?>)</p>
                <p class="tw-mb-0"><?= _l('ams_disp_book_value'); ?>: <span class="tw-font-medium"><?= $fmt($t['book_value'], $t['currency']); ?></span></p>
                <p class="tw-mb-0"><?= _l('ams_disp_proceeds'); ?>: <span class="tw-font-medium"><?= $fmt($t['proceeds'], $t['currency']); ?></span></p>
                <p class="tw-mb-0"><?= _l('ams_disp_gain_loss'); ?>: <span class="tw-font-semibold <?= (float) $t['gain_loss'] < 0 ? 'text-danger' : 'text-success'; ?>"><?= $fmt($t['gain_loss'], $t['currency']); ?></span></p>
            </div></div></div>
            <?php } ?>
        </div>
        <?php } ?>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_disp_date'), _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_disp_method'),
                    _l('ams_disp_recipient'), _l('ams_disp_reference'), _l('ams_disp_book_value'), _l('ams_disp_proceeds'),
                    _l('ams_disp_gain_loss'), _l('ams_done_by'),
                ], 'ams-disposals', [], [
                    'data-last-order-identifier' => 'ams-disposals',
                    'data-default-order'         => get_table_last_order('ams-disposals'),
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-disposals', admin_url + 'asset_management/reports/disposals_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
