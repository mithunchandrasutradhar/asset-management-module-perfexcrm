<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$isNew  = ! $item;
$posted = $posted ?? [];
$saved  = $isNew ? [] : (array) $item;

$v = function ($field, $default = '') use ($posted, $saved) {
    if (array_key_exists($field, $posted)) {
        return $posted[$field];
    }

    return isset($saved[$field]) ? $saved[$field] : $default;
};
$checked = function ($field, $default) use ($posted, $saved, $isNew) {
    if ($posted) {
        return ! empty($posted[$field]);
    }

    return $isNew ? $default : ! empty($saved[$field]);
};
$qtyVal = fn ($field) => $v($field) === '' ? '' : ams_qty($v($field));
?>
<div id="wrapper">
    <div class="content">
        <?= form_open(admin_url('asset_management/inventory/item' . ($isNew ? '?kind=' . $kind : '/' . $item->id)), ['id' => 'ams-item-form', 'autocomplete' => 'off']); ?>
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= e($title); ?></h4>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_item_details'); ?></h4>
                        <div class="row">
                            <div class="col-md-5">
                                <?= render_input('sku', 'ams_sku', $v('sku'), 'text', $isNew ? ['placeholder' => _l('ams_asset_tag_auto')] : []); ?>
                            </div>
                            <div class="col-md-7">
                                <?= render_input('name', '<small class="req text-danger">* </small>' . _l('ams_item_name'), $v('name')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_select('category_id', $categories, ['id', 'name'], 'ams_category', $v('category_id')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_select('brand_id', $brands, ['id', 'name'], 'ams_brand', $v('brand_id')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('model_no', 'ams_model_no', $v('model_no')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('unit', 'ams_unit', $v('unit', 'pcs'), 'text', ['placeholder' => 'pcs, box, m, ...']); ?>
                            </div>
                        </div>
                        <?= render_textarea('description', 'ams_description', $v('description')); ?>
                        <?= render_textarea('notes', 'ams_notes', $v('notes')); ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="active" value="1" <?= $checked('active', true) ? 'checked' : ''; ?>>
                            <label for="active"><?= _l('ams_active'); ?></label>
                        </div>
                        <?php if ($kind === 'stock') { ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="is_sellable" id="is_sellable" value="1" <?= $checked('is_sellable', true) ? 'checked' : ''; ?>>
                            <label for="is_sellable"><?= _l('ams_is_sellable'); ?></label>
                        </div>
                        <p class="text-muted tw-text-sm"><?= _l('ams_is_sellable_help'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_pricing_and_stock'); ?></h4>
                        <div class="row">
                            <div class="col-md-6">
                                <?= render_input('cost', _l('ams_unit_cost') . ' (' . e(get_base_currency()->name) . ')', $v('cost'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($kind === 'stock') { ?>
                                <?= render_input('sale_price', _l('ams_sale_price') . ' (' . e(get_base_currency()->name) . ')', $v('sale_price'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('reorder_level', 'ams_reorder_level', $qtyVal('reorder_level'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('reorder_qty', 'ams_reorder_qty', $qtyVal('reorder_qty'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                            </div>
                        </div>
                        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_reorder_help'); ?></p>
                        <?= render_select('default_location_id', $locations, ['id', 'name'], 'ams_default_location', $v('default_location_id')); ?>
                    </div>
                </div>

                <?php if ($isNew) { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_opening_stock'); ?></h4>
                        <div class="row">
                            <div class="col-md-5">
                                <?= render_input('opening_qty', 'ams_quantity', $v('opening_qty'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                            </div>
                            <div class="col-md-7">
                                <?= render_select('opening_location_id', $locations, ['id', 'name'], 'ams_location', $v('opening_location_id')); ?>
                            </div>
                        </div>
                        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_opening_stock_help'); ?></p>
                    </div>
                </div>
                <?php } ?>

                <?php $cf = render_custom_fields('ams_items', $isNew ? false : $item->id); ?>
                <?php if (trim($cf) !== '') { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('custom_fields'); ?></h4>
                        <?= $cf; ?>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="col-md-12">
                <div class="btn-bottom-toolbar text-right">
                    <a href="<?= $isNew ? admin_url('asset_management/inventory/index/' . $kind) : admin_url('asset_management/inventory/view/' . $item->id); ?>" class="btn btn-default"><?= _l('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
                </div>
            </div>
        </div>
        <?= form_close(); ?>
        <div class="btn-bottom-pusher"></div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        appValidateForm($('#ams-item-form'), { name: 'required' });
    });
</script>
</body>
</html>
