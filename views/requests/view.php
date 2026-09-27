<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$r      = $req;
$isMine = (int) $r->staff_id === (int) get_staff_user_id();
$step   = function ($label, $who, $when, $note, $done) {
    return '<li class="tw-mb-3"><span class="' . ($done ? 'text-success' : 'text-muted') . '"><i class="fa-solid ' . ($done ? 'fa-circle-check' : 'fa-circle') . ' tw-mr-1"></i></span>'
        . '<span class="tw-font-medium">' . $label . '</span>'
        . ($who ? ' - ' . e(get_staff_full_name($who)) : '') . ($when ? ' <span class="text-muted tw-text-sm">' . e(_dt($when)) . '</span>' : '')
        . ($note ? '<div class="tw-text-sm tw-ml-5">' . nl2br(e($note)) . '</div>' : '') . '</li>';
};
$canFulfil = $r->status === 'approved' && (
    ($r->type === 'asset' && staff_can('checkout', 'ams_assets'))
    || ($r->type === 'accessory' && staff_can('checkout', 'ams_accessories'))
    || ($r->type === 'consumable' && (staff_can('issue', 'ams_consumables') || staff_can('issue', 'ams_stock')))
    || ($r->type === 'issue' && staff_can('approve', 'ams_requests'))
);
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                        <?= e($r->request_no); ?> <span class="tw-font-normal tw-text-neutral-500">- <?= e($r->subject); ?></span>
                        <?= ams_request_status_badge($r->status); ?>
                    </h4>
                    <div class="tw-flex tw-gap-1">
                        <?php if ($r->type === 'issue' && $r->asset_id && in_array($r->status, ['pending_dept', 'pending_manager', 'approved']) && staff_can('create', 'ams_maintenance')) { ?>
                        <a href="<?= admin_url('asset_management/maintenance?request_id=' . $r->id); ?>" class="btn btn-default"><i class="fa-solid fa-screwdriver-wrench tw-mr-1"></i><?= _l('ams_mt_create_from_issue'); ?></a>
                        <?php } ?>
                        <?php if ($r->type !== 'issue' && $r->status === 'approved' && staff_can('create', 'ams_procurement')) { ?>
                        <a href="<?= admin_url('asset_management/procurement/po?request_id=' . $r->id); ?>" class="btn btn-default"><i class="fa-solid fa-cart-shopping tw-mr-1"></i><?= _l('ams_po_create_from_request'); ?></a>
                        <?php } ?>
                        <?php if ($isMine && in_array($r->status, ['pending_dept', 'pending_manager'])) { ?>
                        <button type="button" class="btn btn-default ams-req-post" data-url="<?= admin_url('asset_management/requests/cancel/' . $r->id); ?>" data-confirm="1"><?= _l('ams_req_cancel'); ?></button>
                        <?php } ?>
                        <?php if (staff_can('delete', 'ams_requests')) { ?>
                        <a href="<?= admin_url('asset_management/requests/delete/' . $r->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                        <?php } ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-7">
                        <div class="panel_s">
                            <div class="panel-body">
                                <table class="table table-bordered tw-mb-0">
                                    <tbody>
                                        <tr><td class="tw-font-medium tw-text-neutral-500" style="width:35%"><?= _l('ams_requester'); ?></td><td><?= ams_assignee_html('staff', $r->staff_id); ?></td></tr>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_department'); ?></td><td><?= e(ams_department_name($r->department_id)) ?: '-'; ?></td></tr>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_req_type'); ?></td><td><?= _l('ams_req_type_' . $r->type); ?></td></tr>
                                        <?php if ($r->category_id) { ?><tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_category'); ?></td><td><?= e(ams_option_label(ams_category_options(), $r->category_id)); ?></td></tr><?php } ?>
                                        <?php if ($r->item_id) { ?><tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_item'); ?></td><td><a href="<?= admin_url('asset_management/inventory/view/' . $r->item_id); ?>"><?= e($this->db->select('CONCAT(sku, " - ", name) n', false)->where('id', $r->item_id)->get(db_prefix() . 'ams_items')->row()->n ?? ''); ?></a></td></tr><?php } ?>
                                        <?php if ($r->asset_id) { ?><tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_asset'); ?></td><td><a href="<?= admin_url('asset_management/assets/view/' . $r->asset_id); ?>"><?= e($this->db->select('CONCAT(asset_tag, " - ", name) n', false)->where('id', $r->asset_id)->get(db_prefix() . 'ams_assets')->row()->n ?? ''); ?></a></td></tr><?php } ?>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_quantity'); ?></td><td><?= ams_qty($r->qty); ?></td></tr>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_priority'); ?></td><td><?= _l('ams_priority_' . $r->priority); ?></td></tr>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_needed_by'); ?></td><td><?= e(_d($r->needed_by)) ?: '-'; ?></td></tr>
                                        <tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_req_description'); ?></td><td><?= nl2br(e($r->description)) ?: '-'; ?></td></tr>
                                        <?php if ($r->fulfilled_asset_id) { ?><tr><td class="tw-font-medium tw-text-neutral-500"><?= _l('ams_req_given_asset'); ?></td><td><a href="<?= admin_url('asset_management/assets/view/' . $r->fulfilled_asset_id); ?>">#<?= (int) $r->fulfilled_asset_id; ?></a></td></tr><?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_req_progress'); ?></h4>
                                <ul class="list-unstyled tw-mb-0">
                                    <?= $step(_l('ams_req_step_submitted'), $r->staff_id, $r->date_created, null, true); ?>
                                    <?php if ($r->dept_approver_id || $r->status === 'pending_dept') { ?>
                                    <?= $step(_l('ams_req_step_dept'), $r->dept_approver_id, $r->dept_decision_at, $r->dept_note, (bool) $r->dept_decision_at); ?>
                                    <?php } ?>
                                    <?= $step(_l('ams_req_step_manager'), $r->approver_id, $r->decision_at, $r->decision_note, (bool) $r->decision_at); ?>
                                    <?= $step(_l('ams_req_step_fulfilled'), $r->fulfilled_by, $r->fulfilled_at, $r->fulfilment_note, (bool) $r->fulfilled_at); ?>
                                </ul>
                            </div>
                        </div>

                        <?php if ($can_decide) { ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_req_your_decision'); ?></h4>
                                <?= form_open(admin_url('asset_management/requests/decide/' . $r->id), ['class' => 'ams-req-form']); ?>
                                <?= render_textarea('note', 'ams_note'); ?>
                                <input type="hidden" name="decision" value="">
                                <button type="submit" class="btn btn-success" onclick="this.form.decision.value='approve'"><?= _l('ams_req_approve'); ?></button>
                                <button type="submit" class="btn btn-danger" onclick="this.form.decision.value='reject'"><?= _l('ams_req_reject'); ?></button>
                                <?= form_close(); ?>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if ($canFulfil) { ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l($r->type === 'issue' ? 'ams_req_resolve' : 'ams_req_fulfil'); ?></h4>
                                <?= form_open(admin_url('asset_management/requests/fulfil/' . $r->id), ['class' => 'ams-req-form']); ?>
                                <?php if ($r->type === 'asset') { ?>
                                <?= render_select('asset_id', $assets, ['id', 'name'], 'ams_req_pick_asset'); ?>
                                <?php if (! $assets) { ?><p class="text-warning tw-text-sm"><?= _l('ams_req_no_available_assets'); ?></p><?php } ?>
                                <?php } elseif (in_array($r->type, ['accessory', 'consumable'])) { ?>
                                <?= render_select('item_id', $items, ['id', 'name'], 'ams_item', $r->item_id); ?>
                                <div class="row">
                                    <div class="col-md-5"><?= render_input('qty', 'ams_quantity', ams_qty($r->qty), 'number', ['step' => '0.01', 'min' => '0.01']); ?></div>
                                    <div class="col-md-7"><?= render_select('location_id', $locations, ['id', 'name'], 'ams_from_location'); ?></div>
                                </div>
                                <?php } ?>
                                <?= render_textarea('fulfilment_note', 'ams_note'); ?>
                                <button type="submit" class="btn btn-primary"><?= _l($r->type === 'issue' ? 'ams_req_mark_resolved' : 'ams_req_give_to_requester'); ?></button>
                                <?= form_close(); ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        $('.ams-req-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            $.post(form.attr('action'), form.serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
        $('.ams-req-post').on('click', function() {
            if ($(this).data('confirm') && !confirm_delete()) {
                return;
            }
            $.post($(this).data('url')).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
    });
</script>
</body>
</html>
