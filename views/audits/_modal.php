<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="ams_audit_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/audits/save'), ['id' => 'ams-audit-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_audit'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <?= render_input('title', '<small class="req text-danger">* </small>' . _l('ams_audit_title'), '', 'text', ['placeholder' => _l('ams_audit_title_placeholder')]); ?>
                <div class="ams-audit-scope">
                    <p class="text-muted tw-text-sm"><?= _l('ams_audit_scope_help'); ?></p>
                    <?= render_select('location_id', $locations, ['id', 'name'], 'ams_location'); ?>
                    <div class="row">
                        <div class="col-md-6"><?= render_select('department_id', $departments, ['id', 'name'], 'ams_department'); ?></div>
                        <div class="col-md-6"><?= render_select('category_id', $categories, ['id', 'name'], 'ams_category'); ?></div>
                    </div>
                </div>
                <p class="text-muted tw-text-sm ams-audit-scope-locked hide"><?= _l('ams_audit_scope_locked'); ?></p>
                <div class="row">
                    <div class="col-md-6"><?= render_date_input('due_date', 'ams_audit_due_date'); ?></div>
                    <div class="col-md-6"><?= render_date_input('next_audit_date', 'ams_audit_next_date'); ?></div>
                </div>
                <?= render_textarea('notes', 'ams_notes'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
