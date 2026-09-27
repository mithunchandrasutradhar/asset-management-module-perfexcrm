<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_settings_asset_tags'); ?></h4>
<p class="text-muted"><?= _l('ams_settings_asset_tags_help'); ?></p>
<div class="row">
    <div class="col-md-3">
        <?= render_input('settings[ams_asset_tag_prefix]', 'ams_setting_tag_prefix', get_option('ams_asset_tag_prefix')); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_asset_tag_separator]', 'ams_setting_tag_separator', get_option('ams_asset_tag_separator')); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_asset_tag_digits]', 'ams_setting_tag_digits', get_option('ams_asset_tag_digits'), 'number', ['min' => 1, 'max' => 10]); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_default_category_code]', 'ams_setting_default_code', get_option('ams_default_category_code')); ?>
    </div>
</div>
<p class="tw-mb-4">
    <?= _l('ams_setting_tag_example'); ?>
    <code><?= e(implode(get_option('ams_asset_tag_separator'), array_filter([get_option('ams_asset_tag_prefix'), 'LAP', str_pad('42', max(1, (int) get_option('ams_asset_tag_digits')), '0', STR_PAD_LEFT)], 'strlen'))); ?></code>
</p>
<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_general'); ?></h4>
<div class="row">
    <div class="col-md-4">
        <?= render_input('settings[ams_warranty_expiring_days]', 'ams_setting_warranty_days', get_option('ams_warranty_expiring_days'), 'number', ['min' => 1]); ?>
    </div>
    <div class="col-md-4">
        <?= render_input('settings[ams_max_upload_mb]', 'ams_setting_max_upload', get_option('ams_max_upload_mb'), 'number', ['min' => 1]); ?>
    </div>
</div>
<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_inventory'); ?></h4>
<div class="row">
    <div class="col-md-4">
        <?= render_input('settings[ams_item_sku_prefix]', 'ams_setting_sku_prefix', get_option('ams_item_sku_prefix')); ?>
    </div>
    <div class="col-md-8">
        <input type="hidden" name="settings[ams_low_stock_notify_staff][]" value="">
        <?= render_select('settings[ams_low_stock_notify_staff][]', array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options()), ['id', 'name'], 'ams_setting_notify_staff', ams_low_stock_recipients(), ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_setting_notify_staff_help'); ?></p>
    </div>
</div>
<?php render_yes_no_option('ams_block_negative_stock', 'ams_setting_block_negative', 'ams_setting_block_negative_help'); ?>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_people'); ?></h4>
<div class="row">
    <div class="col-md-6">
        <?= render_select('settings[ams_acceptance_mode]', [
            ['id' => 'always', 'name' => _l('ams_acceptance_mode_always')],
            ['id' => 'category', 'name' => _l('ams_acceptance_mode_category')],
            ['id' => 'never', 'name' => _l('ams_acceptance_mode_never')],
        ], ['id', 'name'], 'ams_setting_acceptance_mode', get_option('ams_acceptance_mode'), [], [], '', '', false); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_overdue_reminder_days]', 'ams_setting_overdue_days', get_option('ams_overdue_reminder_days'), 'number', ['min' => 1]); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_request_prefix]', 'ams_setting_request_prefix', get_option('ams_request_prefix')); ?>
    </div>
</div>
<?= render_textarea('settings[ams_acceptance_terms]', 'ams_setting_acceptance_terms', get_option('ams_acceptance_terms'), ['rows' => 5]); ?>
<div class="row">
    <div class="col-md-8">
        <input type="hidden" name="settings[ams_manager_notify_staff][]" value="">
        <?= render_select('settings[ams_manager_notify_staff][]', array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options()), ['id', 'name'], 'ams_setting_manager_staff', ams_manager_recipients(), ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_setting_manager_staff_help'); ?></p>
    </div>
</div>
<?php render_yes_no_option('ams_email_notifications', 'ams_setting_email_notifications', 'ams_setting_email_notifications_help'); ?>
<?php render_yes_no_option('ams_block_staff_deactivation', 'ams_setting_block_deactivation', 'ams_setting_block_deactivation_help'); ?>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_mt_lic_po'); ?></h4>
<div class="row">
    <div class="col-md-3"><?= render_input('settings[ams_maintenance_lead_days]', 'ams_setting_mt_lead_days', get_option('ams_maintenance_lead_days'), 'number', ['min' => 0]); ?></div>
    <div class="col-md-3"><?= render_input('settings[ams_license_reminder_days]', 'ams_setting_lic_days', get_option('ams_license_reminder_days'), 'number', ['min' => 1]); ?></div>
    <div class="col-md-3"><?= render_input('settings[ams_po_prefix]', 'ams_setting_po_prefix', get_option('ams_po_prefix')); ?></div>
</div>
<?php render_yes_no_option('ams_po_require_approval', 'ams_setting_po_approval', 'ams_setting_po_approval_help'); ?>
<?= render_textarea('settings[ams_po_terms]', 'ams_setting_po_terms', get_option('ams_po_terms'), ['rows' => 3]); ?>
<p class="text-muted tw-text-sm"><?= _l('ams_setting_managers_note'); ?></p>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_finance_audit'); ?></h4>
<div class="row">
    <div class="col-md-4">
        <?= render_input('settings[ams_declining_factor]', 'ams_setting_declining_factor', get_option('ams_declining_factor'), 'number', ['step' => '0.1', 'min' => '0.5', 'max' => '4']); ?>
        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_setting_declining_factor_help'); ?></p>
    </div>
    <div class="col-md-4"><?= render_input('settings[ams_audit_prefix]', 'ams_setting_audit_prefix', get_option('ams_audit_prefix')); ?></div>
</div>
<p class="text-muted tw-text-sm"><?= _l('ams_setting_dep_note'); ?></p>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_settings_labels'); ?></h4>
<div class="row">
    <div class="col-md-3"><?= render_input('settings[ams_label_width]', 'ams_setting_label_width', get_option('ams_label_width'), 'number', ['min' => 20, 'max' => 150]); ?></div>
    <div class="col-md-3"><?= render_input('settings[ams_label_height]', 'ams_setting_label_height', get_option('ams_label_height'), 'number', ['min' => 10, 'max' => 150]); ?></div>
    <div class="col-md-3"><?= render_select('settings[ams_label_layout]', ams_label_layout_options(), ['id', 'name'], 'ams_setting_label_layout', get_option('ams_label_layout'), [], [], '', '', false); ?></div>
    <div class="col-md-3"><?= render_select('settings[ams_label_code]', ams_label_code_options(), ['id', 'name'], 'ams_setting_label_code', get_option('ams_label_code'), [], [], '', '', false); ?></div>
</div>
<div class="row">
    <div class="col-md-6">
        <?= render_select('settings[ams_label_qr_content]', [['id' => 'url', 'name' => _l('ams_label_qr_url')], ['id' => 'tag', 'name' => _l('ams_label_qr_tag')]], ['id', 'name'], 'ams_setting_label_qr_content', get_option('ams_label_qr_content'), [], [], '', '', false); ?>
    </div>
    <div class="col-md-6"><?= render_input('settings[ams_label_company_text]', 'ams_setting_label_company_text', get_option('ams_label_company_text'), 'text', ['placeholder' => get_option('companyname')]); ?></div>
</div>
<?php render_yes_no_option('ams_label_show_company', 'ams_setting_label_show_company'); ?>
<?php render_yes_no_option('ams_label_show_name', 'ams_setting_label_show_name'); ?>
<?php render_yes_no_option('ams_label_show_serial', 'ams_setting_label_show_serial'); ?>
<?php render_yes_no_option('ams_label_show_logo', 'ams_setting_label_show_logo'); ?>
<p class="text-muted tw-text-sm"><?= _l('ams_setting_labels_help'); ?></p>

<?php if (is_admin()) { ?>
<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_legacy_import'); ?></h4>
<p class="text-muted"><?= _l('ams_legacy_import_settings_help'); ?></p>
<a href="<?= admin_url('asset_management/legacy_import'); ?>" class="btn btn-default">
    <i class="fa-solid fa-file-import tw-mr-1"></i><?= _l('ams_legacy_import_open'); ?>
</a>
<?php if ($date = get_option('ams_legacy_import_date')) { ?>
<span class="text-muted tw-ml-2"><?= _l('ams_legacy_import_last', e(_dt($date))); ?></span>
<?php } ?>
<?php } ?>
