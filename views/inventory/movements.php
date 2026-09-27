<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_stock_movements'); ?></h4>
                <div class="tw-mb-2 tw-flex tw-justify-end">
                    <div id="vueApp">
                        <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_date'), _l('ams_item'), _l('ams_item_kind'), _l('ams_movement_type'), _l('ams_location'),
                            _l('ams_quantity'), _l('ams_party'), _l('ams_reference'), _l('ams_note'), _l('ams_done_by'),
                        ], 'ams-movements', [], [
                            'data-last-order-identifier' => 'ams-movements',
                            'data-default-order'         => get_table_last_order('ams-movements'),
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
        initDataTable('.table-ams-movements', admin_url + 'asset_management/inventory/movements_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
