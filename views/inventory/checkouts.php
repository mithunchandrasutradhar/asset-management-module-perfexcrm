<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l(staff_can('view', 'ams_accessories') ? 'ams_accessory_checkouts' : 'ams_my_checkouts'); ?></h4>
                <div class="tw-mb-2 tw-flex tw-justify-between">
                    <a href="<?= admin_url('asset_management/inventory/index/accessory'); ?>" class="btn btn-default">
                        <i class="fa-solid fa-arrow-left tw-mr-1"></i><?= _l('ams_accessories'); ?>
                    </a>
                    <div id="vueApp">
                        <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_date'), _l('ams_item'), _l('ams_quantity'), _l('ams_returned'), _l('ams_outstanding'),
                            _l('ams_assigned_to'), _l('ams_department'), _l('ams_expected_return'), _l('ams_status'), _l('ams_done_by'),
                        ], 'ams-checkouts', [], [
                            'data-last-order-identifier' => 'ams-checkouts',
                            'data-default-order'         => get_table_last_order('ams-checkouts'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(AMS_MODULE_NAME . '/inventory/_checkin_modal', ['locations' => $locations]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/inventory/_ajax_forms_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-checkouts', admin_url + 'asset_management/inventory/checkouts_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
