<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><i class="fa-solid fa-qrcode tw-mr-1"></i><?= _l('ams_scan'); ?></h4>
                <?php if ($scan_audit) { ?>
                <div class="alert alert-warning">
                    <?= _l('ams_scan_into_audit', '<a href="' . admin_url('asset_management/audits/view/' . $scan_audit->id) . '">' . e($scan_audit->audit_no . ' - ' . $scan_audit->title) . '</a>'); ?>
                </div>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <form method="get" action="<?= admin_url('asset_management/scan'); ?>" autocomplete="off">
                            <label class="control-label" for="ams_code"><?= _l('ams_scan_code_label'); ?></label>
                            <div class="input-group">
                                <input type="text" name="code" id="ams_code" class="form-control input-lg" autofocus placeholder="<?= e(_l('ams_audit_scan_placeholder')); ?>">
                                <span class="input-group-btn"><button type="submit" class="btn btn-primary btn-lg"><?= _l('ams_scan_open'); ?></button></span>
                            </div>
                        </form>
                        <p class="text-muted tw-text-sm tw-mt-3 tw-mb-0"><?= _l('ams_scan_help'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
