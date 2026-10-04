<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// "Responsible" picker: staff (s:ID) and Perfex departments (d:ID) in one multi-select.
// $options = responsible_options(), $selected = ['s:4', 'd:2'], $select_id = DOM id.
$selected = $selected ?? [];
$required = get_option('ams_mt_responsible_required') == '1';
?>
<div class="form-group">
    <label for="<?= e($select_id); ?>" class="control-label"><?= ($required ? '<small class="req text-danger">* </small>' : '') . _l('ams_mt_responsible') . ams_help_icon(_l('ams_mt_responsible_help')); ?></label>
    <input type="hidden" name="responsible_posted" value="1">
    <select name="responsible[]" id="<?= e($select_id); ?>" class="selectpicker" multiple data-width="100%" data-live-search="true"
        data-none-selected-text="<?= e(_l('ams_mt_unassigned')); ?>" data-actions-box="false">
        <optgroup label="<?= e(_l('ams_mt_group_staff')); ?>">
            <?php foreach ($options['staff'] as $o) { ?>
            <option value="<?= e($o['id']); ?>" <?= in_array($o['id'], $selected, true) ? 'selected' : ''; ?>><?= e($o['name']); ?></option>
            <?php } ?>
        </optgroup>
        <?php if ($options['departments']) { ?>
        <optgroup label="<?= e(_l('ams_mt_group_departments')); ?>">
            <?php foreach ($options['departments'] as $o) { ?>
            <option value="<?= e($o['id']); ?>" data-icon="fa-solid fa-users" <?= in_array($o['id'], $selected, true) ? 'selected' : ''; ?>><?= e($o['name']); ?></option>
            <?php } ?>
        </optgroup>
        <?php } ?>
    </select>
</div>
