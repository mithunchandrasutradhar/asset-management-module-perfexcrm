<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$i           = $item;
$kind        = $i->kind;
$isAccessory = $kind === 'accessory';
$canGlobal   = ams_item_can('view', $kind);
$canAdjust   = ams_item_can('adjust', $kind) && $i->active;
$canOut      = $isAccessory ? ams_item_can('checkout', $kind) && $i->active : ams_item_can('issue', $kind) && $i->active;
$state       = ams_stock_state($i->available, $i->reorder_level);
$cur         = get_base_currency();
$unit        = e($i->unit);

$staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], $staff);
$deptOptions  = array_map(fn ($d) => ['id' => $d['departmentid'], 'name' => $d['name']], $departments);

// Default "from" location: where most stock is, else the item's default location.
$fromDefault = $i->default_location_id;
$best        = 0;
foreach ($levels as $l) {
    if ((float) $l['on_hand'] - (float) $l['reserved'] > $best) {
        $best        = (float) $l['on_hand'] - (float) $l['reserved'];
        $fromDefault = $l['location_id'];
    }
}

$tile = fn ($label, $value, $class = '') => '<div class="col-md-2 col-sm-4 col-xs-6"><div class="tw-rounded-md tw-border tw-border-solid tw-border-neutral-200 tw-p-3 tw-mb-3">'
    . '<p class="tw-text-neutral-500 tw-text-sm tw-mb-1">' . $label . '</p><p class="tw-font-semibold tw-text-xl tw-mb-0 ' . $class . '">' . $value . '</p></div></div>';

$detail = function ($label, $value) {
    return '<tr><td class="tw-font-medium tw-text-neutral-500" style="width:40%">' . _l($label) . '</td><td>' . ($value === null || $value === '' ? '<span class="text-muted">-</span>' : $value) . '</td></tr>';
};
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <div>
                        <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                            <?= e($i->sku); ?> <span class="tw-font-normal tw-text-neutral-500">- <?= e($i->name); ?></span>
                        </h4>
                        <div class="tw-mt-1">
                            <span class="label label-default"><i class="<?= e($kind_cfg['icon']); ?> tw-mr-1"></i><?= _l($kind_cfg['singular']); ?></span>
                            <?= ams_stock_state_badge($state); ?>
                            <?php if (! $i->active) { ?><span class="label label-default"><?= _l('ams_inactive'); ?></span><?php } ?>
                        </div>
                    </div>
                    <div class="tw-flex tw-flex-wrap tw-gap-1">
                        <?php if ($canAdjust) { ?>
                        <a href="#" class="btn btn-success" data-toggle="modal" data-target="#ams_receive_modal"><i class="fa-solid fa-truck-ramp-box tw-mr-1"></i><?= _l('ams_receive_stock'); ?></a>
                        <?php } ?>
                        <?php if ($canOut) { ?>
                        <a href="#" class="btn btn-info" data-toggle="modal" data-target="#ams_out_modal">
                            <i class="fa-solid fa-right-from-bracket tw-mr-1"></i><?= _l($isAccessory ? 'ams_checkout' : 'ams_issue'); ?>
                        </a>
                        <?php } ?>
                        <?php if ($canAdjust) { ?>
                        <a href="#" class="btn btn-default" data-toggle="modal" data-target="#ams_transfer_modal"><i class="fa-solid fa-right-left tw-mr-1"></i><?= _l('ams_transfer'); ?></a>
                        <?php } ?>
                        <?php if (ams_item_can('adjust', $kind)) { ?>
                        <a href="#" class="btn btn-default" data-toggle="modal" data-target="#ams_adjust_modal"><i class="fa-solid fa-scale-balanced tw-mr-1"></i><?= _l('ams_adjust'); ?></a>
                        <?php } ?>
                        <?php if (ams_item_can('edit', $kind)) { ?>
                        <a href="<?= admin_url('asset_management/inventory/item/' . $i->id); ?>" class="btn btn-default"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a>
                        <?php } ?>
                        <?php if (ams_item_can('delete', $kind)) { ?>
                        <a href="<?= admin_url('asset_management/inventory/delete/' . $i->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                        <?php } ?>
                    </div>
                </div>

                <div class="row">
                    <?= $tile(_l('ams_on_hand'), ams_qty($i->on_hand) . ' <span class="tw-text-sm tw-text-neutral-400">' . $unit . '</span>'); ?>
                    <?php if ($isAccessory) { ?>
                    <?= $tile(_l('ams_checked_out'), ams_qty($i->checked_out)); ?>
                    <?php } ?>
                    <?php if ((float) $i->reserved > 0) { ?>
                    <?= $tile(_l('ams_reserved'), ams_qty($i->reserved)); ?>
                    <?php } ?>
                    <?= $tile(_l('ams_available'), ams_qty($i->available), $state === 'out' ? 'text-danger' : ($state === 'low' ? 'text-warning' : 'text-success')); ?>
                    <?= $tile(_l('ams_reorder_level'), ams_qty($i->reorder_level)); ?>
                    <?php if ($canGlobal) { ?>
                    <?= $tile(_l('ams_stock_value'), $i->value !== null ? e(app_format_money($i->value, $cur)) : '-'); ?>
                    <?php } ?>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                            <div class="horizontal-tabs">
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="active"><a href="#tab_overview" role="tab" data-toggle="tab"><?= _l('ams_overview'); ?></a></li>
                                    <?php if ($canGlobal) { ?>
                                    <li role="presentation"><a href="#tab_movements" role="tab" data-toggle="tab"><?= _l('ams_stock_movements'); ?></a></li>
                                    <?php } ?>
                                    <?php if ($isAccessory) { ?>
                                    <li role="presentation"><a href="#tab_checkouts" role="tab" data-toggle="tab"><?= _l($canGlobal ? 'ams_accessory_checkouts' : 'ams_my_checkouts'); ?></a></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content tw-mt-4">
                            <div role="tabpanel" class="tab-pane active" id="tab_overview">
                                <div class="row">
                                    <div class="col-md-7">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <?= $detail('ams_sku', e($i->sku)); ?>
                                                <?= $detail('ams_item_name', e($i->name)); ?>
                                                <?= $detail('ams_category', e($i->category_name)); ?>
                                                <?= $detail('ams_brand', e($i->brand_name)); ?>
                                                <?= $detail('ams_model_no', e($i->model_no)); ?>
                                                <?= $detail('ams_unit', $unit); ?>
                                                <?php if ($canGlobal) { ?>
                                                <?= $detail('ams_unit_cost', $i->cost !== null ? e(app_format_money($i->cost, $cur)) : ''); ?>
                                                <?php if ($kind === 'stock') { ?>
                                                <?= $detail('ams_sale_price', $i->sale_price !== null ? e(app_format_money($i->sale_price, $cur)) : ''); ?>
                                                <?= $detail('ams_is_sellable', $i->is_sellable ? _l('settings_yes') : _l('settings_no')); ?>
                                                <?php } ?>
                                                <?= $detail('ams_reorder_qty', ams_qty($i->reorder_qty)); ?>
                                                <?php } ?>
                                                <?= $detail('ams_default_location', e($i->default_location_name)); ?>
                                                <?php foreach (get_custom_fields('ams_items') as $field) {
                                                    echo $detail($field['name'], get_custom_field_value($i->id, $field['id'], 'ams_items'));
                                                } ?>
                                                <?= $detail('ams_description', nl2br(e($i->description))); ?>
                                                <?= $detail('ams_notes', nl2br(e($i->notes))); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-5">
                                        <h5 class="tw-font-semibold tw-mt-0"><?= _l('ams_stock_by_location'); ?></h5>
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th><?= _l('ams_location'); ?></th>
                                                    <th class="text-right"><?= _l('ams_on_hand'); ?></th>
                                                    <th class="text-right"><?= _l('ams_reserved'); ?></th>
                                                    <th class="text-right"><?= _l('ams_available'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($levels as $l) { ?>
                                                <tr>
                                                    <td><?= e($l['location_name']); ?></td>
                                                    <td class="text-right"><?= ams_qty($l['on_hand']); ?></td>
                                                    <td class="text-right"><?= ams_qty($l['reserved']); ?></td>
                                                    <td class="text-right tw-font-semibold"><?= ams_qty((float) $l['on_hand'] - (float) $l['reserved']); ?></td>
                                                </tr>
                                                <?php } ?>
                                                <?php if (! $levels) { ?>
                                                <tr><td colspan="4" class="text-muted"><?= _l('ams_no_stock_yet'); ?></td></tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <?php if ($canGlobal) { ?>
                            <div role="tabpanel" class="tab-pane" id="tab_movements">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_item_kind'), _l('ams_movement_type'), _l('ams_location'),
                                    _l('ams_quantity'), _l('ams_party'), _l('ams_reference'), _l('ams_note'), _l('ams_done_by'),
                                ], 'ams-item-movements'); ?>
                            </div>
                            <?php } ?>

                            <?php if ($isAccessory) { ?>
                            <div role="tabpanel" class="tab-pane" id="tab_checkouts">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_quantity'), _l('ams_returned'), _l('ams_outstanding'),
                                    _l('ams_assigned_to'), _l('ams_department'), _l('ams_expected_return'), _l('ams_status'), _l('ams_done_by'),
                                ], 'ams-item-checkouts'); ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canAdjust) { ?>
<div class="modal fade" id="ams_receive_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/inventory/action/receive/' . $i->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_receive_stock'); ?> - <?= e($i->sku); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5"><?= render_input('qty', '<small class="req text-danger">* </small>' . _l('ams_quantity') . ' (' . $unit . ')', '', 'number', ['step' => '0.01', 'min' => '0.01']); ?></div>
                    <div class="col-md-7"><?= render_select('location_id', $locations, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_location'), $i->default_location_id, [], [], '', '', false); ?></div>
                    <div class="col-md-5"><?= render_input('unit_cost', _l('ams_unit_cost') . ' (' . e($cur->name) . ')', $i->cost, 'number', ['step' => '0.01', 'min' => '0']); ?></div>
                    <div class="col-md-7"><?= render_select('supplier_id', $suppliers, ['id', 'name'], 'ams_supplier'); ?></div>
                </div>
                <div class="checkbox checkbox-primary tw-mt-0">
                    <input type="checkbox" name="update_cost" id="update_cost" value="1">
                    <label for="update_cost"><?= _l('ams_update_item_cost'); ?></label>
                </div>
                <?= render_input('reference', 'ams_reference', '', 'text', ['placeholder' => _l('ams_reference_placeholder')]); ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-success"><?= _l('ams_receive_stock'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<div class="modal fade" id="ams_transfer_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/inventory/action/transfer/' . $i->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_transfer'); ?> - <?= e($i->sku); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_select('from_location_id', $locations, ['id', 'name'], 'ams_from_location', $fromDefault, [], [], '', '', false); ?>
                <?= render_select('to_location_id', $locations, ['id', 'name'], 'ams_to_location'); ?>
                <?= render_input('qty', '<small class="req text-danger">* </small>' . _l('ams_quantity') . ' (' . $unit . ')', '', 'number', ['step' => '0.01', 'min' => '0.01']); ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('ams_transfer'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if (ams_item_can('adjust', $kind)) { ?>
<div class="modal fade" id="ams_adjust_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/inventory/action/adjust/' . $i->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_adjust'); ?> - <?= e($i->sku); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_select('location_id', $locations, ['id', 'name'], 'ams_location', $fromDefault, [], [], '', '', false); ?>
                <?= render_input('qty', '<small class="req text-danger">* </small>' . _l('ams_adjust_qty') . ' (' . $unit . ')', '', 'number', ['step' => '0.01']); ?>
                <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_adjust_qty_help'); ?></p>
                <?= render_select('reason', ams_adjust_reason_options(), ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_reason'), 'correction', [], [], '', '', false); ?>
                <?= render_textarea('note', '<small class="req text-danger">* </small>' . _l('ams_note')); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('ams_adjust'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if ($canOut) { ?>
<div class="modal fade" id="ams_out_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/inventory/action/' . ($isAccessory ? 'checkout' : 'issue') . '/' . $i->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l($isAccessory ? 'ams_checkout' : 'ams_issue'); ?> - <?= e($i->sku); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5"><?= render_input('qty', '<small class="req text-danger">* </small>' . _l('ams_quantity') . ' (' . $unit . ')', '1', 'number', ['step' => '0.01', 'min' => '0.01']); ?></div>
                    <div class="col-md-7"><?= render_select('location_id', $locations, ['id', 'name'], 'ams_from_location', $fromDefault, [], [], '', '', false); ?></div>
                </div>
                <?= render_select('assign_type', [
                    ['id' => 'staff', 'name' => _l('ams_assign_type_staff')],
                    ['id' => 'department', 'name' => _l('ams_assign_type_department')],
                ], ['id', 'name'], $isAccessory ? 'ams_checkout_to' : 'ams_issue_to', 'staff', [], [], '', 'ams-recipient-type', false); ?>
                <div class="ams-recipient ams-recipient-staff">
                    <?= render_select('assign_id_staff', $staffOptions, ['id', 'name'], 'ams_assign_type_staff'); ?>
                </div>
                <div class="ams-recipient ams-recipient-department hide">
                    <?= render_select('assign_id_department', $deptOptions, ['id', 'name'], 'ams_assign_type_department'); ?>
                </div>
                <div class="ams-recipient-dept">
                    <?= render_select('department_id', $deptOptions, ['id', 'name'], 'ams_department_cost_centre'); ?>
                    <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_department_default_help'); ?></p>
                </div>
                <?php if ($isAccessory) { ?>
                <?= render_date_input('expected_return', 'ams_expected_return'); ?>
                <?php } else { ?>
                <?= render_input('reference', 'ams_reference'); ?>
                <?php } ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?= _l($isAccessory ? 'ams_checkout' : 'ams_issue'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php $this->load->view(AMS_MODULE_NAME . '/inventory/_checkin_modal', ['locations' => $locations]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/inventory/_ajax_forms_js'); ?>
<script>
    $(function() {
        <?php if ($canGlobal) { ?>
        initDataTable('.table-ams-item-movements', admin_url + 'asset_management/inventory/movements_table/<?= (int) $i->id; ?>', [], [], {}, [0, 'desc']).column(1).visible(false, false).column(2).visible(false, false);
        <?php } ?>
        <?php if ($isAccessory) { ?>
        initDataTable('.table-ams-item-checkouts', admin_url + 'asset_management/inventory/checkouts_table/<?= (int) $i->id; ?>', [], [], {}, [0, 'desc']).column(1).visible(false, false);
        <?php } ?>
    });
</script>
</body>
</html>
