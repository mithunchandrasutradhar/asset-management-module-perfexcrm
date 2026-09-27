<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_requests'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_requests')) { ?>
                        <a href="<?= admin_url('asset_management/my_assets'); ?>" class="btn btn-default"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_req_new'); ?></a>
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
                            _l('ams_req_no'), _l('ams_date'), _l('ams_requester'), _l('ams_department'), _l('ams_req_type'),
                            _l('ams_req_subject'), _l('ams_quantity'), _l('ams_priority'), _l('ams_needed_by'), _l('ams_status'),
                        ], 'ams-requests', [], [
                            'data-last-order-identifier' => 'ams-requests',
                            'data-default-order'         => get_table_last_order('ams-requests'),
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
        initDataTable('.table-ams-requests', admin_url + 'asset_management/requests/table', [], [], {}, [1, 'desc']);
    });
</script>
</body>
</html>
