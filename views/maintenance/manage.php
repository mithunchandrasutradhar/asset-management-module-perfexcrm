<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_maintenance'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_maintenance')) { ?>
                        <a href="#" class="btn btn-primary" onclick="ams_mt_new(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_mt_new'); ?></a>
                        <?php } ?>
                        <a href="<?= admin_url('asset_management/maintenance/schedules'); ?>" class="btn btn-default"><i class="fa-regular fa-calendar-check tw-mr-1"></i><?= _l('ams_mt_schedules'); ?></a>
                        <div id="vueApp" class="tw-inline pull-right">
                            <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            '#', _l('ams_asset'), _l('ams_mt_title'), _l('ams_mt_type'), _l('ams_status'),
                            _l('ams_mt_due_date'), _l('ams_mt_start_date'), _l('ams_mt_end_date'), _l('ams_supplier'), _l('ams_mt_cost'),
                        ], 'ams-maintenance', [], [
                            'data-last-order-identifier' => 'ams-maintenance',
                            'data-default-order'         => get_table_last_order('ams-maintenance'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_modals', ['assets' => $assets, 'types' => $types, 'suppliers' => $suppliers, 'request' => $request, 'fixed_asset' => null]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-maintenance', admin_url + 'asset_management/maintenance/table', [], [], {}, [0, 'desc']);
        <?php if ($request && staff_can('create', 'ams_maintenance')) { ?>
        ams_mt_new();
        <?php } ?>
    });
</script>
</body>
</html>
