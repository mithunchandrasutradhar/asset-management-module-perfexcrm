<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$counts = ['new' => 0, 'code_added' => 0, 'exists' => 0];
foreach ($rows as $row) {
    $counts[$row['state']]++;
}
$badge = [
    'new'        => '<span class="label label-success">' . _l('ams_legacy_state_new') . '</span>',
    'code_added' => '<span class="label label-info">' . _l('ams_legacy_state_code') . '</span>',
    'exists'     => '<span class="label label-default">' . _l('ams_legacy_state_exists') . '</span>',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_legacy_import'); ?></h4>

                <div class="panel_s">
                    <div class="panel-body">
                        <p><?= _l('ams_legacy_import_intro'); ?></p>
                        <ul class="tw-mb-4">
                            <li><?= _l('ams_legacy_import_includes'); ?></li>
                            <li><?= _l('ams_legacy_import_excludes'); ?></li>
                        </ul>

                        <?php if ($imported_at) { ?>
                        <div class="alert alert-info"><?= _l('ams_legacy_import_last', e(_dt($imported_at))); ?></div>
                        <?php } ?>

                        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-3 tw-mb-4">
                            <?= $badge['new']; ?> <?= (int) $counts['new']; ?>
                            <?= $badge['code_added']; ?> <?= (int) $counts['code_added']; ?>
                            <?= $badge['exists']; ?> <?= (int) $counts['exists']; ?>

                            <?php if ($counts['new'] || $counts['code_added']) { ?>
                            <?= form_open(admin_url('asset_management/legacy_import/run'), ['class' => 'tw-ml-auto', 'onsubmit' => "return confirm('" . _l('ams_legacy_import_confirm') . "');"]); ?>
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import tw-mr-1"></i><?= _l('ams_legacy_import_run'); ?></button>
                            <?= form_close(); ?>
                            <?php } else { ?>
                            <span class="tw-ml-auto text-success"><i class="fa-solid fa-circle-check tw-mr-1"></i><?= _l('ams_legacy_nothing_to_import'); ?></span>
                            <?php } ?>
                        </div>

                        <table class="table table-bordered table-hover tw-mb-0">
                            <thead>
                                <tr>
                                    <th><?= _l('ams_legacy_type'); ?></th>
                                    <th><?= _l('ams_name'); ?></th>
                                    <th><?= _l('ams_legacy_parent'); ?></th>
                                    <th><?= _l('ams_category_code'); ?></th>
                                    <th><?= _l('ams_legacy_result'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row) { ?>
                                <tr>
                                    <td><?= _l($row['section']); ?></td>
                                    <td class="tw-font-medium"><?= e($row['name']); ?></td>
                                    <td><?= e($row['parent']); ?></td>
                                    <td><?= $row['code'] ? '<code>' . e($row['code']) . '</code>' : ''; ?></td>
                                    <td><?= $badge[$row['state']]; ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
