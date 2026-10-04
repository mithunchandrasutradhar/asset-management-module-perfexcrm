<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$open      = in_array($job->status, ['scheduled', 'in_progress'], true);
$isMine    = $this->ams_maintenance_model->is_responsible($job->id);
$badge     = ['scheduled' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'default'];
$checkCss  = ['working' => 'success', 'partial' => 'warning', 'not_working' => 'danger'];
$overdue   = $open && $job->due_date && $job->due_date < date('Y-m-d');
$holder    = '';
if ($asset && $asset->assigned_type === 'staff') {
    $holder = get_staff_full_name($asset->assigned_id);
} elseif ($asset && $asset->assigned_type === 'department') {
    $holder = (string) ($this->db->select('name')->where('departmentid', (int) $asset->assigned_id)->get(db_prefix() . 'departments')->row()->name ?? '');
} elseif ($asset && $asset->assigned_type === 'location') {
    $holder = (string) ($this->db->select('name')->where('id', (int) $asset->assigned_id)->get(db_prefix() . 'ams_locations')->row()->name ?? '');
}
$detail = function ($label, $value) {
    return '<tr><td class="tw-font-medium tw-text-neutral-500 tw-w-2/5">' . _l($label) . '</td><td>' . $value . '</td></tr>';
};
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                <a href="<?= admin_url('asset_management/maintenance'); ?>" class="tw-text-neutral-500"><?= _l('ams_maintenance'); ?></a> ›
                <?= e($job->title); ?>
                <span class="label label-<?= $badge[$job->status] ?? 'default'; ?> tw-ml-1 tw-align-middle"><?= _l('ams_mt_status_' . $job->status); ?></span>
            </h4>
            <div class="tw-flex tw-flex-wrap tw-gap-1">
                <?php if ($open && $isMine && ! $job->acknowledged_at) { ?>
                <a href="#" class="btn btn-warning" onclick="ams_mt_ack(); return false;"><i class="fa-regular fa-hand tw-mr-1"></i><?= _l('ams_mt_acknowledge'); ?></a>
                <?php } ?>
                <?php if ($canWork && $job->status === 'scheduled') { ?>
                <a href="#" class="btn btn-info" onclick="ams_mt_action('start', <?= (int) $job->id; ?>); return false;"><i class="fa-solid fa-play tw-mr-1"></i><?= _l('ams_mt_start'); ?></a>
                <?php } ?>
                <?php if ($canWork && $open) { ?>
                <a href="#" class="btn btn-success" onclick="ams_mt_action('complete', <?= (int) $job->id; ?>, '<?= e($job->type); ?>'); return false;"><i class="fa-solid fa-check tw-mr-1"></i><?= _l('ams_mt_complete'); ?></a>
                <?php } ?>
                <?php if ($canEdit && $open) { ?>
                <a href="#" class="btn btn-default" onclick="ams_mt_edit(<?= (int) $job->id; ?>); return false;"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a>
                <a href="#" class="btn btn-default text-danger" onclick="ams_mt_cancel(<?= (int) $job->id; ?>); return false;"><?= _l('ams_mt_cancel'); ?></a>
                <?php } ?>
                <?php if ($canDelete && $job->status !== 'in_progress') { ?>
                <a href="<?= admin_url('asset_management/maintenance/delete/' . $job->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                <?php } ?>
            </div>
        </div>

        <?php if ($open && $isMine && ! $job->acknowledged_at) { ?>
        <div class="alert alert-warning"><i class="fa-regular fa-hand tw-mr-1"></i><?= _l('ams_mt_please_acknowledge'); ?></div>
        <?php } ?>

        <div class="row">
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_mt_job_details'); ?></h4>
                        <table class="table table-condensed tw-mb-0">
                            <tbody>
                                <?= $detail('ams_mt_type', e(_l('ams_mt_type_' . $job->type))); ?>
                                <?= $detail('ams_mt_due_date', $job->due_date ? '<span class="' . ($overdue ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($job->due_date)) . ($overdue ? ' (' . _l('ams_overdue') . ')' : '') . '</span>' : '-'); ?>
                                <?= $detail('ams_mt_start_date', $job->start_date ? e(_d($job->start_date)) : '-'); ?>
                                <?php if ($job->end_date) { ?>
                                <?= $detail('ams_mt_end_date', e(_d($job->end_date))); ?>
                                <?php } ?>
                                <?= $detail('ams_mt_vendor', $supplier ? e($supplier->name) : '-'); ?>
                                <?php if ($job->cost !== null) { ?>
                                <?= $detail('ams_mt_cost', e(app_format_money($job->cost, get_base_currency()))); ?>
                                <?php } ?>
                                <?php if ($job->downtime_hours !== null) { ?>
                                <?= $detail('ams_mt_downtime', e(ams_qty($job->downtime_hours))); ?>
                                <?php } ?>
                                <?php if ($job->check_result) { ?>
                                <?= $detail('ams_mt_check_result', '<span class="label label-' . $checkCss[$job->check_result] . '">' . _l('ams_mt_check_' . $job->check_result) . '</span>'); ?>
                                <?php } ?>
                                <?php if ($job->schedule_id) { ?>
                                <?= $detail('ams_mt_schedule', '<i class="fa-regular fa-calendar-check text-muted tw-mr-1"></i>' . _l('ams_mt_from_schedule')); ?>
                                <?php } ?>
                                <?php if ($request) { ?>
                                <?= $detail('ams_request', '<a href="' . admin_url('asset_management/requests/view/' . $request->id) . '">' . e($request->request_no) . '</a> - ' . e(get_staff_full_name($request->staff_id))); ?>
                                <?php } ?>
                                <?php if ($followup) { ?>
                                <?= $detail('ams_mt_followup', '<a href="' . admin_url('asset_management/maintenance/view/' . $followup->id) . '">' . e($followup->title) . '</a> <span class="label label-' . ($badge[$followup->status] ?? 'default') . '">' . _l('ams_mt_status_' . $followup->status) . '</span>'); ?>
                                <?php } ?>
                                <?= $detail('ams_created_by', ($job->created_by ? e(get_staff_full_name($job->created_by)) : _l('ams_mt_created_by_system')) . ' <span class="text-muted">' . e(_dt($job->date_created)) . '</span>'); ?>
                                <?php if ($job->completed_by) { ?>
                                <?= $detail('ams_mt_completed_by', e(get_staff_full_name($job->completed_by)) . ' <span class="text-muted">' . e(_dt($job->date_completed)) . '</span>'); ?>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php if ($job->notes) { ?>
                        <h5 class="tw-font-semibold tw-mt-4"><?= _l('ams_notes'); ?></h5>
                        <p class="tw-mb-0"><?= nl2br(e($job->notes)); ?></p>
                        <?php } ?>
                        <?php if ($job->resolution) { ?>
                        <h5 class="tw-font-semibold tw-mt-4"><?= _l('ams_mt_resolution'); ?></h5>
                        <p class="tw-mb-0"><?= nl2br(e($job->resolution)); ?></p>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_mt_responsible') . ams_help_icon(_l('ams_mt_responsible_help')); ?></h4>
                        <?php if (! $responsible) { ?>
                        <p class="text-muted tw-mb-0"><?= _l('ams_mt_unassigned'); ?></p>
                        <?php } else { ?>
                        <ul class="list-unstyled tw-mb-2">
                            <?php foreach ($responsible as $r) { ?>
                            <li class="tw-py-0.5"><i class="fa-solid fa-<?= $r['staff_id'] ? 'user' : 'users'; ?> text-muted tw-mr-1"></i><?= e($r['label']); ?></li>
                            <?php } ?>
                        </ul>
                        <?php } ?>
                        <?php if ($responsible && $open) { ?>
                        <p class="tw-mb-0 tw-text-sm">
                            <?php if ($job->acknowledged_at) { ?>
                            <i class="fa-solid fa-check text-success tw-mr-1"></i><?= _l('ams_mt_acknowledged_by', [e(get_staff_full_name($job->acknowledged_by)), e(_dt($job->acknowledged_at))]); ?>
                            <?php } else { ?>
                            <i class="fa-regular fa-hourglass-half text-warning tw-mr-1"></i><?= _l('ams_mt_not_acknowledged'); ?>
                            <?php } ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($asset) { ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_asset'); ?></h4>
                        <table class="table table-condensed tw-mb-0">
                            <tbody>
                                <?= $detail('ams_asset_tag', $canAsset ? '<a href="' . admin_url('asset_management/assets/view/' . $asset->id) . '">' . e($asset->asset_tag) . '</a>' : e($asset->asset_tag)); ?>
                                <?= $detail('ams_asset_name', e($asset->name)); ?>
                                <?= $detail('ams_category', e($asset->category_name)); ?>
                                <?php if ($asset->serial_no) { ?>
                                <?= $detail('ams_serial_no', e($asset->serial_no)); ?>
                                <?php } ?>
                                <?= $detail('ams_status', ams_status_badge($asset->status_name, $asset->status_color)); ?>
                                <?= $detail('ams_location', $asset->location_name ? e($asset->location_name) : '-'); ?>
                                <?= $detail('ams_assigned_to', $holder ? e($holder) : '-'); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>
            </div>

            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_mt_progress') . ams_help_icon(_l('ams_mt_progress_help')); ?></h4>
                        <?php if ($canWork && $open) { ?>
                        <?= form_open(admin_url('asset_management/maintenance/add_note/' . $job->id), ['id' => 'ams-mt-note-form']); ?>
                        <textarea name="note" class="form-control" rows="3" placeholder="<?= e(_l('ams_mt_note_placeholder')); ?>"></textarea>
                        <div class="text-right tw-mt-2">
                            <button type="submit" class="btn btn-primary btn-sm"><?= _l('ams_mt_add_note'); ?></button>
                        </div>
                        <?= form_close(); ?>
                        <hr class="hr-panel-separator" />
                        <?php } ?>
                        <?php if (! $notes) { ?>
                        <p class="text-muted tw-mb-0"><?= _l('ams_mt_no_notes'); ?></p>
                        <?php } ?>
                        <ul class="list-unstyled tw-mb-0">
                            <?php foreach ($notes as $n) { ?>
                            <li class="tw-py-2 tw-border-0 tw-border-b tw-border-solid tw-border-neutral-100">
                                <div class="tw-flex tw-justify-between tw-gap-2 tw-text-sm">
                                    <span class="tw-font-medium">
                                        <i class="fa-<?= $n['type'] === 'system' ? 'solid fa-gear' : 'regular fa-comment'; ?> text-muted tw-mr-1"></i><?= $n['staff_id'] ? e(trim($n['firstname'] . ' ' . $n['lastname'])) : _l('ams_mt_created_by_system'); ?>
                                    </span>
                                    <span class="text-muted"><?= e(_dt($n['date_created'])); ?></span>
                                </div>
                                <div class="tw-mt-1 <?= $n['type'] === 'system' ? 'text-muted' : ''; ?>"><?= nl2br(e($n['note'])); ?></div>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_modals', ['assets' => [], 'types' => $types, 'suppliers' => $suppliers, 'request' => null, 'fixed_asset' => $job->asset_id, 'responsible' => $options]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_js'); ?>
<script>
    function ams_mt_ack() {
        $.post(admin_url + 'asset_management/maintenance/acknowledge/<?= (int) $job->id; ?>').done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? window.location.reload() : alert_float('danger', r.message);
        });
    }
    $(function() {
        $('#ams-mt-note-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('[type="submit"]').prop('disabled', true);
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            }).always(function() {
                btn.prop('disabled', false);
            });
        });
    });
</script>
</body>
</html>
