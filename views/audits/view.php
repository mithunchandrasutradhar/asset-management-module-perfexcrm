<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$au      = $audit;
$c       = $au->counts;
$canEdit = staff_can('edit', 'ams_audits');
$running = $au->status === 'in_progress';
$closed  = in_array($au->status, ['completed', 'cancelled']);
$scope   = array_filter([
    $au->location_id ? ams_location_name($au->location_id) : null,
    $au->department_id ? ams_department_name($au->department_id) : null,
    $au->category_id ? ams_option_label(ams_category_options(), $au->category_id) : null,
]);
$table = App_table::find('ams_audit_lines');
$tile  = fn ($key, $n, $cls = '') => '<div class="col-md-2 col-sm-4 col-xs-6"><div class="panel_s"><div class="panel-body tw-py-3"><p class="tw-text-neutral-500 tw-mb-0 tw-text-sm">'
    . _l($key) . '</p><p class="tw-font-semibold tw-text-2xl tw-mb-0 ' . $cls . '" data-count="' . str_replace('ams_audit_result_', '', $key) . '">' . (int) $n . '</p></div></div></div>';
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <div>
                <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= e($au->audit_no); ?> <span class="tw-font-normal tw-text-neutral-500">- <?= e($au->title); ?></span></h4>
                <div class="tw-mt-1">
                    <?= ams_audit_status_badge($au->status); ?>
                    <span class="text-muted tw-ml-2"><?= _l('ams_audit_scope'); ?>: <?= $scope ? e(implode(' / ', $scope)) : _l('ams_audit_scope_all'); ?></span>
                    <?php if ($au->due_date) { ?><span class="text-muted tw-ml-2"><?= _l('ams_audit_due_date'); ?>: <?= e(_d($au->due_date)); ?></span><?php } ?>
                </div>
            </div>
            <div class="tw-flex tw-flex-wrap tw-gap-1">
                <?php if ($canEdit && $au->status === 'draft') { ?>
                <a href="#" class="btn btn-success" onclick="ams_audit_post('start'); return false;"><i class="fa-solid fa-play tw-mr-1"></i><?= _l('ams_audit_start'); ?></a>
                <?php } ?>
                <?php if ($canEdit && $running) { ?>
                <?php if ($scanning) { ?>
                <a href="<?= admin_url('asset_management/audits/scan_mode/' . $au->id . '/0'); ?>" class="btn btn-warning ams-post"><i class="fa-solid fa-mobile-screen tw-mr-1"></i><?= _l('ams_audit_scan_mode_stop'); ?></a>
                <?php } else { ?>
                <a href="<?= admin_url('asset_management/audits/scan_mode/' . $au->id . '/1'); ?>" class="btn btn-default ams-post"><i class="fa-solid fa-mobile-screen tw-mr-1"></i><?= _l('ams_audit_scan_mode_start'); ?></a>
                <?php } ?>
                <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#ams_audit_complete_modal"><i class="fa-solid fa-flag-checkered tw-mr-1"></i><?= _l('ams_audit_complete'); ?></a>
                <?php } ?>
                <?php if ($canEdit && ! $closed) { ?>
                <a href="#" class="btn btn-default" onclick="ams_audit_edit(<?= (int) $au->id; ?>); return false;"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a>
                <a href="#" class="btn btn-default" onclick="if (confirm('<?= e(_l('ams_audit_confirm_cancel')); ?>')) { ams_audit_post('cancel'); } return false;"><?= _l('ams_audit_cancel'); ?></a>
                <?php } ?>
                <?php if (staff_can('delete', 'ams_audits') && in_array($au->status, ['draft', 'cancelled'])) { ?>
                <a href="<?= admin_url('asset_management/audits/delete/' . $au->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                <?php } ?>
            </div>
        </div>

        <?php if ($scanning && $running) { ?>
        <div class="alert alert-warning"><i class="fa-solid fa-mobile-screen tw-mr-1"></i><?= _l('ams_audit_scan_mode_banner'); ?></div>
        <?php } ?>
        <?php if ($au->status === 'draft') { ?>
        <div class="alert alert-info"><?= _l('ams_audit_draft_help'); ?></div>
        <?php } ?>

        <?php if ($au->status !== 'draft') { ?>
        <div class="row">
            <?= $tile('ams_audit_expected', $c['total'] - $c['unexpected']); ?>
            <?= $tile('ams_audit_result_found', $c['found'], 'text-success'); ?>
            <?= $tile('ams_audit_result_misplaced', $c['misplaced'], 'text-warning'); ?>
            <?= $tile('ams_audit_result_unexpected', $c['unexpected'], 'text-info'); ?>
            <?= $tile('ams_audit_result_pending', $c['pending']); ?>
            <?= $tile('ams_audit_result_missing', $c['missing'], 'text-danger'); ?>
        </div>
        <?php } ?>

        <?php if ($canEdit && $running) { ?>
        <div class="panel_s">
            <div class="panel-body">
                <form id="ams-audit-scan" class="row" autocomplete="off">
                    <div class="col-md-5">
                        <label class="control-label" for="ams_scan_code"><?= _l('ams_audit_scan_code'); ?></label>
                        <input type="text" id="ams_scan_code" name="code" class="form-control input-lg" placeholder="<?= e(_l('ams_audit_scan_placeholder')); ?>" autofocus>
                    </div>
                    <div class="col-md-3"><?= render_select('found_location_id', $locations, ['id', 'name'], 'ams_audit_found_at'); ?></div>
                    <div class="col-md-2"><?= render_select('asset_condition', ams_condition_options(), ['id', 'name'], 'ams_condition'); ?></div>
                    <div class="col-md-2">
                        <label class="control-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block"><?= _l('ams_audit_record'); ?></button>
                    </div>
                    <div class="col-md-12"><p class="text-muted tw-text-sm tw-mb-0" id="ams_scan_feedback"><?= _l('ams_audit_scan_help'); ?></p></div>
                </form>
            </div>
        </div>
        <?php } ?>

        <?php if ($au->status !== 'draft') { ?>
        <div class="tw-mb-2 tw-flex tw-justify-end">
            <div id="vueApp">
                <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                    :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                </app-filters>
            </div>
        </div>
        <div class="panel_s">
            <div class="panel-body panel-table-full">
                <?php render_datatable([
                    _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_audit_expected_location'), _l('ams_audit_found_at'),
                    _l('ams_audit_result'), _l('ams_condition'), _l('ams_note'), _l('ams_audit_scanned_by'), _l('ams_audit_scanned_at'),
                ], 'ams-audit-lines'); ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($au->notes || $au->next_audit_date || $au->completed_at) { ?>
        <div class="panel_s">
            <div class="panel-body">
                <?php if ($au->notes) { ?><p><?= nl2br(e($au->notes)); ?></p><?php } ?>
                <?php if ($au->completed_at) { ?><p class="text-muted tw-mb-0"><?= _l('ams_audit_completed_at'); ?>: <?= e(_dt($au->completed_at)); ?><?= $au->completed_by ? ' - ' . e(get_staff_full_name($au->completed_by)) : ''; ?></p><?php } ?>
                <?php if ($au->next_audit_date) { ?><p class="text-muted tw-mb-0"><?= _l('ams_audit_next_date'); ?>: <?= e(_d($au->next_audit_date)); ?></p><?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<?php if ($canEdit && $running) { ?>
<div class="modal fade" id="ams_audit_complete_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/audits/complete/' . $au->id), ['id' => 'ams-audit-complete']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_audit_complete'); ?> - <?= e($au->audit_no); ?></h4>
            </div>
            <div class="modal-body">
                <p><?= _l('ams_audit_complete_help', (int) $c['pending']); ?></p>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="move_misplaced" id="ams_move_misplaced" value="1" checked>
                    <label for="ams_move_misplaced"><?= _l('ams_audit_move_misplaced'); ?></label>
                </div>
                <?= render_date_input('next_audit_date', 'ams_audit_next_date', _d($au->next_audit_date)); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('ams_audit_complete'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>
<?php $this->load->view(AMS_MODULE_NAME . '/audits/_modal', ['locations' => $locations, 'departments' => array_map(fn ($d) => ['id' => $d['departmentid'], 'name' => $d['name']], ams_department_options()), 'categories' => ams_category_options(true)]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/audits/_modal_js'); ?>
<script>
    var AMS_AUDIT_ID = <?= (int) $au->id; ?>;

    $(function() {
        <?php if ($au->status !== 'draft') { ?>
        initDataTable('.table-ams-audit-lines', admin_url + 'asset_management/audits/lines_table/' + AMS_AUDIT_ID, [], [], {}, [9, 'desc']);
        <?php } ?>

        // Scanner / keyboard input: record, keep focus for the next scan.
        $('#ams-audit-scan').on('submit', function(e) {
            e.preventDefault();
            var input = $('#ams_scan_code');
            if (!input.val().trim()) {
                return;
            }
            var data = $(this).serializeArray();
            data.push({ name: csrfData.token_name, value: csrfData.hash });
            $.post(admin_url + 'asset_management/audits/record/' + AMS_AUDIT_ID, data).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                var cls = !r.success ? 'text-danger' : (r.result === 'found' ? 'text-success' : 'text-warning');
                $('#ams_scan_feedback').attr('class', 'tw-text-sm tw-mb-0 tw-font-medium ' + cls).text($('<div>').html(r.message).text());
                if (r.counts) {
                    $.each(r.counts, function(k, v) { $('[data-count="' + k + '"]').text(v); });
                    $('[data-count="ams_audit_expected"]').text(r.counts.total - r.counts.unexpected);
                }
                $('.table-ams-audit-lines').DataTable().ajax.reload(null, false);
            }).always(function() {
                input.val('').focus();
            });
        });

        $('#ams-audit-complete').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button[type="submit"]').prop('disabled', true);
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                if (r.success) {
                    window.location.reload();
                } else {
                    alert_float('danger', r.message);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    function ams_audit_post(action) {
        $.post(admin_url + 'asset_management/audits/' + action + '/' + AMS_AUDIT_ID).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            if (r.success) {
                window.location.reload();
            } else {
                alert_float('danger', r.message);
            }
        });
    }

    function ams_audit_line(lineId, result) {
        $.post(admin_url + 'asset_management/audits/line_result/' + lineId, { result: result }).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            alert_float(r.success ? 'success' : 'danger', $('<div>').html(r.message).text());
            if (r.success) {
                window.location.reload();
            }
        });
    }
</script>
</body>
</html>
