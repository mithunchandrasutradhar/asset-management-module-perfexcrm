<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$a            = $asset;
$activeTab    = in_array($this->input->get('tab'), ['history', 'changelog', 'files', 'maintenance', 'licenses', 'finance']) ? $this->input->get('tab') : 'overview';
$canFinance   = staff_can('view', 'ams_assets');
$canDispose   = staff_can('dispose', 'ams_assets') && ! $disposal && $a->status_type !== 'archived' && $archived;
$canReinstate = staff_can('dispose', 'ams_assets') && $disposal;
$canMt        = staff_can('view', 'ams_maintenance');
$canLic       = staff_can('view', 'ams_licenses');
$canEdit      = staff_can('edit', 'ams_assets');
$canCheckout  = staff_can('checkout', 'ams_assets') && ! $a->assigned_type && $a->status_type === 'deployable';
$canCheckin   = staff_can('checkin', 'ams_assets') && $a->assigned_type;
$staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], $staff);
$deptOptions  = array_map(fn ($dp) => ['id' => $dp['departmentid'], 'name' => $dp['name']], $departments);
$cover        = null;
foreach ($files as $f) {
    if ((int) $f['id'] === (int) $a->cover_file_id) {
        $cover = $f;
    }
}

$detail = function ($label, $value) {
    if ($value === null || $value === '') {
        $value = '<span class="text-muted">-</span>';
    }

    return '<tr><td class="tw-font-medium tw-text-neutral-500" style="width:40%">' . _l($label) . '</td><td>' . $value . '</td></tr>';
};

$giftedBy = '';
if ($a->source === 'gift') {
    if ($a->gifted_by_type === 'staff') {
        $giftedBy = ams_assignee_html('staff', $a->gifted_by_id);
    } elseif ($a->gifted_by_type === 'supplier') {
        $giftedBy = e(ams_option_label(ams_supplier_options(), $a->gifted_by_id));
    } else {
        $giftedBy = e($a->gifted_by_name);
    }
}
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <div>
                        <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                            <?= e($a->asset_tag); ?> <span class="tw-font-normal tw-text-neutral-500">- <?= e($a->name); ?></span>
                        </h4>
                        <div class="tw-mt-1"><?= ams_status_badge($a->status_name, $a->status_color); ?></div>
                    </div>
                    <div class="tw-flex tw-flex-wrap tw-gap-1">
                        <?php if ($canCheckout) { ?>
                        <a href="#" class="btn btn-success" data-toggle="modal" data-target="#ams_checkout_modal">
                            <i class="fa-solid fa-right-from-bracket tw-mr-1"></i><?= _l('ams_checkout'); ?>
                        </a>
                        <?php } ?>
                        <?php if ($canCheckin) { ?>
                        <a href="#" class="btn btn-info" data-toggle="modal" data-target="#ams_checkin_modal">
                            <i class="fa-solid fa-right-to-bracket tw-mr-1"></i><?= _l('ams_checkin'); ?>
                        </a>
                        <?php } ?>
                        <?php if ($canEdit) { ?>
                        <a href="#" class="btn btn-default" data-toggle="modal" data-target="#ams_status_modal">
                            <i class="fa-solid fa-arrows-rotate tw-mr-1"></i><?= _l('ams_change_status'); ?>
                        </a>
                        <a href="<?= admin_url('asset_management/assets/asset/' . $a->id); ?>" class="btn btn-default">
                            <i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?>
                        </a>
                        <?php } ?>
                        <a href="<?= admin_url('asset_management/assets/labels/' . $a->id); ?>" target="_blank" class="btn btn-default">
                            <i class="fa-solid fa-qrcode tw-mr-1"></i><?= _l('ams_print_label'); ?>
                        </a>
                        <?php if ($canDispose) { ?>
                        <a href="#" class="btn btn-warning" data-toggle="modal" data-target="#ams_dispose_modal">
                            <i class="fa-solid fa-box-archive tw-mr-1"></i><?= _l('ams_dispose'); ?>
                        </a>
                        <?php } ?>
                        <?php if ($canReinstate) { ?>
                        <a href="#" class="btn btn-default" data-toggle="modal" data-target="#ams_reinstate_modal">
                            <i class="fa-solid fa-rotate-left tw-mr-1"></i><?= _l('ams_disp_reinstate'); ?>
                        </a>
                        <?php } ?>
                        <?php if (staff_can('delete', 'ams_assets')) { ?>
                        <a href="<?= admin_url('asset_management/assets/delete/' . $a->id); ?>" class="btn btn-danger _delete">
                            <i class="fa-regular fa-trash-can"></i>
                        </a>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                            <div class="horizontal-tabs">
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="<?= $activeTab === 'overview' ? 'active' : ''; ?>">
                                        <a href="#tab_overview" aria-controls="tab_overview" role="tab" data-toggle="tab"><?= _l('ams_overview'); ?></a>
                                    </li>
                                    <li role="presentation" class="<?= $activeTab === 'history' ? 'active' : ''; ?>">
                                        <a href="#tab_history" aria-controls="tab_history" role="tab" data-toggle="tab"><?= _l('ams_history'); ?></a>
                                    </li>
                                    <li role="presentation" class="<?= $activeTab === 'changelog' ? 'active' : ''; ?>">
                                        <a href="#tab_changelog" aria-controls="tab_changelog" role="tab" data-toggle="tab"><?= _l('ams_change_log'); ?></a>
                                    </li>
                                    <li role="presentation" class="<?= $activeTab === 'files' ? 'active' : ''; ?>">
                                        <a href="#tab_files" aria-controls="tab_files" role="tab" data-toggle="tab"><?= _l('ams_photos_files'); ?> <span class="badge"><?= count($files); ?></span></a>
                                    </li>
                                    <?php if ($canMt) { ?>
                                    <li role="presentation" class="<?= $activeTab === 'maintenance' ? 'active' : ''; ?>">
                                        <a href="#tab_maintenance" aria-controls="tab_maintenance" role="tab" data-toggle="tab"><?= _l('ams_maintenance'); ?></a>
                                    </li>
                                    <?php } ?>
                                    <?php if ($canLic) { ?>
                                    <li role="presentation" class="<?= $activeTab === 'licenses' ? 'active' : ''; ?>">
                                        <a href="#tab_licenses" aria-controls="tab_licenses" role="tab" data-toggle="tab"><?= _l('ams_licenses'); ?></a>
                                    </li>
                                    <?php } ?>
                                    <?php if ($canFinance) { ?>
                                    <li role="presentation" class="<?= $activeTab === 'finance' ? 'active' : ''; ?>">
                                        <a href="#tab_finance" aria-controls="tab_finance" role="tab" data-toggle="tab"><?= _l('ams_finance'); ?></a>
                                    </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content tw-mt-4">
                            <!-- Overview -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'overview' ? 'active' : ''; ?>" id="tab_overview">
                                <div class="row">
                                    <div class="col-md-8">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <?= $detail('ams_asset_tag', e($a->asset_tag)); ?>
                                                <?= $detail('ams_asset_name', e($a->name)); ?>
                                                <?= $detail('ams_serial_no', e($a->serial_no)); ?>
                                                <?= $detail('ams_category', e(trim(($a->parent_category_name ? $a->parent_category_name . ' › ' : '') . $a->category_name))); ?>
                                                <?= $detail('ams_brand', e($a->brand_name)); ?>
                                                <?= $detail('ams_model', e(trim($a->model_name . ($a->model_no ? ' (' . $a->model_no . ')' : '')))); ?>
                                                <?= $detail('ams_condition', $a->asset_condition ? e(ams_option_label(ams_condition_options(), $a->asset_condition)) : ''); ?>
                                                <?= $detail('ams_status', ams_status_badge($a->status_name, $a->status_color)); ?>
                                                <?= $detail('ams_assigned_to', ams_assignee_html($a->assigned_type, $a->assigned_id)); ?>
                                                <?php if ($a->assigned_type) { ?>
                                                <?= $detail('ams_assigned_at', e(_dt($a->assigned_at))); ?>
                                                <?= $detail('ams_expected_checkin', e(_d($a->expected_checkin))); ?>
                                                <?php } ?>
                                                <?= $detail('ams_department', e($a->department_name)); ?>
                                                <?= $detail('ams_location', e($a->location_name)); ?>
                                                <?= $detail('ams_source', e(ams_option_label(ams_source_options(), $a->source))); ?>
                                                <?php if ($a->source === 'gift') { ?>
                                                <?= $detail('ams_gifted_by', $giftedBy); ?>
                                                <?php } else { ?>
                                                <?= $detail('ams_supplier', e($a->supplier_name)); ?>
                                                <?= $detail('ams_purchased_by', $a->purchased_by ? ams_assignee_html('staff', $a->purchased_by) : ''); ?>
                                                <?= $detail('ams_purchase_date', e(_d($a->purchase_date))); ?>
                                                <?= $detail('ams_purchase_cost', $a->purchase_cost !== null ? e(app_format_money($a->purchase_cost, $a->currency_name)) : ''); ?>
                                                <?= $detail('ams_invoice_no', e($a->invoice_no)); ?>
                                                <?= $detail('ams_order_no', e($a->order_no)); ?>
                                                <?php if ($a->source === 'lease') { ?>
                                                <?= $detail('ams_lease_end_date', e(_d($a->lease_end_date))); ?>
                                                <?php } ?>
                                                <?php } ?>
                                                <?= $detail('ams_warranty', ams_warranty_html($a->warranty_end) . ($a->warranty_start ? ' <span class="text-muted">(' . _l('ams_from') . ' ' . e(_d($a->warranty_start)) . ')</span>' : '')); ?>
                                                <?= $detail('ams_warranty_provider', e($a->warranty_provider)); ?>
                                                <?= $detail('ams_warranty_notes', nl2br(e($a->warranty_notes))); ?>
                                                <?php foreach ($a->custom_fields as $field) {
                                                    $value = get_custom_field_value($a->id, $field['id'], 'ams_assets');
                                                    echo $detail($field['name'], $value);
                                                } ?>
                                                <?= $detail('ams_notes', nl2br(e($a->notes))); ?>
                                                <?php if ($canMt && $maintenance_cost > 0) { ?>
                                                <?= $detail('ams_mt_total_cost', e(app_format_money($maintenance_cost, get_base_currency()))); ?>
                                                <?php } ?>
                                                <?php if ($canFinance && $depreciation['applicable']) { ?>
                                                <?= $detail('ams_dep_book_value', e(app_format_money($depreciation['book_value'], $a->currency_name))); ?>
                                                <?php } ?>
                                                <?= $detail('ams_last_audit', e(_d($a->last_audit_date))); ?>
                                                <?php if ($disposal) { ?>
                                                <?= $detail('ams_disposed', e(_l('ams_disp_method_' . $disposal->method)) . ' - ' . e(_d($disposal->disposal_date))); ?>
                                                <?php } ?>
                                                <?= $detail('ams_created', e(_dt($a->date_created)) . ($a->created_by ? ' - ' . e(get_staff_full_name($a->created_by)) : '')); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-4">
                                        <?php if ($cover) { ?>
                                        <a href="<?= ams_file_url($cover['id']); ?>" target="_blank">
                                            <img src="<?= ams_file_url($cover['id']); ?>" alt="" class="img-responsive img-thumbnail tw-mb-4" style="width:100%;max-height:280px;object-fit:contain">
                                        </a>
                                        <?php } ?>
                                        <div class="tw-rounded-md tw-border tw-border-solid tw-border-neutral-200 tw-p-4">
                                            <p class="tw-text-neutral-500 tw-mb-1"><?= _l('ams_assigned_to'); ?></p>
                                            <p class="tw-font-medium tw-mb-3"><?= ams_assignee_html($a->assigned_type, $a->assigned_id) ?: '<span class="text-muted">' . _l('ams_not_assigned') . '</span>'; ?></p>
                                            <p class="tw-text-neutral-500 tw-mb-1"><?= _l('ams_location'); ?></p>
                                            <p class="tw-font-medium tw-mb-3"><?= e($a->location_name) ?: '-'; ?></p>
                                            <p class="tw-text-neutral-500 tw-mb-1"><?= _l('ams_warranty'); ?></p>
                                            <p class="tw-font-medium tw-mb-0"><?= ams_warranty_html($a->warranty_end); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- History -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'history' ? 'active' : ''; ?>" id="tab_history">
                                <?php $historyTable = App_table::find('ams_history'); ?>
                                <div class="tw-mb-2 tw-flex tw-justify-end">
                                    <div id="vueApp">
                                        <app-filters id="<?= $historyTable->id(); ?>"
                                            view="<?= $historyTable->viewName(); ?>"
                                            :saved-filters="<?= $historyTable->filtersJs(); ?>"
                                            :available-rules="<?= $historyTable->rulesJs(); ?>">
                                        </app-filters>
                                    </div>
                                </div>
                                <?php render_datatable([
                                    _l('ams_date'),
                                    _l('ams_action'),
                                    _l('ams_status'),
                                    _l('ams_assigned_to'),
                                    _l('ams_location'),
                                    _l('ams_department'),
                                    _l('ams_note'),
                                    _l('ams_done_by'),
                                ], 'ams-history'); ?>
                            </div>

                            <!-- Change log -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'changelog' ? 'active' : ''; ?>" id="tab_changelog">
                                <?php render_datatable([
                                    _l('ams_date'),
                                    _l('ams_action'),
                                    _l('ams_changes'),
                                    _l('ams_done_by'),
                                ], 'ams-audit-log'); ?>
                            </div>

                            <?php if ($canMt) { ?>
                            <!-- Maintenance -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'maintenance' ? 'active' : ''; ?>" id="tab_maintenance">
                                <?php if (staff_can('create', 'ams_maintenance')) { ?>
                                <a href="#" class="btn btn-primary tw-mb-3" onclick="ams_mt_new(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_mt_new'); ?></a>
                                <?php } ?>
                                <?php render_datatable([
                                    '#', _l('ams_asset'), _l('ams_mt_title'), _l('ams_mt_type'), _l('ams_status'),
                                    _l('ams_mt_due_date'), _l('ams_mt_start_date'), _l('ams_mt_end_date'), _l('ams_supplier'), _l('ams_mt_cost'),
                                ], 'ams-asset-maintenance'); ?>
                            </div>
                            <?php } ?>

                            <?php if ($canLic) { ?>
                            <!-- Licences installed on this asset -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'licenses' ? 'active' : ''; ?>" id="tab_licenses">
                                <?php render_datatable([_l('ams_license'), _l('ams_assigned_to'), _l('ams_lic_assigned_at'), _l('ams_note'), _l('ams_lic_released'), _l('ams_done_by')], 'ams-asset-licenses'); ?>
                                <?= form_hidden('ams_asset_id', $a->id); ?>
                            </div>
                            <?php } ?>

                            <?php if ($canFinance) {
                                $this->load->view(AMS_MODULE_NAME . '/assets/_finance_tab', ['a' => $a, 'dep' => $depreciation, 'tco' => $tco, 'disposal' => $disposal, 'active' => $activeTab === 'finance']);
                            } ?>

                            <!-- Files -->
                            <div role="tabpanel" class="tab-pane <?= $activeTab === 'files' ? 'active' : ''; ?>" id="tab_files">
                                <?php if ($canEdit) { ?>
                                <?= form_open_multipart(admin_url('asset_management/assets/upload_files/' . $a->id), ['class' => 'tw-mb-4']); ?>
                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                    <input type="file" name="files[]" multiple class="form-control" style="max-width:420px" required>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload tw-mr-1"></i><?= _l('ams_upload'); ?></button>
                                    <span class="text-muted tw-text-sm"><?= _l('ams_upload_help', get_option('ams_max_upload_mb')); ?></span>
                                </div>
                                <?= form_close(); ?>
                                <?php } ?>

                                <?php if (! $files) { ?>
                                <p class="text-muted"><?= _l('ams_no_files'); ?></p>
                                <?php } else { ?>
                                <div class="row">
                                    <?php foreach ($files as $f) { ?>
                                    <div class="col-xs-6 col-sm-4 col-md-3 col-lg-2 tw-mb-4">
                                        <div class="tw-border tw-border-solid tw-border-neutral-200 tw-rounded-md tw-p-2 tw-h-full">
                                            <a href="<?= ams_file_url($f['id'], ! $f['is_image']); ?>" target="_blank" class="tw-block tw-text-center" style="height:120px">
                                                <?php if ($f['is_image']) { ?>
                                                <img src="<?= ams_file_url($f['id']); ?>" alt="" style="width:100%;height:120px;object-fit:cover" loading="lazy">
                                                <?php } else { ?>
                                                <i class="fa-regular fa-file-lines tw-text-neutral-400" style="font-size:64px;line-height:120px"></i>
                                                <?php } ?>
                                            </a>
                                            <p class="tw-text-sm tw-truncate tw-mt-2 tw-mb-1" title="<?= e($f['original_name']); ?>"><?= e($f['original_name']); ?></p>
                                            <div class="tw-text-sm tw-flex tw-gap-2">
                                                <a href="<?= ams_file_url($f['id'], true); ?>"><?= _l('download'); ?></a>
                                                <?php if ($canEdit) { ?>
                                                <?php if ($f['is_image'] && (int) $f['id'] !== (int) $a->cover_file_id) { ?>
                                                <a href="<?= admin_url('asset_management/assets/set_cover/' . $f['id']); ?>" class="ams-post"><?= _l('ams_set_cover'); ?></a>
                                                <?php } ?>
                                                <a href="<?= admin_url('asset_management/assets/delete_file/' . $f['id']); ?>" class="text-danger _delete"><?= _l('delete'); ?></a>
                                                <?php } ?>
                                            </div>
                                            <?php if ((int) $f['id'] === (int) $a->cover_file_id) { ?>
                                            <span class="label label-info"><?= _l('ams_cover'); ?></span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canCheckout) { ?>
<div class="modal fade" id="ams_checkout_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/assets/checkout/' . $a->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_checkout'); ?> - <?= e($a->asset_tag); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_select('assign_type', ams_assignee_type_options(), ['id', 'name'], 'ams_checkout_to', 'staff', [], [], '', 'ams-assign-type', false); ?>
                <div class="ams-assign ams-assign-staff">
                    <?= render_select('assign_id_staff', $staffOptions, ['id', 'name'], 'ams_assign_type_staff'); ?>
                </div>
                <div class="ams-assign ams-assign-department hide">
                    <?= render_select('assign_id_department', $deptOptions, ['id', 'name'], 'ams_assign_type_department'); ?>
                </div>
                <div class="ams-assign ams-assign-location hide">
                    <?= render_select('assign_id_location', $locations, ['id', 'name'], 'ams_assign_type_location'); ?>
                </div>
                <div class="ams-assign-dept-override">
                    <?= render_select('department_id', $deptOptions, ['id', 'name'], 'ams_department_cost_centre'); ?>
                    <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_department_default_help'); ?></p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <?= render_date_input('expected_checkin', 'ams_expected_checkin'); ?>
                    </div>
                    <div class="col-md-6">
                        <?= render_select('asset_condition', ams_condition_options(), ['id', 'name'], 'ams_condition', $a->asset_condition); ?>
                    </div>
                </div>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-success"><?= _l('ams_checkout'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if ($canCheckin) { ?>
<div class="modal fade" id="ams_checkin_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/assets/checkin/' . $a->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_checkin'); ?> - <?= e($a->asset_tag); ?></h4>
            </div>
            <div class="modal-body">
                <p><?= _l('ams_returning_from'); ?> <strong><?= ams_assignee_html($a->assigned_type, $a->assigned_id); ?></strong></p>
                <?= render_select('location_id', $locations, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_return_to_location'), $a->location_id); ?>
                <?php $checkinStatuses = array_values(array_filter($statuses, fn ($s) => ! in_array($s['type'], ['deployed', 'archived']))); ?>
                <?= render_select('status_id', $checkinStatuses, ['id', 'name'], 'ams_status', ams_get_status_by_key('in_store')['id'] ?? '', [], [], '', '', false); ?>
                <?= render_select('asset_condition', ams_condition_options(), ['id', 'name'], 'ams_condition', $a->asset_condition); ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?= _l('ams_checkin'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if ($canEdit) { ?>
<div class="modal fade" id="ams_status_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/assets/change_status/' . $a->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_change_status'); ?> - <?= e($a->asset_tag); ?></h4>
            </div>
            <div class="modal-body">
                <?php
                $canDispose     = staff_can('dispose', 'ams_assets');
                $statusChoices  = array_values(array_filter($statuses, function ($s) use ($a, $canDispose) {
                    return $s['type'] !== 'deployed' && (int) $s['id'] !== (int) $a->status_id && ($canDispose || $s['type'] !== 'archived');
                }));
                ?>
                <div class="form-group">
                    <label for="ams_status_select" class="control-label"><?= _l('ams_new_status'); ?></label>
                    <select name="status_id" id="ams_status_select" class="selectpicker" data-width="100%" data-live-search="true">
                        <?php foreach ($statusChoices as $s) { ?>
                        <option value="<?= $s['id']; ?>" data-type="<?= e($s['type']); ?>" data-requires-location="<?= (int) $s['requires_location']; ?>" data-requires-note="<?= (int) $s['requires_note']; ?>">
                            <?= e($s['name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <?php if ($a->assigned_type) { ?>
                <p class="text-warning tw-text-sm ams-ends-assignment hide"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i><?= _l('ams_status_ends_assignment'); ?></p>
                <?php } ?>
                <?php if ($canDispose) { ?>
                <p class="text-info tw-text-sm ams-use-dispose hide"><i class="fa-solid fa-circle-info tw-mr-1"></i><?= _l('ams_status_use_dispose'); ?></p>
                <?php } ?>
                <?= render_select('location_id', $locations, ['id', 'name'], 'ams_location', $a->location_id); ?>
                <?= render_select('asset_condition', ams_condition_options(), ['id', 'name'], 'ams_condition', $a->asset_condition); ?>
                <?= render_textarea('note', 'ams_note'); ?>
                <p class="text-muted tw-text-sm ams-note-required hide"><?= _l('ams_note_required_for_status'); ?></p>
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

<?php if ($canDispose) { ?>
<div class="modal fade" id="ams_dispose_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/assets/dispose/' . $a->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_dispose'); ?> - <?= e($a->asset_tag); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6"><?= render_select('method', ams_disposal_method_options(), ['id', 'name'], 'ams_disp_method', 'sold', [], [], '', '', false); ?></div>
                    <div class="col-md-6"><?= render_select('status_id', $archived, ['id', 'name'], 'ams_status', ams_disposal_default_status('sold'), [], [], '', '', false); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><?= render_date_input('disposal_date', 'ams_disp_date', _d(date('Y-m-d'))); ?></div>
                    <div class="col-md-6"><?= render_input('proceeds', _l('ams_disp_proceeds') . ($a->currency_name ? ' (' . e($a->currency_name) . ')' : ''), '', 'number', ['step' => '0.01', 'min' => 0]); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><?= render_input('recipient', 'ams_disp_recipient'); ?></div>
                    <div class="col-md-6"><?= render_input('reference', 'ams_disp_reference'); ?></div>
                </div>
                <?= render_textarea('reason', 'ams_reason'); ?>
                <?php if ($depreciation['applicable'] || $a->purchase_cost !== null) { ?>
                <p class="text-muted tw-text-sm"><?= _l('ams_disp_book_value_hint', e(app_format_money($depreciation['book_value'] ?? 0, $a->currency_name))); ?></p>
                <?php } ?>
                <?php if ($a->assigned_type) { ?>
                <p class="text-warning tw-text-sm"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i><?= _l('ams_status_ends_assignment'); ?></p>
                <?php } ?>
                <p class="text-muted tw-text-sm"><?= _l('ams_disp_side_effects'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-warning"><?= _l('ams_dispose'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if ($canReinstate) { ?>
<div class="modal fade" id="ams_reinstate_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/assets/reinstate/' . $a->id), ['class' => 'ams-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_disp_reinstate'); ?> - <?= e($a->asset_tag); ?></h4>
            </div>
            <div class="modal-body">
                <p><?= _l('ams_disp_reinstate_help'); ?></p>
                <?= render_select('location_id', $locations, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_location'), $a->location_id); ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('ams_disp_reinstate'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php if ($canMt) {
    $this->load->view(AMS_MODULE_NAME . '/maintenance/_modals', ['assets' => [], 'types' => $mt_types, 'suppliers' => $suppliers, 'request' => null, 'fixed_asset' => $a->id]);
} ?>
<?php init_tail(); ?>
<?php if ($canMt) {
    $this->load->view(AMS_MODULE_NAME . '/maintenance/_js');
} ?>
<?php if ($canLic) {
    $this->load->view(AMS_MODULE_NAME . '/licenses/_seat_js');
} ?>
<script>
    $(function() {
        initDataTable('.table-ams-history', admin_url + 'asset_management/assets/history_table/<?= (int) $a->id; ?>', [], [], {}, [0, 'desc']);
        initDataTable('.table-ams-audit-log', admin_url + 'asset_management/assets/audit_table/<?= (int) $a->id; ?>', [2], [2], {}, [0, 'desc']);
        <?php if ($canMt) { ?>
        initDataTable('.table-ams-asset-maintenance', admin_url + 'asset_management/maintenance/table/<?= (int) $a->id; ?>', [], [], {}, [0, 'desc']).column(1).visible(false, false);
        <?php } ?>
        <?php if ($canLic) { ?>
        initDataTable('.table-ams-asset-licenses', admin_url + 'asset_management/licenses/seats_table', [], [], { ams_asset_id: '[name="ams_asset_id"]' }, [2, 'desc']).column(1).visible(false, false);
        <?php } ?>

        <?php if ($canDispose) { ?>
        // Dispose: suggest the archived status that matches the method.
        var amsDisposalStatus = <?= json_encode(array_combine(ams_disposal_methods(), array_map('ams_disposal_default_status', ams_disposal_methods()))); ?>;
        $('#ams_dispose_modal select[name="method"]').on('change', function() {
            $('#ams_dispose_modal select[name="status_id"]').selectpicker('val', amsDisposalStatus[$(this).val()] || '');
        });
        <?php } ?>
        $('.ams-dep-toggle').on('click', function(e) {
            e.preventDefault();
            $('.ams-dep-monthly, .ams-dep-yearly').toggleClass('hide');
        });

        // Check-out: show the picker for the chosen assignee type.
        $('#ams_checkout_modal select[name="assign_type"]').on('change', function() {
            var type = $(this).val();
            var modal = $('#ams_checkout_modal');
            modal.find('.ams-assign').addClass('hide');
            modal.find('.ams-assign-' + type).removeClass('hide');
            modal.find('.ams-assign-dept-override').toggleClass('hide', type === 'department');
        });

        // Change status: hint when the chosen status needs a note or ends the assignment.
        $('#ams_status_select').on('changed.bs.select loaded.bs.select', function() {
            var opt = $(this).find('option:selected');
            $('.ams-note-required').toggleClass('hide', opt.data('requires-note') != 1);
            $('.ams-ends-assignment').toggleClass('hide', ['archived', 'deployable'].indexOf(opt.data('type')) === -1);
            $('.ams-use-dispose').toggleClass('hide', opt.data('type') !== 'archived');
        }).trigger('loaded.bs.select');

        // All action modals post via AJAX and reload on success (Perfex alert shown after reload).
        $('.ams-action-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = form.find('button[type="submit"]');
            btn.prop('disabled', true);
            $.post(form.attr('action'), form.serialize()).done(function(response) {
                response = typeof response === 'string' ? JSON.parse(response) : response;
                if (response.success) {
                    window.location.reload();
                } else {
                    alert_float('danger', response.message);
                    btn.prop('disabled', false);
                }
            }).fail(function(xhr) {
                alert_float('danger', xhr.statusText);
                btn.prop('disabled', false);
            });
        });
    });
</script>
</body>
</html>
