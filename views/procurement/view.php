<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$cur        = get_base_currency();
$canEdit    = staff_can('edit', 'ams_procurement');
$canReceive = $canEdit && in_array($po->status, ['approved', 'sent', 'partially_received']);
$canDecide  = staff_can('approve_po', 'ams_procurement') && $po->status === 'pending_approval'
    && ((int) $po->submitted_by !== (int) get_staff_user_id() || is_admin());
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= e($po->po_number); ?> <span class="tw-font-normal tw-text-neutral-500">- <?= e($po->supplier_name); ?></span> <?= ams_po_status_badge($po->status); ?></h4>
                    <div class="tw-flex tw-flex-wrap tw-gap-1">
                        <a href="<?= admin_url('asset_management/procurement/pdf/' . $po->id); ?>" target="_blank" class="btn btn-default"><i class="fa-regular fa-file-pdf tw-mr-1"></i>PDF</a>
                        <?php if ($canEdit && in_array($po->status, ['draft', 'rejected'])) { ?>
                        <a href="<?= admin_url('asset_management/procurement/po/' . $po->id); ?>" class="btn btn-default"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('create', 'ams_procurement') && $po->status === 'draft') { ?>
                        <button class="btn btn-primary ams-po-post" data-url="<?= admin_url('asset_management/procurement/submit/' . $po->id); ?>"><i class="fa-solid fa-paper-plane tw-mr-1"></i><?= _l(get_option('ams_po_require_approval') == '1' ? 'ams_po_submit_approval' : 'ams_po_approve_now'); ?></button>
                        <?php } ?>
                        <?php if ($canEdit && in_array($po->status, ['approved', 'sent'])) { ?>
                        <button class="btn btn-default ams-po-post" data-url="<?= admin_url('asset_management/procurement/send/' . $po->id); ?>" data-email="1"><i class="fa-regular fa-envelope tw-mr-1"></i><?= _l('ams_po_email_supplier'); ?></button>
                        <?php if ($po->status === 'approved') { ?>
                        <button class="btn btn-default ams-po-post" data-url="<?= admin_url('asset_management/procurement/send/' . $po->id); ?>"><?= _l('ams_po_mark_sent'); ?></button>
                        <?php } ?>
                        <?php } ?>
                        <?php if ($canReceive) { ?>
                        <a href="#" class="btn btn-success" data-toggle="modal" data-target="#ams_receive_modal"><i class="fa-solid fa-truck-ramp-box tw-mr-1"></i><?= _l('ams_po_receive'); ?></a>
                        <?php } ?>
                        <?php if ($canEdit && ! in_array($po->status, ['received', 'cancelled', 'partially_received'])) { ?>
                        <button class="btn btn-default ams-po-post" data-url="<?= admin_url('asset_management/procurement/cancel/' . $po->id); ?>" data-confirm="1"><?= _l('ams_po_cancel'); ?></button>
                        <?php } ?>
                        <?php if (staff_can('delete', 'ams_procurement') && in_array($po->status, ['draft', 'rejected', 'cancelled'])) { ?>
                        <a href="<?= admin_url('asset_management/procurement/delete/' . $po->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($po->decision_note) { ?>
                <div class="alert alert-<?= $po->status === 'rejected' ? 'danger' : 'info'; ?>"><?= _l('ams_po_decision_note'); ?>: <?= nl2br(e($po->decision_note)); ?></div>
                <?php } ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="panel_s">
                            <div class="panel-body">
                                <table class="table table-bordered tw-mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th><th><?= _l('ams_description'); ?></th><th><?= _l('ams_po_line_type'); ?></th>
                                            <th class="text-right"><?= _l('ams_quantity'); ?></th><th class="text-right"><?= _l('ams_po_received_qty'); ?></th>
                                            <th class="text-right"><?= _l('ams_unit_cost'); ?></th><th class="text-right"><?= _l('ams_po_amount'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($po->lines as $i => $l) { ?>
                                        <tr>
                                            <td><?= $i + 1; ?></td>
                                            <td><?= e($l['description']); ?><br><span class="text-muted tw-text-xs"><?= e($l['line_type'] === 'item' ? $l['sku'] : $l['category_name']); ?></span></td>
                                            <td><?= _l('ams_po_type_' . $l['line_type']); ?></td>
                                            <td class="text-right"><?= ams_qty($l['qty']); ?></td>
                                            <td class="text-right <?= (float) $l['received_qty'] >= (float) $l['qty'] ? 'text-success' : ''; ?>"><?= ams_qty($l['received_qty']); ?></td>
                                            <td class="text-right"><?= e(app_format_money($l['unit_cost'], $cur)); ?></td>
                                            <td class="text-right"><?= e(app_format_money($l['qty'] * $l['unit_cost'], $cur)); ?></td>
                                        </tr>
                                        <?php } ?>
                                        <tr><td colspan="6" class="text-right tw-font-semibold"><?= _l('ams_po_total'); ?></td><td class="text-right tw-font-semibold"><?= e(app_format_money($po->total, $cur)); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if ($receipts) { ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_po_receipts'); ?></h4>
                                <?php foreach ($receipts as $r) { ?>
                                <div class="tw-mb-3">
                                    <p class="tw-font-medium tw-mb-1"><?= e(_d($r['receipt_date'])); ?> - <?= e($r['location_name']); ?><?= $r['invoice_no'] ? ' - ' . _l('ams_invoice_no') . ' ' . e($r['invoice_no']) : ''; ?> <span class="text-muted tw-text-sm">(<?= e(get_staff_full_name($r['staff_id'])); ?>)</span></p>
                                    <ul class="tw-mb-0">
                                        <?php foreach ($this->ams_procurement_model->receipt_lines($r['id']) as $rl) { ?>
                                        <li>
                                            <?= ams_qty($rl['qty']); ?> × <?= e($rl['description']); ?>
                                            <?php foreach (array_filter(explode(',', (string) $rl['asset_ids'])) as $aid) { ?>
                                            <a href="<?= admin_url('asset_management/assets/view/' . (int) $aid); ?>" class="label label-default">#<?= (int) $aid; ?></a>
                                            <?php } ?>
                                        </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="col-md-4">
                        <div class="panel_s">
                            <div class="panel-body">
                                <p class="tw-mb-1"><span class="text-muted"><?= _l('ams_po_order_date'); ?>:</span> <?= e(_d($po->order_date)); ?></p>
                                <p class="tw-mb-1"><span class="text-muted"><?= _l('ams_po_expected_date'); ?>:</span> <?= e(_d($po->expected_date)) ?: '-'; ?></p>
                                <p class="tw-mb-1"><span class="text-muted"><?= _l('ams_po_deliver_to'); ?>:</span> <?= e(ams_location_name($po->delivery_location_id)) ?: '-'; ?></p>
                                <p class="tw-mb-1"><span class="text-muted"><?= _l('ams_supplier'); ?>:</span> <?= e($po->supplier_name); ?> <?= $po->supplier_email ? '<br><span class="tw-text-sm">' . e($po->supplier_email) . '</span>' : ''; ?></p>
                                <p class="tw-mb-1"><span class="text-muted"><?= _l('ams_created_by'); ?>:</span> <?= e(get_staff_full_name($po->created_by)); ?></p>
                                <?php if ($po->approved_by) { ?><p class="tw-mb-1"><span class="text-muted"><?= _l($po->status === 'rejected' ? 'ams_po_rejected_by' : 'ams_po_approved_by'); ?>:</span> <?= e(get_staff_full_name($po->approved_by)); ?>, <?= e(_dt($po->approved_at)); ?></p><?php } ?>
                                <?php if ($po->sent_at) { ?><p class="tw-mb-1"><span class="text-muted"><?= _l('ams_po_sent_at'); ?>:</span> <?= e(_dt($po->sent_at)); ?></p><?php } ?>
                                <?php if ($po->request_id) { ?><p class="tw-mb-1"><span class="text-muted"><?= _l('ams_request'); ?>:</span> <a href="<?= admin_url('asset_management/requests/view/' . $po->request_id); ?>">#<?= (int) $po->request_id; ?></a></p><?php } ?>
                                <?php if ($po->notes) { ?><hr><p class="tw-mb-0"><?= nl2br(e($po->notes)); ?></p><?php } ?>
                            </div>
                        </div>

                        <?php if ($canDecide) { ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_req_your_decision'); ?></h4>
                                <?= form_open(admin_url('asset_management/procurement/decide/' . $po->id), ['class' => 'ams-po-form']); ?>
                                <?= render_textarea('note', 'ams_note'); ?>
                                <input type="hidden" name="decision" value="">
                                <button type="submit" class="btn btn-success" onclick="this.form.decision.value='approve'"><?= _l('ams_req_approve'); ?></button>
                                <button type="submit" class="btn btn-danger" onclick="this.form.decision.value='reject'"><?= _l('ams_req_reject'); ?></button>
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

<?php if ($canReceive) { ?>
<div class="modal fade" id="ams_receive_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <?= form_open(admin_url('asset_management/procurement/receive/' . $po->id), ['class' => 'ams-po-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_po_receive'); ?> - <?= e($po->po_number); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4"><?= render_date_input('receipt_date', 'ams_po_receipt_date', _d(date('Y-m-d'))); ?></div>
                    <div class="col-md-4"><?= render_select('location_id', $locations, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_location'), $po->delivery_location_id); ?></div>
                    <div class="col-md-4"><?= render_input('invoice_no', 'ams_invoice_no'); ?></div>
                </div>
                <table class="table table-bordered">
                    <thead><tr><th><?= _l('ams_description'); ?></th><th style="width:12%"><?= _l('ams_po_remaining'); ?></th><th style="width:14%"><?= _l('ams_po_receive_now'); ?></th><th style="width:34%"><?= _l('ams_po_serials_warranty'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($po->lines as $l) {
                            $remaining = (float) $l['qty'] - (float) $l['received_qty'];
                            if ($remaining <= 0) {
                                continue;
                            } ?>
                        <tr>
                            <td><?= e($l['description']); ?><br><span class="text-muted tw-text-xs"><?= _l('ams_po_type_' . $l['line_type']); ?></span></td>
                            <td><?= ams_qty($remaining); ?></td>
                            <td><input type="number" class="form-control" name="qty[<?= (int) $l['id']; ?>]" value="<?= ams_qty($remaining); ?>" min="0" max="<?= ams_qty($remaining); ?>" step="<?= $l['line_type'] === 'asset' ? '1' : '0.01'; ?>"></td>
                            <td>
                                <?php if ($l['line_type'] === 'asset') { ?>
                                <textarea class="form-control tw-mb-1" rows="2" name="serials[<?= (int) $l['id']; ?>]" placeholder="<?= _l('ams_po_serials_placeholder'); ?>"></textarea>
                                <input type="number" class="form-control" name="warranty_months[<?= (int) $l['id']; ?>]" min="0" placeholder="<?= _l('ams_po_warranty_months'); ?>">
                                <?php } else { ?>
                                <span class="text-muted tw-text-sm"><?= _l('ams_po_stock_receipt'); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?= render_textarea('note', 'ams_note'); ?>
                <p class="text-muted tw-text-sm"><?= _l('ams_po_receive_help'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-success"><?= _l('ams_po_receive'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script>
    $(function() {
        $('.ams-po-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('button[type="submit"]').prop('disabled', true);
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : (alert_float('danger', r.message), btn.prop('disabled', false));
            });
        });
        $('.ams-po-post').on('click', function() {
            if ($(this).data('confirm') && !confirm_delete()) {
                return;
            }
            var btn = $(this).prop('disabled', true);
            $.post($(this).data('url'), { email: $(this).data('email') ? '1' : '0' }).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : (alert_float('danger', r.message), btn.prop('disabled', false));
            });
        });
    });
</script>
</body>
</html>
