<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$isNew  = ! isset($asset);
$posted = $posted ?? [];
$saved  = $isNew ? [] : (array) $asset;

/** Current value: re-posted form value > saved asset value > default. */
$v = function ($field, $default = '') use ($posted, $saved) {
    if (array_key_exists($field, $posted)) {
        return $posted[$field];
    }

    return isset($saved[$field]) ? $saved[$field] : $default;
};
$d = fn ($field) => array_key_exists($field, $posted) ? $posted[$field] : _d($v($field));

$staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], $staff);
$deptOptions  = array_map(fn ($dp) => ['id' => $dp['departmentid'], 'name' => $dp['name']], $departments);
$source       = $v('source', 'purchase');
$giftType     = $v('gifted_by_type', 'staff');
$action       = admin_url('asset_management/assets/asset' . ($isNew ? '' : '/' . $asset->id));
?>
<div id="wrapper">
    <div class="content">
        <?= form_open($action, ['id' => 'ams-asset-form', 'autocomplete' => 'off']); ?>
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3">
                    <?= e($title); ?>
                </h4>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_asset_information'); ?></h4>
                        <div class="row">
                            <div class="col-md-6">
                                <?= render_input('asset_tag', 'ams_asset_tag', $v('asset_tag'), 'text', $isNew ? ['placeholder' => _l('ams_asset_tag_auto')] : []); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('serial_no', 'ams_serial_no', $v('serial_no')); ?>
                            </div>
                        </div>
                        <?= render_input('name', '<small class="req text-danger">* </small>' . _l('ams_asset_name'), $v('name')); ?>
                        <div class="row">
                            <div class="col-md-6">
                                <?= render_select('category_id', $categories, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_category'), $v('category_id')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_select('model_id', $models, ['id', 'name'], 'ams_model', $v('model_id')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_select('brand_id', $brands, ['id', 'name'], 'ams_brand', $v('brand_id')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_select('asset_condition', ams_condition_options(), ['id', 'name'], 'ams_condition', $v('asset_condition', 'good')); ?>
                            </div>
                        </div>
                        <?php if (! $isNew) { ?>
                        <?= render_select('department_id', $deptOptions, ['id', 'name'], 'ams_department_cost_centre', $v('department_id')); ?>
                        <?php } ?>
                        <?= render_textarea('notes', 'ams_notes', $v('notes')); ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_source_and_purchase'); ?></h4>
                        <?= render_select('source', ams_source_options(), ['id', 'name'], 'ams_source', $source, [], [], '', '', false); ?>

                        <div class="ams-source ams-source-gift<?= $source === 'gift' ? '' : ' hide'; ?>">
                            <div class="row">
                                <div class="col-md-5">
                                    <?= render_select('gifted_by_type', [
                                        ['id' => 'staff', 'name' => _l('ams_assign_type_staff')],
                                        ['id' => 'supplier', 'name' => _l('ams_supplier')],
                                        ['id' => 'other', 'name' => _l('ams_gifted_by_other')],
                                    ], ['id', 'name'], 'ams_gifted_by', $giftType, [], [], '', '', false); ?>
                                </div>
                                <div class="col-md-7">
                                    <div class="ams-gift ams-gift-staff<?= $giftType === 'staff' ? '' : ' hide'; ?>">
                                        <?= render_select('gifted_by_id_staff', $staffOptions, ['id', 'name'], 'ams_assign_type_staff', $giftType === 'staff' ? $v('gifted_by_id') : ''); ?>
                                    </div>
                                    <div class="ams-gift ams-gift-supplier<?= $giftType === 'supplier' ? '' : ' hide'; ?>">
                                        <?= render_select('gifted_by_id_supplier', $suppliers, ['id', 'name'], 'ams_supplier', $giftType === 'supplier' ? $v('gifted_by_id') : ''); ?>
                                    </div>
                                    <div class="ams-gift ams-gift-other<?= $giftType === 'other' ? '' : ' hide'; ?>">
                                        <?= render_input('gifted_by_name', 'ams_gifted_by_name', $v('gifted_by_name')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ams-source ams-source-purchase ams-source-lease ams-source-transfer<?= $source === 'gift' ? ' hide' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <?= render_select('supplier_id', $suppliers, ['id', 'name'], 'ams_supplier', $v('supplier_id')); ?>
                                </div>
                                <div class="col-md-6">
                                    <?= render_select('purchased_by', $staffOptions, ['id', 'name'], 'ams_purchased_by', $v('purchased_by')); ?>
                                </div>
                                <div class="col-md-4">
                                    <?= render_date_input('purchase_date', 'ams_purchase_date', $d('purchase_date')); ?>
                                </div>
                                <div class="col-md-4">
                                    <?= render_input('purchase_cost', 'ams_purchase_cost', $v('purchase_cost'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                                </div>
                                <div class="col-md-4">
                                    <?= render_select('currency', $currencies, ['id', 'name'], 'ams_currency', $v('currency', get_base_currency()->id), [], [], '', '', false); ?>
                                </div>
                                <div class="col-md-6">
                                    <?= render_input('invoice_no', 'ams_invoice_no', $v('invoice_no')); ?>
                                </div>
                                <div class="col-md-6">
                                    <?= render_input('order_no', 'ams_order_no', $v('order_no')); ?>
                                </div>
                            </div>
                            <div class="ams-source ams-source-lease<?= $source === 'lease' ? '' : ' hide'; ?>">
                                <?= render_date_input('lease_end_date', 'ams_lease_end_date', $d('lease_end_date')); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_warranty'); ?></h4>
                        <div class="row">
                            <div class="col-md-6">
                                <?= render_date_input('warranty_start', 'ams_warranty_start', $d('warranty_start')); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_date_input('warranty_end', 'ams_warranty_end', $d('warranty_end')); ?>
                            </div>
                        </div>
                        <?= render_input('warranty_provider', 'ams_warranty_provider', $v('warranty_provider')); ?>
                        <?= render_textarea('warranty_notes', 'ams_warranty_notes', $v('warranty_notes')); ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-1"><?= _l('ams_depreciation'); ?></h4>
                        <p class="text-muted tw-text-sm tw-mb-3"><?= _l('ams_dep_asset_help'); ?></p>
                        <?= render_select('depreciation_method', ams_depreciation_method_options(true), ['id', 'name'], 'ams_dep_method', $v('depreciation_method'), [], [], '', '', false); ?>
                        <div class="row">
                            <div class="col-md-6">
                                <?= render_input('useful_life_months', 'ams_dep_life_months', $v('useful_life_months'), 'number', ['min' => 1, 'placeholder' => _l('ams_dep_inherit_short')]); ?>
                            </div>
                            <div class="col-md-6">
                                <?= render_input('salvage_value', 'ams_dep_salvage_value', $v('salvage_value'), 'number', ['step' => '0.01', 'min' => 0, 'placeholder' => _l('ams_dep_inherit_short')]); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($isNew) { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-mb-4"><?= _l('ams_initial_state'); ?></h4>
                        <?php $assignType = $v('assign_type', ''); ?>
                        <?= render_select('assign_type', array_merge([['id' => '', 'name' => _l('ams_keep_in_store')]], ams_assignee_type_options()), ['id', 'name'], 'ams_assign_now', $assignType, [], [], '', '', false); ?>

                        <div class="ams-initial-store<?= $assignType ? ' hide' : ''; ?>">
                            <?= render_select('status_id', $statuses, ['id', 'name'], 'ams_status', $v('status_id', ams_get_status_by_key('in_store')['id'] ?? '')); ?>
                            <?= render_select('location_id', $locations, ['id', 'name'], 'ams_location', $v('location_id')); ?>
                        </div>

                        <div class="ams-initial-assign<?= $assignType ? '' : ' hide'; ?>">
                            <div class="ams-assign ams-assign-staff<?= $assignType === 'staff' ? '' : ' hide'; ?>">
                                <?= render_select('assign_id_staff', $staffOptions, ['id', 'name'], 'ams_assign_type_staff', $v('assign_id_staff')); ?>
                            </div>
                            <div class="ams-assign ams-assign-department<?= $assignType === 'department' ? '' : ' hide'; ?>">
                                <?= render_select('assign_id_department', $deptOptions, ['id', 'name'], 'ams_assign_type_department', $v('assign_id_department')); ?>
                            </div>
                            <div class="ams-assign ams-assign-location<?= $assignType === 'location' ? '' : ' hide'; ?>">
                                <?= render_select('assign_id_location', $locations, ['id', 'name'], 'ams_assign_type_location', $v('assign_id_location')); ?>
                            </div>
                            <?= render_select('department_id', $deptOptions, ['id', 'name'], 'ams_department_cost_centre', $v('department_id'), [], [], '', '', true); ?>
                            <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_department_default_help'); ?></p>
                            <?= render_date_input('expected_checkin', 'ams_expected_checkin', $v('expected_checkin')); ?>
                        </div>

                        <?= render_textarea('initial_note', 'ams_note', $v('initial_note')); ?>
                    </div>
                </div>
                <?php } ?>

                <?php $cf = render_custom_fields('ams_assets', $isNew ? false : $asset->id); ?>
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
                    <a href="<?= $isNew ? admin_url('asset_management/assets') : admin_url('asset_management/assets/view/' . $asset->id); ?>" class="btn btn-default"><?= _l('cancel'); ?></a>
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
        appValidateForm($('#ams-asset-form'), {
            name: 'required',
            category_id: 'required'
        });

        $('#source').on('change', function() {
            var source = $(this).val();
            $('.ams-source').addClass('hide');
            $('.ams-source-' + source).removeClass('hide');
        });

        $('#gifted_by_type').on('change', function() {
            $('.ams-gift').addClass('hide');
            $('.ams-gift-' + $(this).val()).removeClass('hide');
        });

        $('#assign_type').on('change', function() {
            var type = $(this).val();
            $('.ams-initial-store').toggleClass('hide', type !== '');
            $('.ams-initial-assign').toggleClass('hide', type === '');
            $('.ams-assign').addClass('hide');
            $('.ams-assign-' + type).removeClass('hide');
        });
    });
</script>
</body>
</html>
