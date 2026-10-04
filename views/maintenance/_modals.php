<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Shared maintenance modals (maintenance list, job page, asset profile tab, My Assets).
// $fixed_asset: asset id when opened from an asset page (asset select hidden).
// $responsible: responsible_options() for the job form.
$request     = $request ?? null;
$fixed_asset = $fixed_asset ?? null;
$assets      = $assets ?? [];
?>
<?php if (staff_can('create', 'ams_maintenance') || staff_can('edit', 'ams_maintenance')) { ?>
<div class="modal fade" id="ams_mt_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/maintenance/save'), ['id' => 'ams-mt-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_maintenance'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <input type="hidden" name="request_id" value="<?= $request ? (int) $request->id : ''; ?>">
                <?php if ($fixed_asset) { ?>
                <input type="hidden" name="asset_id" value="<?= (int) $fixed_asset; ?>">
                <?php } else { ?>
                <div class="ams-mt-asset">
                    <?= render_select('asset_id', $assets, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_asset'), $request ? $request->asset_id : ''); ?>
                </div>
                <?php } ?>
                <?= render_input('title', '<small class="req text-danger">* </small>' . _l('ams_mt_title'), $request ? $request->subject : ''); ?>
                <div class="row">
                    <div class="col-md-6"><?= render_select('type', $types, ['id', 'name'], 'ams_mt_type', 'repair', [], [], '', '', false); ?></div>
                    <div class="col-md-6"><?= render_date_input('due_date', 'ams_mt_due_date'); ?></div>
                </div>
                <?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_responsible_select', ['options' => $responsible, 'select_id' => 'ams_mt_responsible']); ?>
                <?= render_select('supplier_id', $suppliers, ['id', 'name'], 'ams_mt_vendor'); ?>
                <?= render_textarea('notes', 'ams_notes', $request ? (string) $request->description : ''); ?>
                <div class="ams-mt-new-only">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="start_now" id="mt_start_now" value="1">
                        <label for="mt_start_now"><?= _l('ams_mt_start_now'); ?></label>
                    </div>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="set_asset_status" id="mt_set_status_new" value="1" checked>
                        <label for="mt_set_status_new"><?= _l('ams_mt_set_asset_status'); ?></label>
                    </div>
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

<?php // Start / complete: editors and the responsible staff (checked on the server per job). ?>
<div class="modal fade" id="ams_mt_start_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <?= form_open('', ['class' => 'ams-mt-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_mt_start'); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_date_input('start_date', 'ams_mt_start_date', _d(date('Y-m-d'))); ?>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="set_asset_status" id="mt_set_status" value="1" checked>
                    <label for="mt_set_status"><?= _l('ams_mt_set_asset_status'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-info"><?= _l('ams_mt_start'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<div class="modal fade" id="ams_mt_complete_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open('', ['class' => 'ams-mt-action-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_mt_complete') . ams_help_icon(_l('ams_mt_complete_help')); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6"><?= render_date_input('end_date', 'ams_mt_end_date', _d(date('Y-m-d'))); ?></div>
                    <div class="col-md-6"><?= render_input('cost', _l('ams_mt_cost') . ' (' . e(get_base_currency()->name) . ')', '', 'number', ['step' => '0.01', 'min' => '0']); ?></div>
                    <div class="col-md-6"><?= render_input('downtime_hours', 'ams_mt_downtime', '', 'number', ['step' => '0.5', 'min' => '0']); ?></div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ams_mt_check_result" class="control-label"><span class="ams-mt-check-req req text-danger hide">* </span><?= _l('ams_mt_check_result') . ams_help_icon(_l('ams_mt_check_result_help')); ?></label>
                            <select name="check_result" id="ams_mt_check_result" class="selectpicker" data-width="100%" data-none-selected-text="<?= e(_l('dropdown_non_selected_tex')); ?>">
                                <option value=""></option>
                                <?php foreach (['working', 'partial', 'not_working'] as $c) { ?>
                                <option value="<?= $c; ?>"><?= _l('ams_mt_check_' . $c); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="ams-mt-not-working hide alert alert-warning tw-mb-3">
                    <div class="checkbox checkbox-primary tw-mt-0">
                        <input type="checkbox" name="set_damaged" id="ams_mt_set_damaged" value="1" checked>
                        <label for="ams_mt_set_damaged"><?= _l('ams_mt_set_damaged'); ?></label>
                    </div>
                    <div class="checkbox checkbox-primary tw-mb-0">
                        <input type="checkbox" name="create_followup" id="ams_mt_create_followup" value="1" checked>
                        <label for="ams_mt_create_followup"><?= _l('ams_mt_create_followup') . ams_help_icon(_l('ams_mt_create_followup_help')); ?></label>
                    </div>
                </div>
                <?= render_textarea('resolution', 'ams_mt_resolution'); ?>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success"><?= _l('ams_mt_complete'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
