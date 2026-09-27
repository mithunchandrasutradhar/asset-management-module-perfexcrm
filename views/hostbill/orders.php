<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_hb_orders'); ?></h4>
                <?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_status_bar'); ?>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('sync', 'ams_hostbill')) { ?>
                        <button type="button" class="btn btn-primary ams-hb-post" data-url="<?= admin_url('asset_management/hostbill/sync_now'); ?>">
                            <i class="fa-solid fa-rotate tw-mr-1"></i><?= _l('ams_hb_sync_now'); ?>
                        </button>
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
                            _l('ams_date'), _l('ams_hb_order'), _l('ams_hb_client'), _l('ams_hb_order_status'), _l('ams_hb_invoice_status'),
                            _l('ams_hb_total'), _l('ams_hb_items'), _l('ams_hb_stock_state'), _l('ams_hb_fulfilment'), _l('ams_hb_synced'),
                        ], 'ams-hb-orders', [], [
                            'data-last-order-identifier' => 'ams-hb-orders',
                            'data-default-order'         => get_table_last_order('ams-hb-orders'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_post_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-hb-orders', admin_url + 'asset_management/hostbill/orders_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
