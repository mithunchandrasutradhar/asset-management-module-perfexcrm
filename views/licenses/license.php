<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$isNew  = ! $license;
$posted = $posted ?? [];
$saved  = $isNew ? [] : (array) $license;
$v      = fn ($f, $d = '') => array_key_exists($f, $posted) ? $posted[$f] : ($saved[$f] ?? $d);
$d      = fn ($f) => array_key_exists($f, $posted) ? $posted[$f] : _d($saved[$f] ?? '');
$chk    = fn ($f, $def) => $posted ? ! empty($posted[$f]) : ($isNew ? $def : ! empty($saved[$f]));
$canKey = $isNew || staff_can('view_keys', 'ams_licenses');
$cur    = e(get_base_currency()->name);
?>
<div id="wrapper">
    <div class="content">
        <?= form_open(admin_url('asset_management/licenses/license' . ($isNew ? '' : '/' . $license->id)), ['id' => 'ams-lic-form', 'autocomplete' => 'off']); ?>
        <div class="row">
            <div class="col-md-12"><h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= e($title); ?></h4></div>
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <?= render_input('name', '<small class="req text-danger">* </small>' . _l('ams_lic_name'), $v('name'), 'text', ['placeholder' => 'Microsoft 365 Business Standard']); ?>
                        <div class="row">
                            <div class="col-md-6"><?= render_select('brand_id', $brands, ['id', 'name'], 'ams_lic_manufacturer', $v('brand_id')); ?></div>
                            <div class="col-md-6"><?= render_select('category_id', $categories, ['id', 'name'], 'ams_category', $v('category_id')); ?></div>
                            <div class="col-md-6"><?= render_select('license_type', $types, ['id', 'name'], 'ams_lic_type', $v('license_type', 'subscription'), [], [], '', '', false); ?></div>
                            <div class="col-md-6"><?= render_input('seats', '<small class="req text-danger">* </small>' . _l('ams_lic_seats'), $v('seats', 1), 'number', ['min' => 1]); ?></div>
                        </div>
                        <?php if ($canKey) { ?>
                        <?= render_textarea('license_key', 'ams_lic_key', '', ['rows' => 2, 'placeholder' => ! $isNew && $license->has_key ? _l('ams_lic_key_keep') : '']); ?>
                        <?php } else { ?>
                        <p class="text-muted"><?= _l('ams_lic_key_no_permission'); ?></p>
                        <?php } ?>
                        <?= render_input('licensed_to', 'ams_lic_licensed_to', $v('licensed_to'), 'text', ['placeholder' => _l('ams_lic_licensed_to_placeholder')]); ?>
                        <?= render_textarea('notes', 'ams_notes', $v('notes')); ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="active" value="1" <?= $chk('active', true) ? 'checked' : ''; ?>>
                            <label for="active"><?= _l('ams_active'); ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <?= render_select('supplier_id', $suppliers, ['id', 'name'], 'ams_supplier', $v('supplier_id')); ?>
                        <div class="row">
                            <div class="col-md-6"><?= render_input('order_no', 'ams_order_no', $v('order_no')); ?></div>
                            <div class="col-md-6"><?= render_date_input('purchase_date', 'ams_purchase_date', $d('purchase_date')); ?></div>
                            <div class="col-md-6"><?= render_input('purchase_cost', _l('ams_purchase_cost') . ' (' . $cur . ')', $v('purchase_cost'), 'number', ['step' => '0.01', 'min' => '0']); ?></div>
                            <div class="col-md-6"><?= render_date_input('expiry_date', 'ams_lic_expiry', $d('expiry_date')); ?></div>
                            <div class="col-md-6"><?= render_input('renewal_cost', _l('ams_lic_renewal_cost') . ' (' . $cur . ')', $v('renewal_cost'), 'number', ['step' => '0.01', 'min' => '0']); ?></div>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="auto_renew" id="auto_renew" value="1" <?= $chk('auto_renew', false) ? 'checked' : ''; ?>>
                            <label for="auto_renew"><?= _l('ams_lic_auto_renew'); ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="btn-bottom-toolbar text-right">
                    <a href="<?= $isNew ? admin_url('asset_management/licenses') : admin_url('asset_management/licenses/view/' . $license->id); ?>" class="btn btn-default"><?= _l('cancel'); ?></a>
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
    $(function() { appValidateForm($('#ams-lic-form'), { name: 'required', seats: 'required' }); });
</script>
</body>
</html>
