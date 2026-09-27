<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_purchase_register'); ?></h4>

                <div class="tw-mb-2 tw-flex tw-justify-end">
                    <div id="vueApp">
                        <app-filters id="<?= $table->id(); ?>"
                            view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>"
                            :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_purchase_date'),
                            _l('ams_asset_tag'),
                            _l('ams_asset_name'),
                            _l('ams_category'),
                            _l('ams_supplier'),
                            _l('ams_invoice_no'),
                            _l('ams_order_no'),
                            _l('ams_purchased_by'),
                            _l('ams_purchase_cost'),
                            _l('ams_warranty'),
                        ], 'ams-purchases', [], [
                            'data-last-order-identifier' => 'ams-purchases',
                            'data-default-order'         => get_table_last_order('ams-purchases'),
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
        initDataTable('.table-ams-purchases', admin_url + 'asset_management/purchases/table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
