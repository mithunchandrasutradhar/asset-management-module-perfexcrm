<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Responsible picker: Departments (optional, several) and Staff (several). With departments
// chosen, the staff list holds only their members; a chosen department none of whose members
// is picked is responsible as a whole. $options = responsible_options(), $select_id = DOM id prefix.
$required = get_option('ams_mt_responsible_required') == '1';
?>
<div class="ams-resp" id="<?= e($select_id); ?>" data-staff="<?= e(json_encode($options['staff'])); ?>">
    <input type="hidden" name="responsible_posted" value="1">
    <div class="row">
        <div class="col-md-5">
            <div class="form-group">
                <label for="<?= e($select_id); ?>_dept" class="control-label"><?= _l('ams_mt_resp_department') . ams_help_icon(_l('ams_mt_resp_department_help')); ?></label>
                <select name="responsible_departments[]" id="<?= e($select_id); ?>_dept" class="selectpicker ams-resp-dept" multiple data-width="100%" data-live-search="true"
                    data-none-selected-text="<?= e(_l('ams_mt_resp_any_department')); ?>" onchange="ams_resp_filter(this)">
                    <?php foreach ($options['departments'] as $d) { ?>
                    <option value="<?= (int) $d['id']; ?>"><?= e($d['name']); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-7">
            <div class="form-group">
                <label for="<?= e($select_id); ?>_staff" class="control-label"><?= ($required ? '<small class="req text-danger">* </small>' : '') . _l('ams_mt_resp_staff') . ams_help_icon(_l('ams_mt_resp_staff_help')); ?></label>
                <select name="responsible_staff[]" id="<?= e($select_id); ?>_staff" class="selectpicker ams-resp-staff" multiple data-width="100%" data-live-search="true"
                    data-none-selected-text="<?= e(_l('ams_mt_unassigned')); ?>" onchange="ams_resp_hint(this)">
                    <?php foreach ($options['staff'] as $s) { ?>
                    <option value="<?= (int) $s['id']; ?>"><?= e($s['name']); ?></option>
                    <?php } ?>
                </select>
                <p class="text-muted tw-text-xs tw-mt-1 tw-mb-0 ams-resp-whole hide"><i class="fa-solid fa-users tw-mr-1"></i><span></span></p>
            </div>
        </div>
    </div>
</div>
<?php if (empty($GLOBALS['ams_resp_js'])) {
    $GLOBALS['ams_resp_js'] = true; ?>
<script>
    var ams_resp_whole_text = <?= json_encode(_l('ams_mt_resp_whole_department')); ?>;

    function ams_resp_box(el) {
        return $(el).closest('.ams-resp');
    }

    // Rebuild the staff list: members of the chosen departments, or everyone when none is chosen.
    function ams_resp_filter(el) {
        var box = ams_resp_box(el);
        var depts = (box.find('select.ams-resp-dept').val() || []).map(String);
        var staffSel = box.find('select.ams-resp-staff');
        var keep = (staffSel.val() || []).map(String);
        var all = box.data('staff') || [];
        staffSel.empty();
        all.forEach(function(s) {
            var inDept = !depts.length || (s.depts || []).some(function(d) {
                return depts.indexOf(String(d)) !== -1;
            });
            if (inDept) {
                var o = $('<option>').val(s.id).text(s.name);
                if (keep.indexOf(String(s.id)) !== -1) {
                    o.prop('selected', true);
                }
                staffSel.append(o);
            }
        });
        staffSel.selectpicker('refresh');
        ams_resp_hint(el);
    }

    // Name the chosen departments none of whose members is picked: they are responsible as a whole.
    function ams_resp_hint(el) {
        var box = ams_resp_box(el);
        var deptSel = box.find('select.ams-resp-dept');
        var picked = (box.find('select.ams-resp-staff').val() || []).map(String);
        var all = box.data('staff') || [];
        var whole = [];
        (deptSel.val() || []).forEach(function(d) {
            var hit = all.some(function(s) {
                return picked.indexOf(String(s.id)) !== -1 && (s.depts || []).map(String).indexOf(String(d)) !== -1;
            });
            if (!hit) {
                whole.push(deptSel.find('option[value="' + d + '"]').text());
            }
        });
        box.find('.ams-resp-whole').toggleClass('hide', !whole.length).find('span').text(ams_resp_whole_text + ' ' + whole.join(', '));
    }

    // Set the picker from saved values: {departments: [ids], staff: [ids]}.
    function ams_resp_set(id, v) {
        var box = $('#' + id);
        v = v || {};
        box.find('select.ams-resp-dept').selectpicker('val', (v.departments || []).map(String));
        box.find('select.ams-resp-staff').selectpicker('val', []);
        ams_resp_filter(box);
        box.find('select.ams-resp-staff').selectpicker('val', (v.staff || []).map(String));
        ams_resp_hint(box);
    }

    // Also listen through jQuery (bootstrap-select may only trigger jQuery events).
    window.addEventListener('load', function() {
        $(document).on('changed.bs.select', '.ams-resp select.ams-resp-dept', function() {
            ams_resp_filter(this);
        }).on('changed.bs.select', '.ams-resp select.ams-resp-staff', function() {
            ams_resp_hint(this);
        });
    });
</script>
<?php } ?>
