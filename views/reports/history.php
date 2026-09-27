<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><a href="<?= admin_url('asset_management/reports'); ?>" class="tw-text-neutral-500"><?= _l('ams_reports'); ?></a> › <?= _l('ams_report_history'); ?></h4>
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_date'), _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_action'), _l('ams_status'),
                    _l('ams_assigned_to'), _l('ams_location'), _l('ams_department'), _l('ams_note'), _l('ams_done_by'),
                ], 'ams-history-all', [], [
                    'data-last-order-identifier' => 'ams-history-all',
                    'data-default-order'         => get_table_last_order('ams-history-all'),
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-history-all', admin_url + 'asset_management/reports/history_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
