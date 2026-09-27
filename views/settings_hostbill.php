<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options());
$alertStaff   = json_decode((string) get_option('ams_hb_alert_staff'), true) ?: [];
$hasKey       = get_option('ams_hb_api_key') !== '';
$lastSync     = get_option('ams_hb_last_sync');
?>
<div class="alert alert-info tw-mb-4"><?= _l('ams_hb_settings_intro'); ?></div>

<?php if ($lastSync) { ?>
<p class="tw-mb-4">
    <?= _l('ams_hb_last_sync'); ?>: <strong><?= e(_dt($lastSync)); ?></strong>
    <?= get_option('ams_hb_last_sync_status') === 'ok'
        ? '<span class="label label-success">' . _l('ams_hb_ok') . '</span>'
        : '<span class="label label-danger">' . _l('ams_hb_error') . '</span>'; ?>
    <span class="text-muted tw-ml-1"><?= e(get_option('ams_hb_last_sync_message')); ?></span>
</p>
<?php } ?>

<h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_hb_connection'); ?></h4>
<?php render_yes_no_option('ams_hb_enabled', 'ams_hb_enable_integration'); ?>
<div class="row">
    <div class="col-md-6">
        <?= render_input('settings[ams_hb_url]', 'ams_hb_url', get_option('ams_hb_url'), 'text', ['placeholder' => 'https://billing.example.com']); ?>
        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_hb_url_help'); ?></p>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_api_id]', 'ams_hb_api_id', get_option('ams_hb_api_id'), 'text', ['autocomplete' => 'off']); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_api_key]', 'ams_hb_api_key', '', 'password', ['autocomplete' => 'new-password', 'placeholder' => $hasKey ? _l('ams_hb_api_key_saved') : '']); ?>
    </div>
    <div class="col-md-3">
        <?= render_input('settings[ams_hb_timeout]', 'ams_hb_timeout', get_option('ams_hb_timeout'), 'number', ['min' => 5, 'max' => 120]); ?>
    </div>
    <div class="col-md-5">
        <?php render_yes_no_option('ams_hb_verify_ssl', 'ams_hb_verify_ssl'); ?>
    </div>
    <div class="col-md-4 tw-pt-6">
        <button type="button" class="btn btn-default" id="ams-hb-test" data-loading-text="<?= _l('wait_text'); ?>">
            <i class="fa-solid fa-plug tw-mr-1"></i><?= _l('ams_hb_test_connection'); ?>
        </button>
    </div>
</div>
<p class="text-muted tw-text-sm"><?= _l('ams_hb_api_help'); ?></p>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_order_sync'); ?></h4>
<?php render_yes_no_option('ams_hb_sync_enabled', 'ams_hb_sync_enabled', 'ams_hb_sync_enabled_help'); ?>
<div class="row">
    <div class="col-md-4"><?= render_input('settings[ams_hb_sync_interval]', 'ams_hb_sync_interval', get_option('ams_hb_sync_interval'), 'number', ['min' => 1]); ?></div>
    <div class="col-md-4"><?= render_input('settings[ams_hb_lookback_days]', 'ams_hb_lookback_days', get_option('ams_hb_lookback_days'), 'number', ['min' => 1]); ?></div>
    <div class="col-md-4"><?= render_input('settings[ams_hb_max_pages]', 'ams_hb_max_pages', get_option('ams_hb_max_pages'), 'number', ['min' => 1]); ?></div>
</div>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_stock_rules'); ?></h4>
<div class="row">
    <div class="col-md-6">
        <?= render_select('settings[ams_hb_deduct_on]', [
            ['id' => 'paid', 'name' => _l('ams_hb_deduct_on_paid')],
            ['id' => 'active', 'name' => _l('ams_hb_deduct_on_active')],
            ['id' => 'paid_or_active', 'name' => _l('ams_hb_deduct_on_paid_or_active')],
        ], ['id', 'name'], 'ams_hb_deduct_on', get_option('ams_hb_deduct_on'), [], [], '', '', false); ?>
    </div>
    <div class="col-md-6">
        <?= render_select('settings[ams_hb_sales_location_id]', ams_location_options(true), ['id', 'name'], 'ams_hb_sales_location', get_option('ams_hb_sales_location_id')); ?>
        <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_hb_sales_location_help'); ?></p>
    </div>
</div>
<?php render_yes_no_option('ams_hb_reserve_on_pending', 'ams_hb_reserve_on_pending', 'ams_hb_reserve_on_pending_help'); ?>
<?php render_yes_no_option('ams_hb_release_on_refund', 'ams_hb_release_on_refund'); ?>
<?php render_yes_no_option('ams_hb_restock_on_cancel', 'ams_hb_restock_on_cancel', 'ams_hb_restock_on_cancel_help'); ?>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_stock_push'); ?></h4>
<?php render_yes_no_option('ams_hb_push_enabled', 'ams_hb_push_enabled', 'ams_hb_push_enabled_help'); ?>
<?php render_yes_no_option('ams_hb_push_immediately', 'ams_hb_push_immediately', 'ams_hb_push_immediately_help'); ?>
<div class="row">
    <div class="col-md-4">
        <?= render_input('settings[ams_hb_stock_buffer]', 'ams_hb_stock_buffer', get_option('ams_hb_stock_buffer'), 'number', ['min' => 0]); ?>
    </div>
    <div class="col-md-8 tw-pt-6 text-muted tw-text-sm"><?= _l('ams_hb_stock_buffer_help'); ?></div>
</div>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_webhook'); ?></h4>
<?php render_yes_no_option('ams_hb_webhook_enabled', 'ams_hb_webhook_enabled', 'ams_hb_webhook_enabled_help'); ?>
<div class="form-group">
    <label class="control-label"><?= _l('ams_hb_webhook_url'); ?></label>
    <div class="input-group">
        <input type="text" class="form-control" readonly value="<?= e(ams_hb_webhook_url()); ?>&amp;order_id={order_id}" id="ams-hb-webhook-url">
        <span class="input-group-btn">
            <a href="<?= admin_url('asset_management/hostbill/new_webhook_secret'); ?>" class="btn btn-default _delete" data-toggle="tooltip" title="<?= _l('ams_hb_regenerate_secret'); ?>"><i class="fa-solid fa-rotate"></i></a>
        </span>
    </div>
    <p class="text-muted tw-text-sm tw-mt-1"><?= _l('ams_hb_webhook_help'); ?></p>
</div>
<?= render_input('settings[ams_hb_webhook_ips]', 'ams_hb_webhook_ips', get_option('ams_hb_webhook_ips'), 'text', ['placeholder' => '203.0.113.10, 203.0.113.11']); ?>

<hr class="hr-panel-separator" />
<h4 class="tw-font-semibold tw-text-lg"><?= _l('ams_hb_alerts_logs'); ?></h4>
<div class="row">
    <div class="col-md-8">
        <input type="hidden" name="settings[ams_hb_alert_staff][]" value="">
        <?= render_select('settings[ams_hb_alert_staff][]', $staffOptions, ['id', 'name'], 'ams_hb_alert_staff', $alertStaff, ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
    </div>
    <div class="col-md-4">
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
    });
</script>
