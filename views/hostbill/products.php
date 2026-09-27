<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_hb_products'); ?></h4>
                <?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_status_bar'); ?>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('edit', 'ams_hostbill')) { ?>
                        <a href="#" class="btn btn-primary" onclick="ams_hb_new_mapping(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_hb_new_mapping'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('sync', 'ams_hostbill')) { ?>
                        <button type="button" class="btn btn-default ams-hb-post" data-url="<?= admin_url('asset_management/hostbill/refresh_products'); ?>"><i class="fa-solid fa-cloud-arrow-down tw-mr-1"></i><?= _l('ams_hb_refresh_products'); ?></button>
                        <button type="button" class="btn btn-default ams-hb-post" data-url="<?= admin_url('asset_management/hostbill/push_now'); ?>"><i class="fa-solid fa-cloud-arrow-up tw-mr-1"></i><?= _l('ams_hb_push_now'); ?></button>
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
                            _l('ams_item'), _l('ams_hb_product'), _l('ams_hb_multiplier'), _l('ams_location'),
                            _l('ams_hb_perfex_available'), _l('ams_hb_publishable'), _l('ams_hb_hostbill_qty'), _l('ams_hb_push_status'), _l('ams_active'),
                        ], 'ams-hb-mappings'); ?>
                        <p class="text-muted tw-text-sm tw-mt-3 tw-mb-0"><?= _l('ams_hb_mapping_help'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (staff_can('edit', 'ams_hostbill')) { ?>
<div class="modal fade" id="ams_hb_mapping_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/hostbill/save_mapping'), ['id' => 'ams-hb-mapping-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_hb_mapping'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <?= render_select('item_id', $items, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_hb_perfex_item')); ?>
                <?php if (! $items) { ?><p class="text-warning tw-text-sm -tw-mt-2"><?= _l('ams_hb_no_stock_items'); ?></p><?php } ?>
                <?= render_select('hb_product_id', $hb_options, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_hb_product')); ?>
                <?php if (! $hb_options) { ?><p class="text-warning tw-text-sm -tw-mt-2"><?= _l('ams_hb_refresh_first'); ?></p><?php } ?>
                <div class="row">
                    <div class="col-md-5">
                        <?= render_input('qty_multiplier', 'ams_hb_multiplier', '1', 'number', ['step' => '0.01', 'min' => '0.01']); ?>
                    </div>
                    <div class="col-md-7">
                        <?= render_select('location_id', $locations, ['id', 'name'], 'ams_location'); ?>
                    </div>
                </div>
                <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_hb_multiplier_help'); ?></p>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="push_stock" id="push_stock" value="1" checked>
                    <label for="push_stock"><?= _l('ams_hb_push_stock_for_product'); ?></label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="active" id="active" value="1" checked>
                    <label for="active"><?= _l('ams_active'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_post_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-hb-mappings', admin_url + 'asset_management/hostbill/mappings_table', [], [], {}, [0, 'asc']);

        appValidateForm($('#ams-hb-mapping-form'), { item_id: 'required', hb_product_id: 'required', qty_multiplier: 'required' }, function(form) {
            $.post(form.action, $(form).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                alert_float(r.success ? 'success' : 'danger', r.message);
                if (r.success) {
                    $('#ams_hb_mapping_modal').modal('hide');
                    $('.table-ams-hb-mappings').DataTable().ajax.reload(null, false);
                }
            });
            return false;
        });
    });

    function ams_hb_fill(m) {
        var f = $('#ams-hb-mapping-form');
        f.find('[name="id"]').val(m.id || '');
        f.find('[name="item_id"]').selectpicker('val', m.item_id || '');
        f.find('[name="hb_product_id"]').selectpicker('val', m.hb_product_id || '');
        f.find('[name="qty_multiplier"]').val(m.qty_multiplier ? parseFloat(m.qty_multiplier) : 1);
        f.find('[name="location_id"]').selectpicker('val', m.location_id || '');
        f.find('[name="push_stock"]').prop('checked', m.id ? m.push_stock == 1 : true);
        f.find('[name="active"]').prop('checked', m.id ? m.active == 1 : true);
        $('#ams_hb_mapping_modal').modal('show');
    }

    function ams_hb_new_mapping() {
        ams_hb_fill({});
    }

    function ams_hb_edit_mapping(id) {
        $.get(admin_url + 'asset_management/hostbill/mapping/' + id, function(m) {
            ams_hb_fill(typeof m === 'string' ? JSON.parse(m) : m);
        });
    }
</script>
</body>
</html>
