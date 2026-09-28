<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Shared header of Assets → HostBill Inventory: title, Refresh now, tabs, status line. $tab = inventory | log.
$helpParts = [_l('ams_hb_inventory_help')];
?>
<div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
    <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('ams_hb_inventory') . ams_help_icon(implode(' ', $helpParts)); ?></h4>
    <?php if (staff_can('sync', 'ams_hostbill')) { ?>
    <button type="button" class="btn btn-primary ams-hb-post" data-url="<?= admin_url('asset_management/hostbill/refresh'); ?>">
        <i class="fa-solid fa-rotate tw-mr-1"></i><?= _l('ams_hb_refresh_now'); ?>
    </button>
    <?php } ?>
</div>
<div class="horizontal-scrollable-tabs tw-mb-4">
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <li role="presentation" class="<?= $tab === 'inventory' ? 'active' : ''; ?>"><a href="<?= admin_url('asset_management/hostbill'); ?>"><?= _l('ams_hb_tab_inventory'); ?></a></li>
            <li role="presentation" class="<?= $tab === 'log' ? 'active' : ''; ?>"><a href="<?= admin_url('asset_management/hostbill/log'); ?>"><?= _l('ams_hb_sync_log'); ?></a></li>
        </ul>
    </div>
</div>
<?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_status_bar'); ?>
