<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Integration status strip shown on every HostBill page.
$enabled    = get_option('ams_hb_enabled') == '1';
$configured = get_option('ams_hb_url') !== '' && get_option('ams_hb_api_id') !== '' && get_option('ams_hb_api_key') !== '';
$last       = get_option('ams_hb_last_sync');
?>
<?php if (! $enabled || ! $configured) { ?>
<div class="alert alert-warning">
    <?= _l(! $configured ? 'ams_hb_not_configured' : 'ams_hb_not_enabled'); ?>
    <?php if (is_admin() || staff_can('edit', 'ams_settings')) { ?>
    <a href="<?= admin_url('settings?group=ams_hostbill'); ?>" class="alert-link tw-ml-1"><?= _l('ams_hb_open_settings'); ?></a>
    <?php } ?>
</div>
<?php } elseif ($last) { ?>
<p class="text-muted tw-mb-3">
    <?= _l('ams_hb_last_sync'); ?>: <?= e(_dt($last)); ?>
    <?= get_option('ams_hb_last_sync_status') === 'ok' ? '<span class="label label-success">' . _l('ams_hb_ok') . '</span>' : '<span class="label label-danger">' . _l('ams_hb_error') . '</span>'; ?>
    <span class="tw-ml-1"><?= e(get_option('ams_hb_last_sync_message')); ?></span>
</p>
<?php } ?>
