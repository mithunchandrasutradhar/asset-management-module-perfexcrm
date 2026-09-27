<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_stock_levels'); ?></h4>
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
                            _l('ams_sku'), _l('ams_item_name'), _l('ams_item_kind'), _l('ams_category'), _l('ams_location'),
                            _l('ams_on_hand'), _l('ams_reserved'), _l('ams_available'), _l('ams_stock_value'),
                        ], 'ams-levels', [], [
                            'data-last-order-identifier' => 'ams-levels',
                            'data-default-order'         => get_table_last_order('ams-levels'),
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
        initDataTable('.table-ams-levels', admin_url + 'asset_management/inventory/levels_table', [], [], {}, [1, 'asc']);
    });
</script>
</body>
</html>
