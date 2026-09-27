<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_purchase_orders'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_procurement')) { ?>
                        <a href="<?= admin_url('asset_management/procurement/po'); ?>" class="btn btn-primary"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_record', _l('ams_purchase_order')); ?></a>
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
                            _l('ams_po_number'), _l('ams_po_order_date'), _l('ams_supplier'), _l('ams_po_lines'),
                            _l('ams_po_total'), _l('ams_po_expected_date'), _l('ams_status'), _l('ams_created_by'),
                        ], 'ams-pos', [], [
                            'data-last-order-identifier' => 'ams-pos',
                            'data-default-order'         => get_table_last_order('ams-pos'),
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
        initDataTable('.table-ams-pos', admin_url + 'asset_management/procurement/table', [], [], {}, [1, 'desc']);
    });
</script>
</body>
</html>
