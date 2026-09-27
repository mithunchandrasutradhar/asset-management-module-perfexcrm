<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3">
                    <i class="<?= e($kind_cfg['icon']); ?> tw-text-neutral-500 tw-mr-1"></i><?= _l($kind_cfg['plural']); ?>
                </h4>

                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (ams_item_can('create', $kind)) { ?>
                        <a href="<?= admin_url('asset_management/inventory/item?kind=' . $kind); ?>" class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_record', _l($kind_cfg['singular'])); ?>
                        </a>
                        <?php } ?>
                        <?php if ($kind === 'accessory') { ?>
                        <a href="<?= admin_url('asset_management/inventory/checkouts'); ?>" class="btn btn-default">
                            <i class="fa-solid fa-user-check tw-mr-1"></i><?= _l('ams_accessory_checkouts'); ?>
                        </a>
                        <?php } ?>
                        <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0">
                            <app-filters id="<?= $table->id(); ?>"
                                view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>"
                                :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php
                        $headings = [
                            _l('ams_sku'),
                            _l('ams_item_name'),
                            _l('ams_category'),
                            _l('ams_brand'),
                            _l('ams_on_hand'),
                            _l('ams_checked_out'),
                            _l('ams_available'),
                            _l('ams_reorder_level'),
                            _l('ams_stock_state'),
                            _l('ams_unit_cost'),
                            _l('ams_stock_value'),
                            _l('ams_active'),
                        ];
                        foreach (get_custom_fields('ams_items', ['show_on_table' => 1]) as $field) {
                            $headings[] = ['name' => $field['name'], 'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1]];
                        }
                        render_datatable($headings, 'ams-items', [], [
                            'data-last-order-identifier' => 'ams-items-' . $kind,
                            'data-default-order'         => get_table_last_order('ams-items-' . $kind),
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        var table = initDataTable('.table-ams-items', admin_url + 'asset_management/inventory/table/<?= e($kind); ?>', [], [], {}, [1, 'asc']);
        <?php if ($kind !== 'accessory') { ?>
        // "Checked out" only applies to accessories.
        table.column(5).visible(false, false).columns.adjust();
        <?php } ?>
    });
</script>
</body>
</html>
