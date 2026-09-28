<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_import') . ams_help_icon(_l('ams_import_intro') . ' 1. ' . _l('ams_import_step1', [(int) $max, (int) $maxMb]) . ' 2. ' . _l('ams_import_step2') . ' 3. ' . _l('ams_import_step3') . ' 4. ' . _l('ams_import_step4')); ?></h4>
                <div class="panel_s">
                    <div class="panel-body">
                        <?= form_open_multipart(admin_url('asset_management/import/upload')); ?>
                        <div class="row">
                            <div class="col-md-5">
                                <?= render_select('type', array_map(fn ($k, $v) => ['id' => $k, 'name' => _l($v)], array_keys($types), $types), ['id', 'name'], 'ams_import_what', array_key_first($types), [], [], '', '', false); ?>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label class="control-label" for="ams_import_file"><?= _l('ams_import_file') . ams_help_icon(_l('ams_import_step1', [(int) $max, (int) $maxMb]) . ' ' . _l('ams_import_tips')); ?></label>
                                    <input type="file" name="file" id="ams_import_file" class="form-control" accept=".csv,.xlsx,.txt" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload tw-mr-1"></i><?= _l('ams_import_upload'); ?></button>
                        <?= form_close(); ?>
                        <hr />
                        <p class="tw-mb-1 tw-font-medium"><?= _l('ams_import_templates'); ?></p>
                        <?php foreach ($types as $k => $label) { ?>
                        <a href="<?= admin_url('asset_management/import/sample/' . $k); ?>" class="btn btn-default btn-sm tw-mr-1"><i class="fa-solid fa-file-csv tw-mr-1"></i><?= _l($label); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
