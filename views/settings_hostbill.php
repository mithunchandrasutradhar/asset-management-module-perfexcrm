<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options());
$alertStaff   = json_decode((string) get_option('ams_hb_alert_staff'), true) ?: [];
$hasKey       = get_option('ams_hb_api_key') !== '';
$lastSync     = get_option('ams_hb_last_sync');
?>
<?php if ($lastSync) { ?>
<p class="tw-mb-4">
    <?= _l('ams_hb_last_sync'); ?>: <strong><?= e(_dt($lastSync)); ?></strong>
    <?= get_option('ams_hb_last_sync_status') === 'ok'
        ? '<span class="label label-success">' . _l('ams_hb_ok') . '</span>'
        : '<span class="label label-danger">' . _l('ams_hb_error') . '</span>'; ?>
    <span class="text-muted tw-ml-1"><?= e(get_option('ams_hb_last_sync_message')); ?></span>
</p>
<?php } ?>

<h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_hb_connection') . ams_help_icon(_l('ams_hb_settings_intro') . ' ' . _l('ams_hb_api_help')); ?></h4>
<div class="row">
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_enabled', 'ams_hb_enable_integration'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_verify_ssl', 'ams_hb_verify_ssl'); ?></div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_timeout]', 'ams_hb_timeout', get_option('ams_hb_timeout'), 'number', ['min' => 5, 'max' => 120]); ?>
    </div>
    <div class="col-md-3 tw-pt-6">
        <button type="button" class="btn btn-default" id="ams-hb-test" data-loading-text="<?= _l('wait_text'); ?>">
            <i class="fa-solid fa-plug tw-mr-1"></i><?= _l('ams_hb_test_connection'); ?>
        </button>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <?= render_input('settings[ams_hb_url]', ams_label_help('ams_hb_url', _l('ams_hb_url_help')), get_option('ams_hb_url'), 'text', ['placeholder' => 'https://billing.example.com']); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_api_id]', 'ams_hb_api_id', get_option('ams_hb_api_id'), 'text', ['autocomplete' => 'off']); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_api_key]', 'ams_hb_api_key', '', 'password', ['autocomplete' => 'new-password', 'placeholder' => $hasKey ? _l('ams_hb_api_key_saved') : '']); ?>
    </div>
</div>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_inventory_settings'); ?></h4>
<div class="row">
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_sync_enabled', 'ams_hb_auto_refresh', 'ams_hb_auto_refresh_help'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_include_hidden', 'ams_hb_include_hidden', 'ams_hb_include_hidden_help'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_allow_override', 'ams_hb_allow_override', 'ams_hb_allow_override_help'); ?></div>
</div>
<div class="row">
    <div class="col-md-6">
        <?= render_select('settings[ams_hb_product_scope]', [
            ['id' => 'tracked', 'name' => _l('ams_hb_scope_tracked')],
            ['id' => 'all', 'name' => _l('ams_hb_scope_all')],
        ], ['id', 'name'], ams_label_help('ams_hb_product_scope', _l('ams_hb_product_scope_help')), get_option('ams_hb_product_scope'), [], [], '', '', false); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_sync_interval]', ams_label_help('ams_hb_refresh_interval', _l('ams_hb_refresh_interval_help')), get_option('ams_hb_sync_interval'), 'number', ['min' => 5]); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_default_low_level]', ams_label_help('ams_hb_default_low_level', _l('ams_hb_default_low_level_help')), get_option('ams_hb_default_low_level'), 'number', ['min' => 0, 'step' => '0.01']); ?>
    </div>
</div>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_alerts') . ams_help_icon(_l('ams_hb_alerts_help')); ?></h4>
<div class="row">
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_alert_low', 'ams_hb_alert_low'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_alert_out', 'ams_hb_alert_out'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_alert_sync_fail', 'ams_hb_alert_sync_fail', 'ams_hb_alert_sync_fail_help'); ?></div>
    <div class="col-md-3"><?php ams_yes_no_option('ams_hb_alert_email', 'ams_hb_alert_email', 'ams_hb_alert_email_help'); ?></div>
</div>
<div class="row">
    <div class="col-md-6">
        <?= render_select('settings[ams_hb_alert_recipients]', [
            ['id' => 'hostbill', 'name' => _l('ams_hb_recipients_own')],
            ['id' => 'stock', 'name' => _l('ams_hb_recipients_stock')],
        ], ['id', 'name'], 'ams_hb_alert_recipients', get_option('ams_hb_alert_recipients'), [], [], '', '', false); ?>
    </div>
    <div class="col-md-6 ams-hb-own-staff">
        <input type="hidden" name="settings[ams_hb_alert_staff][]" value="">
        <?= render_select('settings[ams_hb_alert_staff][]', $staffOptions, ['id', 'name'], 'ams_hb_alert_staff', $alertStaff, ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
    </div>
</div>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_sync_log'); ?></h4>
<div class="row">
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_log_retention_days]', 'ams_hb_log_retention_days', get_option('ams_hb_log_retention_days'), 'number', ['min' => 1]); ?>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        $('#ams-hb-test').on('click', function() {
            var btn = $(this);
            btn.button('loading');
            $.post(admin_url + 'asset_management/hostbill/test_connection').done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                alert_float(r.success ? 'success' : 'danger', r.message);
            }).always(function() {
                btn.button('reset');
            });
        });

        // The staff list only applies when HostBill alerts use their own recipients.
        var recipients = $('select[name="settings[ams_hb_alert_recipients]"]');
        var toggle = function() {
            $('.ams-hb-own-staff').toggleClass('hide', recipients.val() !== 'hostbill');
        };
        recipients.on('change', toggle);
        toggle();
    });
</script>
