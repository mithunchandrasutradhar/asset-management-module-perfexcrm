<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$done   = ! empty($job['done']);
$type   = $job['type'];
$badge  = ['create' => 'success', 'update' => 'info', 'unchanged' => 'default', 'skip' => 'default', 'error' => 'danger'];
$counts = $result['counts'] ?? null;
$ready  = $counts && $action === 'preview' && ($counts['create'] + $counts['update']) > 0;
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                <a href="<?= admin_url('asset_management/import'); ?>" class="tw-text-neutral-500"><?= _l('ams_import'); ?></a> ›
                <?= e($job['file']); ?> <span class="text-muted tw-font-normal">(<?= _l('ams_import_rows', (int) $total); ?>)</span>
            </h4>
            <a href="<?= admin_url('asset_management/import/cancel'); ?>" class="btn btn-default"><?= $done ? _l('ams_import_new') : _l('cancel'); ?></a>
        </div>

        <?php if ($counts) { ?>
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-base">
                    <?= $action === 'import' ? _l('ams_import_result_title') : _l('ams_import_preview_title'); ?>
                </h4>
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-mb-3">
                    <?php foreach ($counts as $k => $n) { ?>
                    <span class="label label-<?= $badge[$k]; ?> tw-text-sm"><?= _l('ams_import_status_' . $k); ?>: <?= (int) $n; ?></span>
                    <?php } ?>
                </div>
                <?php if ($action === 'preview') { ?>
                <p class="text-muted tw-text-sm"><?= _l('ams_import_preview_help'); ?></p>
                <?php } ?>
                <table class="table dt-table table-condensed" data-order-col="0" data-order-type="asc">
                    <thead><tr><th><?= _l('ams_import_row'); ?></th><th><?= _l('ams_status'); ?></th><th><?= _l('ams_import_reference'); ?></th><th><?= _l('ams_import_message'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($result['results'] as $r) { ?>
                        <tr>
                            <td data-order="<?= (int) $r['row']; ?>"><?= (int) $r['row']; ?></td>
                            <td><span class="label label-<?= $badge[$r['status']]; ?>"><?= _l('ams_import_status_' . $r['status']); ?></span></td>
                            <td><?= e($r['ref']); ?></td>
                            <td><?= nl2br(e(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $r['message'])), ENT_QUOTES))); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <a href="<?= admin_url('asset_management/import/report'); ?>" class="btn btn-default btn-sm"><i class="fa-solid fa-download tw-mr-1"></i><?= _l('ams_import_download_report'); ?></a>
                <?php if ($counts['error']) { ?>
                <a href="<?= admin_url('asset_management/import/report?errors=1'); ?>" class="btn btn-default btn-sm"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i><?= _l('ams_import_download_errors'); ?></a>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if (! $done) { ?>
        <?= form_open(admin_url('asset_management/import/map'), ['id' => 'ams-import-map']); ?>
        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_import_mapping'); ?></h4>
                        <p class="text-muted tw-text-sm"><?= _l('ams_import_mapping_help'); ?></p>
                        <table class="table table-condensed tw-mb-0">
                            <thead><tr><th><?= _l('ams_import_field'); ?></th><th><?= _l('ams_import_column'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($fields as $key => $def) { ?>
                                <tr>
                                    <td class="tw-align-middle"><?= ! empty($def['required']) ? '<small class="req text-danger">* </small>' : ''; ?><?= _l($def['label']); ?> <span class="text-muted tw-text-xs"><?= e($key); ?></span></td>
                                    <td>
                                        <select name="map[<?= e($key); ?>]" class="form-control input-sm">
                                            <option value=""><?= _l('ams_import_not_imported'); ?></option>
                                            <?php foreach ($headers as $i => $h) { ?>
                                            <option value="<?= (int) $i; ?>" <?= isset($map[$key]) && (int) $map[$key] === (int) $i ? 'selected' : ''; ?>><?= e($h !== '' ? $h : _l('ams_import_column_n', $i + 1)); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_import_options'); ?></h4>
                        <div class="form-group">
                            <label class="control-label"><?= _l('ams_import_existing'); ?></label>
                            <div class="radio radio-primary"><input type="radio" name="mode" id="ams_mode_skip" value="skip" <?= $options['mode'] !== 'update' ? 'checked' : ''; ?>><label for="ams_mode_skip"><?= _l('ams_import_mode_skip'); ?></label></div>
                            <div class="radio radio-primary"><input type="radio" name="mode" id="ams_mode_update" value="update" <?= $options['mode'] === 'update' ? 'checked' : ''; ?>><label for="ams_mode_update"><?= _l('ams_import_mode_update_' . $type); ?></label></div>
                        </div>
                        <?php if ($type !== 'suppliers') { ?>
                        <div class="form-group">
                            <label class="control-label" for="date_format"><?= _l('ams_import_date_format'); ?></label>
                            <select name="date_format" id="date_format" class="form-control">
                                <?php foreach (['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'd-m-Y' => 'DD-MM-YYYY', 'd.m.Y' => 'DD.MM.YYYY', 'Y/m/d' => 'YYYY/MM/DD'] as $f => $l) { ?>
                                <option value="<?= $f; ?>" <?= $options['date_format'] === $f ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php } ?>
                            </select>
                            <p class="text-muted tw-text-sm tw-mt-1"><?= _l('ams_import_date_help'); ?></p>
                        </div>
                        <?php if (staff_can('create', 'ams_setup')) { ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="create_missing" id="ams_create_missing" value="1" <?= $options['create_missing'] ? 'checked' : ''; ?>>
                            <label for="ams_create_missing"><?= _l('ams_import_create_missing'); ?></label>
                        </div>
                        <?php } ?>
                        <?php } ?>
                        <?php if ($type === 'items') { ?>
                        <?= render_select('default_kind', ams_item_kind_options(), ['id', 'name'], 'ams_import_default_kind', $options['default_kind'], [], [], '', '', false); ?>
                        <?php } ?>
                        <?php if ($type === 'assets') { ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="notify" id="ams_notify" value="1" <?= $options['notify'] ? 'checked' : ''; ?>>
                            <label for="ams_notify"><?= _l('ams_import_notify'); ?></label>
                        </div>
                        <?php } ?>
                        <hr />
                        <button type="submit" name="action" value="preview" class="btn btn-default"><i class="fa-solid fa-magnifying-glass tw-mr-1"></i><?= _l('ams_import_preview'); ?></button>
                        <?php if ($ready) { ?>
                        <button type="submit" name="action" value="import" class="btn btn-primary" onclick="return confirm('<?= e(_l('ams_import_confirm', (int) ($counts['create'] + $counts['update']))); ?>');">
                            <i class="fa-solid fa-file-import tw-mr-1"></i><?= _l('ams_import_run', (int) ($counts['create'] + $counts['update'])); ?>
                        </button>
                        <?php } else { ?>
                        <p class="text-muted tw-text-sm tw-mt-2 tw-mb-0"><?= _l('ams_import_preview_first'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?= form_close(); ?>

        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_import_first_rows'); ?></h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed tw-mb-0">
                        <thead><tr><?php foreach ($headers as $h) { ?><th><?= e($h); ?></th><?php } ?></tr></thead>
                        <tbody>
                            <?php foreach ($sample as $r) { ?>
                            <tr><?php foreach ($headers as $i => $h) { ?><td><?= e($r[$i] ?? ''); ?></td><?php } ?></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php init_tail(); ?>
<script>
    // Changing the mapping or options invalidates the preview: hide the Import button until previewed again.
    $('#ams-import-map').on('change', 'select, input', function() {
        $(this).closest('form').find('button[value="import"]').remove();
    });
</script>
</body>
</html>
