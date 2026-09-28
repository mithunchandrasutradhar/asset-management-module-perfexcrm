<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                <a href="<?= admin_url('asset_management/assets'); ?>" class="tw-text-neutral-500"><?= _l('ams_assets'); ?></a> › <?= _l('ams_deleted_assets') . ams_help_icon(_l('ams_deleted_assets_help')); ?>
            </h4>
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_serial_no'),
                    _l('ams_date_deleted'), _l('ams_deleted_by'), _l('ams_delete_reason'),
                ], 'ams-assets-deleted', [], [
                    'data-last-order-identifier' => 'ams-assets-deleted',
                    'data-default-order'         => get_table_last_order('ams-assets-deleted'),
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-assets-deleted', admin_url + 'asset_management/assets/deleted_table', [], [], {}, [4, 'desc']);
    });
</script>
</body>
</html>
