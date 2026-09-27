<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><a href="<?= admin_url('asset_management/reports'); ?>" class="tw-text-neutral-500"><?= _l('ams_reports'); ?></a> › <?= _l('ams_report_warranty'); ?></h4>
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_warranty'), _l('ams_warranty_days_left'), _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'),
                    _l('ams_serial_no'), _l('ams_warranty_provider'), _l('ams_warranty_start'), _l('ams_assigned_to'), _l('ams_location'),
                ], 'ams-warranty', [], [
                    'data-last-order-identifier' => 'ams-warranty',
                    'data-default-order'         => get_table_last_order('ams-warranty'),
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-warranty', admin_url + 'asset_management/reports/warranty_table', [], [], {}, [0, 'asc']);
    });
</script>
</body>
</html>
