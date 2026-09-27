<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_licenses'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_licenses')) { ?>
                        <a href="<?= admin_url('asset_management/licenses/license'); ?>" class="btn btn-primary"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_record', _l('ams_license')); ?></a>
                        <?php } ?>
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
                            _l('ams_lic_name'), _l('ams_lic_manufacturer'), _l('ams_lic_type'), _l('ams_lic_seats_used'), _l('ams_lic_seats_free'),
                            _l('ams_lic_expiry'), _l('ams_supplier'), _l('ams_purchase_cost'), _l('ams_active'),
                        ], 'ams-licenses', [], [
                            'data-last-order-identifier' => 'ams-licenses',
                            'data-default-order'         => get_table_last_order('ams-licenses'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-licenses', admin_url + 'asset_management/licenses/table', [], [], {}, [5, 'asc']);
    });
</script>
</body>
</html>
